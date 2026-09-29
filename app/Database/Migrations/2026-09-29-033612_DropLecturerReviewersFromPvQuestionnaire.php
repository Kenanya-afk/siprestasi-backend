<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class DropLecturerReviewersFromPvQuestionnaire extends Migration
{
    public function up()
    {
        $this->forge->dropColumn('pv_questionnaire', ['lecturer_reviewer_1', 'lecturer_reviewer_2']);
    }

    public function down()
    {
        $this->forge->addColumn('pv_questionnaire', [
            'lecturer_reviewer_1' => ['type' => 'BIGINT'],
            'lecturer_reviewer_2' => ['type' => 'BIGINT'],
        ]);
    }
}