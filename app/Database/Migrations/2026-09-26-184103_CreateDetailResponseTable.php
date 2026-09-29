<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateDetailResponseTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'resp_id' => ['type' => 'BIGINT'],
            'quest_id' => ['type' => 'BIGINT'],
            'score' => ['type' => 'INT'],
        ]);
        $this->forge->addPrimaryKey(['resp_id', 'quest_id']); // composite PK
        $this->forge->addForeignKey('resp_id', 'pv_responses', 'resp_id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('quest_id', 'pv_questions', 'quest_id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('detail_response');
    }

    public function down()
    {
        $this->forge->dropTable('detail_response');
    }
}
