<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;use App\Models\Newspaper;
use App\Models\Category;
use App\Models\Publication;
use App\Models\Magazine;
use App\Models\Tag;
use App\Models\UserInfo;
use Illuminate\Support\Str;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PDF;
use App\Http\Resources\BlogCategoryResource;
use App\Models\Blog;
use App\Models\UserBookmark;


class HomeSearchController extends Controller
{
    //
   /*  public function getMagzineOrNewsByQuery($arr,$search){
        $json = [];

        // $magazines = Magazine::active()->where('title','LIKE','%'.$search.'%')->latest()->get($arr);
        $magazines = Magazine::where('title','LIKE','%'.$search.'%')->latest()->get($arr);
        if($magazines->isNotEmpty()){
            foreach ($magazines as $key => $value) {
                $jdata = [];
                foreach ($arr as $val) {
                    
                    if(in_array($val,['thumbnail_image','cover_image'])){
                        $jdata[$val] = asset("storage/{$value->$val}");
                    }else{
                        $jdata[$val] = $value->$val;
                    }
                }
                $jdata['currency'] = auth()->user()->my_currency;
                $jdata['type'] = 'magazine';
                $json[] = $jdata;
            }
        }
        // $newspaper = Newspaper::active()->where('title','LIKE','%'.$search.'%')->latest()->get($arr);
        $newspaper = Newspaper::where('title','LIKE','%'.$search.'%')->latest()->get($arr);
        
        if($newspaper->isNotEmpty()){
            foreach ($newspaper as $key => $value) {
                $jdata = [];
                foreach ($arr as $val) {
                    if(in_array($val,['thumbnail_image','cover_image'])){
                        $jdata[$val] = asset("storage/{$value->$val}");
                    }else{
                        $jdata[$val] = $value->$val;
                    }
                }
                $jdata['currency'] = auth()->user()->my_currency;
                $jdata['type'] = 'newspaper';
                $json[] = $jdata;
            }
        }
        // dd($json);
        return $json;
    }

    public function homeSearching(Request $request){
        if($request->has('search') && $request->search){
            $search = $request->search;
            $getArr = ['id','title','price','thumbnail_image','cover_image'];
            $data = $this->getMagzineOrNewsByQuery($getArr,$search);
            $datacount = count($data);
            // dd($data);
            $htmlData =view('customer.home.search.search', compact('data'))->render();
            return $htmlData;
            // return view('customer/home/search/home_search',compact('data'));
        }
    }*/
    public function getMagzineOrNewsByQuery2($arr,$search){
        $json = [];

        $magazines = Magazine::active()->whereRaw("BINARY `title` like '%$search%'")->latest()->get($arr);
        if($magazines->isNotEmpty()){
            foreach ($magazines as $key => $value) {
                $jdata = [];
                foreach ($arr as $val) {
                    
                    if(in_array($val,['thumbnail_image','cover_image'])){
                        $jdata[$val] = asset("storage/{$value->$val}");
                    }else{
                        $jdata[$val] = $value->$val;
                    }
                }
                $jdata['currency'] = auth()->user()->my_currency ?? null;
                $jdata['type'] = 'magazine';
                $json['magazines'][] = $jdata;
            }
        }else{
            $json['magazines'] = [];
        }
        $newspaper = Newspaper::active()->whereRaw("BINARY `title` like '%$search%'")->latest()->get($arr);
        if($newspaper->isNotEmpty()){
            foreach ($newspaper as $key => $value) {
                $jdata = [];
                foreach ($arr as $val) {
                    if(in_array($val,['thumbnail_image','cover_image'])){
                        $jdata[$val] = asset("storage/{$value->$val}");
                    }else{
                        $jdata[$val] = $value->$val;
                    }
                }
                $jdata['currency'] = auth()->user()->my_currency ?? null;
                $jdata['type'] = 'newspaper';
                $json['newspaper'][] = $jdata;
            }
        }else{
            $json['newspaper'] = [];
        }

        $blog = Blog::active()->whereRaw("BINARY `title` like '%$search%'")->latest()->get();
        if($blog->isNotEmpty()){
            foreach ($blog as $key => $value) {
                $jdata = $value;
                if(strpos("$jdata->content_image","https")!==false){
                $jdata['content_image'] = asset($jdata->content_image);

                }else{
                $jdata['content_image'] =asset("storage/".$jdata->content_image);

                }
                if(strpos("$jdata->slider_image","https")!==false){
                $jdata['slider_image'] = asset($jdata->slider_image);

                }else{
                $jdata['slider_image'] =asset("storage/".$jdata->slider_image);

                }
                $jdata['blog_category'] = [new BlogCategoryResource($jdata->blog_category)];
                $jdata['date'] = $jdata->created_at->format('d-m-Y');
                $jdata['currency'] = auth()->user()->my_currency ?? null;
                if($value->promoted==1){
                    $jdata['type'] = 'popular_content';
                    $json['popular_content'][] = $jdata;
                }if($value->top_story==1){
                    $jdata['type'] = 'top_story';
                    $json['top_story'][] = $jdata;
                }
                
            }
        }
        $json['popular_content'] = (isset($json['popular_content']))?$json['popular_content']:[];
        $json['top_story'] = (isset($json['top_story']))?$json['top_story']:[];
        return $json;
    }
    
    public function homeSearching(Request $request)
    {
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $getArr = ['id', 'title', 'price', 'thumbnail_image', 'cover_image'];
            $data = $this->getMagzineOrNewsByQuery2($getArr, $search);
            $datacount = count($data);
            // dd($data);
            $bnews = (auth()->user())?UserBookmark::where('user_id',auth()->user()->id)->where('type','newspaper')->pluck('pid')->all() : [];
            $bmags = (auth()->user())?UserBookmark::where('user_id',auth()->user()->id)->where('type','magazine')->pluck('pid')->all() : [];

            $btopstory = (auth()->user())?UserBookmark::where('user_id',auth()->user()->id)->where('type','top_story')->pluck('pid')->all() : [];
            $bpromoted = (auth()->user())?UserBookmark::where('user_id',auth()->user()->id)->where('type','popular_content')->pluck('pid')->all() : [];
            $htmlData =view('customer.home.search.search', compact('data','btopstory','bpromoted','bnews','bmags'))->render();
            return $htmlData;
        } 
        // else {
        //     return ApiResponse::ArraynotFound('Enter title of Magzines or Newspaper');
        // }
    }
}
