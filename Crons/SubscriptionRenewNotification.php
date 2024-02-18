<?php

namespace App\Crons;

use App\Models\UserSubscription;
use App\Vars\PushNotification;

class SubscriptionRenewNotification {

    public function __invoke()
    {
        // subscriptions about to be expired in 2 days
        foreach($this->get_subscriptions(2) as $user_id => $subscription) {
            $this->send_notification($user_id, $subscription, 2);
        }

        // subscriptions about to be expired in 1 day
        foreach($this->get_subscriptions(1) as $user_id => $subscription) {
            $this->send_notification($user_id, $subscription, 1);
        }

        $this->send_expired_subscription_mails();
    }

    private function send_expired_subscription_mails()
    {
        // customer.renew_plan
        $ids = [];

        $subscriptions = UserSubscription::query()
            ->with(['user'])
            ->where('pay_status', 1)
            ->where('renew_mail_sent', 0)
            ->whereDate('expires_at', '<', date('Y-m-d H:i:s'))
            ->get();

        foreach( $subscriptions as $subscription ) {

            $link = route('renew_plan', ['userSubscription' => $subscription->id]);

            \App\Vars\SystemMails::customer_subscription_expired($subscription->user, $link);

            $ids[] = $subscription->id;
        }

        if( !empty($ids) ) {
            UserSubscription::whereIn('id', $ids)
                ->update(['renew_mail_sent' => 1]);
        }
    }

    private function get_subscriptions(int $days = 2)
    {
        try {
            return UserSubscription::query()
                ->with(['user', 'plan', 'plan_duration'])
                ->whereRaw(
                    "TIMESTAMPDIFF(DAY, CURDATE(), DATE(expires_at)) = ?",
                    [$days]
                )
                ->get()
                ->reduce(function($acc, UserSubscription $subscription) {

                    $plan = $subscription->plan;
                    $plan_duration = $subscription->plan_duration;
                    $payment = $subscription->payment;

                    $acc[$subscription->user->id] = [
                        "key" => $plan->id,
                        "value" => $plan->title,
                        "type" => $plan->type,
                        "description" => $plan->desc,
                        "duration" => [
                            "key" => $plan_duration->code,
                            "value" => $plan_duration->value
                        ],
                        "amount" => \App\Vars\Helper::to_price($plan_duration->price),
                        "currency" => $plan_duration->currency,
                        "subscribed" => $subscription->subscribed_at->format('d/m/Y'),
                        "expired" => $subscription->expires_at->format('d/m/Y'),
                        "payment_method" => $payment->payment_method
                    ];

                    return $acc;
                }, []);
        } catch(\Exception $e) {
            logger($e->getMessage());
        }
        
        return [];
    }

    private function send_notification($user_id, $data, $days = 2)
    {
        PushNotification::push_subscription($user_id, $data, $days);
    }
}