<?php

namespace App\Http\Resources;

class GridResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return array_merge([
            'content_type' => $this->content_type,
            'thumbnail_image' => asset("storage/{$this->thumbnail_image}"),
            'cover_image' => $this->cover_image ? asset("storage/{$this->cover_image}") : null,
            'title' => $this->title,
            'short_description' => $this->short_description,
            'description' => $this->description,
            'crossAxisCount' => $this->crossAxisCount,
            'mainAxisCount' => $this->mainAxisCount,
            'slide' => $this->slider_page_no
        ], $this->additional);
    }
}
