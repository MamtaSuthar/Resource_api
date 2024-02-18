<?php

namespace App\Http\Resources;

use App\Vars\Helper;

class RefundResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'for' => $this->for,
            'user' => $this->user->name,
            'paid_amount' => $this->paid_amount,
            'refund_amount' => $this->refund_amount,
            'reason' => $this->customer_reason,
            'created_at' => $this->created_at->format('Y-m-d H:i')
        ];
    }
}
