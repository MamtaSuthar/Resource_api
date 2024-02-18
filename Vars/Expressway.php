<?php
namespace App\Vars;

use Expay\SDK\MerchantApi;

class Expressway
{
    private $instance;

    private $data = [
        'currency' => 'GHS',
        'amount' => 0.00,
        'order_id' => '',
        'order_desc' => 'No Description',
        'redirect_url' => '',
        'account_number' => '1234567890',
        'order_img_url' => null,
        'first_name' => 'john',
        'last_name' => null,
        'phone_number' => '',
        'email' => ''
    ];

    public function __construct($data = [])
    {
        $env = (config('app.env') == 'production')
            ? 'production':'sandbox';

        $m_id = $env === 'production'
            ? config('app.ew_merchant_live_id')
            : config('app.ew_merchant_id');

        $api_key = $env === 'production'
            ? config('app.ew_api_live_key')
            : config('app.ew_api_key');

        // $this->data['redirect_url'] = route('paystack_callback_wv');

        $data['postUrl'] = route('expressgh_hook');

        $this->data = \array_merge($this->data, $data);

        $this->instance = new MerchantApi($m_id, $api_key, $env);
    }

    public function setData($data)
    {
        $this->data = \array_merge($this->data, $data);
        return $this;
    }

    // initiate transaction
    public function submit_request()
    {
        extract($this->data);

        $amount = floatval($this->data['amount']);

        $amount = floatval(Helper::price_filter( $amount, false ));

        return $this->instance->submit(
            $currency, $amount, $order_id, $order_desc,
            $redirect_url, $account_number, $order_img_url,
            $first_name, $last_name, $phone_number, $email, $postUrl
        );
    }

    // get expressway url for final checkout
    public function checkout($token)
    {
        return $this->instance->checkout($token);
    }

    // verify transaction
    public function query($token)
    {
        return $this->instance->query($token);
    }
}