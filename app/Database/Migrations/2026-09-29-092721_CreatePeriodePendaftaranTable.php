<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreatePeriodePendaftaranTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'periode_id'      => ['type' => 'BIGINT', 'auto_increment' => true],
            'tahun_ajaran'    => ['type' => 'VARCHAR', 'constraint' => 20],
            'tanggal_mulai'   => ['type' => 'DATE'],
            'tanggal_selesai' => ['type' => 'DATE'], // deadline
            'is_closed'       => ['type' => 'BOOLEAN', 'default' => false],
            'created_at'      => ['type' => 'TIMESTAMP', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('periode_id');
        $this->forge->createTable('periode_pendaftaran');
    }

    public function down()
    {
        $this->forge->dropTable('periode_pendaftaran');
    }
}