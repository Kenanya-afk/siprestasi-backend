<?php

namespace App\Models;

use CodeIgniter\Model;

class DetailResponseModel extends Model
{
    protected $table         = 'detail_response';
    protected $primaryKey    = 'resp_id';
    protected $allowedFields = [
        'resp_id','quest_id','score'
    ];
    protected $useAutoIncrement = false;
}