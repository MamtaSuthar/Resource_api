<?php

namespace App\Http\Resources;

class MagazineDownloadResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $downloaded_at = $this->resource->pivot ? $this->pivot->created_at : $this->created_at;

        if( $downloaded_at && ($downloaded_at = strtotime($downloaded_at)) ) {
            $downloaded_at = date('Y-m-d H:i', $downloaded_at);
        } else {
            $downloaded_at = date('Y-m-d H:i');
        }

        $thumbnail_image = null;

        if( $this->thumbnail_image ) {
            $thumbnail_image = asset("storage/{$this->thumbnail_image}");
        }

        if( empty($thumbnail_image) ) {
            $thumbnail_image = url('assets/frontend/img/no-image-found-88397201.png');
        }

        return array_merge([
            'id' => $this->id,
            'u_id' => intval("1101{$this->id}"),
            'title' => $this->title,
            'thumbnail_image' => $thumbnail_image,
            // 'file' => asset("storage/{$this->file}"),
            'type'=>'magazine',
            'grid_view' => intval($this->file_type === 'grid'),
            'downloaded_at' => $downloaded_at
        ], $this->additional);
    }
}
