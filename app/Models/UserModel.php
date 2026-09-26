<?php

namespace App\Models;

use CodeIgniter\Model;

class UserModel extends Model
{
    protected $table            = 'users';
    protected $primaryKey       = 'user_id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;

    protected $allowedFields = [
        'name',
        'email',
        'password',
        'induk',
        'contact',
        'picture',
        'year',
        'krs_path',
        'status_verifikasi',
        'role',
        'is_active',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'name'     => 'required|min_length[3]|max_length[150]',
        'email'    => 'required|valid_email|is_unique[users.email,user_id,{user_id}]',
        'password' => 'required|min_length[8]',
        'role'     => 'required|in_list[mahasiswa,admin,superadmin,dosen]',
    ];

    protected $validationMessages = [
        'email' => [
            'is_unique' => 'Email sudah terdaftar, silakan gunakan email lain.',
        ],
    ];

    public function findByEmail(string $email)
    {
        return $this->where('email', $email)->first();
    }
}
