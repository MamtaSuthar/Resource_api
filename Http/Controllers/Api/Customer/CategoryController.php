<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Api\ApiResponse;
use App\Http\Resources\BlogResource;
use App\Http\Resources\MagazineResource;
use App\Http\Resources\NewspaperResource;
use App\Http\Resources\TagResource;
use App\Models\Blog;
use App\Models\Magazine;
use App\Models\Newspaper;
use App\Traits\CommonTrait;
use Illuminate\Support\Facades\Validator;

class CategoryController extends Controller
{
    use CommonTrait;
    private $limits = 20;

    public function topics_to_follow(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => ['nullable', 'in:magazine,newspaper,story'],
        ]);

        if($validator->fails()){
            return $this->validation_error_response($validator);
        }

        $content_type = $request->get('type') ?? 'magazine';

        if( $user = auth()->user() ) {
            $topics = $this->getUserPreferances($user->id);
        } else {
            $topics = [];
        }

        if( !empty($topics) ) {
            $categories = Category::whereIn('id',$topics)->active()->latest()->get();
        } else {
            $categories  = Category::active()->latest()->get();
        }

        $data = [
            'magazines' => null,
            'newspapers' => null,
            'stories' => null,
            'categories' => $categories
        ];

        $content = null;
        $key = '';
        $collection = MagazineResource::class;

        switch( $content_type ) {
            case 'magazine':
                $content = Magazine::query();
                $key = 'magazines';
                break;
            case 'newspaper':
                $content = Newspaper::query();
                $key = 'newspapers';
                $collection = NewspaperResource::class;
                break;
            default:
                $content = Blog::query();
                $key = 'stories';
                $collection = BlogResource::class;
                break;

        }

        if( $category_id = intval($request->get('category_id')) ) {
            $content = $content->byCategory($category_id);
        }

        if( array_key_exists($key, $data) ) {
            $data[$key] = $collection::collection($content->paginate(15)->getCollection());
        }

        return ApiResponse::ok('Popular Category Details of Magazines, Newspapers, & Stories', $data);
    }

    #function used for fetching popular categories details with magazines and newspapers
    public function filter_category_magazines_newspapers_data(Category $category){

        $category_magazines = new CategoryResource($category);
        $category_magazines->additional['magazines'] = MagazineResource::collection($category->magazines->where('status', 1));
        $category_magazines->additional['newspapers'] = NewspaperResource::collection($category->newspapers->where('status', 1));
        session()->put('type', 'top_story');
        $category_magazines->additional['stories'] = BlogResource::collection($category->stories->where('top_story',1));
        # Get Categories
        $categories = '';
        $topics = $this->getUserPreferances(auth()->user()->id);
        if(!empty($topics)){
            $categories = Category::whereIn('id',$topics)->active()->latest()->get();
        }else{
            $categories  = Category::active()->latest()->get();
        }
        $category_magazines->additional['categories'] = CategoryResource::collection($categories);

        return ApiResponse::ok('Popular Category Details of Magazines & Newspapers', $category_magazines);

        /*$category_magazines = new CategoryResource($category);
        $magazines = MagazineResource::collection($category->magazines);
        $newspapers = NewspaperResource::collection($category->newspapers);
        $list = $magazines->merge($newspapers)->paginate($this->limits);
        return ApiResponse::ok('Popular Category Details of Magazines & Newspapers', $list);*/
    }

    #function used for fetching all categories listing with magazines and newspapers
    public function all_categories_data(Request $request, Category $category){

        /*
        $all=Category::with('magazines','newspapers')->get();
        $resData = [];
        foreach($all as $category){
            $list = $category->magazines->merge($category->newspapers);
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
                    'published_date' => $data->published_date->format('Y-m-d'),
                    'published_date_readable' => $data->published_date->diffForHumans()

                ];
            }
        });
        return ApiResponse::ok('All Categories Listing', $magNewsData);
        */
        /*
        $allCats=Category::with('magazines','newspapers')->get();
        foreach($allCats as $category){
            // print_r($category);
            $category_magazines = new CategoryResource($category);
            $category_magazines->additional['magazines'] = MagazineResource::collection($category->magazines);
            $category_magazines->additional['newspapers'] = NewspaperResource::collection($category->newspapers);
            $all_categories[]=$category_magazines;
 
        }       
        return ApiResponse::ok('All Categories Listing', $all_categories);
        */ 
        $topics = $this->getUserPreferances(auth()->user()->id);
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
        return ApiResponse::ok('All Categories Listing', $mags_news_datas);
    }

    public function filter_category_newspapers_data(Category $category){
        $category_newspapers=new CategoryResource($category);
        $category_newspapers->additional['newspapers'] = NewspaperResource::collection($category->newspapers()->active()->get());
        return ApiResponse::ok('Category Detail of  Newspapers', $category_newspapers);
    }
}
