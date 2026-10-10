<?php

namespace App\Controllers;

use App\Models\MasterDataModel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Csv as CsvReader;
use App\Models\LogModel;

/**
 * Import data master (IPK & SKPI) oleh admin lewat dashboard.
 *
 * Prasyarat:  composer require phpoffice/phpspreadsheet
 * Kolom file: induk, name, ipk, skpi   (baris pertama = header)
 * Request   : multipart/form-data -> period (contoh 2025/2026), file (.csv / .xlsx)
 *
 * Aturan: kalau ada SATU baris saja yang salah, seluruh import dibatalkan
 * dan semua error dikembalikan sekaligus.
 */
class MasterDataController extends BaseController
{
    private const REQUIRED_HEADERS = 
    [
        'induk', 
        'name', 
        'ipk', 
        'skpi'
    ];

    // POST /master-data/import
    public function import()
    {
        $period = trim((string) $this->request->getPost('period'));
        $file   = $this->request->getFile('file');

        if (! preg_match('#^\d{4}/\d{4}$#', $period)) {
            return $this->fail('Field period wajib berformat YYYY/YYYY, contoh 2025/2026.');
        }
        if (! $file || ! $file->isValid()) {
            return $this->fail('File tidak ditemukan atau gagal diunggah.');
        }

        $ext = strtolower($file->getClientExtension());
        if (! in_array($ext, ['csv', 'xlsx'], true)) {
            return $this->fail('Format file harus .csv atau .xlsx.');
        }

        // ---- 1. Baca file ----
        try {
            $rows = $this->readRows($file->getTempName(), $ext);
        } catch (\Throwable $e) {
            return $this->fail('File tidak dapat dibaca: ' . $e->getMessage());
        }

        if (count($rows) < 2) {
            return $this->fail('File kosong atau hanya berisi header.');
        }

        // ---- 2. Validasi header ----
        $header = array_map(
            static fn($h) => strtolower(trim((string) $h)),
            array_shift($rows)
        );
        $missing = array_diff(self::REQUIRED_HEADERS, $header);
        if ($missing) {
            return $this->fail(
                'Header kolom tidak lengkap. Kolom yang hilang: ' . implode(', ', $missing)
                . '. Kolom wajib: ' . implode(', ', self::REQUIRED_HEADERS) . '.'
            );
        }
        $col = array_flip($header);

        // ---- 3. Validasi seluruh baris (kumpulkan semua error) ----
        $errors = [];
        $clean  = [];
        $seen   = [];

        foreach ($rows as $i => $row) {
            $line = $i + 2; // baris 1 = header

            $induk = trim((string) ($row[$col['induk']] ?? ''));
            $name  = trim((string) ($row[$col['name']] ?? ''));
            $ipk   = trim((string) ($row[$col['ipk']] ?? ''));
            $skpi  = trim((string) ($row[$col['skpi']] ?? ''));

            // lewati baris yang benar-benar kosong
            if ($induk === '' && $name === '' && $ipk === '' && $skpi === '') {
                continue;
            }

            if ($induk === '') {
                $errors[] = $this->err($line, 'induk', 'NIM tidak boleh kosong.');
            } elseif (isset($seen[$induk])) {
                $errors[] = $this->err($line, 'induk', "NIM {$induk} duplikat (sudah muncul di baris {$seen[$induk]}).");
            } else {
                $seen[$induk] = $line;
            }

            if ($name === '') {
                $errors[] = $this->err($line, 'name', 'Nama tidak boleh kosong.');
            }

            // dukung desimal koma (Excel Indonesia): 3,75 -> 3.75
            $ipkNum = str_replace(',', '.', $ipk);
            if ($ipk === '' || ! is_numeric($ipkNum)) {
                $errors[] = $this->err($line, 'ipk', 'IPK harus berupa angka.');
            } elseif ((float) $ipkNum < 0 || (float) $ipkNum > 4) {
                $errors[] = $this->err($line, 'ipk', "IPK {$ipk} di luar rentang 0 - 4.");
            }

            if ($skpi === '' || ! is_numeric($skpi)) {
                $errors[] = $this->err($line, 'skpi', 'SKPI harus berupa angka.');
            } elseif ((float) $skpi < 0) {
                $errors[] = $this->err($line, 'skpi', 'SKPI tidak boleh negatif.');
            }

            $clean[] = [
                'period' => $period,
                'induk'  => $induk,
                'name'   => $name,
                'ipk'    => (float) $ipkNum,
                'skpi'   => (float) $skpi,
            ];
        }

        if (! $clean && ! $errors) {
            return $this->fail('Tidak ada data yang bisa diimpor.');
        }

        // ---- 4. Ada error -> batalkan semua ----
        if ($errors) {
            return $this->response->setStatusCode(422)->setJSON([
                'status'  => 'error',
                'message' => 'Import dibatalkan. Perbaiki file lalu unggah ulang.',
                'total_error' => count($errors),
                'errors'  => $errors,
            ]);
        }

        // ---- 5. Simpan dalam satu transaksi (upsert per induk + period) ----
        $model    = new MasterDataModel();
        $db       = db_connect();
        $inserted = 0;
        $updated  = 0;
        $now      = date('Y-m-d H:i:s');

        $db->transStart();
        foreach ($clean as $data) {
            $data['imported_at'] = $now;
            $existing = $model->findByIndukAndPeriod($data['induk'], $period);

            if ($existing) {
                $model->update($existing['master_id'], $data);
                $updated++;
            } else {
                $model->insert($data);
                $inserted++;
            }
        }
        $db->transComplete();

        if ($db->transStatus() === false) {
            return $this->response->setStatusCode(500)->setJSON([
                'status'  => 'error',
                'message' => 'Gagal menyimpan ke database. Tidak ada data yang diubah.',
            ]);
        }

        (new LogModel())->recordSystemLog(
            (int) session()->get('user_id'),
            mb_strimwidth(
                "Import master data periode {$period} (file: {$file->getClientName()}): {$inserted} baru, {$updated} diperbarui",
                0, 250, '...'
            )
        );

        return $this->response->setStatusCode(200)->setJSON([
            'status'   => 'success',
            'message'  => 'Import data master berhasil.',
            'period'   => $period,
            'inserted' => $inserted,
            'updated'  => $updated,
            'total'    => $inserted + $updated,
        ]);
    }

    // GET /master-data/template  -> unduh template csv
    public function template()
    {
        $csv = "induk,name,ipk,skpi\n2023001,Contoh Nama,3.75,320\n";

        return $this->response
            ->setHeader('Content-Type', 'text/csv')
            ->setHeader('Content-Disposition', 'attachment; filename="template_master_data.csv"')
            ->setBody($csv);
    }

    // ---------- helper ----------

    private function readRows(string $path, string $ext): array
    {
        if ($ext === 'csv') {
            // Excel Indonesia biasanya menyimpan CSV dengan pemisah ';'
            $firstLine = (string) fgets(fopen($path, 'r'));
            $reader    = new CsvReader();
            $reader->setDelimiter(substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',');
            $spreadsheet = $reader->load($path);
        } else {
            $spreadsheet = IOFactory::load($path);
        }

        return $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
    }

    private function err(int $line, string $column, string $message): array
    {
        return ['baris' => $line, 'kolom' => $column, 'pesan' => $message];
    }

    private function fail(string $message)
    {
        return $this->response->setStatusCode(422)->setJSON([
            'status'  => 'error',
            'message' => $message,
        ]);
    }
}