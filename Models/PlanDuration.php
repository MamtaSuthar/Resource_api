<?php

namespace App\Models;

use App\Vars\Helper;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class PlanDuration extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $table = 'plan_durations';

    protected static $auth_user = null;

    public function plan()
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }

    public function siblings()
    {
        return $this->hasMany(self::class, 'plan_id', 'plan_id');
    }

    public function getPriceAttribute()
    {
        $amount = floatval($this->getRawOriginal('price'));

        $user = $this->getAuthUser();

        if( $user && $user->userPlatform != 'ios' && !$user->is_currency_local && $amount > 0 ) {
            // if currency international (USD)
            // add 1.95% to it
            $amount += $amount * 0.0195;
        }

        return Helper::to_price($amount);
    }

    public function getFamilyPriceAttribute()
    {
        $amounts = $this->getRawOriginal('family_price');

        /** @var \App\Models\User $user */
        $user = $this->getAuthUser();

        if( $user && $user->userPlatform != 'ios' && !$user->is_currency_local ) {
            // if currency international (USD)
            // add 1.95% to it
            $amounts = (array) @json_decode($amounts, true);
            $amounts = \array_filter($amounts);
            foreach( $amounts as &$amount ) {
                $amount += $amount * 0.0195;
            }

            $amounts = \json_encode($amounts);
        }

        return $amounts;
    }

    public function getHasDiscountAttribute()
    {
        return $this->discount && $this->discount > 0;
    }

    public function apply_discount()
    {
        $amount = floatval($this->price);

        if( $this->getHasDiscountAttribute() ) {
            $amount = $amount - ($amount * (floatval($this->discount) / 100));
        }

        return to_price($amount);
    }

    private function getAuthUser()
    {
        $user = self::$auth_user;

        if( is_null($user) ) {

            /** @var \App\Models\User $auth_user */
            $auth_user = Auth::user() ?? Auth::guard('api')->user();

            if( $auth_user && $auth_user->isCustomer() ) {
                self::$auth_user = $user = $auth_user;
            } else {
                self::$auth_user = false;
            }
        }

        return $user;
    }
}
