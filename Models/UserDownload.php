<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserDownload extends Model
{
    use HasFactory;

    protected $table = 'user_downloads';

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function file()
    {
        return $this->morphTo('file', 'file_type', 'file_id');
    }

    public function getIsMagazineAttribute()
    {
        return $this->file_type === 'magazine';
    }
}
