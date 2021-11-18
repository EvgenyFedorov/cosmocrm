<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UsersInRoles extends Model
{
    protected $fillable = [
        'user_id',
        'organization_id',
        'role_id'
    ];
}
