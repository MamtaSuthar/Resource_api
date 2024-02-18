<?php

namespace App\Crons;

use App\Models\UserSubscription;

class RemoveExpSubFromOneSignal
{
    public function __invoke()
    {
        // dd($this->edit_tags_at_one_signal(21, 7, ''));
        
        foreach( $this->get_user_subs() as $subscription ) {

            $this->edit_tags_at_one_signal(
                $subscription->user->id,
                $subscription->plan_id
            );
        }
    }

    private function get_user_subs()
    {
        return UserSubscription::query()
            ->with(['user'])
            ->where('pay_status', 1)
            ->whereDate('expires_at', '<', date('Y-m-d'))
            ->get();
    }

    private function edit_tags_at_one_signal($user_id, $plan_id, $val = '')
    {
        $app_id = config('app.os_mobile_id');

        $url = "https://onesignal.com/api/v1/apps/{$app_id}/users/{$user_id}";

        $fields = array(
            'tags' => ['plan_'.$plan_id => $val]
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

        $resultData = json_decode($response, true);

        return $resultData;
    }
}