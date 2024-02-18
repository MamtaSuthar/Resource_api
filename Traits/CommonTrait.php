<?php
namespace App\Traits;

use App\Http\Resources\BlogCategoryResource;
use App\Models\Blog;
use App\Models\Newspaper;
use App\Models\Category;
use App\Models\Publication;
use App\Models\Magazine;
use App\Models\Tag;
use App\Models\UserInfo;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PDF;

trait CommonTrait 
{
    public function getbookmarksCommondata($data,$type){
        $ndata['id'] = $data->id;
        $ndata['title'] = $data->title;
        $ndata['price'] = $data->price;
        $ndata['cover_image'] = asset("storage/{$data->cover_image}");
        $ndata['currency'] = auth()->user()->my_currency;
        $ndata['content_image'] = '';
        $ndata['blog_category'] = [];
        $ndata['date'] = '';
        $ndata['bookmark_type'] = $type;
        return $ndata;
    }
    public function getBlogsdata($data,$type){
        $ndata['id'] = $data->id;
        $ndata['title'] = $data->title;
        $ndata['price'] = '';
        $ndata['cover_image'] ='';
        $ndata['currency'] = '';
        if(strpos("$data->content_image","https")!==false){
        $ndata['content_image'] = asset($data->content_image);

        }else{
        $ndata['content_image'] =asset("storage/".$data->content_image);

        }
        $ndata['blog_category'] = [new BlogCategoryResource($data->blog_category)];
        $ndata['date'] = $data->created_at->format('d-m-Y');
        $ndata['bookmark_type'] = $type;
        return $ndata;
    }
    public function getUserPreferances($userid){
        $ft = UserInfo::where('user_id',$userid)->first()->favourite_topics ?? [];
        $topics = !empty($ft)?json_decode($ft):[];
        return $topics;
    }
    public function getMagzineOrNewsByQuery($arr,$search){
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
                $jdata['currency'] = auth()->user()->my_currency;
                $jdata['type'] = 'magazine';
                $json[] = $jdata;
            }
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
                $jdata['currency'] = auth()->user()->my_currency;
                $jdata['type'] = 'newspaper';
                $json[] = $jdata;
            }
        }
        return $json;
    }

    public function getMagzineOrNewsByQuery2($arr,$search){
        $json = [];

        $user = auth()->user();

        $magazines = Magazine::active()->where('title','LIKE','%'.$search.'%')->latest()->get($arr);
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
                $jdata['currency'] = $user ? $user->my_currency : 'GHS';
                $jdata['type'] = 'magazine';
                $json['magazines'][] = $jdata;
            }
        }else{
            $json['magazines'] = [];
        }
        $newspaper = Newspaper::active()->where('title','LIKE','%'.$search.'%')->latest()->get($arr);
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
                $jdata['currency'] = $user ? $user->my_currency : 'GHS';
                $jdata['type'] = 'newspaper';
                $json['newspaper'][] = $jdata;
            }
        }else{
            $json['newspaper'] = [];
        }

        $blog = Blog::active()->where('title','LIKE','%'.$search.'%')->latest()->get();
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
                $jdata['currency'] = $user ? $user->my_currency : 'GHS';
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

    public function getPublicationsByCategory($catid,$type){
        $category = Category::find($catid);
        if(!empty($category)){
            $json = [];
            if($type=="newspaper"){
                $pubs = Newspaper::where('category_id',$catid)->active()->pluck('publication_id');
                $json[]= $pubs->unique()->join(",");
            }elseif ($type=="magazine") {
                $pubs = Magazine::where('category_id',$catid)->active()->pluck('publication_id');
                $json[] = $pubs->unique()->join(",");
            }
            if(!empty($json)){
                if(collect($json)->count()>1){
                    $json = collect($json)->map(function($item){
                        return $item;
                    })->join(",");
                }
                $pubids = explode(',',collect($json)->join(","));
                $publication = Publication::whereIn('id',$pubids)->active()->get(['id','name','type']);
                return $publication;
            }else{
                return [];
            }
        }else{
            if($catid==0){
                $pubs = [];
                if($type=="newspaper"){
                    $pubs[] = Newspaper::active()->pluck('publication_id')->unique()->join(",");
                }elseif ($type=="magazine") {
                    $pubs[] = Magazine::active()->pluck('publication_id')->unique()->join(",");
                }
                // $pubs[] = Newspaper::active()->pluck('publication_id')->unique()->join(",");
                // $pubs[] = Magazine::active()->pluck('publication_id')->unique()->join(",");

                $cates = collect($pubs)->join(",");
                $pubids = explode(',',$cates);
                $publication = Publication::whereIn('id',$pubids)->active()->get(['id','name','type']);
                return $publication;
            }else{
                return [];
            }
            
        }
    }
    private function instagramApi(){
        $fields = "id,caption,media_type,media_url,permalink,thumbnail_url,timestamp,username";
        // $token = "IGQVJYd1RPWVdWd0ZAsZAlMxTHVnZATV0LTJ2akZAPMjQ4UWs3OXBSdTJyNncyNDU1LXJ2TXVJNExmVWVlekh1dDRWRHhwLVl2dVQwQVpnV092dm5nZAW1OMWRFZAUVGV054dFhhSkNVZAGRraXJlQ2Mta3l4NwZDZD";
        $token = config('app.instragram_api');

        if( empty($token) ) return [];

        $limit = 15;

        $json_feed_url="https://graph.instagram.com/me/media?fields={$fields}&access_token={$token}";

        $Idata = null;

        try {
            $ch = curl_init();
        
            curl_setopt($ch, CURLOPT_URL, $json_feed_url);
            curl_setopt($ch, CURLOPT_POST, false);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true );
            curl_setopt($ch,CURLOPT_ENCODING , "gzip");

            $insta_result = curl_exec($ch);
            $Idata = json_decode($insta_result);
            curl_close($ch);
        } catch(\Exception $e) {
            logger($e->getMessage());
        }

        return $Idata ?? [];
    }
    private function instaDataWeb(){
        try {
            $data = $this->instagramApi();

            if( is_object($data) && isset($data->data) ) {
                return $data->data;
            }
        } catch(\Exception $e) {
            logger($e->getMessage());
        }

        return [];
    }

    public function checkFilterValuesforReport($request,$query){
        $subsc_type = $request->get('status');
        if(in_array($subsc_type, ['0','1'])){
            $query->where('status',$subsc_type);
        }
        if($email = $request->get('email') ){
            $query->where('email','LIKE',"%".$email."%");
        }
        if($refer_code = $request->get('refer_code') ){
            $query->where('refer_code','LIKE',"%".$refer_code."%");
        }
        if($country = $request->get('country') ){
            $query->where('country',$country);
        }
        if( $starts_at = \strtotime($request->query('starts_at')) ) {
            $query->whereDate('created_at', '>=', date('Y-m-d H:i:s', $starts_at));
        }
        if( $starts_at = \strtotime($request->query('ends_at')) ) {
            $query->whereDate('created_at', '<=', date('Y-m-d H:i:s', $starts_at));
        }
        return $query;
    }
}