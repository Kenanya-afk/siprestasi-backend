<?php

namespace App\Models;

use CodeIgniter\Model;

class MasterDataModel extends Model
{
    protected $table         = 'master_data';
    protected $primaryKey    = 'master_id';
    protected $allowedFields = ['period', 'induk', 'name', 'ipk', 'skpi', 'imported_at'];

    // Data master untuk satu NIM pada satu periode
    public function findByIndukAndPeriod(string $induk, string $period): ?array
    {
        return $this->where('induk', $induk)
                    ->where('period', $period)
                    ->first();
    }

    public function existsByInduk(string $induk): ?array
    {
        return $this -> where ('induk', $induk)->first();
    }
}