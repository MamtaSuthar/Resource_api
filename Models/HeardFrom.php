<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HeardFrom extends Model
{
    use HasFactory;

    protected $table = 'heard_froms';

    protected $guarded = ['id'];
}
