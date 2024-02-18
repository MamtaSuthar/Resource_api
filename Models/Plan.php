<?php

namespace App\Models;

use App\Http\Resources\UserSubscription;
use App\Models\UserSubscription as ModelsUserSubscription;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $table = 'plans';

    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    public function publications()
    {
        return $this->belongsToMany(Publication::class, PlanPublication::class, 'plan_id', 'publication_id');
    }

    public function durations()
    {
        return $this->hasMany(PlanDuration::class, 'plan_id');
    }
    /**
     * # Define Accessors
     */
    public function getStatusTextAttribute()
    {
        $status = [
            0 => 'Deactive',
            1 => 'Active'
        ];
        return $status[$this->status];
    }

    public function getUserSubscriptions(){
        return $this->hasMany(ModelsUserSubscription::class, 'plan_id');
    }
    

}
