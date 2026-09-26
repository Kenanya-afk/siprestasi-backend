<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class SuperAdminSeeder extends Seeder
{
    public function run()
    {
        $sudahAda = $this->db->table('users')
            ->where('email', 'superadmin@siprestasi.com')
            ->get()
            ->getRow();

        if ($sudahAda) {
            echo "Superadmin sudah ada, skip.\n";
            return;
        }

        $this->db->table('users')->insert([
            'nama'              => 'Super Admin',
            'email'             => 'superadmin@siprestasi.com',
            'password'          => password_hash('SuperAdmin123!', PASSWORD_BCRYPT),
            'role'              => 'superadmin',
            'status'            => 'aktif',
            'status_verifikasi' => 'terverifikasi',
            'created_at'        => date('Y-m-d H:i:s'),
            'updated_at'        => date('Y-m-d H:i:s'),
        ]);

        echo "Superadmin berhasil dibuat.\n";
    }
}
