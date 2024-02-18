<?php

namespace App\Vars;

class Currency {

    public function rate($from = 'USD', $to = 'GHS')
    {
        $api_key = config('app.curr_conversion_api');

        try {

            $response = file_get_contents(
                "https://v6.exchangerate-api.com/v6/{$api_key}/pair/{$from}/{$to}"
            );

            return \json_decode($response);

        } catch(\Exception $e) {
            logger($e->getMessage());
        }
    }
}