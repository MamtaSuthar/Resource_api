<?php

namespace App\Http\Resources;

class GalleryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $image = null;

        if( $this->image ) {
            $image = asset("storage/{$this->image}");
        }

        if( empty($image) ) {
            $image = url('assets/frontend/img/no-image-found-88397201.png');
        }

        return array_merge([
            'id' => $this->id,
            'title' => $this->title,
            'image' => $image,
            'link'  => $this->link ?? null,
        ], $this->additional);
    }
}
