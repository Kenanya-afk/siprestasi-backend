<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddSoftDeleteToUsersAndFixLog extends Migration
{
    public function up()
    {
        // 1) Soft delete user: baris tetap ada, jadi ranking/submission/log tidak rusak.
        $this->forge->addColumn('users', [
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        // 2) Perbaikan bug log:
        //    - recordSystemLog() mengisi sub_id = NULL, tapi kolomnya NOT NULL
        //      (error "null value in column sub_id of relation log" di log 2026-10-07).
        //    - MasterDataController memotong action sampai 250 karakter, kolomnya cuma VARCHAR(30).
        $this->forge->modifyColumn('log', [
            'sub_id' => ['type' => 'BIGINT', 'null' => true],
            'action' => ['type' => 'VARCHAR', 'constraint' => 255],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('users', 'deleted_at');
        // Perubahan tabel log sengaja tidak dikembalikan: baris log dengan sub_id NULL
        // sudah mungkin ada dan mengembalikan NOT NULL akan gagal / menghapus audit trail.
    }
}