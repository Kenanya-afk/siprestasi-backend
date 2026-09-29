<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateSkpiTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'skpi_id' => ['type' => 'BIGINT', 'auto_increment' => true],
            'sub_id' => ['type' => 'BIGINT'],
            'skpi_nilai' => ['type' => 'INT'],
            'skpi_score' => ['type' => 'INT'],
        ]);
        $this->forge->addPrimaryKey('skpi_id');
        $this->forge->addUniqueKey('sub_id');
        $this->forge->addForeignKey('sub_id', 'submission', 'sub_id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('skpi');
    }

    public function down()
    {
        $this->forge->dropTable('skpi');
    }
}
