<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreatePrestasiTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'pres_id' => ['type' => 'BIGINT', 'auto_increment' => true],
            'sub_id' => ['type' => 'BIGINT'],
            'pres_cat' => ['type' => 'VARCHAR', 'constraint' => 50],
            // contoh: 'internasional_juara', 'nasional_finalis', dst
            'pres_score' => ['type' => 'INT', 'null' => true],
            'pres_file' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'pres_status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'pending'],
        ]);
        $this->forge->addPrimaryKey('pres_id');
        $this->forge->addUniqueKey('sub_id');
        $this->forge->addForeignKey('sub_id', 'submission', 'sub_id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('prestasi');
    }

    public function down()
    {
        $this->forge->dropTable('prestasi');
    }
}
