<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserBlogSubscription extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $table = 'user_blog_subscriptions';

    protected $casts = [
        'subscribed_at' => 'datetime:Y-m-d H:i:s',
        'expires_at' => 'datetime:Y-m-d H:i:s'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    
    public function plan()
    {
        return $this->belongsTo(BlogPlan::class, 'blog_plan_id');
    }

    public function plan_duration()
    {
        return $this->belongsTo(BlogPlanDuration::class, 'blog_plan_duration_id');
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }
}
