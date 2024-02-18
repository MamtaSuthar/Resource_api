<?php

namespace App\Vars;

class OneSignalNotification
{
    private $errors = [];

    private $_notif_app_id;

    private $_notif_api_key;

    protected $notif_data = array(
        "n_id" => null,
        "n_type" => null,
        "n_data" => null
    );

    public function __construct($device = 'phone')
    {
        if( $device == 'phone' ) {
            $this->_notif_app_id = config('app.os_mobile_id');
            $this->_notif_api_key = config('app.os_api_key');
        } else {
            $this->_notif_app_id = env('ONE_SIGNAL_WEB_APP_ID');
            $this->_notif_api_key = env('ONE_SIGNAL_WEB_API_KEY');
        }
    }

    public function setData(array $data)
    {
        $this->notif_data = \array_merge(
            $this->notif_data,
            $data
        );

        return $this;
    }

    public function send($to, $title, $message = '', $img = '',$mag_id='')
    {
        return $this->sendNotification($to, $title, $message, $img,$mag_id);
    }

    public function setExternalUserId($externalID, $playerID)
    {
        $fields = array( 
            'app_id' => $this->_notif_app_id,
            'external_user_id' => $externalID
        );

        $fields = json_encode($fields); 

        $ch = curl_init(); 
        curl_setopt($ch, CURLOPT_URL, 'https://onesignal.com/api/v1/players/'.$playerID); 
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json')); 
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
        curl_setopt($ch, CURLOPT_HEADER, false); 
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields); 
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
        $response = curl_exec($ch); 
        curl_close($ch); 

        $resultData = json_decode($response, true);

        return $resultData;
    }

    public function updateGlobalTag($user_id, $key, $value = '')
    {
        $url = "https://onesignal.com/api/v1/apps/{$this->_notif_app_id}/users/{$user_id}";

        $fields = array(
            'tags' => [$key => $value]
        );

        $fields = \json_encode($fields);

        $ch = curl_init(); 
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json')); 
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
        curl_setopt($ch, CURLOPT_HEADER, false); 
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
        $response = curl_exec($ch); 
        curl_close($ch); 

        if( !empty($response) ) {
            return \json_decode($response, true);
        }

        return $response;
    }

    public function sendNotification($to, $title, $message = '', $left_small_icon = null,$mag_id='')
    {
        if( empty($to) ) {
            return false;
        }

        $to = \array_map(function($val) { return strval($val); }, $to);

        $content = ['en' => $message];

        $headings = ['en' => $title];

        $large_icon = url("assets/frontend/img/logo_big.png");

        $fields = array(
            'app_id' => $this->_notif_app_id,
            // 'included_segments' => array('All'),
            // 'data' => array("foo" => "bar"),
            // 'large_icon' => $large_icon,
            'small_icon' => $large_icon,
            'headings' => $headings,
            'contents' => $content,
            // 'data'=> array("n_id" => $mag_id,"n_type"=>'magazine'),
            'data' => $this->notif_data,
            // 'include_player_ids' => (array)$to,
            'include_external_user_ids' => $to,
            'channel_for_external_user_ids' => 'push'
        );

        $fields = json_encode($fields);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://onesignal.com/api/v1/notifications");
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json; charset=utf-8',
            'Authorization: Basic ' . $this->_notif_api_key
        ));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

        $response = curl_exec($ch);
        curl_close($ch);

        if( !empty($response) ) {
            return \json_decode($response, true);
        }

        return $response;
    }

    public function getErrors()
    {
        return $this->errors;
    }
}
