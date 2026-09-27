<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateIpkTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'ipk_id' => ['type' => 'BIGINT', 'auto_increment' => true],
            'sub_id' => ['type' => 'BIGINT'],
            'ipk_nilai' => ['type' => 'DECIMAL', 'constraint' => '3,2'],
            'ipk_score' => ['type' => 'INT'],
        ]);
        $this->forge->addPrimaryKey('ipk_id');
        $this->forge->addUniqueKey('sub_id'); // 1:1 wajib
        $this->forge->addForeignKey('sub_id', 'submission', 'sub_id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('ipk');
    }

    public function down()
    {
        $this->forge->dropTable('ipk');
    }
}
