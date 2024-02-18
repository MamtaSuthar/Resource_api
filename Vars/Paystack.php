<?php

namespace App\Vars;

class Paystack {

    public static $errors = [];

    private $data = [
        'currency' => 'GHS'
    ];

    public function __construct($data = [])
    {
        $this->data = \array_merge($this->data, $data);
    }

    // set amount in basic monetary unit
    public function setAmount($amount)
    {
        $this->data['amount'] = (string)$amount;
    }

    public function setCustomerEmail($email)
    {
        $this->data['email'] = $email;
    }

    public function setCurrency($currency)
    {
        $this->data['currency'] = $currency;
    }

    public function setUUID($uuid)
    {
        $this->data['reference'] = $uuid;
    }

    public function setCallback($url)
    {
        $this->data['callback_url'] = $url;
    }

    public function init()
    {
        $url = "https://api.paystack.co/transaction/initialize";

        $data = $this->data;

        // filter final price based on many params
        $data['amount'] = Helper::price_filter( floatval($data['amount']) / 100, false ) * 100;

        $fields_string = http_build_query($data);

        //open connection
        $ch = curl_init();

        $secret = (config('app.env') == 'production')
            ? config('app.ps_secret_live_key')
            : config('app.ps_secret_key');
        
        //set the url, number of POST vars, POST data
        curl_setopt($ch,CURLOPT_URL, $url);
        curl_setopt($ch,CURLOPT_POST, true);
        curl_setopt($ch,CURLOPT_POSTFIELDS, $fields_string);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "Authorization: Bearer " . $secret,
            "Cache-Control: no-cache",
        ));
        
        //So that curl_exec returns the contents of the cURL; rather than echoing it
        curl_setopt($ch,CURLOPT_RETURNTRANSFER, true); 
        
        //execute post
        $result = curl_exec($ch);

        curl_close($ch);

        if( ! empty($result) ) {
            return \json_decode(trim($result), true);
        }

        return trim($result);
    }

    public static function verify($reference)
    {
        $curl = curl_init();

        $secret = (config('app.env') == 'production')
            ? config('app.ps_secret_live_key')
            : config('app.ps_secret_key');
  
        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://api.paystack.co/transaction/verify/".$reference,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_HTTPHEADER => array(
            "Authorization: Bearer ".$secret,
            "Cache-Control: no-cache",
            ),
        ));
        
        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);
        
        if ($err) {
            self::$errors = $err;

            return false;
        }

        return \json_decode($response, true);
    }
}