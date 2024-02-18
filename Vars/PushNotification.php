<?php

namespace App\Vars;

use App\Models\Publication;
use App\Models\User;

class PushNotification
{
    // $content - Magazine, Newspaper
    public static function push_pc(Publication $publication, $content, $content_type = 'magazine')
    {
        $subscribed_users = null;

        $request = request();

        $notifManager = new OneSignalNotification();

        if ($request->get('to_notification') == 'all') {
            $resultArr = User::getUsersForNotification('new_newspaper');
            if ($resultArr) {
                [$subscribed_users, $notif_template] = $resultArr;
            }
        } else {
            $subscribed_users = $publication->subscribed_users();
        }

        // send notification to all the active subscribers
        if (!empty($subscribed_users)) {

            // prevent it from blowing up
            try {
                if (is_object($subscribed_users)) {
                    $subscribed_users = $subscribed_users->pluck('id')->toArray();
                }

                $notifManager->setData([
                    'n_id' => $content->id, 'n_type' => $content_type
                ]);

                $response = $notifManager->send(
                    $subscribed_users,
                    \ucwords($content_type)." added for {$publication->name}",
                    $content->title
                );

                logger('magazine,news push notif response: ' . json_encode($response ?? []));

                if (is_array($response) && isset($response['errors'])) {
                    logger('One signal Error: ' . \json_encode($response['errors']));
                }
            } catch (\Exception $e) {
                logger('Push Notification: ' . $e->getMessage());
            }
        }
    }

    public static function push_subscription($user_id, $subscription, $days = 2)
    {
        $notifManager = new OneSignalNotification();

        try {
            $notifManager->setData([
                'n_id' => null,
                'n_type' => 'renew',
                'n_data' => $subscription
            ]);

            $response = $notifManager->send(
                [(string)$user_id],
                'Subscription Expiration',
                "Your plan '{$subscription['value']}' about to be expired in {$days} days"
            );

            if( is_array($response) && isset($response['errors']) ) {
                foreach((array)$response['errors'] as $error) {
                    logger('One signal: '.$error . ': all users [cron: SubscriptionRenewNotification]');
                }
            }
        } catch(\Exception $e) {
            logger('SubscriptionRenewNotification: '.$e->getMessage());
        }
    }
}
