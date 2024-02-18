<?php

namespace App\Vars;

class AppleInApp {

    public static function verifyReceipt(string $receiptData)
    {
        $url = config('app.apple_env') == 'production'
            ? "https://buy.itunes.apple.com/verifyReceipt"
            : "https://sandbox.itunes.apple.com/verifyReceipt";

        $fields_string = json_encode([
            'receipt-data' => $receiptData,
            'password' => config('app.apple_shared_secret')
        ]);

        //open connection
        $ch = curl_init();
        
        //set the url, number of POST vars, POST data
        curl_setopt($ch,CURLOPT_URL, $url);
        curl_setopt($ch,CURLOPT_POST, true);
        curl_setopt($ch,CURLOPT_POSTFIELDS, $fields_string);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "Cache-Control:no-cache",
            "Content-Type:application/json"
        ));
        
        //So that curl_exec returns the contents of the cURL; rather than echoing it
        curl_setopt($ch,CURLOPT_RETURNTRANSFER, true);

        try {
            //execute post
            $result = curl_exec($ch);

            curl_close($ch);

            if( ! empty($result) ) {
                return \json_decode($result, true);
            }
        } catch(\Exception $e) {
            logger($e->getMessage());
        }

        return false;
    }

    public static function getReceipt($receiptData, string $product_id = '')
    {
        if( is_array($receiptData) && isset($receiptData['receipt']) ) {
            
            $receipt = $receiptData['receipt'];

            if( isset($receipt['product_id']) ) {
                return $receipt;
            }

            if( isset($receipt['in_app']) && $product_id !== '' ) {

                foreach( $receipt['in_app'] as $in_app ) {
                    if( $in_app['product_id'] == $product_id ) {
                        return $in_app;
                    }
                }
            }
        }

        return null;
    }

    // https://appstoreconnect.apple.com/WebObjects/iTunesConnect.woa/ra/ng/app/1602213036/pricingMatrix/subscription/1625877214
    // no-renewing price list

    // https://appstoreconnect.apple.com/WebObjects/iTunesConnect.woa/ra/ng/app/1602213036/pricingMatrix/consumable/1625633232
    // consumable price list
    public static function getPricesList($type = 'non-renewing')
    {
        if( $type == 'non-renewing' ) {
            return array(0.99, 1.99, 2.99, 3.99, 4.99, 5.99, 6.99, 7.99, 8.99, 9.99, 10.99, 11.99, 12.99, 13.99, 14.99, 15.99, 16.99, 17.99, 18.99, 19.99, 20.99, 21.99, 22.99, 23.99, 24.99, 25.99, 26.99, 27.99, 28.99, 29.99, 30.99, 31.99, 32.99, 33.99, 34.99, 35.99, 36.99, 37.99, 38.99, 39.99, 40.99, 41.99, 42.99, 43.99, 44.99, 45.99, 46.99, 47.99, 48.99, 49.99, 54.99, 59.99, 64.99, 69.99, 74.99, 79.99, 84.99, 89.99, 94.99, 99.99, 109.99, 119.99, 124.99, 129.99, 139.99, 149.99, 159.99, 169.99, 174.99, 179.99, 189.99, 199.99, 209.99, 219.99, 229.99, 239.99, 249.99, 299.99, 349.99, 399.99, 449.99, 499.99, 599.99, 699.99, 799.99, 899.99, 999.99);
        }

        if( $type == 'consumable' ) {
            return array(0.99, 1.99, 2.99, 3.99, 4.99, 5.99, 6.99, 7.99, 8.99, 9.99, 10.99, 11.99, 12.99, 13.99, 14.99, 15.99, 16.99, 17.99, 18.99, 19.99, 20.99, 21.99, 22.99, 23.99, 24.99, 25.99, 26.99, 27.99, 28.99, 29.99, 30.99, 31.99, 32.99, 33.99, 34.99, 35.99, 36.99, 37.99, 38.99, 39.99, 40.99, 41.99, 42.99, 43.99, 44.99, 45.99, 46.99, 47.99, 48.99, 49.99, 54.99, 59.99, 64.99, 69.99, 74.99, 79.99, 84.99, 89.99, 94.99, 99.99, 109.99, 119.99, 124.99, 129.99, 139.99, 149.99, 159.99, 169.99, 174.99, 179.99, 189.99, 199.99, 209.99, 219.99, 229.99, 239.99, 249.99, 299.99, 349.99, 399.99, 449.99, 499.99, 599.99, 699.99, 799.99, 899.99, 999.99);
        }

        return [];
    }
}