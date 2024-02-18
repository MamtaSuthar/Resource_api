<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Tag;
use App\Api\ApiResponse;
use App\Http\Resources\TagResource;
use App\Http\Resources\MagazineResource;
use App\Http\Resources\NewspaperResource;


class TagController extends Controller
{
    //
    private $limits = 20;

    public function tag_details(Tag $tag,$id){
        /*
        $abc=Tag::findorFail($id);
        $tags = new TagResource($abc);
        $tags_magazines = MagazineResource::collection($tags->magazines);
        $tags_newspapers = NewspaperResource::collection($tags->newspapers);
        // dd($tags->magazines);
        // $list = $tags->magazines->merge($tags->newspapers)->paginate($this->limits);
        $list = $tags_magazines->merge($tags_newspapers)->paginate($this->limits);
        return ApiResponse::ok('Category',$list);
        */
        // dd($tag);
        $tag_datas=Tag::findorFail($id);
        $tags = new TagResource($tag_datas);
        $tags->additional['magazines'] = MagazineResource::collection($tag_datas->magazines);
        $tags->additional['newspapers'] = NewspaperResource::collection($tag_datas->newspapers);
        return ApiResponse::ok('Tag Details Data',$tags);

    }
    public function tags_listing(Request $request){


        /*
        $all_datas=Tag::with('magazines','newspapers')->get();
        // dd(($all_datas));
        $resData = [];
        foreach($all_datas as $tags){
            $list = $tags->magazines->merge($tags->newspapers);
            $resData = $list->merge($resData);
        }
        // dd($resData);
        $magNewsData = $resData->paginate($this->limits)->through( function($data){
            if($data->count() > 0){
                return [
                    'id' => $data->id,
                    'title' => $data->title,
                    'short_description' => $data->short_description ?? null,
                    'description' =>$data->description ?? null,
                    'price' =>$data->price ?? null,
                    'cover_image' => asset("storage/{$data->cover_image}"),
                    'thumbnail_image' => asset("storage/{$data->thumbnail_image}"),
                    'tags' => TagResource::collection($data->tags),

                    'published_date' => $data->published_date->format('Y-m-d'),
                    'published_date_readable' => $data->published_date->diffForHumans()

                ];
            }
        });
        return ApiResponse::ok('All Tags Listing', $magNewsData);
        */

        $allTags=Tag::with('magazines','newspapers')->get();
        // $mags=[];
        // $news=[];
        $mags_tags_datas=[];
        $news_tags_datas=[];
        $mags_news_tags_datas = [
            'magazines' => [],
            'newspapers' => []
        ];
        foreach($allTags as $tags){
            $collection =  \App\Http\Resources\MagazineResource::collection($tags->magazines)
                ->toArray($request);

            $mags_news_tags_datas['magazines']= \array_merge(
                $mags_news_tags_datas['magazines'],
                $collection
            );

            $collection =  \App\Http\Resources\NewspaperResource::collection($tags->newspapers)
                ->toArray($request);

            $mags_news_tags_datas['newspapers'] = \array_merge(
                $mags_news_tags_datas['newspapers'],
                $collection
            );
        }
        return ApiResponse::ok('All Tags Listing', $mags_news_tags_datas);
        
    }

}
