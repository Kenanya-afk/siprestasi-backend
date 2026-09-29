<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddIsKaprodiToUsers extends Migration
{
    public function up()
    {
        $this->forge->addColumn('users', [
            'is_kaprodi' => ['type' => 'BOOLEAN', 'default' => false, 'after' => 'role'],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('users', 'is_kaprodi');
    }
}