<?php

namespace App\Http\Controllers\Api\Customer;

use App\Api\ApiResponse;
use App\Http\Controllers\ApiController as Controller;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\PlanDuration;
use App\Models\Refund;
use App\Models\UserSubscription;
use App\Vars\Helper;
use App\Vars\HSP;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SubscriptionController extends Controller
{
    public function all_plans()
    {
        $user = $this->user();

        $user_currency = $user->my_currency;

        $user_platform = $user->userPlatform;
        
        $plan_id = env('FREE_PLAN_ID')??null;
             $plans = Plan::query()
            ->active()
            ->where('plans.id','!=',$plan_id)
            ->with(['durations'])
            ->orderBy('display_order', 'ASC')
            ->get()
            ->filter(function($plan) {
                return $plan->durations->isNotEmpty();
            })
            // change currency on each to user's currency
            ->map(function($plan) use($user_currency) {

                $durations = $plan->durations
                    ->filter(function($duration) use($user_currency) {
                        return $duration->currency === $user_currency;
                    });

                $plan->setRelation('durations', $durations);

                return $plan;
            })
            ->reduce(function($acc, $plan) use($user_platform) {
                
                if( $user_platform == 'ios' && $plan->type == 'custom' ) {
                    return $acc;
                }
                $planidkey = $plan->id;

                $durations = $plan->durations
                    ->map(function($duration) use($user_platform,$planidkey) {
                        if($planidkey == env('FREE_PLAN_ID') && $duration->code != 'W'){
                            return false;
                        }
                        $amount = floatval($duration->price);
                        $family_amount = (array) @json_decode($duration->family_price, true);
                        $family_amount = \array_filter($family_amount);
                        $discount = $user_platform != 'ios'
                            ? floatval($duration->discount)
                            : 0;

                        $family_amount_array = [];
                        $final_member = array_key_last($family_amount);
                        foreach( $family_amount as $member => $f_amount ) {
                            if( $member == 0 ) break;

                            // for ios there can be only one member price (max member price)
                            if( $user_platform == 'ios' && $member != $final_member ) continue;

                            if( $discount> 0 ) {
                                $f_amount -= $f_amount * ($discount/100);
                            }
                            $family_amount_array[] = [
                                // 'member' => $member,
                                'member' => $user_platform == 'ios' ? 6 : $member, // TODO: just for testing purpose
                                'amount' => Helper::to_price($f_amount)
                            ];
                        }

                        if( $discount > 0 ) {
                            $amount -= $amount * ($discount/100);
                        }

                        $d = [];
                        $d['key'] = $duration->code;
                        $d['price'] = Helper::to_price($amount);
                        $d['currency'] = $duration->currency ?? 'GHS';
                        $d['family_price'] = '0.00';
                        $d['family_price_arr'] = $family_amount_array;
                        $d['discount'] = $discount.'%';
                        $d['apple_product_id'] = $duration->apple_product_id;
                        $d['apple_family_product_id'] = $duration->apple_family_product_id;

                        return $d;
                    })
                    ->toArray();

                $package = [
                    'key' => $plan->id,
                    'value' => $plan->title,
                    'description' => $plan->desc,
                    'duration' => \array_values($durations)
                ];
                if( ! isset($acc[$plan->type]) ) {
                    $plan_type = Helper::plan_types($plan->type);

                    if( !$plan_type ) {
                        throw new \Exception('Plan type removed from Helper');
                    }

                    $desc = $plan_type['desc'];

                    if( $user_platform == 'ios' && $plan->type == 'bundle' ) {
                        $desc = '';
                    }

                    $acc[$plan->type] = [
                        'key' => $plan_type['key'],
                        'value' => \ucwords($plan->type),
                        'description' => $desc,
                        'period' => Helper::plan_durations($plan->type),
                        'packages' => []
                    ];
                }

                $acc[$plan->type]['packages'][] = $package;

                return $acc;
            }, []);

        foreach( $plans as &$plan ) {
            $plan['period'] = \array_filter($plan['period'], function($period) use($plan) {
                    
                foreach( $plan['packages'] as $package ) {
                    foreach( $package['duration'] as $duration ) {
                        if( $period['key'] === $duration['key'] ) {
                            return true;
                        }
                    }
                }

                return false;
            });
        }

        $web_url = url('all_plans?al_7624109=' .encrypt($user->id));

        return ApiResponse::ok('All Plans', [
            'web_url' => $web_url,
            'currency' => $user_currency,
            'plans' => \array_values($plans)
        ]);
    }

    // subscribe to a plan
    // initialize a payment
    // also for renew
    public function paystack_new_plan(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'package_key' => ['array', 'min:1'],
            'package_key.*' => ['exists:plans,id'],
            'duration_key' => ['required'],
            'is_family' => ['nullable'],
            'payment_method' => ['nullable', 'in:paystack,express']
        ]);

        if($validator->fails()){
            return $this->validation_error_response($validator);
        }

        $user = $this->user();

        $for_renew = $request->get('renew') == '1';

        $duration_key = $request->get('duration_key');
        $user_currency = $user->my_currency;

        $plans = \array_filter($request->get('package_key'));

        $plans = Plan::query()
            ->with(['durations'])
            ->findMany($plans)
            ->filter(function($plan) use($duration_key, $user_currency, $for_renew, $user) {

                $plan_duration = $plan->durations
                    ->where('code', $duration_key)
                    ->firstWhere('currency', $user_currency);

                $existing_sub = $user->subscriptions
                    ->where('plan_id', $plan->id)
                    ->where('plan_duration_id', $plan_duration ? $plan_duration->id : -1)
                    ->first();

                $plan->setRelation('set_duration', $plan_duration);
                $plan->setRelation('existing_sub', $existing_sub);

                return !empty($plan_duration) || (!$for_renew && empty($existing_sub));
            });

        try {

            // get amount for payment
            // if amount is invalid on any plan then unset the plan
            $final_amount = 0;

            foreach( $plans as $key => $plan ) {

                $plan_duration = $plan->set_duration;

                $discount = floatval($plan_duration->discount);

                $is_family = intval($request->get('is_family'));

                $amount = floatval($plan_duration->price);

                if( $is_family > 0 ) {
                    $fp = (array) @json_decode($plan_duration->family_price, true);
                    $fp = \array_filter($fp);

                    $family_price = floatval($fp[$is_family] ?? 0);

                    if( $family_price > 0 ) {
                        $amount = $family_price;
                    } else {
                        $amount += $amount * ($is_family - 1);
                    }
                }

                if( $discount > 0 ) {
                    $amount -= $amount * ($discount/100);
                }

                if( floatval($amount) <= 0 &&  $plan->id != '29') {
                    logger("Incorrect price for id: ".$plan->id);
                    unset($plans[$key]);
                    continue;
                }

                $final_amount += $amount;
            }

            if( !empty($coupon = $request->get('coupon')) && $final_amount > 0 ) {
                $final_amount = $this->use_coupon($coupon, $final_amount);
            }

            if( $plans->isEmpty() ) {
                return ApiResponse::forbidden('Cannot process empty plan(s)');
            }

            // this purchase is free
            if( $final_amount <= 0 ) {
                $payment = HSP::create_zero_payment_instance('subscription');

                if( $payment ) {
                    $subscriptions = HSP::create_subscription(
                        $plans,
                        $payment
                    );

                    if( $subscriptions ) {
                        $plans = $plans->implode('title', ', ');
                        
                        return ApiResponse::ok("Subscriptions \"{$plans}\" purchased/updated successfully");
                    }
                }
                
                return ApiResponse::error('Purchase was not successful');
            }

            $final_amount = $amount = \number_format($final_amount, 2, '.', '');

            // create payment instance
            $uuid = \Illuminate\Support\Str::uuid();

            $pm = $request->get('payment_method') ?? 'paystack';

            $remote_init_id = null;
            $remote_init_response = [
                'authorization_url' => '',
                'access_code' => '',
                'reference' => ''
            ];
            $remote_init_raw = [];
            
            if( $pm == 'paystack' ) {

                $paystack = new \App\Vars\Paystack([
                    'amount' => (string)($final_amount * 100),
                    'email' => $user->email,
                    // TODO: currency not supported by merchant
                    'currency' => 'GHS',
                    // 'currency' => $user_currency,
                    'reference' => $uuid,
                    'callback_url'=>route('paystack_callback_wv')
                ]);

                $result = $paystack->init();

                if( empty($result) || ! isset($result['status']) ) {
                    throw new \Exception('Empty response from paystack server');
                }

                if( ! $result['status'] ) {
                    throw new \Exception('Error from paystack server: ' . \json_encode($result));
                }

                if( empty($result = $result['data']) ) {
                    throw new \Exception('Empty response from paystack server');
                }

                $remote_init_response['authorization_url'] = $result['authorization_url'];
                $remote_init_response['access_code'] = $result['access_code'];
                $remote_init_response['reference'] = $result['reference'];

                $remote_init_raw = (array)$result;
                
                $remote_init_id = $result['reference'];
            }
            elseif( $pm == 'express' ) {
                $data = [
                    'amount' => $final_amount,
                    'order_id' => $uuid,
                    'email' => $user->email,
                    'first_name' => $user->name,
                    'redirect_url'=>route('paystack_callback_wv')
                ];

                if( $user->phone ) {
                    $data['phone_number'] = $user->phone;
                }

                $merchantApi = new \App\Vars\Expressway($data);

                $result = $merchantApi->submit_request();

                if( empty($result) ) {
                    throw new \Exception('Empty response from expresswaygh');
                }

                if( $result['status'] != 1 ) {
                    throw new \Exception('Error from express server: ' . \json_encode($result));
                }

                $remote_init_response['authorization_url'] =
                    $merchantApi->checkout($result['token']);

                $remote_init_raw = (array)$result;

                $remote_init_id = $result['token'];
            } else {
                return ApiResponse::forbidden('Invalid payment gateway');
            }

            $payment = Payment::create([
                'type' => 'subscription',
                'user_id' => $user->id,
                'currency' => $user_currency,
                'amount' => $final_amount,
                'payment_method' => $pm,
                'status' => 'PENDING',
                'local_ref_id' => $uuid,
                'remote_id' => trim($remote_init_id),
                'remote_response_raw' => \json_encode($remote_init_raw)
            ]);

            $subscriptions = [];

            // create subscriptions
            foreach( $plans as $plan ) {
                $existing_sub = $plan->existing_sub;

                // update an expired paid subscription
                if( !empty($existing_sub) ) {
                    $existing_sub->renew_payment_id = $payment->id;
                    $existing_sub->update();
                } else {

                    $now = date('Y-m-d H:i:s');

                    $referral_code = "MAG{$user->id}-{$plan->id}-".Helper::generate_random_code();

                    $subscriptions[] = new UserSubscription([
                        'plan_id' => $plan->id,
                        'plan_duration_id' => $plan_duration->id,
                        'payment_id' => $payment->id,
                        'purchased_at' => Helper::to_price($final_amount),
                        'is_family' => intval($request->get('is_family')),
                        'referral_code' => $referral_code,
                        'total_members' => 0,
                        'subscribed_at' => $now,
                        'expires_at' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }

            if( !empty($subscriptions) ) {
                $user->subscriptions()->saveMany($subscriptions);
            }

            return ApiResponse::ok('Payment Initiated', [
                'amount' => (string)($final_amount*100),
                'currency' => $user_currency,
                'paystack' => $remote_init_response,
            ]);

        } catch(\Exception $e) {
            logger($e->getMessage());
        }

        DB::rollBack();
        
        return ApiResponse::error('Something went wrong at the server.');
    }

    public function apple_in_app($reference, $apple_product_id)
    {
        $user = $this->user();

        $user_currency = $user->user_currency ?? 'GHS';

        $request = request();

        $for_renew = $request->get('renew') == '1';

        $plan_duration = PlanDuration::query()
            ->where(function($query) use($apple_product_id) {
                $query->where('apple_product_id', $apple_product_id)
                    ->orWhere('apple_family_product_id', $apple_product_id);
            })
            ->firstWhere('currency', $user_currency);

        if( empty($plan_duration) ) {
            return ApiResponse::error(__('Invalid plan duration'));
        }

        $plan = $plan_duration->plan;

        $existing_sub = $user->subscriptions()
            ->where('plan_id', $plan->id)
            ->where('plan_duration_id', $plan_duration->id)
            ->latest()
            ->first();

        if( $for_renew && empty($existing_sub) ) {
            return ApiResponse::error(__('Subscription does not exist for renewing'));
        }

        $final_amount = floatval($plan_duration->price);

        if( $final_amount > 0 ) {
            $final_amount = Helper::to_price($final_amount);
        } else {
            $final_amount = '0.00';
        }

        $receipt = [];

        // 
        // if a purchase id is present the request, just mark it successful
        if( $purchase_id = request()->get('purchase_id') ) {
            $receipt['transaction_id'] =
                \Illuminate\Support\Str::random(6).'-'.$purchase_id;
        } else {

            // verify txn at apple pay
            $response = \App\Vars\AppleInApp::verifyReceipt($reference);

            if( empty($response) || !isset($response['receipt']) ) {
                try {
                    logger("iap response: " . \json_encode($response));
                } catch(\Exception $e) {}

                return ApiResponse::error(__('Something went wrong while verifying IAP txn'));
            }

            // $receipt = $response['receipt'];
            $receipt = \App\Vars\AppleInApp::getReceipt($response, $apple_product_id);
            
            if( empty($receipt) ) {
                return ApiResponse::error(__('Invalid purchase receipt'));
            }
        }

        $is_family = intval($request->get('is_family'));

        $is_family = $is_family > 0
            // ? $is_family
            ? 6
            : (isset($receipt['product_id']) && $receipt['product_id'] == $plan_duration->apple_family_product_id ? 6 : 0);

        $uuid = \Illuminate\Support\Str::uuid();

        $now = date('Y-m-d H:i:s');

        DB::beginTransaction();

        try {

            // there could be an existing payment slip on it.
            $payment = Payment::where(['payment_method' => 'apple_in_app', 'remote_id' => $receipt['transaction_id']])->first();

            if( empty($payment) ) {

                $payment = Payment::create([
                    'user_id' => $user->id,
                    'currency' => $user_currency,
                    'amount' => $final_amount,
                    'payment_method' => 'apple_in_app',
                    'status' => 'SUCCESS',
                    'local_ref_id' => $uuid,
                    'remote_id' => $receipt['transaction_id']
                ]);
            }
    
            $referral_code = "MAG{$user->id}-{$plan->id}-".Helper::generate_random_code();

            $message = __('Unable to verify transaction');

            // $for_renew && $existing_sub
            // simply renew an existing subscription
            if( $existing_sub ) {
                $expires_at = $existing_sub->expires_at;

                if( now()->gt($expires_at) ) {
                    $expires_at = now();
                }

                $existing_sub->expires_at =
                    Helper::add_days(
                        $expires_at,
                        Helper::get_days_plan_duration($plan_duration->code)
                    )
                    ->format('Y-m-d H:i:s');

                $existing_sub->payment_id = $payment->id;
                $existing_sub->pay_status = 1;

                $existing_sub->update();

                $message = 'Subscription renewed successfully';
            } else {
                $expires_at =
                    Helper::add_days(
                        now(),
                        Helper::get_days_plan_duration($plan_duration->code)
                    )
                    ->format('Y-m-d H:i:s');
        
                UserSubscription::create([
                    'user_id' => $user->id,
                    'plan_id' => $plan->id,
                    'plan_duration_id' => $plan_duration->id,
                    'payment_id' => $payment->id,
                    'purchased_at' => Helper::to_price($final_amount),
                    'is_family' => $is_family,
                    'referral_code' => $referral_code,
                    'total_members' => 0,
                    'pay_status'  => 1,
                    'subscribed_at' => $now,
                    'expires_at' => $expires_at,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $message = 'Subscription purchased successfully';
            }

            DB::commit();

            return ApiResponse::ok($message);

        } catch(\Exception $e) {
            DB::rollBack();

            logger($e->getMessage());
        }

        return ApiResponse::error(__('Something went wrong'));
    }

    public function paystack_verify(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'reference' => ['required'],
            'apple_product_id' => ['nullable'],
            'payment_method' => ['nullable', 'in:paystack,express,apple_in_app']
        ]);

        if($validator->fails()){
            return $this->validation_error_response($validator);
        }

        $reference = $request->get('reference');

        // if it was an apple in-app purchase, we'll do init and verify on same request
        if( $request->get('apple_product_id') ) {
            return $this->apple_in_app($reference, $request->get('apple_product_id'));
        }

        $_message = __('Unable to verify transaction');

        try {

            $user = $this->user();

            $payment = Payment::query()
                ->where('remote_id', $reference)
                // ->where('status', 'PENDING')
                ->firstOrFail();

            if( $payment->isPaid() ) {
                return ApiResponse::ok('Purchased successfully');
            }

            $pm = \strtolower($payment->payment_method);

            $data = [
                'status' => null,
                'ip_address' => null
            ];

            if( $pm == 'paystack' ) {
                $pay_response = \App\Vars\Paystack::verify($payment->remote_id);

                if( empty($pay_response) ) {
                    throw new \Exception('paystack - empty verify response');
                }

                $data = $pay_response['data'];
            }
            elseif( $pm == 'express' ) {
                $merchantApi = new \App\Vars\Expressway();

                $response = $merchantApi->query($payment->remote_id);

                $data['status'] = \strtolower($response['result-text']);
            }
            else {
                return ApiResponse::forbidden('invalid payment method');
            }

            try {
                // paystack payment
                logger("ps txn data: " . \json_encode($data));
            } catch(\Exception $e) {};

            if( $data['status'] === 'success' ) {
                $payment->ip_addresses = trim($data['ip_address']??'');
                $payment->paid_at = date('Y-m-d H:i:s');
                $payment->status = 'SUCCESS';
                $payment->update();

                $now = now();

                $user_subscriptions = $payment->user_subscriptions
                    ->concat($payment->user_subscriptions_for_renew);

                foreach( $user_subscriptions as $user_subscription ) {

                    $user_subscription->subscribed_at =
                        $now->format('Y-m-d H:i:s');

                    $plan_duration = $user_subscription->plan_duration;

                    // up for renewation
                    if( !empty($user_subscription->renew_payment_id) ) {
                        $user_subscription->payment_id
                            = $user_subscription->renew_payment_id;

                        $user_subscription->renew_payment_id = null;

                        $expires_at = $user_subscription->expires_at;

                        if( now()->gt($expires_at) ) {
                            $expires_at = now();
                        }

                        $user_subscription->expires_at =
                            Helper::add_days(
                                $expires_at,
                                Helper::get_days_plan_duration($plan_duration->code)
                            )
                            ->format('Y-m-d H:i:s');

                        $_message = 'Subscription renewed successfully';
                    } else {
                        $user_subscription->expires_at =
                            Helper::add_days(
                                $now,
                                Helper::get_days_plan_duration($plan_duration->code)
                            )
                            ->format('Y-m-d H:i:s');

                        $_message = 'Subscription purchased successfully';
                    }

                    $user_subscription->pay_status = 1;
                    $user_subscription->update();

                    if( empty($user) ) {
                       $user = $user_subscription->user;
                    }

                }

                \App\Vars\SystemMails::payment_success($user, $payment);

                return ApiResponse::ok(
                    $_message,
                    new \App\Http\Resources\UserSubscription($user_subscription)
                );
            }
            elseif( $data['status'] === 'failed' ) {
                $_message = 'Transaction failed';

                \App\Vars\SystemMails::payment_failed($user, $payment);

                $payment->status = 'FAILED';
                $payment->update();
            }
            else {
                $_message = 'Transaction cancelled';

                \App\Vars\SystemMails::payment_failed($user, $payment);

                $payment->status = 'CANCELLED';
                $payment->update();
            }

        } catch(\Exception $e) {
            logger($e->getMessage());
        }

        return ApiResponse::error($_message);
    }

    public function paystack_webhook(Request $request)
    {
        if ((strtoupper($_SERVER['REQUEST_METHOD']) != 'POST' ) ||
            !array_key_exists('x-paystack-signature', $_SERVER) ) {
            exit;
        }

        $input = @file_get_contents("php://input");

        if($_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] !== hash_hmac('sha512', $input, config('app.ps_secret_key'))) {
            exit;
        }

        logger('webhook response:' . (string)$input);

        $event = \json_decode($input, true);

        if( ! empty($event) ) {
            try {
                if( $event['event'] === 'charge.success' ) {
    
                    $data = $event['data'];
    
                    if( empty($data) || ! isset($data['reference']) ) {
                        throw new \Exception('paystack - empty webhook response');
                    }

                    $ref = $data['reference'];
    
                    $payment = Payment::where('remote_id', $ref)
                        ->with(['user_subscription'])
                        ->first();

                    if( ! $payment ) {
                        throw new \Exception('webhook - no payment found in the db');
                    }

                    if( ! $payment->isPaid() ) {
                        $user_subscription = $payment->user_subscription;

                        $payment->ip_addresses = trim($data['ip_address'] ?? '');
                        $payment->paid_at = date('Y-m-d H:i:s');
                        $payment->status = 'SUCCESS';
                        $payment->update();

                        $user_subscription->pay_status = 1;
                        $user_subscription->update();
                    }
    
                }
            } catch(\Exception $e) {
                logger($e->getMessage());
            }
        }

        return 'OK';
    }

    public function my_subscriptions()
    {
        $user = $this->user();

        $subscriptions = $user->subscriptions()
            ->latest()
            ->with(['plan', 'plan_duration.siblings', 'payment', 'member_subscriptions.user'])
            ->get()
            ->filter(function($subscription) {
                return !empty($subscription->plan_duration);
            })
            ->map(function($subscription) use($user) {
                if( $user->my_currency != $subscription->currency ) {
                    if( $user->my_currency == 'GHS' ) {
                        if( $pd = $subscription->plan_duration->siblings->firstWhere('currency', 'GHS') ) {
                            $subscription->setRelation('plan_duration', $pd);
                        }
                    }
                    else if( $user->my_currency == 'USD' ) {
                        if( $pd = $subscription->plan_duration->siblings->firstWhere('currency', 'USD') ) {
                            $subscription->setRelation('plan_duration', $pd);
                        }
                    }
                }
                
                return $subscription;
            });

        return ApiResponse::ok(
            'My Subscriptions',
            \App\Http\Resources\UserSubscription::collection($subscriptions)
        );
    }

    public function referral_new_plan(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'referral_code' => ['required', 'max:100']
        ]);

        if($validator->fails()){
            return $this->validation_error_response($validator);
        }

        $user = $this->user();

        $referral_code = $request->get('referral_code');

        $subscription = UserSubscription::query()
            ->where('referral_code', $referral_code)
            ->first();

        if( ! $subscription ) {
            return ApiResponse::notFound('Code not found');
        }else{
            if($user->id == $subscription->user_id){
                return ApiResponse::forbidden('Code is not available for subscribed user.');
            }
        }

        if( ! $subscription->is_active || ! $subscription->is_family ) {
            return ApiResponse::forbidden('Subscription on this code is not active');
        }

        $used = UserSubscription::query()
            ->where('via_referral', $referral_code)
            ->count();

        // if( $used > Helper::MAX_SUBSCRIPTION_MEMBERS ) {
        //     return ApiResponse::forbidden('Referral code expired');
        // }

        if( $used >= intval($subscription->is_family) ) {
            return ApiResponse::forbidden('Referral code expired');
        }

        if( $user->subscriptions()->where('via_referral', $referral_code)->exists() ) {
            return ApiResponse::forbidden('Referral code already utilized');
        }

        $new_subscription = $subscription->replicate();

        $new_subscription->referral_code = null;
        // $new_subscription->is_family = 0;
        $new_subscription->via_referral = $subscription->referral_code;
        $new_subscription->user_id = $user->id;
        $new_subscription->created_at =
            $new_subscription->updated_at = date('Y-m-d H:i:s');

        $new_subscription->save();

        $subscription->increment('total_members');

        return ApiResponse::ok(
            'Subscribed via referral code successfully'
            // new \App\Http\Resources\UserSubscription($new_subscription)
        );
    }

    public function subscription_refund(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'reference_id' => ['required'],
            'reason' => ['nullable', 'max:5000']
        ]);

        if($validator->fails()){
            return $this->validation_error_response($validator);
        }

        DB::beginTransaction();
        try {

            $user = $this->user();

            $subscription = $user->active_subscriptions()
                ->with(['payment'])
                ->find($request->get('reference_id'));

            if( empty($subscription) ) {
                return ApiResponse::notFound('Subscription not found');
            }

            $payment = $subscription->payment;

            if( !empty($payment->refund_id) ) {
                return ApiResponse::error('Cancellation already requested');
            }

            $refund = Refund::create([
                'user_id' => $user->id,
                'for' => 'plan_subscription',
                'entity_id' => $subscription->id,
                'paid_amount' => $payment->amount,
                'customer_reason' => $request->get('reason') ??'',
                'status' => 'requested'
            ]);

            // attach a refund instance to this plan's payment
            $payment->refund_id = $refund->id;
            $payment->update();

            // mark this subscription as cancelled
            $subscription->pay_status = -1;
            $subscription->update();

            \App\Vars\SystemMails::payment_refund_initiated($user, $payment->amount);

            DB::commit();

            return ApiResponse::ok('Cancellation requested. You will get a response in two to three working days.');

        } catch(\Exception $e) {
            logger($e->getMessage());
        }

        DB::rollBack();

        return ApiResponse::error('Unable to process refund');
    }
}
