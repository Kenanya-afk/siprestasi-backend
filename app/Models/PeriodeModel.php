<?php

namespace App\Models;

use CodeIgniter\Model;
use App\Models\PrestasiModel;
use App\Models\OrganisasiModel;
use App\Models\BahasaModel;
use App\Models\ScoreResultModel;

class PeriodeModel extends Model
{
    protected $table         = 'periode_pendaftaran';
    protected $primaryKey    = 'periode_id';
    protected $useTimestamps = false;
    protected $allowedFields = ['tahun_ajaran', 'tanggal_mulai', 'tanggal_selesai', 'is_closed'];

    public function getCurrent(): ?array
    {
        return $this->orderBy('periode_id', 'DESC')->first();
    }

    /**
     * Kalau deadline periode aktif sudah lewat dan Top 3 belum dihitung,
     * hitung sekarang dan kunci periodenya. Aman dipanggil berkali-kali.
     */
        public function closeIfDeadlinePassed(): void
    {
        $periode = $this->getCurrent();

        if (! $periode) {
            return;
        }

        $isClosed = in_array($periode['is_closed'], [true, 't', '1', 1], true);

        if ($isClosed) {
            return;
        }

        if (strtotime($periode['tanggal_selesai']) >= strtotime(date('Y-m-d'))) {
            return; // deadline belum lewat
        }

        $db = \Config\Database::connect();

                // Finalisasi: kategori yang masih pending/revisi saat periode ditutup -> ditolak permanen
        $kategoriMap = [
            'prestasi'   => ['model' => PrestasiModel::class, 'prefix' => 'pres'],
            'organisasi' => ['model' => OrganisasiModel::class, 'prefix' => 'org'],
            'bahasa'     => ['model' => BahasaModel::class, 'prefix' => 'bah'],
        ];

        $subIdsTerdampak = [];

        foreach ($kategoriMap as $cfg) {
            $model  = new $cfg['model']();
            $prefix = $cfg['prefix'];

            $belumFinal = $model->whereIn($prefix . '_status', ['pending', 'revisi'])->findAll();

            foreach ($belumFinal as $doc) {
                $model->update($doc[$prefix . '_id'], [
                    $prefix . '_status' => 'ditolak',
                    $prefix . '_score'  => 0,
                ]);
                $subIdsTerdampak[] = $doc['sub_id'];
            }
        }

        $scoreResultModel = new ScoreResultModel();
        foreach (array_unique($subIdsTerdampak) as $subId) {
            $scoreResultModel->calculateAllResults((int) $subId);
        }
        
        $angkatanList = $db->table('users')
            ->distinct()
            ->select('year')
            ->where('year IS NOT NULL', null, false)
            ->get()->getResultArray();

        foreach ($angkatanList as $row) {
            $top3 = $db->table('score_result sr')
                ->select('sr.sub_id')
                ->join('submission s', 's.sub_id = sr.sub_id')
                ->join('users u', 'u.user_id = s.user_id')
                ->where('u.year', $row['year'])
                ->where('s.status', 'submitted')
                ->orderBy('sr.total_score', 'DESC')
                ->limit(3)
                ->get()->getResultArray();

            foreach ($top3 as $t) {
                $db->table('submission')->where('sub_id', $t['sub_id'])->update(['status' => 'top3']);
            }
        }

        $this->update($periode['periode_id'], ['is_closed' => true]);
    }
}