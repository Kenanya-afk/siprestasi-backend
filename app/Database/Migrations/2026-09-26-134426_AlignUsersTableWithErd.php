<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AlignUsersTableWithErd extends Migration
{
    public function up()
    {
        // Rename kolom-kolom supaya sesuai ERD
    $this->db->query('ALTER TABLE users RENAME COLUMN id TO user_id');
    $this->db->query('ALTER TABLE users RENAME COLUMN nama TO name');
    $this->db->query('ALTER TABLE users RENAME COLUMN nim TO induk');
    $this->db->query('ALTER TABLE users RENAME COLUMN no_telp TO contact');

    // Tambah kolom baru dari ERD yang belum ada
    $this->forge->addColumn('users', [
        'picture' => [
            'type'       => 'VARCHAR',
            'constraint' => 255,
            'null'       => true,
            'after'      => 'contact',
        ],
        'year' => [
            // angkatan mahasiswa, contoh: "2024"
            'type'       => 'VARCHAR',
            'constraint' => 4,
            'null'       => true,
            'after'      => 'picture',
        ],
    ]);

    // Ganti status (VARCHAR aktif/nonaktif) jadi is_active (BOOLEAN), sesuai ERD
    $this->db->query('ALTER TABLE users ADD COLUMN is_active BOOLEAN NOT NULL DEFAULT true');
    $this->db->query("UPDATE users SET is_active = (status = 'aktif')");
    $this->db->query('ALTER TABLE users DROP COLUMN status');
    }

    public function down()
    {
         $this->db->query('ALTER TABLE users RENAME COLUMN user_id TO id');
    $this->db->query('ALTER TABLE users RENAME COLUMN name TO nama');
    $this->db->query('ALTER TABLE users RENAME COLUMN induk TO nim');
    $this->db->query('ALTER TABLE users RENAME COLUMN contact TO no_telp');

    $this->forge->dropColumn('users', ['picture', 'year']);

    $this->db->query("ALTER TABLE users ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'aktif'");
    $this->db->query("UPDATE users SET status = CASE WHEN is_active THEN 'aktif' ELSE 'nonaktif' END");
    $this->db->query('ALTER TABLE users DROP COLUMN is_active');
    }
}
