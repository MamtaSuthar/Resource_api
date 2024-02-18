<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserSubscription extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $table = 'user_subscriptions';

    protected $casts = [
        'subscribed_at' => 'datetime:Y-m-d H:i:s',
        'expires_at' => 'datetime:Y-m-d H:i:s'
    ];

    // user who bought this subscription
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    
    public function plan()
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }

    public function plan_duration()
    {
        return $this->belongsTo(PlanDuration::class, 'plan_duration_id');
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }

    public function member_subscriptions()
    {
        return $this->hasMany(UserSubscription::class, 'via_referral', 'referral_code');
    }

    public function scopeActive($query)
    {
        $now = now()->format('Y-m-d H:i:s');

        return $query->where('pay_status', 1)
            ->whereDate('subscribed_at', '<=', $now)
            ->whereDate('expires_at', '>=', $now);
    }

    // whether a subscription is bought using referral
    public function getUsingReferralAttribute()
    {
        return !is_null($this->via_referral);
    }

    // is subscription currently active
    public function getIsActiveAttribute()
    {
        $now = now();

        return $this->pay_status == 1
            && $this->subscribed_at->lte($now)
            && $this->expires_at->gte($now);
    }

    public function getStatusStrAttribute()
    {
        if( $this->is_active ) {
            return 'Active';
        }

        return 'Expired or Pending Payment';
    }
}
