<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddCatatanToKategoriTables extends Migration
{
    public function up()
    {
        $this->forge->addColumn('prestasi', ['catatan' => ['type' => 'TEXT', 'null' => true]]);
        $this->forge->addColumn('organisasi', ['catatan' => ['type' => 'TEXT', 'null' => true]]);
        $this->forge->addColumn('bahasa', ['catatan' => ['type' => 'TEXT', 'null' => true]]);
    }

    public function down()
    {
        $this->forge->dropColumn('prestasi', 'catatan');
        $this->forge->dropColumn('organisasi', 'catatan');
        $this->forge->dropColumn('bahasa', 'catatan');
    }
}