<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BlogCategory extends Model
{
    use HasFactory;

    protected $guarded = ['id'];
    

    ## Define Query Scopes ##
    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    ## Define Accessors ##
    public function getStatusTextAttribute()
    {
        $status = [
            0 => 'Deactive',
            1 => 'Active'
        ];
        return $status[$this->status];
    }

    public function blogs()
    {
        return $this->hasMany(Blog::class);
    }
}
