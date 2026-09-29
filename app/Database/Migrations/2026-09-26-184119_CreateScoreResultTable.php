<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateScoreResultTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'result_id' => ['type' => 'BIGINT', 'auto_increment' => true],
            'sub_id' => ['type' => 'BIGINT'],
            'ipk_result' => ['type' => 'DECIMAL', 'constraint' => '5,2', 'null' => true],
            'pres_result' => ['type' => 'DECIMAL', 'constraint' => '5,2', 'null' => true],
            'skpi_result' => ['type' => 'DECIMAL', 'constraint' => '5,2', 'null' => true],
            'pv_result' => ['type' => 'DECIMAL', 'constraint' => '5,2', 'null' => true],
            'org_result' => ['type' => 'DECIMAL', 'constraint' => '5,2', 'null' => true],
            'bah_result' => ['type' => 'DECIMAL', 'constraint' => '5,2', 'null' => true],
            'total_score' => ['type' => 'DECIMAL', 'constraint' => '5,2', 'null' => true], // read-only, hasil SUM
            'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'pending_review'],
            'calculated_at' => ['type' => 'TIMESTAMP', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('result_id');
        $this->forge->addUniqueKey('sub_id');
        $this->forge->addForeignKey('sub_id', 'submission', 'sub_id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('score_result');
    }

    public function down()
    {
        $this->forge->dropTable('score_result');
    }
}
