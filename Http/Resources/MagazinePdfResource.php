<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class MagazinePdfResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $file = $this->file;

        $file_type = $this->file_type;

        if( $this->file_type == 'epub' ) {
            $file = $this->file_converted;
        }

        if( isset($this->subscribed) && empty($this->subscribed) ) {
            $file = $this->file_preview ?? '';
        }

        if( empty($file) ) {
            $file_type = '';
            $file = url('pdf/pdf_file_doesnt_exist.pdf');
        } else {
            $file = asset("storage/{$file}");
        }

        // return parent::toArray($request);
         return array_merge([
            'id' => $this->id,
            'title' => $this->title,
            'file' => $file,
            'file_type'=>$file_type,
            'file_preview' => $this->file_preview
                ? asset("storage/{$this->file_preview}")
                : url('pdf/pdf_file_doesnt_exist.pdf')
        ], $this->additional);
    }
}
