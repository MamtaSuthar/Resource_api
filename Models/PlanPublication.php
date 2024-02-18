<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// many-to-many pivot table
class PlanPublication extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $table = 'plan_publications';

    public function plan()
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }
}
