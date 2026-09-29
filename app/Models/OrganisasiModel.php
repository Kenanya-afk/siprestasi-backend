<?php

namespace App\Models;

use CodeIgniter\Model;

class OrganisasiModel extends Model
{
    protected $table = 'organisasi';
    protected $primaryKey = 'org_id';
    protected $allowedFields = ['sub_id', 'org_cat', 'org_score', 'org_file', 'org_status'];

    public const SCORES = [
        'internasional_ketua' => 100,
        'internasional_wakil' => 90,
        'internasional_anggota_aktif' => 70,
        'nasional_ketua' => 90,
        'nasional_wakil' => 80,
        'nasional_anggota_aktif' => 60,
        'lokal_ketua' => 80,
        'lokal_wakil' => 70,
        'lokal_anggota_aktif' => 50,
    ];

    public function setOrgScore(string $kategori): int
    {
        return self::SCORES[$kategori] ?? 0;
    }
}