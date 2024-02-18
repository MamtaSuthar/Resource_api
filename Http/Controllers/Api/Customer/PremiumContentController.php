<?php

namespace App\Http\Controllers\Api\Customer;

use App\Api\ApiResponse;
use App\Http\Controllers\ApiController;
use App\Http\Resources\MagazineDownloadResource;
use App\Http\Resources\NewspaperDownloadResource;
use App\Models\Magazine;
use App\Models\Newspaper;
use App\Models\Payment;
use App\Models\User;
use App\Models\UserDownload;
use App\Models\UserOneTimePurchase;
use App\Models\UserSubscription;
use App\Vars\Helper;
use App\Vars\HSP;
use App\Vars\OneSignalNotification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

// common controller for magazine & newspaper
class PremiumContentController extends ApiController
{
    protected $content_type;

    public function grid_collection(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'key' => ['required', 'numeric'],
            'type' => ['required', 'in:magazine,newspaper']
        ]);

        if($validator->fails()) {
            return $this->validation_error_response($validator);
        }

        $content = $this->get_content_instance($request->get('key'));

        if( ! $content ) {
            return ApiResponse::notFound(
                $this->get_content_type(true) . __(' Not Found')
            );
        }

        $slides = [];

        $grids = $content->grids()
            ->where('content_type', $request->get('type'))
            ->orderBy('order', 'ASC')
            ->get()
            ->filter(function($grid) {
                return !empty($grid->thumbnail_image);
            });

        foreach( $grids as $grid ) {
            if( !isset($slides[$grid->slider_page_no]) ) {
                $slides[$grid->slider_page_no] = [];
            }

            $slides[$grid->slider_page_no][] = $grid;
        }

        foreach( $slides as $slide => &$grids ) {
            $grids = [
                'slide' => $slide,
                'grids' => \App\Http\Resources\GridResource::collection($grids)
            ];
        }

        $slides = \array_values($slides);

        if( empty($slides) ) {
            return ApiResponse::okNoData();
        }

        return ApiResponse::ok(
            'Grid collection',
            [
                'slides' => $slides
            ]
        );
    }

    // @route
    public function buy(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'key' => ['required', 'numeric'],
            'payment_method' => ['nullable', 'in:paystack,express,apple_in_app'],
            'reference' => ['required_if:payment_method,apple_in_app']
        ]);

        if($validator->fails()){
            return $this->validation_error_response($validator);
        }

        $content = $this->get_content_instance($request->get('key'));

        if( ! $content ) {
            return ApiResponse::notFound(
                $this->get_content_type(true) . __(' Not Found')
            );
        }

        $reference = $request->get('reference');

        if( $request->get('payment_method') == 'apple_in_app' ) {
            return $this->apple_in_app($reference, $content);
        }

        if( !empty($reference) ) {
            return $this->buy_verify($reference, $content, $request->get('payment_method')??'paystack');
        }

        $user = $this->user();

        $user_currency = $user->my_currency;

        // check if already bought
        $already_bought = $content->users_who_bought()
            ->wherePivot('user_id', $user->id)
            ->wherePivot('pay_status', 1)
            ->exists();
            
        if( $already_bought ) {
            return ApiResponse::forbidden(
                $this->get_content_type(true) . __(' Already Bought')
            );
        }

        $amount = $user->is_currency_local
            ? floatval($content->price)
            : floatval($content->price);

        if( !empty($coupon = $request->get('coupon')) ) {
            $amount = $this->use_coupon($coupon, $amount);
        }

        // this purchase is free
        if( $amount <= 0 ) {
            $payment = HSP::create_zero_payment_instance('single-purchase');

            if( $payment ) {
                
                $purchase = HSP::create_one_time_purchase($content, $payment);

                if( $purchase ) {
                    return ApiResponse::ok('Purchased successfully');
                }
            }
            
            return ApiResponse::error('Purchased was not successful');
        }

        DB::beginTransaction();

        try {
            $uuid = \Illuminate\Support\Str::uuid();
            
            $amount = \number_format($amount, 2, '.', '');

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
                    'amount' => (string)($amount * 100),
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
                    'amount' => $amount,
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
                'type' => 'single-purchase',
                'user_id' => $user->id,
                'currency' => $user_currency,
                'amount' => $amount,
                'payment_method' => $pm,
                'status' => 'PENDING',
                'local_ref_id' => $uuid,
                'remote_id' => trim($remote_init_id),
                'remote_response_raw' => \json_encode($remote_init_raw)
            ]);

            $now = date('Y-m-d H:i:s');

            (new UserOneTimePurchase([
                'user_id' => $user->id,
                'payment_id' => $payment->id,
                'package_id' => $content->id,
                'package_type' => $this->get_content_type(),
                'pay_status' => 0,
                'price' => Helper::to_price($amount),
                'bought_at' => $now,
                'created_at' => $now,
                'updated_at' => $now
            ]))->save();

            DB::commit();

            return ApiResponse::ok('Payment Initiated', [
                'amount' => (string)($amount*100),
                'currency' => $user_currency,
                'paystack' => $remote_init_response,
            ]);

        } catch(\Exception $e) {
            logger($e->getMessage());
        }

        DB::rollBack();

        return ApiResponse::error('Something went wrong at the server.');
    }

    public function apple_in_app($reference, $content)
    {
        $user = $this->user();

        $already_bought = $content->users_who_bought()
            ->wherePivot('user_id', $user->id)
            ->wherePivot('pay_status', 1)
            ->exists();
            
        if( $already_bought ) {
            return ApiResponse::forbidden(
                $this->get_content_type(true) . __(' already bought')
            );
        }

        $receipt = [];

        // 
        // if a purchase id is present the request, just mark it successful
        if( $purchase_id = request()->get('purchase_id') ) {
            $receipt['transaction_id'] =
                \Illuminate\Support\Str::random(6).'-'.$purchase_id;
        } else {
            $response = \App\Vars\AppleInApp::verifyReceipt($reference);

            if( empty($response) || !isset($response['receipt']) ) {
                try {
                    logger("iap response: " . \json_encode($response));
                } catch(\Exception $e) {}
                
                return ApiResponse::error(__('Something went wrong while verifying IAP txn'));
            }
    
            $publication = $content->publication;
    
            if( empty($publication) ) {
                return ApiResponse::error(__('Invalid publication'));
            }
    
            $receipt = \App\Vars\AppleInApp::getReceipt($response, $publication->apple_product_id);
    
            if( empty($receipt) ) {
                return ApiResponse::error(__('Invalid purchase receipt'));
            }
    
            if( Payment::where(['remote_id' => $receipt['transaction_id']])->exists() ) {
                return ApiResponse::error(__('Payment already processed'));
            }   
        }

        DB::beginTransaction();

        try {

            $user_currency = $user->my_currency;

            $amount = $user->is_currency_local
                ? floatval($content->price)
                : floatval($content->price);

            $amount = $amount ? \number_format($amount, 2, '.', '') : '0.00';

            $uuid = \Illuminate\Support\Str::uuid();

            $now = date('Y-m-d H:i:s');

            $payment = Payment::create([
                'user_id' => $user->id,
                'currency' => $user_currency,
                'amount' => $amount,
                'payment_method' => 'apple_in_app',
                'status' => 'SUCCESS',
                'paid_at' => $now,
                'local_ref_id' => $uuid,
                'remote_id' => $receipt['transaction_id']
            ]);
    
            (new UserOneTimePurchase([
                'user_id' => $user->id,
                'payment_id' => $payment->id,
                'package_id' => $content->id,
                'package_type' => $this->get_content_type(),
                'pay_status' => 1,
                'price' => Helper::to_price($amount),
                'bought_at' => $now,
                'created_at' => $now,
                'updated_at' => $now
            ]))->save();

            DB::commit();
            
            return ApiResponse::ok(__('Purchased successfully'));

        } catch(\Exception $e) {

            DB::rollBack();

            logger($e->getMessage());
        }

        return ApiResponse::error(__('Something went wrong'));
    }

    public function buy_verify($reference, $content = null, $pm = 'paystack')
    {
        try {
            $data = [
                'status' => null,
                'ip_address' => null
            ];

            $payment = Payment::where('remote_id', $reference)
                ->firstOrFail();

            if( $payment->isPaid() ) {
                return ApiResponse::ok('Purchased successfully');
            }

            if( $pm == 'paystack' ) {
                $pay_response = \App\Vars\Paystack::verify($reference);

                if( empty($pay_response) ) {
                    throw new \Exception('paystack - empty verify response');
                }

                $data = $pay_response['data'];
            }
            elseif( $pm == 'express' ) {
                $merchantApi = new \App\Vars\Expressway();

                $response = $merchantApi->query($reference);

                $data['status'] = \strtolower($response['result-text']);
            }
            else {
                return ApiResponse::forbidden('invalid payment method');
            }

            $txn = UserOneTimePurchase::where('payment_id', $payment->id)
                ->firstOrFail();

            try {
                // paystack payment
                logger("ps txn data sp: " . \json_encode($data));
            } catch(\Exception $e) {};

            if( $data['status'] === 'success' ) {
                $payment->ip_addresses = trim($data['ip_address'] ?? '');
                $payment->paid_at = date('Y-m-d H:i:s');
                $payment->status = 'SUCCESS';
                $payment->update();

                $txn->pay_status = 1;
                $txn->update();

                return ApiResponse::ok('Purchased successfully');
            } else {
                return ApiResponse::error('Purchase was not successful');
            }

        } catch(\Exception $e) {
            logger($e->getMessage());
        }

        return ApiResponse::bad_request('Unable to verify Txn');
    }

    // @route
    // public function user_downloads(Request $request)
    // {
    //     $user = $this->user();

    //     $downloads = UserDownload::query()
    //         ->where('user_id', $user->id)
    //         ->with(['file','user'])
    //         ->latest()
    //         ->paginate(15)
    //         ->through(function($userDownload) {
    //             if( empty($userDownload->getRelation('file')) ) {
    //                 return null;
    //             }

    //             if( $userDownload->is_magazine ) {
    //                 $userDownload = new MagazineDownloadResource($userDownload->file);
    //             } else {
    //                 $userDownload = new NewspaperDownloadResource($userDownload->file);
    //             }
    //             return $userDownload;
    //         })
    //         ->getCollection()
    //         ->filter()
    //         ->values();
        

    //     return ApiResponse::ok(__('User Downloads'), $downloads->unique());
    // }
    
    public function user_downloads(Request $request)
    {
        $user = $this->user();
        // $publications = $user->getSubscribedPublications();
        // $subpaper = collect();
        // foreach( $publications as $publication ) {
        //     $papers = Newspaper::where('publication_id',$publication->id)->whereDate('newspapers.published_date', '>=', $publication->puchased_at->format('Y-m-d'))->get();
        //     $subpaper = $subpaper->merge($papers);
        // }
        // $papers1 = $user->bought_newspapers()
        //     ->where('user_onetime_purchases.pay_status', 1)
        //     ->get();
        // $papers = $subpaper->merge($papers1);
        // $papers2 = $user->bought_magazines()
        //     ->where('user_onetime_purchases.pay_status', 1)
        //     ->get();
        // $papers= $papers->merge($papers2);
        $downloads = UserDownload::query()
            ->where('user_id', $user->id)
            ->with(['file','user'])
            ->latest()
            ->paginate(15)
            ->through(function($userDownload) {
                if( empty($userDownload->getRelation('file')) ) {
                    return null;
                }
                if( $userDownload->is_magazine ) {
                    $userDownload = new MagazineDownloadResource($userDownload->file);
                } else {
                    $userDownload = new NewspaperDownloadResource($userDownload->file);
                }
                return $userDownload;
            })
            ->getCollection()
            ->filter()
            ->values();

        return ApiResponse::ok(__('User Downloads'), $downloads->unique());
        // return ApiResponse::ok(__('User Downloads'), $papers->filter());
    }
    
    public function user_downloads1(Request $request)
    {
        $user = $this->user();
        $publications = $user->getSubscribedPublications();
        $subpaper = collect();
        foreach( $publications as $publication ) {
            $papers = Newspaper::where('publication_id',$publication->id)->whereDate('newspapers.published_date', '>=', $publication->puchased_at->format('Y-m-d'))->get();
            // $papers->put('bought_at', $publication->puchased_at);
            $subpaper = $subpaper->merge($papers);
        }
        $papers1 = $user->bought_newspapers()
            ->where('user_onetime_purchases.pay_status', 1)
            ->get();
        $subpaper = $subpaper->merge($papers1);
        $papers2 = $user->bought_magazines()
            ->where('user_onetime_purchases.pay_status', 1)
            ->get();
        $papers= $subpaper->merge($papers2);
        // dd($papers);
        $quoCollect = collect();
        foreach($papers as $key => $pap){
            $newArr[$key]['id'] = intval($pap->id);
            $newArr[$key]['u_id'] = intval('1102'.$pap->id);
            $newArr[$key]['title'] = $pap->title;
            $newArr[$key]['thumbnail_image'] = asset("storage/{$pap->thumbnail_image}");
            $newArr[$key]['file'] = asset("storage/{$pap->file}");
            $newArr[$key]['grid_view'] = intval(0);
            $newArr[$key]['type'] = ($pap->type == 'Newspaper')?'newspaper':'magazine';
            $newArr[$key]['downloaded_at'] = $pap->created_at;
            $quoCollect = $quoCollect->merge($newArr);
        }
        return ApiResponse::ok(__('User Downloads'), ($quoCollect->unique())->values());
    }

    // @route
    public function markFileAsDownloaded($content_id)
    {
        $user = $this->user();

        $content = $this->get_content_instance($content_id);

        if( ! $content ) {
            return ApiResponse::notFound(
                $this->get_content_type(true) . __(' Not Found')
            );
        }

        DB::beginTransaction();
        try {
            // reset counter
            if( ! $user->download_date ||
                ! $user->download_date->isToday() ) {

                $user->download_date = date('Y-m-d H:i:s');
                $user->download_counter = 1;

                $user->update();
            }
            else {
                $user->increment('download_counter');
            }

            DB::table('user_downloads')->insert([
                'user_id' => $user->id,
                'file_type' => $this->get_content_type(),
                'file_id' => $content->id,
                'created_at' => date('Y-m-d H:i:s')
            ]);

            DB::commit();

            return ApiResponse::ok(
                __('File added as downloaded for today ('.$user->download_date->format('d,M Y').')'),
                [
                    'download_counter' => $user->download_counter,
                    'total_allowed_limit' => 5,
                    'file_added' => [
                        'id' => $content->id,
                        'title' => $content->title
                ]
            ]);

        } catch( \Exception $e ) {
            logger($e->getMessage());
        }

        DB::rollBack();

        return ApiResponse::error(__('Something went wrong'));
    }

    public function notification_test(Request $request)
    {
        $notificationManager = new OneSignalNotification();

        $type = $request->get('type');

        $user = $this->user();

        if( !empty($user_email = $request->get('user_email')) ) {
            $user_find = User::where('email', $user_email)->first();
            $user = $user_find ? $user_find : $user;
        }

        try {
            if( $type == 'magazine' ) {
                $magazine = Magazine::query()
                    ->with(['publication'])
                    ->has('publication')
                    ->first();
    
                $notificationManager->setData([
                    'n_id' => $magazine->id, 'n_type' => 'magazine'
                ]);
    
                $data = $notificationManager->send(
                    [$user->id],
                    "Magazine added for {$magazine->publication->name}",
                    $magazine->title
                );
    
                return ApiResponse::ok('notification sent', $data);
            }

            elseif( $type == 'newspaper' ) {
                $magazine = Newspaper::query()
                    ->with(['publication'])
                    ->has('publication')
                    ->first();
    
                $notificationManager->setData([
                    'n_id' => $magazine->id, 'n_type' => 'newspaper'
                ]);
    
                $data =$notificationManager->send(
                    [$user->id],
                    "Newspaper added for {$magazine->publication->name}",
                    $magazine->title
                );
    
                return ApiResponse::ok('notification sent', $data);
            }

            elseif( $type == 'renew_sub' ) {

                $subscription = $user
                    ->active_subscriptions()
                    ->first();

                if( !$subscription ) {
                    return ApiResponse::notFound('no active subscription found');
                }

                $plan = $subscription->plan;
                $plan_duration = $subscription->plan_duration;
                $payment = $subscription->payment;

                $data = [
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

                $notificationManager->setData([
                    'n_id' => null,
                    'n_type' => 'renew',
                    'n_data' => $data
                ]);

                $data= $notificationManager->send(
                    [(string)$user->id],
                    'Subscription Expiration',
                    "Your plan '{$data['value']}' about to be expired in 2 days"
                );

                return ApiResponse::ok('notification sent', $data);
            }

            return ApiResponse::notFound('type required');
        } catch(\Exception $e) {
            logger($e->getMessage());
        }

        return ApiResponse::error(__('Something went wrong'));
    }

    // @helper - magazine or news
    protected function get_content_instance($content_id)
    {
        $c_type = $this->get_content_type();

        if( !$c_type ) {
            return false;
        }

        return $c_type === 'magazine'
            ? \App\Models\Magazine::find($content_id)
            : \App\Models\Newspaper::find($content_id);
    }

    // @helper - magazine or news
    protected function get_content_type($uppercase = false)
    {
        $request = request();

        if( ! $this->content_type ) {

            if( \in_array($request->get('type'), ['newspaper', 'magazine']) ) {

                $this->content_type = $request->get('type');

            } else if($request->is('*/magazines/*')) {

                $this->content_type = 'magazine';

            } else if($request->is('*/newspapers/*')) {

                $this->content_type = 'newspaper';
            }
        }

        if( empty($this->content_type) ) {
            return false;
        }

        return $uppercase
            ? \ucwords($this->content_type)
            : $this->content_type;
    }

    // @helper -
    protected function get_total_downloads($content, $content_type = 'magazine')
    {
        $user = $this->user();

        $result = [];

        if( ! is_object($content) ) {
            $content = $this->get_content_instance($content);
        }

        $post_downloads = DB::table('user_downloads')
            ->where('user_id', $user->id)
            ->where('file_type', $content_type)
            ->where('file_id', $content->id)
            ->get();

        // all time downloads
        $result[] = $post_downloads->count();

        // today's downloads
        $result[] = $post_downloads
            ->filter(function($download) {
                if( $download->created_at ) {
                    return
                        Carbon::createFromTimestamp(
                            strtotime($download->created_at)
                        )->isToday();
                }

                return false;
            })
            ->count();

        return $result;
    }

    public function countPdfPages($path) {
        try {
            $pdf = new \Mpdf\Mpdf(
                ['tempDir' => storage_path('temp')]
            );

            $pdf->SetAutoPageBreak(false);

            $totalPages = $pdf->setSourceFile($path);

            if( $totalPages = intval($totalPages) ) {
                return $totalPages;
            }

         } catch(\Exception $e) {
            logger($e->getMessage());
         }

         return 0;
    }
}
