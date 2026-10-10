<?php

namespace App\Filters;

use App\Models\UserModel;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class AuthFilter implements FilterInterface
{
    /**
     * Cek login + role. Contoh di route: 'auth:admin,superadmin'
     *
     * Role dan status akun dibaca ulang dari database di setiap request, bukan dari
     * session saja. Kalau tidak, user yang role-nya diubah / dinonaktifkan / dihapus
     * superadmin tetap memakai role lama sampai session-nya habis.
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        if (! session()->get('isLoggedIn')) {
            return $this->deny(401, 'Silakan login terlebih dahulu.');
        }

        // find() otomatis mengabaikan user yang sudah di-soft-delete
        $user = (new UserModel())->find((int) session()->get('user_id'));

        if (! $user || ! $user['is_active']) {
            session()->destroy();

            return $this->deny(401, 'Sesi tidak valid, silakan login kembali.');
        }

        session()->set('role', $user['role']);

        if ($arguments && ! in_array($user['role'], $arguments, true)) {
            return $this->deny(403, 'Anda tidak memiliki akses ke resource ini.');
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Tidak dipakai
    }

    private function deny(int $status, string $message)
    {
        return service('response')->setStatusCode($status)->setJSON([
            'success' => false,
            'message' => $message,
        ]);
    }
}