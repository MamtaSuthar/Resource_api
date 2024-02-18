<?php

namespace App\Vars;

class PaystackRefund {

    public static $errors = [];

    private $data = [
        'transaction' => '',
        'amount' => 0.00,
        'currency' => 'GHS',
        'reason' => ''
    ];

    public function __construct($data = [])
    {
        $this->data = \array_merge($this->data, $data);
    }

    // $amount should be in basic monetary units like cents
    public function init()
    {
        $url = "https://api.paystack.co/refund";

        // $fields_string = http_build_query([
        //     'transaction' => $reference,
        //     'amount' => $amount
        // ]);

        $fields_string = http_build_query($this->data);

        $ch = curl_init();

        curl_setopt($ch,CURLOPT_URL, $url);
        curl_setopt($ch,CURLOPT_POST, true);
        curl_setopt($ch,CURLOPT_POSTFIELDS, $fields_string);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "Authorization: Bearer " . config('app.ps_secret_key'),
            "Cache-Control: no-cache",
        ));

        curl_setopt($ch,CURLOPT_RETURNTRANSFER, true);
        
        $result = curl_exec($ch);

        curl_close($ch);

        if( ! empty($result) ) {
            return \json_decode(trim($result), true);
        }

        return trim($result);
    }

    public static function fetch_refund($reference)
    {
        $curl = curl_init();
  
        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://api.paystack.co/refund/{$reference}",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_HTTPHEADER => array(
                "Authorization: Bearer ".config('app.ps_secret_key'),
                "Cache-Control: no-cache",
            ),
        ));
        
        $result = curl_exec($curl);

        curl_close($curl);

        if( ! empty($result) ) {
            return \json_decode(trim($result), true);
        }

        return trim($result);
    }

}