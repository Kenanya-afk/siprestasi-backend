<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreatePvQuestionsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'quest_id' => ['type' => 'BIGINT', 'auto_increment' => true],
            'quiz_id' => ['type' => 'BIGINT'],
            'head' => ['type' => 'VARCHAR', 'constraint' => 150],
            'subhead' => ['type' => 'TEXT'],
            'answer_type' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'skala_1_5'],
            'weight' => ['type' => 'INT', 'default' => 1],
        ]);
        $this->forge->addPrimaryKey('quest_id');
        $this->forge->addForeignKey('quiz_id', 'pv_questionnaire', 'quiz_id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('pv_questions');
    }

    public function down()
    {
        $this->forge->dropTable('pv_questions');
    }
}
