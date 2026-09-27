<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateSubmissionLogTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'log_id' => ['type' => 'BIGINT', 'auto_increment' => true],
            'sub_id' => ['type' => 'BIGINT'],
            'user_id' => ['type' => 'BIGINT'],
            'action' => ['type' => 'VARCHAR', 'constraint' => 30],
            // submitted | approved | rejected | dst
            'logged_at' => ['type' => 'TIMESTAMP', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('log_id');
        $this->forge->addForeignKey('sub_id', 'submission', 'sub_id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('user_id', 'users', 'user_id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('log');
    }

    public function down()
    {
        $this->forge->dropTable('log');
    }
}
