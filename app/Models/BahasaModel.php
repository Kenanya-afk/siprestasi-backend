<?php

namespace App\Models;

use CodeIgniter\Model;

class BahasaModel extends Model
{
    protected $table = 'bahasa';
    protected $primaryKey = 'bah_id';
    protected $allowedFields = ['sub_id', 'bah_cat', 'bah_score', 'bah_file', 'bah_status'];

    public const SCORES = [
        'tinggi'        => 100,
        'menengah_atas' => 90,
        'menengah'      => 80,
        'dasar'         => 60,
    ];

    public function setBahasaScore(string $kategori): int
    {
        return self::SCORES[$kategori] ?? 0;
    }
}