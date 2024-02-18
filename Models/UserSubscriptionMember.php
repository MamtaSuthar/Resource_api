<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#OBSOLETE
class UserSubscriptionMember extends Model
{
    use HasFactory;

    protected $timestamps = false;

    protected $guarded = ['id'];

    protected $table = 'user_subscription_members';

    public function user_subscription()
    {
        return $this->belongsTo(UserSubscription::class, 'user_subscription_id');
    }

    public function member()
    {
        return $this->belongsTo(User::class, 'member_id');
    }
}
