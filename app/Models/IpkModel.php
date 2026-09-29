<?php

namespace App\Models;

use CodeIgniter\Model;

class IpkModel extends Model
{
    protected $table         = 'ipk';
    protected $primaryKey    = 'ipk_id';
    protected $allowedFields = ['sub_id', 'ipk_nilai', 'ipk_score'];

    public function convertIpkScore(float $ipk): int
    {
        if ($ipk >= 3.75) return 100;
        if ($ipk >= 3.50) return 80;
        if ($ipk >= 3.00) return 60;
        if ($ipk >= 2.50) return 40;
        return 20;
    }
}