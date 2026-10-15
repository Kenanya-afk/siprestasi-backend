<?php

namespace App\Controllers;

use App\Libraries\OtpMailer;
use App\Libraries\OtpService;

/**
 * Langkah 1 dan 2 registrasi (belum ada akun yang dibuat di sini):
 *   POST /auth/register/otp         -> kirim OTP ke email
 *   POST /auth/register/otp/verify  -> tukar OTP dengan registration_token
 * Langkah 3 adalah POST /auth/register yang sudah ada, dengan field registration_token.
 */
class RegistrationOtpController extends BaseController
{
    /**
     * POST /auth/register/otp
     * Body (JSON atau form): email
     */
    public function sendOtp()
    {
        $input = $this->input();
        $email = OtpService::normalizeEmail((string) ($input['email'] ?? ''));
        $otp   = new OtpService();

        $rules = [
            'email' => [
                'rules'  => 'required|valid_email|max_length[150]|is_unique[users.email]',
                'errors' => ['is_unique' => 'Email sudah terdaftar, silakan login.'],
            ],
        ];
        if (! $this->validateData(['email' => $email], $rules)) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'errors'  => $this->validator->getErrors(),
            ]);
        }

        if (! $otp->isAllowedDomain($email)) {
            return $this->fail(422, 'Email harus menggunakan email student kampus.');
        }

        // Batas permintaan: per IP dan per email, selain jeda kirim ulang di OtpService.
        $throttler = service('throttler');
        $ip        = $this->request->getIPAddress();
        if (! $throttler->check('otp_ip_' . md5($ip), 10, HOUR) || ! $throttler->check('otp_mail_' . md5($email), 5, HOUR)) {
            return $this->fail(429, 'Terlalu banyak permintaan kode. Coba lagi nanti.');
        }

        $issued = $otp->issue($email);
        if (! $issued['ok']) {
            if ($issued['code'] === 'cooldown') {
                return $this->response->setStatusCode(429)->setJSON([
                    'success'     => false,
                    'message'     => 'Tunggu sebentar sebelum meminta kode baru.',
                    'retry_after' => $issued['retry_after'],
                ]);
            }

            return $this->fail(429, 'Permintaan sedang diproses, coba lagi sebentar.');
        }

        if (! (new OtpMailer())->sendRegistrationOtp($email, $issued['otp'], intdiv(OtpService::OTP_TTL, 60))) {
            $otp->discard($email); // supaya jeda kirim ulang tidak menghalangi percobaan berikutnya

            return $this->fail(502, 'Gagal mengirim email. Coba lagi nanti.');
        }

        // Hanya untuk development tanpa SMTP: set otp.logToLog = true di .env.
        if (ENVIRONMENT === 'development' && filter_var(env('otp.logToLog'), FILTER_VALIDATE_BOOLEAN)) {
            log_message('debug', "[DEV] OTP untuk {$email}: {$issued['otp']}");
        }

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Kode verifikasi telah dikirim ke email Anda.',
            'data'    => ['expires_in' => OtpService::OTP_TTL, 'resend_after' => OtpService::COOLDOWN],
        ]);
    }

    /**
     * POST /auth/register/otp/verify
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

        if (! service('throttler')->check('otpv_ip_' . md5($this->request->getIPAddress()), 30, HOUR)) {
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
            'message' => 'Email terverifikasi. Lanjutkan pendaftaran.',
            'data'    => [
                'registration_token' => $result['token'],
                'expires_in'         => $result['expires_in'],
            ],
        ]);
    }

    /** Terima JSON maupun form, supaya sama dengan cara klien memanggil auth/register. */
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