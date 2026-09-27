<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateSubmissionTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'sub_id' => ['type' => 'BIGINT', 'auto_increment' => true],
            'user_id' => ['type' => 'BIGINT'],
            'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'draft'],
            // draft | submitted | pending_reviewed | rejected | ranked
            'peer_reviewer_1' => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'peer_reviewer_2' => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'rejection_notes' => ['type' => 'TEXT', 'null' => true],
            'reviewed_by' => ['type' => 'BIGINT', 'null' => true],
            'submitted_at' => ['type' => 'TIMESTAMP', 'null' => true],
            'created_at' => ['type' => 'TIMESTAMP', 'null' => true],
            'updated_at' => ['type' => 'TIMESTAMP', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('sub_id');
        $this->forge->addForeignKey('user_id', 'users', 'user_id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('reviewed_by', 'users', 'user_id', 'CASCADE', 'SET NULL');
        $this->forge->createTable('submission');
    }

    public function down()
    {
        $this->forge->dropTable('submission');
    }
}
