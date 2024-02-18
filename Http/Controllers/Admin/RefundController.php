<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Refund;
use App\Vars\PaystackRefund;
use Illuminate\Http\Request;

class RefundController extends Controller
{
    public function index()
    {
        $refunds = Refund::query()
            ->with(['user', 'payment'])
            ->latest()
            ->get()
            ->filter(function($refund) {
                return !empty($refund->payment);
            })
            // only paystack allow initiating refund from our end
            ->map(function($refund) {
                $refund->can_be_refunded =
                    \strtolower($refund->payment->payment_method) == 'paystack';
                return $refund;
            });

        return view('admin.refund.index', compact('refunds'));
    }

    public function process_refund(Request $request)
    {
        $request->validate([
            'refund_fee_percent' => ['nullable', 'numeric', 'digits_between:1,50'],
            'refund_id' => ['required', 'exists:refunds,id']
        ]);

        $refund = Refund::find($request->get('refund_id'));

        $refund_fee_percent = intval($request->get('refund_fee_percent'));
        $refund_fee_percent = $refund_fee_percent > 0 ? $refund_fee_percent : 0;

        $payment = $refund->payment;

        $refund_amount = floatval($payment->amount);

        $refund_fee_amount = 0;

        if( $refund_fee_percent > 0 ) {
            $refund_fee_amount = $refund_amount * ($refund_fee_percent/100);
            $refund_amount -= $refund_fee_amount;
        }

        try {

            $response = PaystackRefund::fetch_refund(
                intval($refund->remote_ref)
            );

            if( empty($response) || !isset($response['status']) || !$response['status'] ) {
                $paystack = new PaystackRefund([
                    'transaction' => $payment->remote_id,
                    'amount' => intval($refund_amount*100),
                    'reason' => $refund->customer_reason ?? ''
                ]);
        
                $response = $paystack->init();
            }
    
            if( empty($response) || !isset($response['status']) ) {
                throw new \Exception('Empty paystack response');
            }
    
            if( !$response['status'] ) {
                throw new \Exception('Paystack response error : '.$response['message']??'');
            }

            $response = $response['data'];

            $refund->fill([
                'status' => $response['status'],
                'remote_ref' => $response['id'],
                'refund_amount' => $refund_amount,
                'customer_reason' => $response['customer_note'],
                'admin_reason' => $response['merchant_note'],
            ]);

            $refund->update();

            return back()->withSuccess('Refund initiated.');

        } catch(\Exception $e) {
            logger($e->getMessage());
        }

        return back()->withError('Something went wrong at the server.');
    }
}
