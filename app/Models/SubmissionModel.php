<?php

namespace App\Models;

use CodeIgniter\Model;

class SubmissionModel extends Model
{
    protected $table         = 'submission';
    protected $primaryKey    = 'sub_id';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'user_id', 'status',
        'peer_reviewer_1', 'peer_reviewer_2', 'peer_reviewer_3',
        'lecturer_reviewer_1', 'lecturer_reviewer_2',
        'rejection_notes', 'reviewed_by', 'submitted_at',
    ];

    // Submission yang masih "hidup" (belum ditolak)
    public function findActiveByUser(int $userId): ?array
    {
        return $this->where('user_id', $userId)
                    ->whereIn('status', ['draft', 'submitted', 'pending_reviewed', 'top3', 'ranked'])
                    ->first();
    }
}