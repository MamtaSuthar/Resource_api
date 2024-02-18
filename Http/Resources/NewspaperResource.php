<?php

namespace App\Http\Resources;

use App\Models\Category;
use App\Models\Publication;
use App\Models\UserBookmark;
use App\Vars\Helper;

class NewspaperResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $cover_image = null; $thumbnail_image = null;

        if( $request->is_collection ){
            $cover_image = asset("storage/{$this->cover_image}");
            $thumbnail_image = asset("storage/{$this->thumbnail_image}");
        }
        elseif( $request->get('base') != 'n' ) {
            $cover_image = Helper::to_base64_str(
                public_path('storage/'.$this->cover_image)
            );
            $thumbnail_image = Helper::to_base64_str(
                public_path('storage/'.$this->thumbnail_image)
            );
        }
        // $bookmark_status = UserBookmark::where('user_id',$this->user->id)->where('pid',$this->id)->where('type','newspaper')->exists();
        // dd(auth()->user()->id);
        $user = auth()->user() ?? auth('api')->user();

        $currency = $user ? $user->my_currency : 'GHS';

        if( $currency == 'GHS' ) {
            $price = $this->publication->newspaper_price_ghs;
        } else {
            $price = $this->publication->newspaper_price_usd;
        }

        if( empty($cover_image) ) {
            $cover_image = url('assets/frontend/img/no-image-found-88397201.png');
        }

        if( empty($thumbnail_image) ) {
            $thumbnail_image = url('assets/frontend/img/no-image-found-88397201.png');
        }

        return array_merge([
            'id' => $this->id,
            'u_id' => intval("1102{$this->id}"),
            'apple_product_id' => $this->publication->apple_product_id,
            'title' => $this->title,
            'short_description' => $this->short_description ?? null,
            'description' =>$this->description ?? null,
            'price' => $price,
            'currency' => $currency,
            'cover_image' => $cover_image,
            'thumbnail_image' => $thumbnail_image,
            $this->mergeWhen(!$request->is_collection, [
                'cover_image_link' => asset("storage/{$this->cover_image}"),
                'thumbnail_image_link' => asset("storage/{$this->thumbnail_image}"),
            ]),
            'category' => new CategoryResource($this->category),
            'publication' => new PublicationResource($this->publication),
            'tags' => TagResource::collection($this->tags),
            'published_date' => $this->published_date->format('F d,Y'),
            'published_date_readable' => $this->published_date->diffForHumans(),
            'bookmark' => $user ? UserBookmark::where('user_id',$user->id)->where('pid',$this->id)->where('type','newspaper')->exists() : false,
            'grid_view' => $this->file_type == 'grid',
            'type'=>'newspaper'
        ], $this->additional);
    }
}
