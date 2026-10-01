<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddTokenToPvResponses extends Migration
{
    public function up()
    {
        $this->forge->addColumn('pv_responses', [
            'token' => [
                'type' => 'VARCHAR',
                'constraint' => 64,
                'unique' => true,
                'null' => true
            ],
        ]);
    }

    public function down()
    {
        $this -> forge -> dropColumn('pv_responses', 'token');
    }
}
