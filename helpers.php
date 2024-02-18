<?php

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

if( ! function_exists('to_price') ) {
    function to_price($amount = 0.00, $render = false) {
        $amount = \number_format(round(floatval($amount), 2), 2, '.', '');

        if( $render ) {
            return user_currency() . ' ' . $amount;
        }

        return $amount;
    }
}

if( ! function_exists('user_currency') ) {
    function user_currency() {
        $user = auth()->user() ?? auth('api')->user();

        if( $user ) {
            return $user->myCurrency;
        }

        return 'GHS';
    }
}

if( ! function_exists('get_auth_user') ) {
    function get_auth_user(): User {
        return auth()->user()
            ?? auth('api')->user();
    }
}

if( ! function_exists('carbon_add_days') ) {

    function carbon_add_days(Carbon $carbon_date, int $days) {
        return $carbon_date->clone()
            ->setTime(0,0,0)
            ->addDays($days);
    }
}

if( ! function_exists('_h_cache') ) {

    function _h_cache($key, \Closure $callback, $seconds = 86400) {
        return Cache::remember($key, $seconds, $callback);
    }
}

if( ! function_exists('_user_get_referral_code') ) {
    function _user_get_referral_code($name){
        $subPart = \substr($name,0,4).\Illuminate\Support\Str::random(8);
    
        if(User::where('refer_code','LIKE','%'.$subPart.'%')->exists()){
            $subPart =  _user_get_referral_code($name);
        }
    
        return \strtoupper($subPart);
    }
}