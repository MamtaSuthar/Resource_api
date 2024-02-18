<?php

namespace App\Http\Resources;

// use Illuminate\Http\Resources\Json\JsonResource;

class PodcastResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $thumbnail_image = null;

        if( $this->thumbnail_image ) {
            $thumbnail_image = asset("storage/{$this->thumbnail_image}");
        }

        if( empty($thumbnail_image) ) {
            $thumbnail_image = url('assets/frontend/img/no-image-found-88397201.png');
        }

        return array_merge([
            'id'    => $this->id,
            'title' => $this->title ?? null,
            'thumbnail_image' => $thumbnail_image,
            'podcast_file'  => asset("storage/{$this->podcast_file}"),
            'date' => $this->created_at->format('Y-m-d'),
            'date_readable' => $this->created_at->diffForHumans(),
        ], $this->additional);
    }
}
