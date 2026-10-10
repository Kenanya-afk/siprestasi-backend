<?php

namespace App\Controllers;

use App\Models\BahasaModel;
use App\Models\OrganisasiModel;
use App\Models\PrestasiModel;
use App\Models\ScoreResultModel;
use App\Models\LogModel;

class ValidasiController extends BaseController
{
    private const KATEGORI_MAP = [
        'prestasi'   => ['model' => PrestasiModel::class, 'prefix' => 'pres'],
        'organisasi' => ['model' => OrganisasiModel::class, 'prefix' => 'org'],
        'bahasa'     => ['model' => BahasaModel::class, 'prefix' => 'bah'],
    ];

    /**
     * PUT /pengajuan/{subId}/validasi/{kategori}
     * Body (JSON): { "status": "verified|revisi|ditolak", "catatan": "..." }
     * Route dipasang dengan filter auth:admin,superadmin
     */
    public function updateStatus(int $subId, string $kategori)
    {
        if (! isset(self::KATEGORI_MAP[$kategori])) {
            return $this->response->setStatusCode(404)->setJSON([
                'success' => false,
                'message' => 'Kategori tidak dikenali.',
            ]);
        }

        $modelClass = self::KATEGORI_MAP[$kategori]['model'];
        $prefix     = self::KATEGORI_MAP[$kategori]['prefix'];
        $model      = new $modelClass();

        $doc = $model->where('sub_id', $subId)->first();
        if (! $doc) {
            return $this->response->setStatusCode(404)->setJSON([
                'success' => false,
                'message' => 'Dokumen belum diupload mahasiswa.',
            ]);
        }

        $input = $this->request->getJSON(true) ?? [];

        $rules = [
            'status'  => 'required|in_list[verified,revisi]',
            'catatan' => 'permit_empty',
        ];

        if (! $this->validateData($input, $rules)) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'errors'  => $this->validator->getErrors(),
            ]);
        }

        if ($input['status'] === 'revisi' && empty($input['catatan'])) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'errors'  => ['catatan' => 'Wajib diisi kalau meminta revisi.'],
            ]);
        }

        $updateData = [
            $prefix . '_status' => $input['status'],
            'catatan'           => $input['catatan'] ?? null,
        ];

        if ($input['status'] === 'verified') {
            $updateData[$prefix . '_score'] = $modelClass::SCORES[$doc[$prefix . '_cat']] ?? 0;
        } else { // revisi
            $updateData[$prefix . '_score'] = 0;
        }

        $model->update($doc[$prefix . '_id'], $updateData);

        // Status kategori berubah -> hitung ulang total_score dari skor terbaru
        (new ScoreResultModel())->calculateAllResults((int) $subId);

        (new LogModel())->recordLog(
            (int) $subId,
            (int) session()->get('user_id'),
            "Validasi {$kategori}: {$input['status']}"
        );

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Status ' . $kategori . ' berhasil diperbarui.',
        ]);
    }
}