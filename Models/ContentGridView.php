<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContentGridView extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $table = 'content_grid_view';

    public $timestamps =false;
}
