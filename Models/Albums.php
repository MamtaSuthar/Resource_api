<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Albums extends Model
{
    use HasFactory;
    protected $guarded = [];

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
    public function gallary_images(){
        return $this->hasMany(AlbumGallery::class,'album_id');
    }
}
