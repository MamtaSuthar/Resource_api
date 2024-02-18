<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AlbumGallery extends Model
{
    use HasFactory;
    protected $table = 'album_galleries';
    protected $guarded = [];
}
