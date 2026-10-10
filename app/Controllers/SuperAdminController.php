<?php

namespace App\Controllers;

use App\Models\LogModel;
use App\Models\UserModel;

/**
 * Semua route di controller ini dipasang dengan filter auth:superadmin.
 */
class SuperAdminController extends BaseController
{
    private const ROLES = ['mahasiswa', 'admin', 'dosen', 'superadmin'];

    /**
     * GET /superadmin/users?role=&year=&is_active=&q=&page=&per_page=
     */
    public function users()
    {
        $page    = max(1, (int) ($this->request->getGet('page') ?? 1));
        $perPage = min(100, max(1, (int) ($this->request->getGet('per_page') ?? 20)));

        $b = \Config\Database::connect()->table('users')
            ->select('user_id, name, email, induk, contact, year, role, is_kaprodi, is_active, status_verifikasi, created_at')
            ->where('deleted_at', null);

        if ($role = $this->request->getGet('role')) {
            $b->where('role', $role);
        }
        if ($year = $this->request->getGet('year')) {
            $b->where('year', $year);
        }
        $active = $this->request->getGet('is_active');
        if ($active !== null && $active !== '') {
            $b->where('is_active', filter_var($active, FILTER_VALIDATE_BOOLEAN));
        }
        if ($q = trim((string) $this->request->getGet('q'))) {
            $b->groupStart()
                ->like('name', $q, 'both', null, true)
                ->orLike('email', $q, 'both', null, true)
                ->orLike('induk', $q, 'both', null, true)
                ->groupEnd();
        }

        $total = $b->countAllResults(false);
        $rows  = $b->orderBy('user_id', 'ASC')
            ->limit($perPage, ($page - 1) * $perPage)
            ->get()
            ->getResultArray();

        return $this->response->setJSON([
            'success' => true,
            'data'    => $rows,
            'meta'    => ['page' => $page, 'per_page' => $perPage, 'total' => $total],
        ]);
    }

    /**
     * PUT /superadmin/users/{id}/role
     * Body (JSON): { "role": "admin|dosen|mahasiswa|superadmin", "is_kaprodi": true|false (opsional) }
     */
    public function updateRole(int $id)
    {
        $input   = $this->request->getJSON(true) ?? [];
        $actorId = (int) session()->get('user_id');
        $newRole = $input['role'] ?? null;

        if (! in_array($newRole, self::ROLES, true)) {
            return $this->fail(422, 'Role tidak valid.');
        }
        if (array_key_exists('is_kaprodi', $input) && ! is_bool($input['is_kaprodi'])) {
            return $this->fail(422, 'is_kaprodi harus true atau false.');
        }
        if ($id === $actorId) {
            return $this->fail(403, 'Tidak bisa mengubah role akun sendiri.');
        }

        $users  = new UserModel();
        $target = $users->find($id);
        if (! $target) {
            return $this->fail(404, 'User tidak ditemukan.');
        }

        // Pengaman tambahan: jangan sampai superadmin terakhir diturunkan.
        if ($target['role'] === 'superadmin' && $newRole !== 'superadmin' && $this->activeSuperadminCount() <= 1) {
            return $this->fail(422, 'Minimal harus tersisa satu superadmin.');
        }

        // is_kaprodi hanya berlaku untuk dosen; role lain otomatis false.
        $isKaprodi = $input['is_kaprodi'] ?? $target['is_kaprodi'];
        if ($newRole !== 'dosen') {
            if (($input['is_kaprodi'] ?? false) === true) {
                return $this->fail(422, 'is_kaprodi hanya berlaku untuk role dosen.');
            }
            $isKaprodi = false;
        }

        // skipValidation: aturan model mewajibkan password/name, padahal di sini hanya role yang diubah.
        $users->skipValidation(true)->update($id, [
            'role'       => $newRole,
            'is_kaprodi' => $isKaprodi,
        ]);

        (new LogModel())->recordSystemLog($actorId, sprintf(
            'Ubah hak akses user #%d (%s): role %s -> %s, kaprodi %s -> %s',
            $id,
            $target['name'],
            $target['role'],
            $newRole,
            $target['is_kaprodi'] ? 'ya' : 'tidak',
            $isKaprodi ? 'ya' : 'tidak'
        ));

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Hak akses user berhasil diperbarui.',
            'data'    => ['user_id' => $id, 'role' => $newRole, 'is_kaprodi' => (bool) $isKaprodi],
        ]);
    }

    /**
     * DELETE /superadmin/users/{id}
     * Soft delete: baris user tetap ada, submission/skor/log dan ranking tidak berubah.
     */
    public function delete(int $id)
    {
        $actorId = (int) session()->get('user_id');

        if ($id === $actorId) {
            return $this->fail(403, 'Tidak bisa menghapus akun sendiri.');
        }

        $users  = new UserModel();
        $target = $users->find($id);
        if (! $target) {
            return $this->fail(404, 'User tidak ditemukan.');
        }

        if ($target['role'] === 'superadmin' && $this->activeSuperadminCount() <= 1) {
            return $this->fail(422, 'Minimal harus tersisa satu superadmin.');
        }

        $users->delete($id); // useSoftDeletes = true -> hanya mengisi deleted_at

        (new LogModel())->recordSystemLog($actorId, sprintf(
            'Hapus user #%d (%s, role %s)',
            $id,
            $target['name'],
            $target['role']
        ));

        return $this->response->setJSON([
            'success' => true,
            'message' => 'User dihapus. Data submission dan ranking tetap tersimpan.',
        ]);
    }

    private function activeSuperadminCount(): int
    {
        return (new UserModel())->where('role', 'superadmin')->where('is_active', true)->countAllResults();
    }

    private function fail(int $status, string $message)
    {
        return $this->response->setStatusCode($status)->setJSON([
            'success' => false,
            'message' => $message,
        ]);
    }
}