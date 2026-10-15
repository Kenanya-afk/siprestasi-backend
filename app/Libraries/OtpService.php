<?php

namespace App\Libraries;

use CodeIgniter\Database\BaseConnection;

/**
 * Logika OTP registrasi: buat, verifikasi, dan validasi token registrasi.
 *
 * Alur: issue() -> (email dikirim) -> verify() menghasilkan registration_token
 *       -> validateToken() dipakai AuthController::register -> consume() setelah akun dibuat.
 */
class OtpService
{
    public const OTP_TTL      = 300; // detik: OTP berlaku 5 menit
    public const MAX_ATTEMPTS = 5;   // salah maksimal 5 kali per OTP
    public const COOLDOWN     = 60;  // detik: jeda minta kirim ulang
    public const TOKEN_TTL    = 900; // detik: token registrasi berlaku 15 menit

    private BaseConnection $db;

    public function __construct()
    {
        $this->db = \Config\Database::connect();
    }

    public static function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    /**
     * Domain email student yang diizinkan, dari .env: auth.studentEmailDomain
     * (boleh lebih dari satu, pisahkan dengan koma). Kalau belum diisi, jatuh ke aturan lama
     * `@student.` yang longgar (menerima apa saja seperti x@student.domain-apa-saja.com).
     */
    public function isAllowedDomain(string $email): bool
    {
        $domains = array_filter(array_map('trim', explode(',', strtolower((string) env('auth.studentEmailDomain')))));

        if (! $domains) {
            return (bool) preg_match('/@student\./', $email);
        }

        foreach ($domains as $domain) {
            if (str_ends_with($email, '@' . $domain)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Buat OTP baru untuk email (menggantikan OTP sebelumnya).
     *
     * @return array{ok: bool, code?: string, retry_after?: int, otp?: string}
     */
    public function issue(string $email): array
    {
        $now = time();

        // Bersihkan baris lama (lebih dari 1 hari) agar tabel tidak menumpuk.
        $this->db->table('email_otps')->where('created_at <', date('Y-m-d H:i:s', $now - 86400))->delete();

        $row = $this->db->table('email_otps')->where('email', $email)->get()->getRowArray();
        if ($row) {
            $wait = self::COOLDOWN - ($now - strtotime($row['last_sent_at']));
            if ($wait > 0) {
                return ['ok' => false, 'code' => 'cooldown', 'retry_after' => $wait];
            }
        }

        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        try {
            $this->db->table('email_otps')->where('email', $email)->delete();
            $this->db->table('email_otps')->insert([
                'email'            => $email,
                'otp_hash'         => $this->hashOtp($email, $otp),
                'attempts'         => 0,
                'last_sent_at'     => date('Y-m-d H:i:s', $now),
                'expires_at'       => date('Y-m-d H:i:s', $now + self::OTP_TTL),
                'verified_at'      => null,
                'token_hash'       => null,
                'token_expires_at' => null,
                'created_at'       => date('Y-m-d H:i:s', $now),
            ]);
        } catch (\Throwable $e) {
            // Biasanya dua permintaan bersamaan untuk email yang sama (primary key bentrok).
            log_message('error', 'Gagal menyimpan OTP: ' . $e->getMessage());

            return ['ok' => false, 'code' => 'busy'];
        }

        return ['ok' => true, 'otp' => $otp];
    }

    /** Batalkan OTP (dipakai kalau pengiriman email gagal, supaya cooldown tidak menghalangi coba lagi). */
    public function discard(string $email): void
    {
        $this->db->table('email_otps')->where('email', $email)->delete();
    }

    /**
     * Verifikasi OTP. Kalau benar, kembalikan registration_token (sekali pakai, berlaku 15 menit).
     *
     * @return array{ok: bool, code?: string, attempts_left?: int, token?: string, expires_in?: int}
     */
    public function verify(string $email, string $otp): array
    {
        $now = time();
        $row = $this->db->table('email_otps')->where('email', $email)->get()->getRowArray();

        if (! $row || $row['otp_hash'] === null || strtotime($row['expires_at']) < $now) {
            return ['ok' => false, 'code' => 'invalid'];
        }

        // Pakai satu jatah percobaan SECARA ATOMIK sebelum membandingkan, supaya request paralel
        // tidak bisa menebak lebih dari MAX_ATTEMPTS kali.
        $this->db->table('email_otps')
            ->where('email', $email)
            ->where('attempts <', self::MAX_ATTEMPTS)
            ->set('attempts', 'attempts + 1', false)
            ->update();

        if ($this->db->affectedRows() !== 1) {
            $this->discard($email);

            return ['ok' => false, 'code' => 'locked'];
        }

        if (! hash_equals($row['otp_hash'], $this->hashOtp($email, $otp))) {
            return [
                'ok'            => false,
                'code'          => 'invalid',
                'attempts_left' => max(0, self::MAX_ATTEMPTS - ((int) $row['attempts'] + 1)),
            ];
        }

        // OTP benar. Tukar jadi token lewat compare-and-set pada otp_hash: kalau dua request
        // dengan OTP yang sama datang bersamaan, hanya satu yang berhasil.
        $token = bin2hex(random_bytes(32));
        $this->db->table('email_otps')
            ->where('email', $email)
            ->where('otp_hash', $row['otp_hash'])
            ->set([
                'otp_hash'         => null,
                'verified_at'      => date('Y-m-d H:i:s', $now),
                'token_hash'       => hash('sha256', $token),
                'token_expires_at' => date('Y-m-d H:i:s', $now + self::TOKEN_TTL),
            ])
            ->update();

        if ($this->db->affectedRows() !== 1) {
            return ['ok' => false, 'code' => 'invalid'];
        }

        return ['ok' => true, 'token' => $token, 'expires_in' => self::TOKEN_TTL];
    }

    /** Apakah token registrasi ini sah untuk email tersebut. */
    public function validateToken(string $email, string $token): bool
    {
        if ($token === '') {
            return false;
        }

        $row = $this->db->table('email_otps')->where('email', $email)->get()->getRowArray();
        if (! $row || $row['verified_at'] === null || $row['token_hash'] === null) {
            return false;
        }
        if (strtotime((string) $row['token_expires_at']) < time()) {
            return false;
        }

        return hash_equals($row['token_hash'], hash('sha256', $token));
    }

    /** Hapus baris setelah akun berhasil dibuat (token sekali pakai). */
    public function consume(string $email): void
    {
        $this->discard($email);
    }

    private function hashOtp(string $email, string $otp): string
    {
        // HMAC dengan kunci server: tanpa kunci, 6 digit bisa ditebak dari hash dalam hitungan detik.
        $key = (string) config('Encryption')->key;
        if ($key === '') {
            throw new \RuntimeException('encryption.key belum diset di .env (jalankan: php spark key:generate).');
        }

        return hash_hmac('sha256', $email . '|' . $otp, $key);
    }
}