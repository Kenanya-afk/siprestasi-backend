<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddReviewersToSubmission extends Migration
{
    public function up()
    {
        $this->forge->addColumn('submission', [
            'peer_reviewer_3'     => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'lecturer_reviewer_1' => ['type' => 'BIGINT', 'null' => true], // kaprodi
            'lecturer_reviewer_2' => ['type' => 'BIGINT', 'null' => true], // dosen prodi lain
        ]);
        $this->forge->addForeignKey('lecturer_reviewer_1', 'users', 'user_id', 'CASCADE', 'SET NULL', 'fk_submission_lecturer1');
        $this->forge->addForeignKey('lecturer_reviewer_2', 'users', 'user_id', 'CASCADE', 'SET NULL', 'fk_submission_lecturer2');
        $this->forge->processIndexes('submission');
    }

    public function down()
    {
        $this->forge->dropForeignKey('submission', 'fk_submission_lecturer1');
        $this->forge->dropForeignKey('submission', 'fk_submission_lecturer2');
        $this->forge->dropColumn('submission', ['peer_reviewer_3', 'lecturer_reviewer_1', 'lecturer_reviewer_2']);
    }
}