<?php

namespace App\Libraries;

/**
 * Mengirim link kuesioner Pradita Value ke evaluator (rekan sejawat / dosen).
 * Butuh di .env: email.* (SMTP) dan app.frontendUrl
 */
class PvMailer
{
    /**
     * @return bool true kalau email berhasil dikirim
     */
    public function sendEvaluatorLink(string $to, string $role, string $studentName, string $token): bool
    {
        if ($to === '') {
            return false;
        }

        $baseUrl = rtrim((string) env('app.frontendUrl'), '/');
        $link    = $baseUrl . '/pv-respond/' . $token;

        $email = \Config\Services::email();
        $email->setFrom((string) env('email.fromEmail'), (string) env('email.fromName', 'SIPRESTASI'));
        $email->setTo($to);
        $email->setSubject('Permohonan Penilaian Pradita Value - ' . $studentName);
        $email->setMessage($this->buildBody($role, $studentName, $link));

        if (! $email->send()) {
            // Jangan log link/token, cukup tujuan dan header debug
            log_message('error', "Gagal kirim email PV ke {$to}: " . $email->printDebugger(['headers']));
            return false;
        }

        return true;
    }

    private function buildBody(string $role, string $studentName, string $link): string
    {
        $peran = $role === 'dosen' ? 'dosen penilai' : 'rekan sejawat';
        $nama  = esc($studentName);
        $url = esc($link, 'attr');

        return <<<HTML
        <div style="font-family: Arial, sans-serif; line-height: 1.6; color: #222; max-width: 560px;">
            <h2 style="margin-bottom: 8px;">Permohonan Penilaian Pradita Value</h2>
            <p>Halo,</p>
            <p>
                <strong>{$nama}</strong> sedang mengikuti seleksi Mahasiswa Berprestasi
                dan menunjuk Anda sebagai <strong>{$peran}</strong> untuk memberikan penilaian
                Pradita Value.
            </p>
            <p>Silakan isi kuesioner melalui tautan berikut (tidak perlu login):</p>
            <p>
                <a href="{$url}"
                   style="display: inline-block; padding: 10px 18px; background: #1d4ed8; color: #fff;
                          text-decoration: none; border-radius: 6px;">
                    Isi Kuesioner
                </a>
            </p>
            <p style="font-size: 13px; color: #555;">
                Jika tombol tidak berfungsi, salin tautan ini ke browser:<br>
                {$url}
            </p>
            <p style="font-size: 13px; color: #555;">
                Tautan ini khusus untuk Anda dan hanya dapat digunakan satu kali.
                Mohon jangan dibagikan ke orang lain.
            </p>
        </div>
        HTML;
    }
}