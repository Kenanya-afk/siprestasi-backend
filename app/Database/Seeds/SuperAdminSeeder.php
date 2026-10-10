<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class SuperAdminSeeder extends Seeder
{
    public function run()
    {
        // Seeder lama memakai kolom `nama` dan `status` yang sudah di-rename/dihapus
        // oleh migrasi AlignUsersTableWithErd (jadi `name` dan `is_active`), sehingga gagal.
        // Password juga tidak lagi ditulis di kode: set SUPERADMIN_PASSWORD di .env.
        $password = env('SUPERADMIN_PASSWORD');
        if (! $password || strlen($password) < 12) {
            echo "Set SUPERADMIN_PASSWORD (minimal 12 karakter) di .env dulu.\n";
            return;
        }

        $email = 'superadmin@siprestasi.com';

        $sudahAda = $this->db->table('users')->where('email', $email)->get()->getRow();
        if ($sudahAda) {
            echo "Superadmin sudah ada, skip.\n";
            return;
        }

        $now = date('Y-m-d H:i:s');
        $this->db->table('users')->insert([
            'name'              => 'Super Admin',
            'email'             => $email,
            'password'          => password_hash($password, PASSWORD_BCRYPT),
            'role'              => 'superadmin',
            'is_kaprodi'        => false,
            'is_active'         => true,
            'status_verifikasi' => 'terverifikasi',
            'created_at'        => $now,
            'updated_at'        => $now,
        ]);

        echo "Superadmin berhasil dibuat.\n";
    }
}