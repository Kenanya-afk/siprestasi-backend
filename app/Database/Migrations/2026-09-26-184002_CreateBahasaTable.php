<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateBahasaTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'bah_id' => ['type' => 'BIGINT', 'auto_increment' => true],
            'sub_id' => ['type' => 'BIGINT'],
            'bah_cat' => ['type' => 'VARCHAR', 'constraint' => 50],
            // kategori nilai TOEFL/IELTS
            'bah_score' => ['type' => 'INT', 'null' => true],
            'bah_file' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'bah_status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'pending'],
        ]);
        $this->forge->addPrimaryKey('bah_id');
        $this->forge->addUniqueKey('sub_id');
        $this->forge->addForeignKey('sub_id', 'submission', 'sub_id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('bahasa');
    }

    public function down()
    {
        $this->forge->dropTable('bahasa');
    }
}
