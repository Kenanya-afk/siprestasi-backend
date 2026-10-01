<?php

namespace App\Models;

use CodeIgniter\Model;

class PraditaValueModel extends Model {
    protected $table         = 'pradita_value';
    protected $primaryKey    = 'pv_id';
    protected $allowedFields = [
        'sub_id', ' pv_score'
    ];
    
    public function aggregatePvScore(int $subId): ?int
    {
        $db = \Config\Database::connect();

        $responses = $db->table('pv_responses')
            ->select('total_score')
            ->where('sub_id', $subId)
            ->where('status', 'submitted')
            ->get()->getResultArray();

        if (count($responses) < 5) {
            return null; // belum lengkap, jangan hitung dulu
        }

        $avg = array_sum(array_column($responses, 'total_score')) / count($responses);
        $pvScore = (int) round($avg);

        $existing = $this->where('sub_id', $subId)->first();
        if ($existing) {
            $this->update($existing['pv_id'], ['pv_score' => $pvScore]);
        } else {
            $this->insert(['sub_id' => $subId, 'pv_score' => $pvScore]);
        }

        return $pvScore;
    }
}