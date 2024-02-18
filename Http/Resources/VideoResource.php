<?php

namespace App\Http\Resources;

// use Illuminate\Http\Resources\Json\JsonResource;

class VideoResource extends JsonResource
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
        } else {
            $thumbnail_image = asset('assets/frontend/img/default_video_image.png');
        }

        $video_url = $this->video_link;

        preg_match(
            "/\s*[a-zA-Z\/\/:\.]*youtu(be.com\/watch\?v=|.be\/)([a-zA-Z0-9\-_]+)([a-zA-Z0-9\/\*\-\_\?\&\;\%\=\.]*)/i",
            $video_url,
            $matches
        );

        if( !empty($matches) && isset($matches[2]) ) {
            $video_url = "https//www.youtube.com/embed/".$matches[2];
        }

        return array_merge([
            'id'    => $this->id,
            'title' => $this->title ?? null,
            'thumbnail_image' => $thumbnail_image,
            'video_file'  => asset("storage/{$this->video_file}"),
            'video_link'  => $video_url,
            'date' => $this->created_at->format('Y-m-d'),
            'date_readable' => $this->created_at->diffForHumans(),
        ], $this->additional);
    }
}
