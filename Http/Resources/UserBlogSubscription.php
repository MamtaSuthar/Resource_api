<?php

namespace App\Http\Resources;

use App\Vars\Helper;

class UserBlogSubscription extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        if( $this->resource instanceof \App\Models\UserSubscription ) {
            $this->resource->load(['plan', 'plan_duration', 'payment.refund', 'member_subscriptions.user']);
        } else {
            $this->resource->load(['plan', 'plan_duration', 'payment.refund']);
        }

        $plan = $this->resource->plan;
        $plan_duration = $this->resource->plan_duration;
        $payment = $this->resource->payment;

        return array_merge([
            "reference_id" => $this->id,
            "key" => $plan->id,
            "value" => $plan->title,
            "description" => $plan->desc,
            "duration" => [
                "key" => $plan_duration->code,
                "value" => $plan_duration->value
            ],
            "amount" => Helper::to_price($plan_duration->price),
            "currency" => $plan_duration->currency,
            "subscribed" => $this->subscribed_at->format('d/m/Y'),
            "expired" => $this->expires_at->subDay()->format('d/m/Y'),
            "payment_method" => $payment->payment_method
        ], $this->additional);
    }
}
