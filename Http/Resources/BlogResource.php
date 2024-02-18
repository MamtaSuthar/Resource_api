<?php

namespace App\Http\Resources;

use App\Models\UserBookmark;
use Illuminate\Http\Resources\Json\JsonResource;

class BlogResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        if( strpos($this->thumbnail_image, 'http') !== 0 ) {
            $this->thumbnail_image = ! empty($this->thumbnail_image)
                ? asset("storage/{$this->thumbnail_image}")
                : '';
        }
        if( strpos($this->content_image, 'http') !== 0 ) {
            $this->content_image = ! empty($this->content_image)
                ? asset("storage/{$this->content_image}")
                : '';
        }
        if( strpos($this->slider_image, 'http') !== 0 ) {
            $this->slider_image = ! empty($this->slider_image)
                ? asset("storage/{$this->slider_image}")
                : '';
        }

        $user = auth()->user() ?? auth('api')->user();

        // return parent::toArray($request);
        return array_merge([
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'blog_category_id' => $this->blog_category_id ?? null,
            'thumbnail_image' => $this->thumbnail_image,
            'content_image' => $this->content_image,
            'thumbnail_image' => $this->thumbnail_image,
            'slider_image' => $this->slider_image,
            'promoted' => $this->promoted ?? null,
            'top_story' => $this->top_story ?? null,
            'short_description' => $this->short_description ?? null,
            'content' => $user ? ($this->content ?? null) : null,
            // 'visit_count' => $this->visit_count ?? null,  
            // 'status' => $this->status ?? null,  
            'blog_category' => new BlogCategoryResource($this->blog_category),
            'tags' => TagResource::collection($this->tags),
            'date' => $this->created_at ? $this->created_at->format('d-m-Y') : null,
            'date_readable' => $this->created_at ? $this->created_at->diffForHumans() : null,
            'bookmark' => $user ? UserBookmark::where('user_id',$user->id)->where('pid',$this->id)->whereIn('type',['popular_content','top_story'])->exists() : false,
            // 'type' =>($this->promoted)?'popular_content':(($this->top_story)?'top_story':''),
            'type'=>(session()->get('type'))?session()->get('type'):(($this->promoted)?'popular_content':(($this->top_story)?'top_story':'')),
            'needs_subscription' => false
            
        ], $this->additional);
 
    }
}
