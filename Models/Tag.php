<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tag extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public $timestamps = false;

    public function magazines()
    {
        return $this->morphedByMany(Magazine::class, 'taggable');
    }
    public function newspapers()
    {
        return $this->morphedByMany(Newspaper::class, 'taggable');
    }
}
