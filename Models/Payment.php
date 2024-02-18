<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $table  = 'payments';

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function isPaid()
    {
        return $this->status === 'SUCCESS';
    }

    // @obsolete
    public function user_subscription()
    {
        return $this->hasOne(UserSubscription::class, 'payment_id');
    }

    public function user_subscriptions()
    {
        return $this->hasMany(UserSubscription::class, 'payment_id');
    }

    // @obsolete
    public function user_subscription_for_renew()
    {
        return $this->hasOne(UserSubscription::class, 'renew_payment_id');
    }

    public function user_subscriptions_for_renew()
    {
        return $this->hasMany(UserSubscription::class, 'renew_payment_id');
    }

    // @obsolete
    public function user_blog_subscription()
    {
        return $this->hasOne(UserBlogSubscription::class, 'payment_id');
    }

    public function refund()
    {
        return $this->belongsTo(Refund::class, 'refund_id');
    }

    public function user_one_time_purchase()
    {
        return $this->hasOne(UserOneTimePurchase::class, 'payment_id');
    }

    public static function booted()
    {
        static::saved(function($payment) {

            if( ! $payment->isPaid() ) {
                return;
            }

            /** @var \App\Models\User $user */
            $user = auth()->user();

            // mark this users' coupons as used
            if( $user ) {
                try {
                    $userCoupon = $user->user_used_coupons()
                        ->with(['coupon'])
                        ->whereDate('created_at', today())
                        ->latest()
                        ->first();

                    if( $userCoupon ) {
                        $userCoupon->increment('is_used');

                        if( $coupon = $userCoupon->coupon ) {
                            $coupon->decrement('used_times');
                        }
                    }
                    else if( $applied_coupon = session()->pull('applying_coupon') ) {
                        CouponCode::where('code', strtoupper($applied_coupon))
                            ->decrement('used_times');
                    }

                    session()->forget('applying_coupon');
                } catch(\Exception $e) {
                    logger($e->getMessage());
                }
            }
        });
    }
}
