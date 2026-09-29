<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreatePraditaValueTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'pv_id' => ['type' => 'BIGINT', 'auto_increment' => true],
            'sub_id' => ['type' => 'BIGINT'],
            'pv_score' => ['type' => 'INT', 'null' => true], // diisi via aggregatePvScore()
        ]);
        $this->forge->addPrimaryKey('pv_id');
        $this->forge->addUniqueKey('sub_id');
        $this->forge->addForeignKey('sub_id', 'submission', 'sub_id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('pradita_value');
    }

    public function down()
    {
        $this->forge->dropTable('pradita_value');
    }
}
