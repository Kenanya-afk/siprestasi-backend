<?php

namespace App\Models;

use CodeIgniter\Model;

class LogModel extends Model
{
    protected $table      = 'log';
    protected $primaryKey = 'log_id';
    protected $allowedFields = ['sub_id', 'user_id', 'action', 'logged_at'];

    public function recordLog(int $subId, int $userId, string $action): bool
    {
        return (bool) $this->insert([
            'sub_id'    => $subId,
            'user_id'   => $userId,
            'action'    => mb_strimwidth($action, 0, 255, '...'),
            'logged_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function recordSystemLog(int $userId, string $action): bool
    {
        return (bool) $this->insert([
            'sub_id'    => null,
            'user_id'   => $userId,
            'action'    => mb_strimwidth($action, 0, 255, '...'),
            'logged_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Daftar log (hanya baca) dengan filter + paginasi.
     * $filters: user_id, sub_id, action (LIKE), from (Y-m-d), to (Y-m-d)
     *
     * @return array{rows: array, total: int}
     */
    public function search(array $filters, int $page, int $perPage): array
    {
        $b = $this->db->table('log l')
            ->select('l.log_id, l.sub_id, l.user_id, u.name AS user_name, u.role AS user_role, l.action, l.logged_at')
            ->join('users u', 'u.user_id = l.user_id', 'left'); // left join: user terhapus tetap tampil

        if (! empty($filters['user_id'])) {
            $b->where('l.user_id', (int) $filters['user_id']);
        }
        if (! empty($filters['sub_id'])) {
            $b->where('l.sub_id', (int) $filters['sub_id']);
        }
        if (! empty($filters['action'])) {
            $b->like('l.action', $filters['action'], 'both', null, true);
        }
        if (! empty($filters['from'])) {
            $b->where('l.logged_at >=', $filters['from'] . ' 00:00:00');
        }
        if (! empty($filters['to'])) {
            $b->where('l.logged_at <=', $filters['to'] . ' 23:59:59');
        }

        $total = $b->countAllResults(false);

        $rows = $b->orderBy('l.logged_at', 'DESC')
            ->orderBy('l.log_id', 'DESC')
            ->limit($perPage, ($page - 1) * $perPage)
            ->get()
            ->getResultArray();

        return ['rows' => $rows, 'total' => $total];
    }
}