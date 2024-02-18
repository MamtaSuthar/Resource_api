<?php

namespace App\Http\Resources;

use App\Vars\Helper;

class UserSubscription extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        if( !$this->resource->relationLoaded('plan') && !$this->resource->relationLoaded('plan_duration') ) {
            
            if( $this->resource instanceof \App\Models\UserSubscription ) {
                $this->resource->load(['plan', 'plan_duration', 'payment.refund', 'member_subscriptions.user']);
            } else {
                $this->resource->load(['plan', 'plan_duration', 'payment.refund']);
            }
        }

        $plan = $this->resource->plan;
        $plan_duration = $this->resource->plan_duration;
        $payment = $this->resource->payment;

        $member_subs = null;

        $is_family = boolval($this->is_family);

        if( isset($this->resource->member_subscriptions) ) {
            $member_subs = $this->resource->member_subscriptions;
        }

        $refund = $payment->refund;

        return array_merge([
            "reference_id" => $this->id,
            "key" => $plan->id,
            "value" => $plan->title,
            "type" => $plan->type,
            "description" => $plan->desc,
            "duration" => [
                "key" => $plan_duration->code,
                "value" => $plan_duration->value
            ],
            "amount" => Helper::to_price($plan_duration->price),
            "currency" => $plan_duration->currency,
            "family" => $is_family,
            "apple_product_id" => $plan_duration->apple_product_id,
            "apple_family_product_id" => $plan_duration->apple_family_product_id,
            $this->mergeWhen(boolval($member_subs), [
                "referral_code" => $is_family ? $this->referral_code : null,
                "members" => $is_family ? [
                    // 'total' => $member_subs ? $member_subs->count() : 0,
                    // 'total' => Helper::MAX_SUBSCRIPTION_MEMBERS,
                    'total' => $this->total_members,
                    'emails' => $member_subs
                        ? $member_subs->pluck('user.email')->filter()->toArray()
                        : []
                ] : null
            ]),
            "subscribed" => $this->subscribed_at->format('d/m/Y'),
            "expired" => $this->expires_at->subDay()->format('d/m/Y'),
            "payment_method" => $payment->payment_method,
            "via_referral" => !empty($this->via_referral),
            "via_referral_code" => $this->via_referral,
            "cancel_status" => !empty($refund),
            "cancel_description" => !empty($refund) ? $refund->status_str : null
        ], $this->additional);
    }
}
