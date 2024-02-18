<?php

namespace App\Http\Controllers\Api;

use App\Api\ApiResponse;
use App\Http\Controllers\ApiController as Controller;
use App\Http\Resources\MagazineResource;
use App\Http\Resources\NewspaperResource;
use App\Http\Resources\BlogResource;
use App\Models\Category;
use App\Models\Blog;
use App\Models\Payment;
use App\Vars\Helper;
use Illuminate\Http\Request;

class CommonController extends Controller
{
    private $limits=2;
    // RELATED MAGAZINE / NEWSPAPERS
    public function related_magazines($type, Category $category)
    {
        $magazines = $type === 'magazine' 
            ? MagazineResource::collection($category->magazines)
            : NewspaperResource::collection($category->newspapers);
            // dd($magazines);
        return $magazines;

    }
      public function related_blogs($type, Category $category)
    {
        $blogs = $type === 'popular_content' 
            ? BlogResource::collection($category->blogs)->where('promoted',1)
            :BlogResource::collection($category->blogs)->where('top_story',1);
        return $blogs;

    }

    public function top_stories($is_story = false)
    {
        // $titles = ['Gov’t donate GH¢470,000.00 to DOL Clubs', 'Gov’t donate GH¢730,000.00 to DOL Clubs', 'Gov’t donate GH¢120,000.00 to DOL Clubs'];
        // $categories = ['Football', 'Business', 'Lifestyle', 'News', 'Fashion', 'Health'];

        // $stories = [];
        // for($i=1; $i<4; $i++){
        //     $stories[] = [
        //         'id' => $i,
        //         'title' => $titles[rand(0,2)],
        //         'category' => $categories[rand(0,5)],
        //         'date'  => now()->format('d-m-Y'),
        //         'image' => ! $is_story 
        //             ? asset("assets/frontend/img/p{$i}.jpg")
        //             : asset("assets/frontend/img/ts{$i}.jpg"),
        //         'link'  => null
        //     ];
        // }

        // return $stories;
        $top_story = Blog::where('top_story',1)->active()->latest()->paginate($this->limits);
       $top_story_data= BlogResource::collection($top_story);
        
        return $top_story_data;
    }

    public function user_refunds()
    {
        $user = $this->user();

        $refunds = $user->refunds;
        
        return ApiResponse::ok(
            'User Refunds',
            \App\Http\Resources\RefundResource::collection($refunds)
        );
    }

    public function expressgh_hook(Request $request)
    {
        rescue(function() use($request) {
            logger("Expressgh hook: " . json_encode($request->all()));
        });

        $post = $request->all();

        $token = $post['token'] ?? null;

        if( !$token ) {
            return 'ok';
        }

        $payment = Payment::firstWhere('remote_id', $token);

        if( $payment ) {

            if( $payment->isPaid() ) {
                return 'ok';
            }

            $merchantApi = new \App\Vars\Expressway();

            $response = $merchantApi->query($token);

            if( empty($response) || !isset($response['result']) ) {
                return 'invalid-response';
            }

            else if( $response['result'] != '1' ) {
                return 'cancelled-or-failed';
            }

            if( $payment->type == 'subscription' ) {
                $this->payment_process_subscriptions($payment);
            }
            else if( $payment->type == 'single-purchase' ) {
                $this->payment_process_single_purchase($payment);
            }
            else if( $payment->user_one_time_purchase ) {
                $this->payment_process_single_purchase($payment);
            } else {
                $this->payment_process_subscriptions($payment);
            }
        }

        return 'ok';
    }

    public function payment_process_subscriptions(Payment $payment)
    {
        $user_subscriptions = $payment->user_subscriptions
            ->concat($payment->user_subscriptions_for_renew);

        if( empty($user_subscriptions) ||
            (is_object($user_subscriptions) && $user_subscriptions->isEmpty()) ) {
            return;
        }

        $now = now();

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
            } else {
                $user_subscription->expires_at =
                    Helper::add_days(
                        $now,
                        Helper::get_days_plan_duration($plan_duration->code)
                    )
                    ->format('Y-m-d H:i:s');
            }

            $user_subscription->pay_status = 1;
            $user_subscription->update();

            if( $user = $user_subscription->user ) {
                \App\Vars\SystemMails::payment_success($user, $payment);
            }
        }
    }

    public function payment_process_single_purchase(Payment $payment)
    {
        $txn = $payment->user_one_time_purchase;

        if( empty($txn) ) {
            return;
        }

        $payment->ip_addresses = trim($data['ip_address'] ?? '');
        $payment->paid_at = date('Y-m-d H:i:s');
        $payment->status = 'SUCCESS';
        $payment->update();

        $txn->pay_status = 1;
        $txn->update();

        if( $user = $txn->user ) {
            \App\Vars\SystemMails::payment_success($user, $payment);
        }

        return;
    }
}
