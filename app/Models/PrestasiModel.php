<?php

namespace App\Models;

use CodeIgniter\Model;

class PrestasiModel extends Model
{
    protected $table         = 'prestasi';
    protected $primaryKey = 'prestasi_id';
    protected $allowedFields = ['sub_id', 'pres_cat', 'pres_score', 'pres_file', 'pres_status'];

    public const SCORES = [
        'internasional_juara'   => 100,
        'internasional_finalis' => 95,
        'nasional_juara'        => 90,
        'nasional_finalis'      => 85,
        'regional_juara'        => 80,
        'regional_finalis'      => 75,
        'universitas_juara'     => 70,
        'universitas_finalis'   => 65,
    ];

    public function setPrestasiScore(string $kategori): int
    {
        return self::SCORES[$kategori] ?? 0;
    }
}