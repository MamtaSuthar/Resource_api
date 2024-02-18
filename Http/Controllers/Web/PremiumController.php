<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Api\ApiResponse;
// use App\Http\Controllers\Controller;
use App\Http\Resources\MagazineDownloadResource;
use App\Http\Resources\NewspaperDownloadResource;
use App\Models\Magazine;
use App\Models\Newspaper;
use App\Models\Payment;
use App\Models\UserOneTimePurchase;
use App\Models\UserSubscription;
use App\Vars\Helper;
use App\Vars\HSP;
use App\Vars\OneSignalNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;


class PremiumController extends Controller
{
    //
    protected $content_type;

    public function singleMagazinePurchase($id){
        // dd($request->all());
        // $coupons = $this->user()->myValidCoupons();
        $coupons = [];
        $datas = Magazine::with('category','publication')->where('id',$id)->first();
        // dd($datas->getTable());
        $getTable = $datas->getTable();
        $type = 'magazine';
        return view('customer.single-purchase.single-mag-purchase',compact('datas','getTable','type','coupons'));
    }

    public function singleNewspaperPurchase($id){
        // dd($request->all());
        $datas = Newspaper::with('category','publication')->where('id',$id)->first();
        $getTable = $datas->getTable();
        $type = 'newspaper';
        // $coupons = $this->user()->myValidCoupons();
        $coupons = [];
        // $price = $request->price;
        // $magid = $request->magsid;
        // $pub_name = $request->pub_name;
        // $title = $request->title;
        // $gettable =  $request->gettable;
        // return view('customer.single-purchase.singlepurchase',compact('price','magid','pub_name','title','gettable'));
        return view('customer.single-purchase.single-news-purchase',compact('datas','getTable','type','coupons'));

    }

    public function buy(Request $request)
    {
        // dd($request->all());
       $this->validate($request,[
            'key' => 'required|numeric',
            'pm' => ['nullable', 'in:express,expresspay,paystack']
            // 'duration_key' => 'required',
            // 'is_family' => 'nullable' 
        ],[
            // 'duration_key.required' => 'Please select at least one duration',
            'key.required' => 'Please select at least one plan.',

        ]);

        $pm = $request->get('pm') ?? 'paystack';

        $pm = $pm == 'expresspay' ? 'express' : $pm;

        $content = $this->get_content_instance($request->get('key'));

        if( ! $content ) {
            return back()->with('error',$this->get_content_type(true) . __(' Not Found')
            );
        }

        // if( !empty($reference = $request->get('reference')) ) {
        //     return $this->buy_verify($reference, $content,$request->key);
        // }

        $user = $this->user();

        $user_currency = $user->my_currency;

        // check if already bought
        $already_bought = $content->users_who_bought()
            ->wherePivot('user_id', $user->id)
            ->wherePivot('pay_status', 1)
            ->exists();
            
        if( $already_bought ) {
            return back()->with('error',
                $this->get_content_type(true) . __(' Already Bought')
            );
        }

        # implementation just for paystack for now
        $amount = $user->is_currency_local
            ? floatval($content->price)
            : floatval($content->price);

        if( !empty($coupon = $request->get('code')) ) {
            $amount = $this->use_coupon($coupon, $amount);
        }

        if( $amount <= 0 ) {
            // this purchase is free
            $payment = HSP::create_zero_payment_instance('single-purchase');

            if( $payment ) {
                
                $purchase = HSP::create_one_time_purchase($content, $payment);

                if( $purchase ) {
                    return redirect("magazine/{$content->id}/details")->with('success','Purchased successfully');
                }
            }
            
            return back()->withError('Something went wrong');
        }

        DB::beginTransaction();

        try {
            $uuid = \Illuminate\Support\Str::uuid();
            if($pm == 'paystack' ){
                $paystack = new \App\Vars\Paystack([
                    'amount' => (string)($amount * 100),
                    'email' => $user->email,
                    // TODO: currency not supported by merchant
                    'currency' => 'GHS',
                    // 'currency' => $user_currency,
                    'reference' => $uuid,
                    'callback_url'=>route('verify_mags',['id'=>$request->key]),

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
            }elseif( $pm == 'expresspay' || $pm == 'express' ) {
                $data = [
                    'amount' => $amount,
                    'order_id' => $uuid,
                    'email' => $user->email,
                    'first_name' => $user->name,
                    'redirect_url' => route('verify_mags',['id'=>$request->key,'payment_method'=>$request->pm])
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

                $remote_init_response['authorization_url'] = $merchantApi->checkout($result['token']);

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

            // return ApiResponse::ok('Payment Initiated', [
            //     'amount' => (string)($amount*100),
            //     'currency' => $user_currency,
            //     'paystack' => $result,
            // ]);
            return redirect($remote_init_response['authorization_url']);


        } catch(\Exception $e) {
            logger($e->getMessage());
        }

        DB::rollBack();

         return back()->with('error','Something went wrong at the server.');
    }

    public function buy_news(Request $request)
    {
        // dd($request->all());
       $this->validate($request,[
            'key' => 'required|numeric',
            // 'duration_key' => 'required',
            // 'is_family' => 'nullable' 
        ],[
            // 'duration_key.required' => 'Please select at least one duration',
            'key.required' => 'Please select at least one plan.',

        ]);
        // if($validator->fails()){
        //     return $this->validation_error_response($validator);
        // }

        $content = $this->get_content_instance($request->get('key'));

        if( ! $content ) {
            
          
            return back()->with('error',
                $this->get_content_type(true) . __(' Not Found')
            );
        }

        // if( !empty($reference = $request->get('reference')) ) {
        //     return $this->buy_verify($reference, $content,$request->key);
        // }

        $user = $this->user();

        $user_currency = $user->my_currency;

        // check if already bought
        $already_bought = $content->users_who_bought()
            ->wherePivot('user_id', $user->id)
            ->wherePivot('pay_status', 1)
            ->exists();
            
        if( $already_bought ) {
            return back()->with('error',
                $this->get_content_type(true) . __(' Already Bought')
            );
        }

        # implementation just for paystack for now
        $amount = $user->is_currency_local
            ? floatval($content->price)
            : floatval($content->price);

        if( !empty($coupon = $request->get('code')) ) {
            $amount = $this->use_coupon($coupon, $amount);
        }

        if( $amount <= 0 ) {
            // this purchase is free
            $payment = HSP::create_zero_payment_instance('single-purchase');

            if( $payment ) {
                
                $purchase = HSP::create_one_time_purchase($content, $payment);

                if( $purchase ) {
                    return redirect("newspapers/{$content->id}/details")->with('success','Purchased successfully');
                }
            }
            
            return back()->withError('Something went wrong');
        }

        DB::beginTransaction();

        try {
            $uuid = \Illuminate\Support\Str::uuid();
            if($request->pm == 'paystack' ){
                $paystack = new \App\Vars\Paystack([
                    'amount' => (string)($amount * 100),
                    'email' => $user->email,
                    // TODO: currency not supported by merchant
                    'currency' => 'GHS',
                    // 'currency' => $user_currency,
                    'reference' => $uuid,
                    'callback_url'=>route('verifyNews',['id'=>$request->key,'payment_method'=>$request->pm]),

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
            }elseif( $request->pm == 'expresspay' || $request->pm == 'express' ) {
                $data = [
                    'amount' => $amount,
                    'order_id' => $uuid,
                    'email' => $user->email,
                    'first_name' => $user->name,
                    'currency' => 'GHS',
                    'redirect_url' => route('verifyNews',['id'=>$request->key,'payment_method'=>$request->pm])
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

                $remote_init_response['authorization_url'] = $merchantApi->checkout($result['token']);

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
                'payment_method' => 'Paystack',
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

            // return ApiResponse::ok('Payment Initiated', [
            //     'amount' => (string)($amount*100),
            //     'currency' => $user_currency,
            //     'paystack' => $result,
            // ]);
            return redirect($remote_init_response['authorization_url']);


        } catch(\Exception $e) {
            // dd($e->getMessage());
            logger($e->getMessage());
        }

        DB::rollBack();

         return back()->with('error','Something went wrong at the server.');
    }


    public function buy_verify(Request $request, $content=null,$key=null)
    {
        // dd($request->all());
        $pm = $request->payment_method ? $request->payment_method :'paystack';
        if ($pm=='paystack') {
            $reference = $request->reference;
        }elseif ($pm=='expresspay') {
            $reference = $request->token;
        }else{
            return back()->with('error','Invalid payment method');
        }
        
         // if( !empty($reference = $request->get('reference')) ) {
        try {
            $data = [
                'status' => null,
                'ip_address' => null
            ];

            $payment = Payment::where('remote_id', $reference)
                ->firstOrFail();

            if( $payment->isPaid() ) {
                return redirect("magazine/{$request->id}/details")->with('success','Purchased successfully');
            }

            if ($pm=='paystack') {
                $pay_response = \App\Vars\Paystack::verify($reference);

                if( empty($pay_response) ) {
                    throw new \Exception('paystack - empty verify response');
                }

                $data = $pay_response['data'];
            }elseif ($pm=='expresspay') {
                $merchantApi = new \App\Vars\Expressway();

                $response = $merchantApi->query($reference);

                $data['status'] = \strtolower($response['result-text']);
            }else{
                return back()->with('error','Invalid payment method');
            }

            $txn = UserOneTimePurchase::where('payment_id', $payment->id)
                ->firstOrFail();
                // dd($txn);

            if( $data['status'] === 'success' ) {
                $payment->ip_addresses = trim($data['ip_address']);
                $payment->paid_at = date('Y-m-d H:i:s');
                $payment->status = 'SUCCESS';
                $payment->update();

                $txn->pay_status = 1;
                $txn->update();

                return redirect("magazine/$request->id/details")->with('success','Purchased successfully');
            } else {
                return back()->with('error','Purchased was not successful');
            }

        } catch(\Exception $e) {
            logger($e->getMessage());
        }
    // }
// 
        return back()->with('error','Unable to verify Txn');
    }
    public function buy_verify_news(Request $request, $content=null,$key=null)
    {
        // dd($request->all());
        $pm = $request->payment_method ? $request->payment_method :'paystack';
        if ($pm=='paystack') {
            $reference = $request->reference;
        }elseif ($pm=='expresspay') {
            $reference = $request->token;
        }else{
            return back()->with('error','Invalid payment method');
        }
        // $reference = $request->reference;
         // if( !empty($reference = $request->get('reference')) ) {
        try {
            $data = [
                'status' => null,
                'ip_address' => null
            ];

            $payment = Payment::where('remote_id', $reference)
                ->firstOrFail();

            if( $payment->isPaid() ) {
                return redirect("newspapers/$request->id/details")->with('success','Purchased successfully');
            }

            if ($pm=='paystack') {
                $pay_response = \App\Vars\Paystack::verify($reference);

                if( empty($pay_response) ) {
                    throw new \Exception('paystack - empty verify response');
                }

                $data = $pay_response['data'];
            }elseif ($pm=='expresspay') {
                $merchantApi = new \App\Vars\Expressway();

                $response = $merchantApi->query($reference);

                $data['status'] = \strtolower($response['result-text']);
            }else{
                return back()->with('error','Invalid payment method');
            }

            $txn = UserOneTimePurchase::where('payment_id', $payment->id)
                ->firstOrFail();
                // dd($txn);

            if( $data['status'] === 'success' ) {
                $payment->ip_addresses = trim($data['ip_address']);
                $payment->paid_at = date('Y-m-d H:i:s');
                $payment->status = 'SUCCESS';
                $payment->update();

                $txn->pay_status = 1;
                $txn->update();

                return redirect("newspapers/$request->id/details")->with('success','Purchased successfully');
            } else {
                return back()->with('error','Purchased was not successful');
            }

        } catch(\Exception $e) {
            logger($e->getMessage());
        }
    // }
// 
        return back()->with('error','Unable to verify Txn');
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

            } else if($request->is('*/magazine/*')) {

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

}
