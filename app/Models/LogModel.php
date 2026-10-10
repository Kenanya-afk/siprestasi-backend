<?php

namespace App\Models;

use CodeIgniter\Model;

class LogModel extends Model {
    protected $table = 'log';
    protected $primaryKey = 'log_id';
    protected $allowedFields = ['sub_id', 'user_id', 'action', 'logged_at'];

    public function recordLog(int $subId, int $userId, string $action): bool
    {
        return (bool) $this->insert([
            'sub_id' => $subId,
            'user_id' => $userId,
            'action' => $action,
            'logged_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function recordSystemLog(int $userId, string $action): bool
    {
        return (bool) $this->insert([
            'sub_id'  => null,
            'user_id' => $userId,
            'action'  => $action,
            'logged_at' => date('Y-m-d H:i:s') 
        ]);
    }
}