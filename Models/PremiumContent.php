<?php

namespace App\Models;

use App\Vars\Helper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class PremiumContent extends Model
{
    protected static $auth_user = null;

    protected static $publication = null;

    protected static $cache_cleard = false;

    #obsolete
    public function grids()
    {
        return $this->hasMany(ContentGridView::class, 'content_id');
    }

    #obsolete
    public function grid_with_type()
    {
        $content_type = \strtolower($this->type);

        return $this->grids()->where('content_type', $content_type);
    }

    public function getPriceAttribute()
    {
        $amount = floatval($this->getRawOriginal('price'));

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

        // price for newspaper will always come from publication
        if( $this->type === 'Newspaper' ) {
            if( is_null(static::$publication) ) {
                static::$publication = $this->publication;
            }

            $publication = static::$publication;

            if( $user && $publication ) {
                if( $user->isIos || !$user->isCurrencyLocal ) {
                    return Helper::to_price($publication->newspaper_price_usd);
                }

                return Helper::to_price($publication->newspaper_price_ghs);
            }
        }

        if( $user && $user->userPlatform != 'ios' && $amount > 0 && !$user->is_currency_local ) {
            // if currency international (USD)
            // add 1.95% to it
            $amount += $amount * 0.0195; // 1.95%
        }

        return Helper::to_price($amount);
    }

    public function vendor()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public static function booted()
    {
        static::saved(function() {

            // clear magazine, newspaper homepage cache
            if( ! static::$cache_cleard ) {
                static::$cache_cleard = true;

                Cache::forget('home_mags0');
                Cache::forget('home_mags1');
                Cache::forget('home_news0');
                Cache::forget('home_news1');
            }
        });
    }
}
