<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateOrganisasiTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'org_id' => ['type' => 'BIGINT', 'auto_increment' => true],
            'sub_id' => ['type' => 'BIGINT'],
            'org_cat' => ['type' => 'VARCHAR', 'constraint' => 50],
            // ketua | wakil_ketua | koordinator | anggota_aktif | pasif
            'org_score' => ['type' => 'INT', 'null' => true],
            'org_file' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'org_status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'pending'],
        ]);
        $this->forge->addPrimaryKey('org_id');
        $this->forge->addUniqueKey('sub_id');
        $this->forge->addForeignKey('sub_id', 'submission', 'sub_id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('organisasi');
    }

    public function down()
    {
        $this->forge->dropTable('organisasi');
    }
}
