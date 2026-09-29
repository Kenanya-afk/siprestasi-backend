<?php

namespace App\Models;

use CodeIgniter\Model;

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