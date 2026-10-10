<?php

namespace App\Models;

use CodeIgniter\Model;

class PvResponseModel extends Model {
    protected $table = 'pv_responses';
    protected $primaryKey = 'resp_id';
    protected $allowedFields = [
        'sub_id',
        'quiz_id',
        'reviewer',
        'role',
        'status',
        'total_score',
        'token',
        'email_sent_at'
    ];

    public function findByToken(string $token): ?array
    {
        return $this -> where('token', $token) -> first();
    }
}