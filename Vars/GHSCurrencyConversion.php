<?php

namespace App\Vars;

class GHSCurrencyConversion {

    protected static $conversion = null;

    public static function convert($from = 'USD', $unit = 1, $format = true)
    {
        $oneUnit = static::getSingleUnitConversion($from);

        $oneUnit *= $unit;

        return $format ? Helper::to_price($oneUnit) : $oneUnit;
    }

    public static function sync($symbol = 'GHS')
    {
        $api = config('app.curr_conversion_api');
        $url = "https://openexchangerates.org/api/latest.json?app_id={$api}&symbols={$symbol}";

        if( $response = file_get_contents($url) ) {
            $response = \json_decode($response, true);

            if( $response && isset($response['rates']) ) {
                $rate = round(floatval($response['rates']['GHS']), 2);

                file_put_contents(
                    dirname(__FILE__) . '/to-ghana-exchage-rate.json',
                    \json_encode(['USD' => "{$rate}"])
                );

                return 1;
            }
        }

        return 0;
    }

    protected static function getSingleUnitConversion($from = 'USD')
    {
        if( is_null(static::$conversion) ) {
            $file_path = dirname(__FILE__) . '/to-ghana-exchage-rate.json';

            if( !file_exists($file_path) ) {
                file_put_contents($file_path, '{"USD":"8.76"}');
            }

            $file = file_get_contents($file_path);
            
            if( $file = \json_decode($file, true) ) {
                static::$conversion = floatval($file[$from] ?? $file['USD']);
            }
        }

        return static::$conversion ? static::$conversion : 7.78;
    }
}