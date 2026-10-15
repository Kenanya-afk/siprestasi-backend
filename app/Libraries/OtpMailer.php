<?php

namespace App\Libraries;

/**
 * Mengirim kode OTP registrasi. Memakai konfigurasi SMTP yang sama dengan PvMailer
 * (email.* di .env).
 */
class OtpMailer
{
    /**
     * @return bool true kalau email berhasil dikirim
     */
    public function sendRegistrationOtp(string $to, string $otp, int $ttlMinutes): bool
    {
        $email = \Config\Services::email();
        $email->setFrom((string) env('email.fromEmail'), (string) env('email.fromName', 'SIPRESTASI'));
        $email->setTo($to);
        $email->setSubject('Kode verifikasi pendaftaran SIPRESTASI');
        $email->setMailType('html');
        $email->setMessage($this->buildBody($otp, $ttlMinutes));

        if (! $email->send()) {
            // Jangan pernah mencatat OTP ke log; cukup tujuan dan header debug.
            log_message('error', "Gagal kirim OTP ke {$to}: " . $email->printDebugger(['headers']));

            return false;
        }

        return true;
    }

    private function buildBody(string $otp, int $ttlMinutes): string
    {
        $kode = esc($otp);

        return <<<HTML
        <div style="font-family: Arial, sans-serif; line-height: 1.6; color: #222; max-width: 560px;">
            <h2 style="margin-bottom: 8px;">Verifikasi Email SIPRESTASI</h2>
            <p>Gunakan kode berikut untuk melanjutkan pendaftaran akun Anda:</p>
            <p style="font-size: 32px; font-weight: bold; letter-spacing: 8px; margin: 16px 0;">{$kode}</p>
            <p>Kode berlaku selama <strong>{$ttlMinutes} menit</strong> dan hanya dapat digunakan satu kali.</p>
            <p style="font-size: 13px; color: #555;">
                Jangan bagikan kode ini kepada siapa pun, termasuk pihak yang mengaku dari SIPRESTASI.
                Jika Anda tidak merasa mendaftar, abaikan email ini.
            </p>
        </div>
        HTML;
    }
}