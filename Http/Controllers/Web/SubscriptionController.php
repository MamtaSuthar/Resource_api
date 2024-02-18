<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\PlanDuration;
use App\Models\UserSubscription;
use App\Vars\Helper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Api\ApiResponse;
use App\Models\Refund;


class SubscriptionController extends Controller
{
    //
    public function subsView(){
        return view('customer.plans.plans');
    }
    public function my_subscriptions()
    {
        $user = $this->user();

        $subscriptions = $user->subscriptions()
            ->with(['plan', 'plan_duration', 'payment', 'member_subscriptions.user','payment.refund'])
            ->orderby('id','desc')->get()
            ->filter(function($subscription) {
                return !empty($subscription->plan_duration);
            });
            $subscription =  \App\Http\Resources\UserSubscription::collection($subscriptions);
            // echo "<pre>";
            // print_r($subscription);
            // die;
            return view('customer.subscription.my-subscription',compact('subscription'));


        // return ApiResponse::ok(
        //     'My Subscriptions',
        //     \App\Http\Resources\UserSubscription::collection($subscriptions)
        // );

    }


    public function mysubs(){
        return view('customer.subscription.my-subscription');
    }

    public function referFriend(){
        $user = $this->user();

        if( empty($user->refer_code) ) {
            $user->refer_code = _user_get_referral_code($user->first_name);
            $user->update();
        }

        return view('refer.refer-friend',compact('user'));
    }

    public function renew(Request $request, UserSubscription $userSubscription)
    {
        $user = $this->user();

        $userSubscription->load(['payment', 'plan.publications', 'plan_duration']);

        if( !$userSubscription->payment->isPaid() ) {
            abort(404);
        }

        $payment = $userSubscription->payment;
        $plan = $userSubscription->plan;
        $plan_duration = $userSubscription->plan_duration;
        
        // if currency don't match with what user intially paid with
        if( $user->my_currency !== $plan_duration->currency ) {

            $new_pd = PlanDuration::query()
                ->where('plan_id', $plan_duration->plan_id)
                ->where('code', $plan_duration->code)
                ->where('currency', $user->my_currency)
                ->first();

            if( $new_pd ) {
                $plan_duration = $new_pd;
            }
        }

        // $coupons = $this->user()->myValidCoupons();
        $coupons = [];
        return view('customer.subscription.renew', compact('userSubscription', 'payment', 'plan', 'plan_duration','coupons'));
    }

    public function singlePurchase(){
        return view('customer.single-purchase.singlepurchase');
    }

     public function show(){
        return view('payform');
    }

    public function all_plans(Request $request)
    {
        $user_currency = $this->user()->my_currency;
        // $coupons = $this->user()->myValidCoupons();
        $coupons = [];
        $user = $this->user();
        $plan_id = env('FREE_PLAN_ID')??null;
        // $free_plan = 0;
        // if(!empty($plan_id)){
        //     if(!empty($user))
        //         $free_plan = UserSubscription::where('plan_id',$plan_id)->where('user_id',$user->id)->count();
        // }
        
        if(!empty($plan_id)){
        $plans = Plan::query()
            ->active()
            ->with(['durations'])
            ->where('id','!=',$plan_id)
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
            ->reduce(function($acc, $plan) {

                if( ! isset($acc[$plan->type]) ) {
                    $plan_type = Helper::plan_types($plan->type);

                    if( !$plan_type ) {
                        throw new \Exception('Plan type removed from Helper');
                    }

                    $acc[$plan->type] = [
                        'key' => $plan_type['key'],
                        'value' => \ucwords($plan->type),
                        'description' => $plan_type['desc'],
                        'period' => Helper::plan_durations($plan->type),
                        'packages' => []
                    ];
                }

                foreach( $plan->durations as $duration ) {
                    $amount = floatval($duration->price);
                    $discount = floatval($duration->discount);

                    if( $discount > 0 ) {
                        $amount -= $amount * ($discount/100);
                    }

                    if( ! isset($acc[$plan->type]['packages'][$duration->code]) ) {
                        $acc[$plan->type]['packages'][$duration->code] = [
                            'key' => $duration->code,
                            'value' => $duration->value,
                            'list' => []
                        ];
                    }

                    $acc[$plan->type]['packages'][$duration->code]['list'][] = [
                        'key' => $plan->id,
                        'value' => $plan->title,
                        'description' => $plan->desc,
                        'price' => Helper::to_price($amount),
                        'currency' => $duration->currency ?? 'GHS',
                        'family_price' => Helper::to_price($amount),
                        'discount' => $discount.'%'
                    ];
                }

                return $acc;
            }, []);
        }else{
            $plans = Plan::query()
            ->active()
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
            ->reduce(function($acc, $plan) {

                if( ! isset($acc[$plan->type]) ) {
                    $plan_type = Helper::plan_types($plan->type);

                    if( !$plan_type ) {
                        throw new \Exception('Plan type removed from Helper');
                    }

                    $acc[$plan->type] = [
                        'key' => $plan_type['key'],
                        'value' => \ucwords($plan->type),
                        'description' => $plan_type['desc'],
                        'period' => Helper::plan_durations($plan->type),
                        'packages' => []
                    ];
                }

                foreach( $plan->durations as $duration ) {
                    $amount = floatval($duration->price);
                    $discount = floatval($duration->discount);

                    if( $discount > 0 ) {
                        $amount -= $amount * ($discount/100);
                    }

                    if( ! isset($acc[$plan->type]['packages'][$duration->code]) ) {
                        $acc[$plan->type]['packages'][$duration->code] = [
                            'key' => $duration->code,
                            'value' => $duration->value,
                            'list' => []
                        ];
                    }

                    $acc[$plan->type]['packages'][$duration->code]['list'][] = [
                        'key' => $plan->id,
                        'value' => $plan->title,
                        'description' => $plan->desc,
                        'price' => Helper::to_price($amount),
                        'currency' => $duration->currency ?? 'GHS',
                        'family_price' => Helper::to_price($amount),
                        'discount' => $discount.'%'
                    ];
                }

                return $acc;
            }, []);
        }
           
            $plans =collect($plans)->values();
            // dd($plans);

            if( $resource = intval($request->query('resource')) ) {
                $request->session()->put('_blog_sub_resource', $resource);
            }

           
            return view('customer.plans.plans',compact('plans','coupons'));
       
    }

     public function referral_new_plan(Request $request)
    {
        // $validator = Validator::make($request->all(), [
        //     'referral_code' => ['required', 'max:100']
        // ]);

        $this->validate($request,[
            'referral_code' => 'required|max:100',
        ]);

        // if($validator->fails()){
        //     return $this->validation_error_response($validator);
        // }

        $user = $this->user();

        $referral_code = $request->get('referral_code');

        $subscription = UserSubscription::query()
            ->where('referral_code', $referral_code)
            ->first();

         // dd($subscription->user_id);   

        if( ! $subscription ) {

            return back()->with('error','Code not found');
        }else{
            if($user->id == $subscription->user_id){
                return back()->with('error','Code is not available for subscribed user.');
            }
        }

        if( ! $subscription->is_active || ! $subscription->is_family ) {
            return back()->with('error','Subscription on this code is not active');
        }

        $used = UserSubscription::query()
            ->where('via_referral', $referral_code)
            ->count();

        // if( $used > Helper::MAX_SUBSCRIPTION_MEMBERS ) {
        //     return ApiResponse::forbidden('Referral code expired');
        // }

        if( $used >= intval($subscription->is_family) ) {
            return back()->with('error','Referral code expired');
        }

        if( $user->subscriptions()->where('via_referral', $referral_code)->exists() ) {
           return back()->with('error','Referral code already utilized');
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

        return back()->with('success','Subscribed via referral code successfully');

        // return ApiResponse::ok(
        //     'Subscribed via referral code successfully'
        //     // new \App\Http\Resources\UserSubscription($new_subscription)
        // );
    }

    public function subscription_refund(Request $request)
    {
        // dd($request->all());
         $this->validate($request,[
            'reference_id' => 'required',
            'reason' => 'nullable|max:5000' 
        ]);
     

        DB::beginTransaction();
        try {

            $user = $this->user();

            $subscription = $user->active_subscriptions()
                ->with(['payment'])
                ->find($request->reference_id);
            // dd($subscription) ;   

            if( empty($subscription) ) {
                return back()->with('error','Subscription not found');
            }

            $payment = $subscription->payment;

            if( !empty($payment->refund_id) ) {
                 return back()->with('error','Cancellation already requested');
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

            DB::commit();

            return back()->with('success','Cancellation requested. You will get a response in two to three working days.');

        } catch(\Exception $e) {
            logger($e->getMessage());
        }

        DB::rollBack();
        return back()->with('error','Unable to process refund');
    }
}
