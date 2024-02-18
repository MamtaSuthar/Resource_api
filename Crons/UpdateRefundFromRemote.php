<?php

namespace App\Crons;

use App\Models\Refund;
use App\Vars\PaystackRefund;

class UpdateRefundFromRemote
{
    public function __invoke()
    {
        foreach( $this->refunds() as $refund ) {
            try {

                $response = PaystackRefund::fetch_refund(
                    strval($refund->remote_ref)
                );

                if( !empty($response) && $response['status'] ) {
                    $data = $response['data'];

                    if( $data['status'] == 'processed' ) {
                        $refund->status = 'processed';
                        $refund->update();
                    }
                }

            } catch(\Exception $e) {
                logger($e->getMessage());
            }
        }
    }

    public function refunds()
    {
        return Refund::query()
            ->where('status', 'pending')
            ->whereNotNull('remote_ref')
            ->has('payment')
            ->with(['payment'])
            ->get();
    }
}