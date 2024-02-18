<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AlbumGalleryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        // return parent::toArray($request);
        return array_merge([
            'id' => $this->id,
            'title' => $this->title,
            'shor_description' => $this->short_description,
            'image' => asset("storage/{$this->image}"),
        ], $this->additional);
    }
}
