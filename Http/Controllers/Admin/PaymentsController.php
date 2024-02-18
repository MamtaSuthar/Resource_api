<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\Request;

class PaymentsController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'status' => ['nullable', 'in:SUCCESS,PENDING,CANCELLED'],
            'q' => ['nullable', 'max:255']
        ]);

        $payments = Payment::query()
            ->with([
                'user_subscriptions.user',
                'user_subscriptions_for_renew.user',
                'user_one_time_purchase.user'
            ])
            ->latest();

        if( $search = $request->get('q') ) {
            $payments = $this->search_query($payments, $search);
        }

        if( $status = $request->get('status') ) {
            $payments->where('status', $status);
        }

        $payments = $payments->paginate(15)
            ->through(function($payment) {
                return $this->attach_user_to_payment($payment);
            });

        return view('admin.payments.index', compact('payments'));
    }

    private function attach_user_to_payment(Payment $payment)
    {
        $user = null;

        if( $payment->user_subscriptions->isNotEmpty() ) {
            if($usb = $payment->user_subscriptions->first()) {
                $user = $usb->user;
            }
        }

        else if( $payment->user_subscriptions_for_renew->isNotEmpty() ) {
            if($usb = $payment->user_subscriptions_for_renew->first()) {
                $user = $usb->user;
            }
        }

        else if( $usb = $payment->user_one_time_purchase ) {
            $user = $usb->user;
        }

        $payment->setRelation('user', $user);

        return $payment;
    }

    private function search_query($payments, $search = '')
    {
        if( filter_var($search, FILTER_VALIDATE_INT) ) {
            $payments->where('id', $search);
        }

        else if( filter_var($search, FILTER_VALIDATE_EMAIL) ) {

            $payments->where(function($query) use($search) {
                $query->orWhereHas('user_subscriptions', function($query) use($search) {
                    $query->whereHas('user', function($query) use($search) {
                        $query->where('users.email', 'like', "%{$search}%");
                    });
                });
    
                $query->orWhereHas('user_subscriptions_for_renew', function($query) use($search) {
                    $query->whereHas('user', function($query) use($search) {
                        $query->where('users.email', 'like', "%{$search}%");
                    });
                });
    
                $query->orWhereHas('user_one_time_purchase', function($query) use($search) {
                    $query->whereHas('user', function($query) use($search) {
                        $query->where('users.email', 'like', "%{$search}%");
                    });
                });
            });
        }

        else {
            $payments->where(function($query) use($search) {
                $query->orWhere('local_ref_id', 'like', "%{$search}%");
                $query->orWhere('remote_id', 'like', "%{$search}%");
            });
        }

        return $payments;
    }

    public function update(Request $request)
    {
        $request->validate([
            'payment_id' => ['required'],
            'status' => ['required', 'in:SUCCESS,CANCELLED,PENDING,FAILED']
        ]);

        $payment = Payment::findOrFail($request->get('payment_id'));

        $payment->status = $request->get('status');
        $payment->update();

        // also mark related content as success
        if( $payment->status === 'SUCCESS' ) {
            if( $payment->user_subscriptions()->exists() ) {
                $payment->user_subscriptions()->update(['pay_status' => 1]);
            }

            else if( $payment->user_subscriptions_for_renew()->exists() ) {
                $payment->user_subscriptions_for_renew()->update(['pay_status' => 1]);
            }

            else if( $p = $payment->user_one_time_purchase ) {
                $p->pay_status = 1;
                $p->update();
            }
        }

        return back()->withSuccess("Payment #{$payment->id} is updated with status: {$payment->status}");
    }
}
