<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Magazine;
use App\Models\Category;
use App\Models\Blog;
use App\Http\Resources\MagazineResource;
use App\Http\Resources\MagazinePdfResource;
use App\Http\Resources\BlogResource;
use App\Models\Publication;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Session;
use App\Models\User;
use App\Http\Resources\MagazineDownloadResource;
use App\Http\Resources\NewspaperDownloadResource;
use App\Http\Resources\NewspaperResource;
use App\Models\Newspaper;
use App\Models\UserBookmark;
use Illuminate\Pagination\Paginator;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class MagazineController extends Controller
{
    //
    // private $limits=2;
      protected $content_type;

      // public function de(Magazine $magazine){
      //   // dd('shiv');
      //   $magazine =$magazine;
      //   $magDatas = Magazine::where('id',$magazine->id)->first();
      //   // dd($magDatas->file_type);
      //   return view('shiv',compact('magDatas'));
      // }

    public function pdfViewer($magazine, Request $request){
        $magDatas = Magazine::query();

        $publication_id = intval($request->query('publication_id'));
        // $date = \strtotime($request->query('date'));

        if( $publication_id ) {
            $magDatas->where('publication_id', $publication_id);
        }

        if( $date = strtotime($request->query('date')) ) {
            $magDatas->whereDate('published_date', date('Y-m-d', $date));
        }

        $magDatas = ($publication_id && $date)
            ? $magDatas->first()
            : $magDatas->find($magazine);

        if( empty($magDatas) ) {
            return back()->withError('Sorry! No magazine found');
        }

        // dd($magDatas->file_type);
        $user = $this->user();
        if($user) {
            $is_sub = $user->isSubToPublication($magDatas->publication, $magDatas->published_date);

            $subscribed = $magDatas->is_free == 1
                || $is_sub === true
                || $user->hasBoughtMagazine($magDatas);

            $subscribed = !$subscribed ? '0' : '1';
        } else {
            $subscribed= ''; 
        }

        $publications = Publication::active()->get();

        $file_url = '';

        if( $subscribed !== '1' ) {
            $file_url = Storage::url( $magDatas->file_preview );
        }
        else if( $magDatas->file_converted ) {
            $file_url = Storage::url( $magDatas->file_converted );
        }
        else if( $magDatas->file ) {
            $file_url = Storage::url( $magDatas->file );
        }

        if( !empty($file_url) ) {
            $file_url = url( $file_url );
        } else {
            $file_url = url('pdf/pdf_file_doesnt_exist.pdf');
        }

        return view('pdfviewer.pdf-viewer',compact('magDatas','subscribed', 'publications', 'file_url'));
    }

    public function details(Magazine $magazine){
        $user = $this->user();

        $is_sub = null;

        if($user){
            $is_sub = $user->isSubToPublication($magazine->publication, $magazine->published_date);

            $subscribed = $magazine->is_free == 1
                || $is_sub === true
                || $user->hasBoughtMagazine($magazine);

            $subscribed = $subscribed == false ? '0' : '1';
        } else {
            $subscribed = ''; 
        }
       
        // $id=$magazine->id;
        // $magDetails= Magazine::with('category','publication')->where('id',$id)->first();
        $magDetails = $magazine;
        $post = new MagazineResource($magazine);
        #related magazines
        $related = $this->related_magazines('magazine', $magazine->category);
        $related = collect($related)->where('id','<>',$magazine->id)->where('status',1)->values()->all();
        #top stories
        $top_stories = $this->top_stories();
        $getTable=$magazine->getTable();
        $bmags = (auth()->user())?UserBookmark::where('user_id',auth()->user()->id)->where('type','magazine')->pluck('pid')->all() : [];

        return view('customer.magazines.show',compact('magDetails','bmags','related','top_stories','subscribed','getTable', 'is_sub'));

    }

    public function relatedMagazineList(Magazine $magazine){
        
        $related = $this->related_magazines('magazine', $magazine->category);
        // dd($related);
        return view('customer.magazines.relatedmagazine',compact('related'));
    }

    public function related_magazines($type, Category $category)
    {
        // dd($type);
        $magazines = $type === 'magazine' 
            ? MagazineResource::collection($category->magazines)
            : NewspaperResource::collection($category->newspapers);
        return $magazines;

    }
    public function top_stories($is_story = false)
    {
       $top_story = Blog::where('top_story',1)->active()->latest()->get();
       $top_story_data= BlogResource::collection($top_story);
       return $top_story_data;
    }
    public function listing(){
        $mags = Magazine::with('category','publication')->get();
         $catsDatas=Category::get();
        $pubsData= Publication::get();
        return view('customer.magazines.index',compact('mags','catsDatas','pubsData'));


    }

    //  public function magazinesListing(){
    //     $magsDatas= Magazine::active()->with('category','publication')->latest()->get();
    //     $magCat = $magsDatas->pluck('category_id')->unique();
    //     $magPubs = $magsDatas->pluck('publication_id')->unique();
    //     $catsDatas=Category::active()->whereIn('id',$magCat)->latest()->get();
    //     $pubsData= Publication::active()->whereIn('id',$magPubs)->latest()->get();
    //     // $bnews = (auth()->user())?UserBookmark::where('user_id',auth()->user()->id)->where('type','newspaper')->pluck('pid')->all() : [];
    //     $bmags = (auth()->user())?UserBookmark::where('user_id',auth()->user()->id)->where('type','magazine')->pluck('pid')->all() : [];
    //     return view('customer.magazines.magazines_search',compact('magsDatas','catsDatas','pubsData','bmags'));
    // }

    public function magazinesListing(Request $request)
    {

        $magsDatas= Magazine::active()
            ->with(['category','publication'])
            ->latest();

        if( $category_id = intval($request->query('category_id')) ) {
            $magsDatas->where('category_id', $category_id);
        }

        if( $publication_id = intval($request->query('publication_id')) ) {
            $magsDatas->where('publication_id', $publication_id);
        }

        if( $date = strtotime($request->query('date')) ) {
            $magsDatas->whereDate('published_date', date('Y-m-d', $date));
        }

        $magsDatas = $magsDatas->paginate(10);
        
        $catsDatas = Category::active()
            ->latest()->get();

        $pubsData = Publication::active()
            ->where('type', 'like', '%magazine%')
            ->latest()->get();

        $bmags = (auth()->user())?UserBookmark::where('user_id',auth()->user()->id)->where('type','magazine')->pluck('pid')->all() : [];
        // $catsDatas=Category::get();
        //  $pubsData= Publication::get();
        return view('customer.magazines.magazines_search',compact('magsDatas','catsDatas','pubsData','bmags'));
    }

    public function magazinesByCategory(Category $category){
    // dd($category);
        $magsDatas=Magazine::active()->with('category','publication')->where('category_id',$category->id)->latest()->get();
        $mags = Magazine::active()->with('category','publication')->latest()->get(['category_id','publication_id']);
        $magsCat = $mags->pluck('category_id')->unique();
        $magsPubs = $magsDatas->pluck('publication_id')->unique();
        $catsDatas=Category::active()->whereIn('id',$magsCat)->latest()->get();
        $pubsData= Publication::active()->whereIn('id',$magsPubs)->latest()->get();
        // $catsDatas=Category::get();
        $category_details=Category::where('id',$category->id)->first();
        // dd($mags);
        // $pubsData= Publication::get();
        // $bnews = (auth()->user())?UserBookmark::where('user_id',auth()->user()->id)->where('type','newspaper')->pluck('pid')->all() : [];
        $bmags = (auth()->user())?UserBookmark::where('user_id',auth()->user()->id)->where('type','magazine')->pluck('pid')->all() : [];
       return view('customer.magazines.magazines_search',compact('magsDatas','catsDatas','category_details','pubsData','category','bmags'));
    }


    public function searchFilter(Request $request){
        // dd($request->all());
        $magazines = Magazine::active()->latest();
        if($request->has('publication_id') && !empty($request->get('publication_id'))){
           $magazines = $magazines->where('publication_id',$request->publication_id);  
        }
        if($request->has('from') || $request->has('to')){
            if($request->from){
                $from = Carbon::parse($request->from)->format('Y-m-d');
                // DB::enableQueryLog();
                $magazines = $magazines->whereBetween('published_date',[$from, now()->format('Y-m-d')]);
                // dd(count($magazines));
            }
        }
        $magazines = $magazines->get();
        // $catsDatas=Category::get();
        // $pubsData= Publication::get();
        return response()->json($magazines);
    }


    public function magazinesByCat(Category $category){
    // dd($category);
        $mags=Magazine::with('category','publication')->where('category_id',$category->id)->get();
        $catsDatas=Category::get();
        $category_details=Category::where('id',$category->id)->first();
        // dd($mags);
        $pubsData= Publication::get();
       return view('customer.magazines.index',compact('mags','catsDatas','category_details','pubsData'));
    }


    public function searchFilterMagazine(Request $request){
        // dd($request->all());
        $magazines = Magazine::active()->latest();
        if($request->has('category_id') && !empty($request->get('category_id'))){
            $magazines = $magazines->where('category_id',$request->category_id);  
         }
        if($request->has('publication_id') && !empty($request->get('publication_id'))){
           $magazines = $magazines->where('publication_id',$request->publication_id);  
        }
        if($request->has('from') || $request->has('to')){
            if($request->from){
                $from = Carbon::parse($request->from)->format('Y-m-d');
                // DB::enableQueryLog();
                $magazines = $magazines->whereBetween('published_date',[$from, now()->format('Y-m-d')]);
                // dd(count($magazines));
            }
        }
        $magazines = $magazines->get();
        return response()->json($magazines);
    }

     public function markFileAsDownloaded($content_id,$subscribed)
    {
        // dd($content_id);
        $user = $this->user();
        $file = Magazine::findOrFail($content_id);

        return redirect()->route('magazine.pdfviewer', ['magazine' => $file->id]);

        $content = $this->get_content_instance($content_id);
        // dd($content);

        if( ! $content ) {
            // return ApiResponse::notFound(
            //     $this->get_content_type(true) . __(' Not Found')
            // );
            return redirect()->back()->with('error','Data not found');
        }

        if($subscribed == "1"){
        DB::beginTransaction();
        try {
            // reset counter
        if($file->file !="" && $file->file_type== "pdf"){
            if( ! $user->download_date ||
                ! $user->download_date->isToday() ) {

                $user->download_date = date('Y-m-d H:i:s');
                $user->download_counter = 1;

                $user->update();
            }
            else {
                // dd($user->download_counter);
                DB::enableQueryLog();
                $down_count = DB::table('user_downloads')->where('user_id',$user->id)->where('file_id',$content->id)->where('file_type',$this->get_content_type())->get();
                // dd($down_count);
                // dd(DB::getQueryLog());
                if(count($down_count) == "1"){
                return redirect()->back()->with("error","$content->title already downloaded please preview");

                }
                $user->increment('download_counter');
            }

            DB::table('user_downloads')->insert([
                'user_id' => $user->id,
                'file_type' => $this->get_content_type(),
                'file_id' => $content->id,
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }else{
            if($file->file_converted!="" && $file->file_preview!=""){

            }else{
            return redirect()->back()->with("error","Invalid Pdf File");

            }
        }

            DB::commit();
            if($file->file != '' && $file->file_type =="pdf"){
                return Storage::download("public/".$file->file,$content->title);

            }
            if($file->file_converted !="" && $file->file_type =="epub"){
                return Storage::download("public/".$file->file_converted,$content->title);

            }

            else{
            return redirect()->back()->with("error","file does not exist ");

            }

            return redirect()->back()->withSuccess("$content->title downloaded successfully");
           

        } catch( \Exception $e ) {
            logger($e->getMessage());
        }
    }else{
        return redirect()->back()->with("error","You have to subscribe first ");

    }

        DB::rollBack();

        return redirect()->back()->with('error','Something went wrong');
    }

    // @helper - magazine or news
    protected function get_content_instance($content_id)
    {
        // dd($content_id);
        return $this->get_content_type() === 'magazine'
            ? \App\Models\Magazine::find($content_id)
            : \App\Models\Newspaper::find($content_id);
    }

    // @helper - magazine or news
    protected function get_content_type($uppercase = false)
    {
        // dd()
        if( ! $this->content_type ) {
            $this->content_type = request()->is('*/newspapers/*')
                ? 'newspaper'
                : 'magazine';
        }

        return $uppercase
            ? \ucwords($this->content_type)
            : $this->content_type;
    }

    public function download(Request $request){
        $content_id = $request->magsid;
        $subscribed = $request->subscribed;
        return $this->markFileAsDownloaded($content_id,$subscribed);
    }

    public function user_downloads(Request $request)
    {
        echo 'e';
        $user = $this->user();

        $magazinesData = $user->magazine_downloads;

        $newspapersData = $user->newspaper_downloads;

        return view('customer.home.downloads.list',compact('magazinesData','newspapersData'));
    }

    public function my_purchases(Request $request)
    {
        $user = $this->user();

        $type = $request->get('type');
        $type = \in_array($type, ['magazine', 'newspaper']) ? $type : 'magazine';

        if( $type == 'magazine' ) {
            
            $papers = $user->bought_magazines()
                ->where('user_onetime_purchases.pay_status', 1)
                ->latest()
                ->paginate(10);
        }
        else {
            $publications = $user->getSubscribedPublications();
            $subpaper = collect();
            foreach( $publications as $publication ) {
                $papers = Newspaper::where('publication_id',$publication->id)->whereDate('newspapers.published_date', '>=', $publication->puchased_at->format('Y-m-d'))->get();
                $subpaper = $subpaper->merge($papers);
            }
            $papers1 = $user->bought_newspapers()
                ->where('user_onetime_purchases.pay_status', 1)
                ->get();
                $papers = $subpaper->merge($papers1);
                $papers = $this->paginate($papers);
        }

        return view('customer.my-purchases',compact('papers'));
    }
    
    
    public function paginate($items, $perPage = 10, $page = null, $options = [])
    {
        $page = $page ?: (Paginator::resolveCurrentPage() ?: 1);
        $items = $items instanceof Collection ? $items : Collection::make($items);
        return (new LengthAwarePaginator($items->forPage($page, $perPage), $items->count(), $perPage, $page, $options))->withPath('/user/my_purchases?type=newspaper');
    }


}
