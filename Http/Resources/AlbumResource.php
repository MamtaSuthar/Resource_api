<?php

namespace App\Http\Resources;

use App\Models\AlbumGallery;
use Illuminate\Http\Resources\Json\JsonResource;

class AlbumResource extends JsonResource
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
        if( $this->cover_image ) {
            $image = asset("storage/{$this->cover_image}");
        } elseif( $gallery = $this->gallary_images->last() ) {
            $image = asset("storage/{$gallery->image}");
        }
        // return parent::toArray($request);
        return array_merge([
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status ?? null,
            'image' => $image,
            'date' => $this->created_at->format('d-m-Y'),
            'date_readable' => $this->created_at->diffForHumans(),
            'image_count' =>$this->gallary_images->count(),
        ], $this->additional);
    }
}
