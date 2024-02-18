<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserDevice extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $table = 'user_devices';

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
