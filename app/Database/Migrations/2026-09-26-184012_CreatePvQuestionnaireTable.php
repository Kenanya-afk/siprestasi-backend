<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreatePvQuestionnaireTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'quiz_id' => ['type' => 'BIGINT', 'auto_increment' => true],
            'year' => ['type' => 'VARCHAR', 'constraint' => 20],
            'head' => ['type' => 'VARCHAR', 'constraint' => 150],
            'subhead' => ['type' => 'TEXT', 'null' => true],
            'lecturer_reviewer_1' => ['type' => 'BIGINT'],
            'lecturer_reviewer_2' => ['type' => 'BIGINT'],
            'is_active' => ['type' => 'BOOLEAN', 'default' => true],
        ]);
        $this->forge->addPrimaryKey('quiz_id');
        $this->forge->addForeignKey('lecturer_reviewer_1', 'users', 'user_id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('lecturer_reviewer_2', 'users', 'user_id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('pv_questionnaire');
    }

    public function down()
    {
        $this->forge->dropTable('pv_questionnaire');
    }
}
