<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    # Define Query Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    # Define Accessors
    public function getStatusTextAttribute()
    {
        $status = [
            0 => 'Deactive',
            1 => 'Active'
        ];
        return $status[$this->status];
    }

    public function magazines()
    {
        return $this->hasMany(Magazine::class);
    }
    public function newspapers()
    {
        return $this->hasMany(Newspaper::class);
    }

    public function stories()
    {
        return $this->hasMany(Blog::class,'blog_category_id');
    }

    public function blogs()
    {
        return $this->stories();
    }
}
