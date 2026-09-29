<?php

namespace App\Models;

use CodeIgniter\Model;

class SkpiModel extends Model
{
    protected $table         = 'skpi';
    protected $primaryKey    = 'skpi_id';
    protected $allowedFields = ['sub_id', 'skpi_nilai', 'skpi_score'];

    public function convertSkpiScore(int $nilai): int
    {
        if ($nilai >= 300) return 100;
        if ($nilai >= 250) return 85;
        if ($nilai >= 200) return 70;
        if ($nilai >= 101) return 60;
        return 30;
    }
}