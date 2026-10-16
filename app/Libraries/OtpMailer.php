<?php

namespace App\Libraries;

/**
 * Pengirim email untuk OTP dan notifikasi akun. Memakai konfigurasi SMTP email.* di .env
 * (sama dengan PvMailer). File ini MENGGANTIKAN OtpMailer.php yang lama: sendRegistrationOtp()
 * tetap sama, ditambah sendPasswordResetOtp() dan sendPasswordChangedNotice().
 */
class OtpMailer
{
    public function sendRegistrationOtp(string $to, string $otp, int $ttlMinutes): bool
    {
        return $this->sendCode(
            $to,
            'Kode verifikasi pendaftaran SIPRESTASI',
            'Verifikasi Email SIPRESTASI',
            'Gunakan kode berikut untuk melanjutkan pendaftaran akun Anda:',
            $otp,
            $ttlMinutes,
            'Jika Anda tidak merasa mendaftar, abaikan email ini.'
        );
    }

    public function sendPasswordResetOtp(string $to, string $otp, int $ttlMinutes): bool
    {
        return $this->sendCode(
            $to,
            'Kode reset password SIPRESTASI',
            'Reset Password SIPRESTASI',
            'Kami menerima permintaan untuk mengatur ulang password akun Anda. Gunakan kode berikut:',
            $otp,
            $ttlMinutes,
            'Jika Anda tidak meminta reset password, abaikan email ini. Password Anda tidak akan berubah.'
        );
    }

    /** Pemberitahuan setelah password berhasil diganti lewat reset. Kegagalan kirim tidak menggagalkan reset. */
    public function sendPasswordChangedNotice(string $to): bool
    {
        $waktu = date('d-m-Y H:i');
        $html  = <<<HTML
        <div style="font-family: Arial, sans-serif; line-height: 1.6; color: #222; max-width: 560px;">
            <h2 style="margin-bottom: 8px;">Password SIPRESTASI Diubah</h2>
            <p>Password akun Anda baru saja diubah melalui fitur lupa password pada {$waktu}.</p>
            <p style="font-size: 13px; color: #555;">
                Jika ini bukan Anda, segera hubungi admin SIPRESTASI.
            </p>
        </div>
        HTML;

        return $this->send(
            $to,
            'Password akun SIPRESTASI Anda telah diubah',
            $html,
            "Password akun SIPRESTASI Anda baru saja diubah pada {$waktu}. Jika ini bukan Anda, segera hubungi admin SIPRESTASI."
        );
    }

    private function sendCode(string $to, string $subject, string $title, string $intro, string $otp, int $ttlMinutes, string $footer): bool
    {
        $kode = esc($otp);
        $html = <<<HTML
        <div style="font-family: Arial, sans-serif; line-height: 1.6; color: #222; max-width: 560px;">
            <h2 style="margin-bottom: 8px;">{$title}</h2>
            <p>{$intro}</p>
            <p style="font-size: 32px; font-weight: bold; letter-spacing: 8px; margin: 16px 0;">{$kode}</p>
            <p>Kode berlaku selama <strong>{$ttlMinutes} menit</strong> dan hanya dapat digunakan satu kali.</p>
            <p style="font-size: 13px; color: #555;">
                Jangan bagikan kode ini kepada siapa pun, termasuk pihak yang mengaku dari SIPRESTASI.
                {$footer}
            </p>
        </div>
        HTML;

        // Versi teks biasa membantu mengurangi skor spam.
        $alt = "{$intro} {$otp}. Berlaku {$ttlMinutes} menit dan hanya bisa dipakai satu kali. "
            . 'Jangan bagikan kode ini kepada siapa pun. ' . $footer;

        return $this->send($to, $subject, $html, $alt);
    }

    private function send(string $to, string $subject, string $html, string $alt): bool
    {
        $email = \Config\Services::email();
        $email->setFrom((string) env('email.fromEmail'), (string) env('email.fromName', 'SIPRESTASI'));
        $email->setTo($to);
        $email->setSubject($subject);
        $email->setMailType('html');
        $email->setMessage($html);
        $email->setAltMessage($alt);

        if (! $email->send()) {
            // Jangan pernah mencatat isi email/OTP ke log; cukup tujuan dan header debug.
            log_message('error', "Gagal kirim email ke {$to}: " . $email->printDebugger(['headers']));

            return false;
        }

        return true;
    }
}