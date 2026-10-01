<?php

namespace App\Models;

use CodeIgniter\Model;

class PvQuestionModel extends Model
{
    protected $table         = 'pv_questions';
    protected $primaryKey    = 'quest_id';
    protected $allowedFields = ['quiz_id', 'head', 'subhead', 'answer_type', 'weight'];
}