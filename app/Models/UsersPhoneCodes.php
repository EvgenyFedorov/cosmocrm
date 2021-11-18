<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UsersPhoneCodes extends Model
{
    protected $fillable = [
        'user_id',
        'code'
    ];
}
