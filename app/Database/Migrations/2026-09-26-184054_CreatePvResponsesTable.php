<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreatePvResponsesTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'resp_id' => ['type' => 'BIGINT', 'auto_increment' => true],
            'sub_id' => ['type' => 'BIGINT'],
            'quiz_id' => ['type' => 'BIGINT'],
            'reviewer' => ['type' => 'VARCHAR', 'constraint' => 150],
            // email rekan sejawat, atau user_id dosen (disimpan sbg string)
            'role' => ['type' => 'VARCHAR', 'constraint' => 20], // dosen | sejawat
            'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'pending'],
            'total_score' => ['type' => 'INT', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('resp_id');
        $this->forge->addForeignKey('sub_id', 'submission', 'sub_id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('quiz_id', 'pv_questionnaire', 'quiz_id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('pv_responses');
    }

    public function down()
    {
        $this->forge->dropTable('pv_responses');
    }
}
