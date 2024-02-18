<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Category;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\MagazineResource;
use App\Http\Resources\NewspaperResource;
use App\Http\Resources\TagResource;
use App\Traits\CommonTrait;
use App\Models\Magazine;
use App\Http\Resources\BlogResource;
use App\Models\UserBookmark;

class CategoryController extends Controller
{
    //
     use CommonTrait;

    public function details(Category $category){
        // dd($category->name);
          # Get Categories
        $categories = '';
        $topics = $this->getUserPreferances(!empty(auth()->user())?auth()->user()->id:0);
        // dd($topics);
        if(!empty($topics)){
            $categories = Category::whereIn('id',$topics)->active()->latest()->get();

        }else{
            $categories  = Category::active()->latest()->get();
        }
            // dd($categories);
        $category_magazines = new CategoryResource($category);
        $category_magazines->additional['magazines'] = MagazineResource::collection($category->magazines->where('status', 1));
        $category_magazines->additional['newspapers'] = NewspaperResource::collection($category->newspapers->where('status', 1));
        session()->put('type', 'top_story');
        $category_magazines->additional['stories'] = BlogResource::collection($category->stories->where('top_story',1));
        $catName = $category->name ?? null;
        // dd($category_magazines);
         $bnews = (auth()->user())?UserBookmark::where('user_id',auth()->user()->id)->where('type','newspaper')->pluck('pid')->all() : [];
        $bmags = (auth()->user())?UserBookmark::where('user_id',auth()->user()->id)->where('type','magazine')->pluck('pid')->all() : [];

        $btopstory = (auth()->user())?UserBookmark::where('user_id',auth()->user()->id)->where('type','top_story')->pluck('pid')->all() : [];
        $bpromoted = (auth()->user())?UserBookmark::where('user_id',auth()->user()->id)->where('type','popular_content')->pluck('pid')->all() : [];
        return view('customer.categories.show',compact('category_magazines','catName','categories','category','bmags','bnews','btopstory','bpromoted'));
        
    }
    public function listing(Request $request, Category $category){
        /*  if($request->id){
            $allCats=Category::with('magazines','newspapers')->where('id',$request->id)->get();
            // dd($allCats);
        }else{
         $allCats=Category::with('magazines','newspapers','stories')->active()->latest()->get();
         // dd($allCats);
        }

      $mags_datas=[];
        $news_datas=[];
        $mags_news_datas = [
            'magazines' => [],
            'newspapers' => [],
            'stories'=>[],
            'categories' => []
        ];
        foreach($allCats as $category){
            $collection =  \App\Http\Resources\MagazineResource::collection($category->magazines()->active()->get())
                ->toArray($request);

            $mags_news_datas['magazines']= \array_merge(
                $mags_news_datas['magazines'],
                $collection
            );

            $collection =  \App\Http\Resources\NewspaperResource::collection($category->newspapers()->active()->get())
                ->toArray($request);

            $mags_news_datas['newspapers'] = \array_merge(
                $mags_news_datas['newspapers'],
                $collection
            );
            session()->put('type', 'top_story');
            $collection =  \App\Http\Resources\BlogResource::collection($category->stories()->active()->where('top_story',1)->latest()->get())->toArray($request);


            $mags_news_datas['stories'] = \array_merge(
                $mags_news_datas['stories'],
                $collection
            );

            # Get Categories
            $categories = '';
            $topics = $this->getUserPreferances(!empty(auth()->user())?auth()->user()->id:0);
            // dd($topics);
            if(!empty($topics)){
                $categories = Category::whereIn('id',$topics)->active()->latest()->get();

            }else{
                $categories  = Category::active()->latest()->get();
            }
            
        }
        $collection =  \App\Http\Resources\CategoryResource::collection($categories)->toArray($request);
        // dd($collection);
        $mags_news_datas['categories'] = \array_merge(
            $mags_news_datas['categories'],
            $collection
        );
        */

        #29
         $topics = $this->getUserPreferances(!empty(auth()->user())?auth()->user()->id:0);
        if(!empty($topics)){
            $allCats=Category::whereIn('id',$topics)->with('magazines','newspapers')->get();
        }else{
            $allCats  = Category::active()->latest()->get();
        }
        
        // $mags=[];
        // $news=[];
        $mags_datas=[];
        $news_datas=[];
        $mags_news_datas = [
            'magazines' => [],
            'newspapers' => [],
            'stories' =>[],
            'categories' => []
        ];
        foreach($allCats as $category){
            $collection =  \App\Http\Resources\MagazineResource::collection($category->magazines()->active()->get())
                ->toArray($request);

            $mags_news_datas['magazines']= \array_merge(
                $mags_news_datas['magazines'],
                $collection
            );

            $collection =  \App\Http\Resources\NewspaperResource::collection($category->newspapers()->active()->get())
                ->toArray($request);

            $mags_news_datas['newspapers'] = \array_merge(
                $mags_news_datas['newspapers'],
                $collection
            );
            session()->put('type', 'top_story');
            $collection = \App\Http\Resources\BlogResource::collection($category->stories()->active()->where('top_story',1)->latest()->get())->toArray($request);

            $mags_news_datas['stories'] = \array_merge(
                $mags_news_datas['stories'],
                $collection
            );
            # Get Categories
            
            
            if(!empty($topics)){
                $categories = Category::whereIn('id',$topics)->active()->latest()->get();
            }else{
                $categories  = Category::active()->latest()->get();
            }
            
        }
        $collection =  \App\Http\Resources\CategoryResource::collection($categories)->toArray($request);

            $mags_news_datas['categories'] = \array_merge(
                $mags_news_datas['categories'],
                $collection
            );

        $bnews = (auth()->user())?UserBookmark::where('user_id',auth()->user()->id)->where('type','newspaper')->pluck('pid')->all() : [];
        $bmags = (auth()->user())?UserBookmark::where('user_id',auth()->user()->id)->where('type','magazine')->pluck('pid')->all() : [];

        $btopstory = (auth()->user())?UserBookmark::where('user_id',auth()->user()->id)->where('type','top_story')->pluck('pid')->all() : [];
        $bpromoted = (auth()->user())?UserBookmark::where('user_id',auth()->user()->id)->where('type','popular_content')->pluck('pid')->all() : [];

      
        // dd($mags_news_datas);
        return view('customer.categories.index',compact('mags_news_datas','bmags','bnews','bpromoted','btopstory'));
    }
}
