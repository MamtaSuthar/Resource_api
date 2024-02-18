<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Tag;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\MagazineResource;
use App\Http\Resources\NewspaperResource;
use App\Http\Resources\TagResource;
class TagsController extends Controller
{
    //
    public function details(Tag $tag){
        $id = $tag->id;
        $tagName=$tag->name;
        $tag_datas=Tag::findorFail($id);
        $tags = new TagResource($tag_datas);
        $tags->additional['magazines'] = MagazineResource::collection($tag_datas->magazines);
        $tags->additional['newspapers'] = NewspaperResource::collection($tag_datas->newspapers);
        // dd($tags);
        return view('customer.tags.show',compact('tags','tagName'));


    }
    public function listing(){
        $allTags=Tag::with('magazines','newspapers')->get();
        // $mags=[];
        // $news=[];
        $mags_tags_datas=[];
        $news_tags_datas=[];
        $mags_news_tags_datas=[];
        foreach($allTags as $tags){

            foreach($tags->magazines as $mags_tags){
                $mags= new MagazineResource($mags_tags);
                array_push($mags_tags_datas, $mags);
                
            }
            foreach($tags->newspapers as $news_tags){
                $news= new NewspaperResource($news_tags);
                array_push($news_tags_datas, $news);

            }
            $mags_news_tags_datas =array('magazines'=>$mags_tags_datas,'newspapers'=>$news_tags_datas);
        }
        return view('customer.tags.index',compact('mags_news_tags_datas'));


    }
}
