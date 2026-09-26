<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddVerificationFieldsToUsers extends Migration
{
    public function up()
    {
      $this->forge->addColumn('users', [
        'no_telp' => [
            'type'       => 'VARCHAR',
            'constraint' => 20,
            'null'       => true,
            'after'      => 'nim',
        ],
        'krs_path' => [
            // menyimpan nama/path file KRS yang di-upload
            'type'       => 'VARCHAR',
            'constraint' => 255,
            'null'       => true,
            'after'      => 'no_telp',
        ],
        'status_verifikasi' => [
            // pending | terverifikasi | ditolak
            'type'       => 'VARCHAR',
            'constraint' => 20,
            'default'    => 'pending',
            'after'      => 'krs_path',
        ],
    ]);
    }

    public function down()
    {
        $this->forge->dropColumn('users', ['no_telp', 'krs_path', 'status_verifikasi']);
    }
}
