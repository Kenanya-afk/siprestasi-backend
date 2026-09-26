<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class AuthFilter implements FilterInterface
{
    /**
     * Cek apakah user sudah login.
     * Kalau butuh role tertentu, pass di route: 'auth:admin,prodi'
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        if (! session()->get('isLoggedIn')) {
            return service('response')
                ->setStatusCode(401)
                ->setJSON([
                    'success' => false,
                    'message' => 'Silakan login terlebih dahulu.',
                ]);
        }

        if ($arguments) {
            $allowedRoles = $arguments; // contoh: ['admin', 'prodi']
            $userRole     = session()->get('role');

            if (! in_array($userRole, $allowedRoles, true)) {
                return service('response')
                    ->setStatusCode(403)
                    ->setJSON([
                        'success' => false,
                        'message' => 'Anda tidak memiliki akses ke resource ini.',
                    ]);
            }
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Tidak dipakai
    }
}
