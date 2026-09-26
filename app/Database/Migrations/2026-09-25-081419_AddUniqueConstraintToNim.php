<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddUniqueConstraintToNim extends Migration
{
    public function up()
    {
        $this->db->query('ALTER TABLE users ADD CONSTRAINT unique_nim UNIQUE (nim)');
    }

    public function down()
    {
        $this->db->query('ALTER TABLE users DROP CONSTRAINT unique_nim');
    }
}
