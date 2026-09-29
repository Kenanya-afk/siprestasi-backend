<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateMasterDataTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'master_id' => ['type' => 'BIGINT', 'auto_increment' => true],
            'period'    => ['type' => 'VARCHAR', 'constraint' => 20],
            'induk'     => ['type' => 'VARCHAR', 'constraint' => 30],
            'name'      => ['type' => 'VARCHAR', 'constraint' => 150],
            'ipk'       => ['type' => 'DECIMAL', 'constraint' => '3,2'],
            'skpi'      => ['type' => 'INT'],
            'imported_at' => ['type' => 'TIMESTAMP', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('master_id');
        $this->forge->addKey(['induk', 'period']); // buat lookup cepat pas matching submission
        $this->forge->createTable('master_data');
    }

    public function down()
    {
        $this->forge->dropTable('master_data');
    }
}
 