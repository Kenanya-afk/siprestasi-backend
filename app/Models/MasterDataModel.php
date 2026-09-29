<?php

namespace App\Models;

use CodeIgniter\Model;

class MasterDataModel extends Model
{
    protected $table         = 'master_data';
    protected $primaryKey    = 'master_id';
    protected $allowedFields = ['period', 'induk', 'name', 'ipk', 'skpi', 'imported_at'];

    // Data terbaru untuk satu NIM (kalau ada beberapa periode)
    public function findLatestByInduk(string $induk): ?array
    {
        return $this->where('induk', $induk)
                    ->orderBy('period', 'DESC')
                    ->first();
    }
}