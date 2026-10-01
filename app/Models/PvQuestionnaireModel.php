<?php

namespace App\Models;

use CodeIgniter\Model;

class PvQuestionnaireModel extends Model
{
    protected $table            = 'pv_questionnaire';
    protected $primaryKey       = 'quiz_id';
    protected $allowedFields    = [
        'year',
        'head',
        'subhead',
        'is_active'
    ];

    public function getActive(): ?array
    {
        return $this->where(
            'is_active', true
            ) -> orderBy (
                'quiz_id', 
                'DESC'
        ) -> first();
    }
}
