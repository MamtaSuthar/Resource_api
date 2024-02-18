<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserInfo extends Model
{
    use HasFactory;

    protected $table = 'user_info';
    protected $guarded = ['id'];
    
    protected $casts = [
        'dob' => 'date:Y-m-d',
    ];
}
