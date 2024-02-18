<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\Ad;

class AdsResource extends JsonResource
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
        // return array_merge([
        //     'id' => $this->id,
        //     'ads_type' => $this->ads_type,
        //     'preffered_type' => $this->preffered_type ,
        //     'c_banner_ads' => ($this->c_banner_ads) ? asset("storage/{$this->c_banner_ads}") : null,
        //     'c_banner_ads_name' =>($this->c_banner_ads_name) ?? null,
        //     'c_medium_ads' => ($this->c_medium_ads) ? asset("storage/{$this->c_medium_ads}"):null,
        //     'c_medium_ads_name' => $this->c_medium_ads_name,
        //     'c_full_ads' => ($this->c_full_ads) ? asset("storage/{$this->c_full_ads}"):null,
        //     'c_full_ads_name' => $this->c_full_ads_name,
        //     'g_ads_id' => $this->g_ads_id,
        //     'g_banner_ads' => $this->g_banner_ads,
        //     'g_medium_ads' => $this->g_medium_ads,
        //     'g_full_ads' => $this->g_full_ads,
        //     // 'published_date' => $this->published_date->format('Y-m-d'),
        //     // 'published_date_readable' => $this->published_date->diffForHumans(),
        // ], $this->additional);
        
        
         return array_merge([
            // 'id' => $this->id,
            // 'ads_type' => $this->ads_type,
            
            'preffered_type' => ($this->preffered_type=="Google")? $this->getGoogleAds():null,
     
        ], $this->additional);
    }
}
