<?php

namespace App\Http\Controllers\Web;

use Illuminate\Http\Request;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\PlanDuration;
use App\Models\UserSubscription;
use App\Vars\Helper;
use App\Vars\HSP;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    //

    // subscribe to a plan
    // initialize a payment
    // public function paystack_new_plan(Request $request)
    // {
    //     $this->validate($request,[
    //         'package_key' => ['array', 'min:1'],
    //         'package_key.*' => ['exists:plans,id'],
    //         'duration_key' => ['required'],
    //         'is_family' => ['nullable', 'numeric'],
    //         'payment_method' => ['nullable', 'in:paystack,express']
    //     ],[
    //         // 'duration_key.required' => 'Please select at least one duration',
    //         'package_key.required' => 'Please select at least one plan.',
    //     ]);

    //     if( empty( $request->get('package_key') ) ) {
    //         return back()->withError('Please select a package');
    //     }

    //     $pm = $request->get('payment_method')
    //         ?? $request->get('pm') ?? 'paystack';

    //     $pm = $pm == 'expresspay' ? 'express' : $pm;

    //     $user = $this->user();

    //     $for_renew = $request->get('renew') == '1';

    //     $duration_key = $request->get('duration_key');
    //     $user_currency = $user->my_currency;

    //     $plans = \array_filter($request->get('package_key'));

    //     $plans = Plan::query()
    //         ->with(['durations'])
    //         ->findMany($plans)
    //         ->filter(function($plan) use($duration_key, $user_currency, $for_renew, $user) {

    //             $plan_duration = $plan->durations
    //                 ->where('code', $duration_key)
    //                 ->firstWhere('currency', $user_currency);

    //             $existing_sub = $user->subscriptions
    //                 ->where('plan_id', $plan->id)
    //                 ->where('plan_duration_id', $plan_duration ? $plan_duration->id : -1)
    //                 ->first();

    //             $plan->setRelation('set_duration', $plan_duration);
    //             $plan->setRelation('existing_sub', $existing_sub);

    //             return !empty($plan_duration) || (!$for_renew && empty($existing_sub));
    //         });

    //     try {

    //         // get amount for payment
    //         // if amount is invalid on any plan then unset the plan
    //         $final_amount = 0;

    //         foreach( $plans as $key => $plan ) {

    //             $plan_duration = $plan->set_duration;

    //             $discount = floatval($plan_duration->discount);

    //             $is_family = intval($request->get('is_family'));

    //             $amount = floatval($plan_duration->price);

    //             if( $is_family > 0 ) {
    //                 $fp = (array) @json_decode($plan_duration->family_price, true);
    //                 $fp = \array_filter($fp);

    //                 $family_price = floatval($fp[$is_family] ?? 0);

    //                 if( $family_price > 0 ) {
    //                     $amount = $family_price;
    //                 } else {
    //                     $amount += $amount * ($is_family - 1);
    //                 }
    //             }

    //             if( $discount > 0 ) {
    //                 $amount -= $amount * ($discount/100);
    //             }

    //             if( floatval($amount) <= 0 ) {
    //                 logger("Incorrect price for id: ".$plan->id);
    //                 unset($plans[$key]);
    //                 continue;
    //             }

    //             $final_amount += $amount;
    //         }

    //         if( !empty($coupon = $request->get('code')) && $final_amount > 0 ) {
    //             $final_amount = $this->use_coupon($coupon, $final_amount);
    //         }

    //         if( $plans->isEmpty() ) {
    //             return back()->withError('Cannot process empty plan(s)');
    //         }

    //         // this purchase is free
    //         if( $final_amount <= 0 ) {
    //             $payment = HSP::create_zero_payment_instance('subscription');

    //             if( $payment ) {
    //                 $subscriptions = HSP::create_subscription(
    //                     $plans,
    //                     $payment
    //                 );

    //                 if( $subscriptions ) {
    //                     $plans = $plans->implode('title', ', ');
                        
    //                     if(!empty($request->get('resource'))){
    //                         return redirect("promoted/{$request->get('resource')}/details")->with('success','Purchased successfully');

    //                     }else{
    //                         return redirect('/subscriptions')
    //                             ->withSuccess("Subscriptions \"{$plans}\" purchased/updated successfully");
    //                     }
    //                 }
    //             }
                
    //             return back()->withError('Something went wrong');
    //         }

    //         $final_amount = $amount = \number_format($final_amount, 2, '.', '');

    //         // create payment instance
    //         $uuid = \Illuminate\Support\Str::uuid();

    //         $remote_init_id = null;
    //         $remote_init_response = [
    //             'authorization_url' => '',
    //             'access_code' => '',
    //             'reference' => ''
    //         ];
    //         $remote_init_raw = [];
            
    //         if( $pm == 'paystack' ) {

    //             $paystack = new \App\Vars\Paystack([
    //                 'amount' => (string)($final_amount * 100),
    //                 'email' => $user->email,
    //                 // TODO: currency not supported by merchant
    //                 'currency' => 'GHS',
    //                 // 'currency' => $user_currency,
    //                 'reference' => $uuid,
    //                 'callback_url'=>route('verifyWebPayment'),
    //             ]);

    //             $result = $paystack->init();

    //             if( empty($result) || ! isset($result['status']) ) {
    //                 throw new \Exception('Empty response from paystack server');
    //             }

    //             if( ! $result['status'] ) {
    //                 throw new \Exception('Error from paystack server: ' . \json_encode($result));
    //             }

    //             if( empty($result = $result['data']) ) {
    //                 throw new \Exception('Empty response from paystack server');
    //             }

    //             $remote_init_response['authorization_url'] = $result['authorization_url'];
    //             $remote_init_response['access_code'] = $result['access_code'];
    //             $remote_init_response['reference'] = $result['reference'];

    //             $remote_init_raw = (array)$result;
                
    //             $remote_init_id = $result['reference'];
    //         }
    //         elseif( $pm == 'express' || $pm == 'expresspay' ) {
    //             $data = [
    //                 'amount' => $final_amount,
    //                 'order_id' => $uuid,
    //                 'email' => $user->email,
    //                 'first_name' => $user->name,
    //                 'redirect_url' => route('verifyWebPayment',['payment_method'=>'expresspay'])
    //             ];

    //             if( $user->phone ) {
    //                 $data['phone_number'] = $user->phone;
    //             }

    //             $merchantApi = new \App\Vars\Expressway($data);

    //             $result = $merchantApi->submit_request();

    //             if( empty($result) ) {
    //                 throw new \Exception('Empty response from expresswaygh');
    //             }

    //             if( $result['status'] != 1 ) {
    //                 throw new \Exception('Error from express server: ' . \json_encode($result));
    //             }

    //             $remote_init_response['authorization_url'] =
    //                 $merchantApi->checkout($result['token']);

    //             $remote_init_raw = (array)$result;

    //             $remote_init_id = $result['token'];
    //         } else {
    //             return back()->withError('Invalid payment gateway');
    //         }

    //         $payment = Payment::create([
    //             'type' => 'subscription',
    //             'user_id' => $user->id,
    //             'currency' => $user_currency,
    //             'amount' => $final_amount,
    //             'payment_method' => $pm,
    //             'status' => 'PENDING',
    //             'local_ref_id' => $uuid,
    //             'remote_id' => trim($remote_init_id),
    //             'remote_response_raw' => \json_encode($remote_init_raw)
    //         ]);

    //         $subscriptions = [];

    //         // create subscriptions
    //         foreach( $plans as $plan ) {
    //             $existing_sub = $plan->existing_sub;

    //             // update an expired paid subscription
    //             if( !empty($existing_sub) ) {
    //                 $existing_sub->renew_payment_id = $payment->id;
    //                 $existing_sub->update();
    //             } else {

    //                 $now = date('Y-m-d H:i:s');

    //                 $referral_code = "MAG{$user->id}-{$plan->id}-".Helper::generate_random_code();

    //                 $subscriptions[] = new UserSubscription([
    //                     'plan_id' => $plan->id,
    //                     'plan_duration_id' => $plan_duration->id,
    //                     'payment_id' => $payment->id,
    //                     'purchased_at' => Helper::to_price($final_amount),
    //                     'is_family' => intval($request->get('is_family')),
    //                     'referral_code' => $referral_code,
    //                     'total_members' => 0,
    //                     'subscribed_at' => $now,
    //                     'expires_at' => $now,
    //                     'created_at' => $now,
    //                     'updated_at' => $now,
    //                 ]);
    //             }
    //         }

    //         if( !empty($subscriptions) ) {
    //             $user->subscriptions()->saveMany($subscriptions);
    //         }

    //         return redirect( $remote_init_response['authorization_url'] );

    //     } catch(\Exception $e) {
    //         logger($e->getMessage());
    //     }

    //     DB::rollBack();
        
    //     return back()->withError('Something went wrong at the server.');
    // }
    
    public function paystack_new_plan(Request $request)
    {
        $this->validate($request,[
            'package_key' => ['array', 'min:1'],
            'package_key.*' => ['exists:plans,id'],
            'duration_key' => ['required'],
            'is_family' => ['nullable', 'numeric'],
            'payment_method' => ['nullable', 'in:paystack,express']
        ],[
            // 'duration_key.required' => 'Please select at least one duration',
            'package_key.required' => 'Please select at least one plan.',
        ]);

        if( empty( $request->get('package_key') ) ) {
            return back()->withError('Please select a package');
        }
        $pm = $request->get('payment_method')
            ?? $request->get('pm') ?? 'paystack';

        $pm = $pm == 'expresspay' ? 'express' : $pm;

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

                if( floatval($amount) <= 0 && $plan->id != env('FREE_PLAN_ID')) {
                    logger("Incorrect price for id: ".$plan->id);
                    unset($plans[$key]);
                    continue;
                }

                $final_amount += $amount;
            }

            if( !empty($coupon = $request->get('code')) && $final_amount > 0 ) {
                $final_amount = $this->use_coupon($coupon, $final_amount);
            }

            if( $plans->isEmpty() ) {
                return back()->withError('Cannot process empty plan(s)');
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
                        
                        if(!empty($request->get('resource'))){
                            $this->free_plan();
                            return redirect("promoted/{$request->get('resource')}/details")->with('success','Purchased successfully');

                        }else if($request->get('type') == 'Web'){
                            return 'Free Subscription Updated';
                        }
                        else {
                            return redirect('/subscriptions')
                                ->withSuccess("Subscriptions \"{$plans}\" purchased/updated successfully");
                        }
                    }
                }
                return back()->withError('Something went wrong');
            }

            $final_amount = $amount = \number_format($final_amount, 2, '.', '');

            // create payment instance
            $uuid = \Illuminate\Support\Str::uuid();

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
                    'callback_url'=>route('verifyWebPayment'),
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
            elseif( $pm == 'express' || $pm == 'expresspay' ) {
                $data = [
                    'amount' => $final_amount,
                    'order_id' => $uuid,
                    'email' => $user->email,
                    'first_name' => $user->name,
                    'redirect_url' => route('verifyWebPayment',['payment_method'=>'expresspay'])
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
                return back()->withError('Invalid payment gateway');
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

            return redirect( $remote_init_response['authorization_url'] );

        } catch(\Exception $e) {
            logger($e->getMessage());
        }

        DB::rollBack();
        
        return back()->withError('Something went wrong at the server.');
    }


    public function free_plan()
    {
        $user = $this->user();
        $free_plan_id = env('FREE_PLAN_ID')??null;
        $freePlanActive = Plan::where('id',$free_plan_id)->first(['status']);
        if(!empty($freePlanActive)){
            if($freePlanActive->status == 1){
                $checkFreeAccess = UserSubscription::where('plan_id',$free_plan_id)->where('user_id',$user->id)->count();
                $checkFirstSubs = UserSubscription::where('user_id',$user->id)->count();
                $freeplan_arr=[
                    'duration_key' => 'W',
                    'package_key' => [29],
                    'member_select' => 'only_me',
                    'pm' => 'paystack',
                    'code' => null,
                    'is_family'=>0,
                ];
                if($checkFreeAccess == 0 && $checkFirstSubs > 0){
                    if( empty( $freeplan_arr['package_key'] ) ) {
                        return back()->withError('Please select a package');
                    }
                    $pm = $freeplan_arr['pm']
                        ?? $freeplan_arr['pm'] ?? 'paystack';

                    $pm = $pm == 'expresspay' ? 'express' : $pm;

                    $duration_key = $freeplan_arr['duration_key'];
                    $user_currency = $user->my_currency;

                    $plans = \array_filter($freeplan_arr['package_key']);

                    $plans = Plan::query()
                        ->with(['durations'])
                        ->findMany($plans)
                        ->filter(function($plan) use($duration_key, $user_currency, $user) {

                            $plan_duration = $plan->durations
                                ->where('code', $duration_key)
                                ->firstWhere('currency', $user_currency);

                            $existing_sub = $user->subscriptions
                                ->where('plan_id', $plan->id)
                                ->where('plan_duration_id', $plan_duration ? $plan_duration->id : -1)
                                ->first();

                            $plan->setRelation('set_duration', $plan_duration);
                            $plan->setRelation('existing_sub', $existing_sub);

                            return !empty($plan_duration) || (empty($existing_sub));
                        });

                    try {

                        // get amount for payment
                        // if amount is invalid on any plan then unset the plan
                        $final_amount = 0;

                        foreach( $plans as $key => $plan ) {

                            $plan_duration = $plan->set_duration;

                            $discount = floatval($plan_duration->discount);

                            $is_family = intval($freeplan_arr['is_family']);

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

                            if( floatval($amount) <= 0 && $plan->id != env('FREE_PLAN_ID')) {
                                logger("Incorrect price for id: ".$plan->id);
                                unset($plans[$key]);
                                continue;
                            }

                            $final_amount += $amount;
                        }

                        if( !empty($coupon = $freeplan_arr['code']) && $final_amount > 0 ) {
                            $final_amount = $this->use_coupon($coupon, $final_amount);
                        }

                        if( $plans->isEmpty() ) {
                            return back()->withError('Cannot process empty plan(s)');
                        }
                        $payment = HSP::create_zero_payment_instance('subscription');
                        if( $payment ) {
                            $subscriptions = HSP::create_subscription(
                                $plans,
                                $payment
                            );

                            if( $subscriptions ) {
                                $plans = $plans->implode('title', ', ');
                            }
                        }
                        return back()->withError('Something went wrong');

                    } catch(\Exception $e) {
                        logger($e->getMessage());
                    }
                    DB::rollBack();
                    return true;
                }
            }
        }
        return true;
    }

    /*for renew*/

    public function paystack_verify(Request $request)
    {
        // dd($request->all());
         $this->validate($request,[
            'payment_method' => ['nullable', 'in:paystack,express']
        ]);

        $_message = __('Unable to verify transaction');
        $redirect = redirect('/');

        try {

            $user = $this->user();

            $payment = null;

            if( $remoteId = $request->get('reference') ) {
                $payment = Payment::query()
                    ->where('remote_id', $remoteId)
                    ->firstOrFail();
            }

            if( empty($payment) && ($remoteId = $request->get('token')) ) {
                $payment = Payment::query()
                    ->where('remote_id', $remoteId)
                    ->firstOrFail();
            }

            if( empty($payment) ) {
                return $redirect->withError('Invalid payment method');
            }

            if( $payment->isPaid() ) {
                return $redirect->withSuccess($_message);
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
            elseif( $pm == 'express' || $pm == 'expresspay' ) {
                $merchantApi = new \App\Vars\Expressway();

                $response = $merchantApi->query($payment->remote_id);

                $data['status'] = \strtolower($response['result-text']);
            }
            else {
                return $redirect->withError('Invalid payment method');
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

                        $_message = __('Subscription renewed successfully');
                    } else {
                        $user_subscription->expires_at =
                            Helper::add_days(
                                $now,
                                Helper::get_days_plan_duration($plan_duration->code)
                            )
                            ->format('Y-m-d H:i:s');
                            $this->free_plan();
                        $_message = __('Subscription purchased successfully');
                    }

                    $user_subscription->pay_status = 1;
                    $user_subscription->update();

                    if( empty($user) ) {
                       $user = $user_subscription->user;
                    }

                }

                \App\Vars\SystemMails::payment_success($user, $payment);

                if( ($user_subscription = $user_subscriptions->first()) &&
                    ($plan = $user_subscription->plan)) {

                    switch( $plan->type ) {
                        case 'premium':
                            $redirect = redirect('/topstory'); break;
                    }
                }

                try {
                    if( $blog_content = $request->session()->pull('_blog_sub_resource') ) {
                        $redirect = redirect()->route('blog.post',['blog' => $blog_content]);
                    }
                } catch(\Exception $e) { logger($e->getMessage()); }

                return $redirect->withSuccess($_message);
            }
            elseif( $data['status'] === 'failed' ) {
                $_message = __('Transaction failed');

                \App\Vars\SystemMails::payment_failed($user, $payment);

                $payment->status = 'FAILED';
                $payment->update();
            }
            else {
                $_message = __('Transaction cancelled');

                \App\Vars\SystemMails::payment_failed($user, $payment);

                $payment->status = 'CANCELLED';
                $payment->update();
            }

            return $redirect->with('warning', $_message);

        } catch(\Exception $e) {
            logger($e->getMessage());
        }

        return back()->with('error', $_message);
    }

    public function paymentSuccess($user,$payment){
        try{
            \Mail::send('mail/payment/successmail', array( 
                'name' => $user['first_name'],
                'currency'=>$payment['currency'], 
                'amount' => $payment['amount'], 

            ), function($message) use ($user){ 
                $message->from('admin@magazine.com'); 
                $message->to($user->email, 'User')->subject("Payment Successful"); 
            }); 
        } 
        catch(\Exception $e) {
            logger(' issue: '.$e->getMessage());
        }
    }

     public function paymentFailed($user,$payment){
        try{
            \Mail::send('mail/payment/paymentfail', array( 
                'name' => $user['first_name'],
                'currency'=>$payment['currency'], 
                'amount' => $payment['amount'], 

            ), function($message) use ($user){ 
                $message->from('admin@magazine.com'); 
                $message->to($user->email, 'User')->subject("Payment Failed"); 
            }); 
        } 
        catch(\Exception $e) {
            logger(' issue: '.$e->getMessage());
        }
    }
   /* public function custom_paystack_verify(Request $request)
    {

        $this->validate($request,[
             'reference' => 'required',    
        ]);

        $payment = Payment::where('remote_id', $request->get('reference'))
            ->with(['user_subscription'])
            ->firstOrFail();

        if( $payment->isPaid() ) {
            return back()->with('success','Subscription already purchased');
        }

        $user_subscription = $payment->user_subscription;

        try {
            $pay_response = \App\Vars\Paystack::verify($payment->remote_id);

            if( empty($pay_response) ) {
                throw new \Exception('paystack - empty verify response');
            }

            $data = $pay_response['data'];
    
            if( $data['status'] === 'success' ) {
                $payment->ip_addresses = trim($data['ip_address']);
                $payment->paid_at = date('Y-m-d H:i:s');
                $payment->status = 'SUCCESS';
                $payment->update();

                $user_subscription->pay_status = 1;
                $user_subscription->update();

                return back()->with('success','Subscription purchased successfully');
            }

        } catch(\Exception $e) {
            logger($e->getMessage());
        }
        return back()->with('error','Unable to verify Txn');

    }
    */

    /*public function redirectToGateway(Request $request)
    {

        try{

            return Paystack::getAuthorizationUrl()->redirectNow();
        }catch(\Exception $e) {
            return Redirect::back()->withMessage(['msg'=>'The paystack token has expired. Please refresh the page and try again.', 'type'=>'error']);
        }        
    }*/

    /*public function handleGatewayCallback()
    {
        $paymentDetails = Paystack::getPaymentData();
        $payment = new PaymentG;
        $payment->email =$paymentDetails['data']['customer']['email'];
        $payment->status =$paymentDetails['data']['status'];
        $payment->amount =$paymentDetails['data']['amount'];
        $payment->trans_id =$paymentDetails['data']['id'];
        $payment->ref_id =$paymentDetails['data']['reference'];
        if($payment->save()){
            return view('success');
        }else{
            return view('form');
        }
        // Now you have the payment details,
        // you can store the authorization_code in your db to allow for recurrent subscriptions
        // you can then redirect or do whatever you want
    }*/

    public function membersPrice(Request $request){
        $package_id = $request->get('package_id');
        $members = $request->get('members');
        $duration_key = $request->get('duration_key');
        $amount = '0.00';
        if(!empty($package_id) && !empty($members) && !empty($duration_key)){
            $planduration = PlanDuration::where('plan_id',$package_id)->where('code',$duration_key)->first('family_price','price');
            if(!empty($planduration)){
                if(!empty($planduration->family_price)){
                    $decodedPlanDur = json_decode($planduration->family_price,true);
                    $amount = $planduration->price;
                    if(array_key_exists($members,$decodedPlanDur)){
                        $amount = $decodedPlanDur[$members];
                    }
                }else{
                    $amount = $planduration->price;
                }
            }
        }
        return $amount;
    }
}
