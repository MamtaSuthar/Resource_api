<?php

namespace App\Vars;

use App\Models\Payment;
use App\Models\Plan;
use App\Models\PlanDuration;
use App\Models\User;
use App\Models\UserOneTimePurchase;
use App\Models\UserSubscription;
use Illuminate\Support\Collection;

// Handle Subscription and Purchases
//
class HSP
{
    // for discounted money
    public static function create_zero_payment_instance($type = 'subscription'): Payment
    {
        $user = get_auth_user();

        if (!$user) {
            return null;
        }

        $user_currency = $user->my_currency;

        return Payment::create([
            'type' => $type,
            'user_id' => $user->id,
            'currency' => $user_currency,
            'amount' => 0,
            'payment_method' => 'WAIVED - Discounted',
            'status' => 'SUCCESS',
            'local_ref_id' => \Illuminate\Support\Str::uuid()
        ]);
    }

    public static function create_subscription($plans, Payment $payment)
    {
        $user = get_auth_user();

        if (!$user || !$payment->isPaid()) {
            return false;
        }

        $request = request();

        $subscriptions = [];

        $udpated = false;

        try {

            foreach ($plans as $plan) {
                if (!isset($plan->set_duration)) continue;

                $plan_duration = $plan->set_duration;

                $existing_sub = $plan->existing_sub;

                // update an likely expired paid subscription
                if (!empty($existing_sub)) {
                    $expires_at = $existing_sub->expires_at;

                    if (now()->gt($expires_at)) {
                        $expires_at = now();
                    }

                    $expires_at =
                        carbon_add_days(
                            $expires_at,
                            Helper::get_days_plan_duration($plan_duration->code)
                        )
                        ->format('Y-m-d H:i:s');


                    $existing_sub->expires_at = $expires_at;
                    $existing_sub->payment_id = $payment->id;
                    $existing_sub->pay_status = 1;
                    $existing_sub->update();

                    $udpated= true;
                } else {

                    $now = date('Y-m-d H:i:s');

                    $referral_code = "MAG{$user->id}-{$plan->id}-" . Helper::generate_random_code();

                    $expires_at =
                        carbon_add_days(
                            now(),
                            Helper::get_days_plan_duration($plan_duration->code)
                        )
                        ->format('Y-m-d H:i:s');

                    $subscriptions[] = new UserSubscription([
                        'plan_id' => $plan->id,
                        'plan_duration_id' => $plan_duration->id,
                        'payment_id' => $payment->id,
                        'purchased_at' => 0,
                        'is_family' => intval($request->get('is_family')),
                        'referral_code' => $referral_code,
                        'total_members' => 0,
                        'pay_status' => 1,
                        'subscribed_at' => $now,
                        'expires_at' => $expires_at,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }

            if (!empty($subscriptions)) {
                $user->subscriptions()->saveMany($subscriptions);

                return true;
            }

            if( $udpated ) {
                return true;
            }

        } catch (\Exception $e) {
            logger($e->getMessage());
        }

        return false;
    }

    public static function create_one_time_purchase($content, Payment $payment)
    {
        $user = get_auth_user();

        if (!$user || !$payment->isPaid()) {
            return false;
        }

        $now = date('Y-m-d H:i:s');

        $oneTimeP = UserOneTimePurchase::create([
            'user_id' => $user->id,
            'payment_id' => $payment->id,
            'package_id' => $content->id,
            'package_type' => \strtolower($content->type),
            'pay_status' => 1,
            'price' => 0,
            'bought_at' => $now,
            'created_at' => $now,
            'updated_at' => $now
        ]);

        return !empty($oneTimeP);
    }

    public static function subscribe_user_to_a_plan(User $user, Plan $plan, $plan_duration)
    {
        try {

            if( $plan_duration instanceof Collection ) {
                $plan_duration = $plan_duration
                    ->firstWhere('currency', $user->my_currency);
            }

            if( empty($plan) || empty($plan_duration) ) {
                return 0;
            }

            $uuid = \Illuminate\Support\Str::uuid();

            $amount = \number_format($plan_duration->price, 2, '.', '');

            // create new payment
            $payment = Payment::create([
                'user_id' => $user->id,
                'currency' => $user->my_currency,
                'amount' => $amount,
                'payment_method' => 'OFFLINE',
                'status' => 'SUCCESS',
                'local_ref_id' => $uuid
            ]);


            // crate subscription
            $now = date('Y-m-d H:i:s');

            $referral_code = "MAG{$user->id}-{$plan->id}-".Helper::generate_random_code();

            $expires_at = Helper::add_days(
                now(),
                Helper::get_days_plan_duration($plan_duration->code)
            )
            ->format('Y-m-d H:i:s');

            $sub = $user->subscriptions()->create([
                'plan_id' => $plan->id,
                'plan_duration_id' => $plan_duration->id,
                'payment_id' => $payment->id,
                'purchased_at' => Helper::to_price($amount),
                'is_family' => 0,
                'pay_status' => 1,
                'referral_code' => $referral_code,
                'total_members' => 0,
                'subscribed_at' => $now,
                'expires_at' => $expires_at,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return $sub;

        } catch(\Exception $e) {
            logger($e->getMessage());
        }

        return 0;
    }
}
