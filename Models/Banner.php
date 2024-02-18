<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
    use HasFactory;

    protected $table = 'banners';

    protected $guarded = ['id'];

    public static function booted()
    {
        static::addGlobalScope('active', function($builder) {
            $builder->where('status', 1);
        });
    }
}
