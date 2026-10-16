<?php

namespace App\Controllers;

use App\Libraries\OtpMailer;
use App\Libraries\OtpService;

/**
 * Lupa password, tiga langkah (memakai OtpService dan tabel email_otps yang sama dengan registrasi;
 * aman dipakai bersama karena registrasi hanya untuk email yang BELUM terdaftar, sedangkan reset
 * hanya untuk email yang SUDAH terdaftar):
 *
 *   POST /auth/forgot-password          -> kirim OTP ke email (respons selalu sama, ada atau tidaknya akun)
 *   POST /auth/forgot-password/verify   -> tukar OTP dengan reset_token
 *   POST /auth/forgot-password/reset    -> set password baru (butuh reset_token)
 */
class ForgotPasswordController extends BaseController
{
    private const GENERIC_MESSAGE = 'Jika email terdaftar, kode verifikasi telah dikirim.';

    /**
     * POST /auth/forgot-password
     * Body (JSON atau form): email
     */
    public function sendOtp()
    {
        $input = $this->input();
        $email = OtpService::normalizeEmail((string) ($input['email'] ?? ''));

        if (! $this->validateData(['email' => $email], ['email' => 'required|valid_email|max_length[150]'])) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'errors'  => $this->validator->getErrors(),
            ]);
        }

        // Batas permintaan dihitung untuk email MANAPUN (bukan hanya yang terdaftar) agar tidak membocorkan apa pun.
        $throttler = service('throttler');
        $ip        = $this->request->getIPAddress();
        if (! $throttler->check('fp_ip_' . md5($ip), 10, HOUR) || ! $throttler->check('fp_mail_' . md5($email), 5, HOUR)) {
            return $this->fail(429, 'Terlalu banyak permintaan kode. Coba lagi nanti.');
        }

        $user = $this->findActiveUser($email);

        if ($user) {
            $otp    = new OtpService();
            $issued = $otp->issue($email);

            // Jeda kirim ulang ('cooldown') dan 'busy' sengaja diabaikan: respons tetap sama.
            if ($issued['ok']) {
                if (! (new OtpMailer())->sendPasswordResetOtp($email, $issued['otp'], intdiv(OtpService::OTP_TTL, 60))) {
                    $otp->discard($email);
                    // Gagal kirim tidak diberitahukan ke klien (akan membocorkan bahwa akun ada); cek log server.
                }

                // Hanya untuk development tanpa SMTP: set otp.logToLog = true di .env.
                if (ENVIRONMENT === 'development' && filter_var(env('otp.logToLog'), FILTER_VALIDATE_BOOLEAN)) {
                    log_message('debug', "[DEV] OTP reset password untuk {$email}: {$issued['otp']}");
                }
            }
        } else {
            // Samakan waktu respons dengan jalur yang mengirim email (kira-kira 1 detik),
            // supaya orang tidak bisa membedakan email terdaftar dari lamanya respons.
            usleep(random_int(700000, 1400000));
        }

        return $this->response->setJSON([
            'success' => true,
            'message' => self::GENERIC_MESSAGE,
            'data'    => ['expires_in' => OtpService::OTP_TTL, 'resend_after' => OtpService::COOLDOWN],
        ]);
    }

    /**
     * POST /auth/forgot-password/verify
     * Body (JSON atau form): email, otp
     */
    public function verifyOtp()
    {
        $input = $this->input();
        $email = OtpService::normalizeEmail((string) ($input['email'] ?? ''));

        $rules = [
            'email' => 'required|valid_email|max_length[150]',
            'otp'   => ['rules' => 'required|regex_match[/^[0-9]{6}$/]', 'errors' => ['regex_match' => 'Kode harus 6 digit angka.']],
        ];
        if (! $this->validateData(['email' => $email, 'otp' => (string) ($input['otp'] ?? '')], $rules)) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'errors'  => $this->validator->getErrors(),
            ]);
        }

        if (! service('throttler')->check('fpv_ip_' . md5($this->request->getIPAddress()), 30, HOUR)) {
            return $this->fail(429, 'Terlalu banyak percobaan. Coba lagi nanti.');
        }

        $result = (new OtpService())->verify($email, (string) $input['otp']);

        if (! $result['ok']) {
            if ($result['code'] === 'locked') {
                return $this->fail(429, 'Terlalu banyak percobaan salah. Silakan minta kode baru.');
            }

            return $this->response->setStatusCode(422)->setJSON(array_filter([
                'success'       => false,
                'message'       => 'Kode tidak valid atau sudah kedaluwarsa.',
                'attempts_left' => $result['attempts_left'] ?? null,
            ], static fn ($v) => $v !== null));
        }

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Kode benar. Silakan buat password baru.',
            'data'    => [
                'reset_token' => $result['token'],
                'expires_in'  => $result['expires_in'],
            ],
        ]);
    }

    /**
     * POST /auth/forgot-password/reset
     * Body (JSON atau form): email, reset_token, new_password, confirm_password
     */
    public function reset()
    {
        $input = $this->input();
        $email = OtpService::normalizeEmail((string) ($input['email'] ?? ''));

        $rules = [
            'email'            => 'required|valid_email|max_length[150]',
            'reset_token'      => 'required',
            'new_password'     => [
                'rules'  => 'required|min_length[8]|regex_match[/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).+$/]',
                'errors' => ['regex_match' => 'Password harus mengandung huruf besar, huruf kecil, angka, dan karakter spesial.'],
            ],
            'confirm_password' => 'required|matches[new_password]',
        ];
        $data = [
            'email'            => $email,
            'reset_token'      => (string) ($input['reset_token'] ?? ''),
            'new_password'     => (string) ($input['new_password'] ?? ''),
            'confirm_password' => (string) ($input['confirm_password'] ?? ''),
        ];
        if (! $this->validateData($data, $rules)) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'errors'  => $this->validator->getErrors(),
            ]);
        }

        if (! service('throttler')->check('fpr_ip_' . md5($this->request->getIPAddress()), 30, HOUR)) {
            return $this->fail(429, 'Terlalu banyak percobaan. Coba lagi nanti.');
        }

        $otp  = new OtpService();
        $user = $this->findActiveUser($email);

        if (! $user || ! $otp->validateToken($email, $data['reset_token'])) {
            return $this->fail(422, 'Sesi reset tidak valid atau sudah kedaluwarsa. Silakan ulangi dari awal.');
        }

        if (password_verify($data['new_password'], $user['password'])) {
            return $this->fail(422, 'Password baru tidak boleh sama dengan password lama.');
        }

        // Query builder langsung: aturan validasi UserModel mewajibkan field lain yang tidak relevan di sini.
        \Config\Database::connect()->table('users')
            ->where('user_id', $user['user_id'])
            ->update([
                'password'   => password_hash($data['new_password'], PASSWORD_BCRYPT),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

        $otp->consume($email); // token sekali pakai

        (new OtpMailer())->sendPasswordChangedNotice($email); // best-effort

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Password berhasil diganti. Silakan login dengan password baru.',
        ]);
    }

    /**
     * Cari user aktif (is_active = true, dan belum di-soft-delete kalau kolom deleted_at ada).
     * Filter is_active dilakukan di SQL, bukan di PHP, karena PostgreSQL mengembalikan boolean
     * sebagai string 't'/'f' yang dua-duanya dianggap truthy oleh PHP.
     */
    private function findActiveUser(string $email): ?array
    {
        $db = \Config\Database::connect();
        $b  = $db->table('users')->where('LOWER(email) =', $email)->where('is_active', true);

        if ($db->fieldExists('deleted_at', 'users')) {
            $b->where('deleted_at', null);
        }

        return $b->get()->getRowArray() ?: null;
    }

    /** Terima JSON maupun form, sama seperti endpoint OTP registrasi. */
    private function input(): array
    {
        if ($this->request->is('json')) {
            return $this->request->getJSON(true) ?? [];
        }

        return $this->request->getPost() ?? [];
    }

    private function fail(int $status, string $message)
    {
        return $this->response->setStatusCode($status)->setJSON([
            'success' => false,
            'message' => $message,
        ]);
    }
}