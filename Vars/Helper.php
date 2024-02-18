<?php

namespace App\Vars;

use Illuminate\Support\Carbon;

class Helper {

    const MAX_SUBSCRIPTION_MEMBERS = 6;

    public static function to_base64_str($file)
    {
        if( file_exists($file) && ($mime = mime_content_type($file)) ) {
            return 'data:'
                . $mime . ';base64,' . base64_encode(file_get_contents($file));
        }
        
        return '';
    }

    public static function generate_random_code($len = 10)
    {
        $chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
        $res = "";
        for ($i = 0; $i < $len; $i++) {
            $res .= $chars[mt_rand(0, strlen($chars)-1)];
        }
        return $res;
    }

    public static function to_price($amount = 0)
    {
        return \number_format($amount, 2, '.', '');
    }

    public static function add_days(Carbon $carbon_date, int $days)
    {
        return $carbon_date->clone()
            ->setTime(0,0,0)
            ->addDays($days);
    }

    public static function get_days_plan_duration($key)
    {
        if( $pd = static::get_plan_duration($key) ) {
            return $pd['days'];
        }

        return 0;
    }

    public static function plan_durations($type = 'bundle')
    {
        $days_in_month = 28;
        return [
            [
                'key' => 'W',
                'name' => 'Weekly',
                'days' => 7
            ],
            [
                'key' => 'M',
                'name' => 'Monthly',
                'days' => $days_in_month
            ],
            [
                'key' => 'Q',
                'name' => '3 Months',
                'days' => $days_in_month*3
            ],
            [
                'key' => 'H',
                'name' => 'Half-Yearly',
                'days' => $days_in_month*6
            ],
            [
                'key' => 'Y',
                'name' => 'Yearly',
                'days' => $days_in_month*12
            ]
        ];
    }

    public static function plan_types($name = '')
    {
        $types = [
            [
                'key' => 'BU',
                'name' => 'bundle',
                'desc' => 'To buy just one publication, use the CUSTOM option'
            ],
            [
                'key' => 'CU',
                'name' => 'custom',
                'desc' => 'To buy mutliple publications, use the BUNDLE option'
            ],
            [
                'key' => 'PR',
                'name' => 'premium',
                'desc' => 'This gives you access to premium content outside your subscribed epaper publications for the duration selected.'
            ]
        ];

        if( $name !== '' ) {
            $key = \array_search($name, \array_column($types, 'name'));
            if( $key > -1 ) {
                return $types[$key];
            }

            return false;
        }

        return $types;
    }

    public static function get_plan_duration($key)
    {
        foreach( self::plan_durations() as $duration ) {
            if( $duration['key'] === $key ) {
                return $duration;
            }
        }

        return null;
    }

    // convert usd to ghs
    // $amount in USD
    public static function price_filter($amount = 0.00, $format = true)
    {
        $user = auth()->user() ?? auth('api')->user();

        if( $user && !$user->isIos && !$user->isCurrencyLocal ) {
            $amount = GHSCurrencyConversion::convert('USD', $amount);
        }

        return $format ? Helper::to_price($amount) : $amount;
    }
}