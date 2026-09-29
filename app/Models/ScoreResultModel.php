<?php

namespace App\Models;

use CodeIgniter\Model;

class ScoreResultModel extends Model
{
    protected $table         = 'score_result';
    protected $primaryKey    = 'result_id';
    protected $allowedFields = [
        'sub_id', 'ipk_result', 'pres_result', 'skpi_result',
        'pv_result', 'org_result', 'bah_result', 'total_score',
        'status', 'calculated_at',
    ];

    private const WEIGHT_IPK  = 0.30;
    private const WEIGHT_PRES = 0.20;
    private const WEIGHT_SKPI = 0.20;
    private const WEIGHT_ORG  = 0.10;
    private const WEIGHT_BAH  = 0.05;

    public function calculateAllResults(int $subId): array
    {
        $db = \Config\Database::connect();

        $ipk  = $db->table('ipk')->select('ipk_score')->where('sub_id', $subId)->get()->getRowArray();
        $skpi = $db->table('skpi')->select('skpi_score')->where('sub_id', $subId)->get()->getRowArray();
        $pres = $db->table('prestasi')->select('pres_score')->where('sub_id', $subId)->get()->getRowArray();
        $org  = $db->table('organisasi')->select('org_score')->where('sub_id', $subId)->get()->getRowArray();
        $bah  = $db->table('bahasa')->select('bah_score')->where('sub_id', $subId)->get()->getRowArray();

        $ipkResult  = round((float) ($ipk['ipk_score'] ?? 0) * self::WEIGHT_IPK, 2);
        $skpiResult = round((float) ($skpi['skpi_score'] ?? 0) * self::WEIGHT_SKPI, 2);
        $presResult = round((float) ($pres['pres_score'] ?? 0) * self::WEIGHT_PRES, 2);
        $orgResult  = round((float) ($org['org_score'] ?? 0) * self::WEIGHT_ORG, 2);
        $bahResult  = round((float) ($bah['bah_score'] ?? 0) * self::WEIGHT_BAH, 2);

        $totalScore = $ipkResult + $skpiResult + $presResult + $orgResult + $bahResult;

        $data = [
            'sub_id'        => $subId,
            'ipk_result'    => $ipkResult,
            'skpi_result'   => $skpiResult,
            'pres_result'   => $presResult,
            'org_result'    => $orgResult,
            'bah_result'    => $bahResult,
            'pv_result'     => null,
            'total_score'   => $totalScore,
            'status'        => 'pending_review',
            'calculated_at' => date('Y-m-d H:i:s'),
        ];

        $existing = $this->where('sub_id', $subId)->first();
        if ($existing) {
            $this->update($existing['result_id'], $data);
        } else {
            $this->insert($data);
        }

        return $data;
    }
}