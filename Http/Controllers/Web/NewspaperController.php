<?php

namespace App\Http\Controllers\Web;
use App\Api\ApiResponse;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Newspaper;
use App\Models\Category;
use App\Models\Blog;
use App\Http\Resources\NewspaperResource;
use App\Http\Resources\MagazinePdfResource;
use App\Http\Resources\BlogResource;
use App\Http\Resources\MagazineResource;
use App\Models\Magazine;
use App\Models\Publication;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Session;
use App\Models\User;
use App\Models\UserBookmark;

class NewspaperController extends Controller
{
    //
    protected $content_type;
    
    public function pdfViewer($newspaper, Request $request){
       
        $newsDatas = Newspaper::query();

        $publication_id = intval($request->query('publication_id'));
        $date = strtotime($request->query('date'));

        if( $publication_id ) {
            $newsDatas->where('publication_id', $publication_id);
        }

        if( $date ) {
            $newsDatas->where('published_date', date('Y-m-d', $date));
        }

        $newsDatas = ($publication_id || $date)
            ? $newsDatas->first()
            : $newsDatas->find($newspaper);

        if( empty($newsDatas) ) {
            return back()->withError('Sorry! No newspaper found');
        }

        $user = $this->user();

        if($user){
            $is_sub = $user->isSubToPublication($newsDatas->publication, $newsDatas->published_date);

            $subscribed = $newsDatas->is_free == 1
                || $is_sub === true
                || $user->hasBoughtNewspaper($newsDatas);

            $subscribed= !$subscribed ? '0' : '1';
        } else {
            $subscribed= '';
        }

        $publications = Publication::active()->get();
        $file_url = '';

        if( $subscribed !== '1' ) {
            $file_url = Storage::url( $newsDatas->file_preview );
        }
        else if( $newsDatas->file_converted ) {
            $file_url = Storage::url( $newsDatas->file_converted );
        }
        else if( $newsDatas->file ) {
            $file_url = Storage::url( $newsDatas->file );
        }

        if( !empty($file_url) ) {
            $file_url = url( $file_url );
        } else {
            $file_url = url('pdf/pdf_file_doesnt_exist.pdf');
        }
        
        if($publication_id || $date){
            return redirect('/pdf/'.$newsDatas->id.'/pdfviewer');
        }else{
            return view('pdfviewer.pdf-viewer',compact('newsDatas','subscribed', 'publications', 'file_url'));
        }
      }
 
    public function details(Newspaper $newspaper){
        // dd(); 
        $user = $this->user();

        $is_sub = null;

        if($user){
            $is_sub = $user->isSubToPublication($newspaper->publication, $newspaper->published_date);

            $subscribed = $newspaper->is_free == 1
                || $is_sub === true
                || $user->hasBoughtNewspaper($newspaper);

            $subscribed = $subscribed == false ? '0' : '1';
        } else {
            $subscribed = '';
        }
        
        $id=$newspaper->id;
        $newsDetails= Newspaper::with('category','publication')->where('id',$id)->first();
        $post = new NewspaperResource($newsDetails);
        #related magazines
        // $related = MagazineResource::collection($newspaper->category);
        $related = $this->related_magazines('newspaper', $newspaper->category);
        $rel = collect($related)->toArray();
        array_multisort($rel,SORT_DESC);
        $related = collect($rel)->where('publication_id',$newsDetails->publication_id)->where('id','<>',$newspaper->id)->where('status',1)->values()->all();  

        #top stories
        $top_stories = $this->top_stories();
        $getTable=$newspaper->getTable();
         $bnews = (auth()->user())?UserBookmark::where('user_id',auth()->user()->id)->where('type','newspaper')->pluck('pid')->all() : [];

        return view('customer.newspapers.show',compact('newsDetails','bnews','related','top_stories','subscribed','getTable', 'is_sub'));

    }
     public function relatedMagazineList(Newspaper $newspaper){
        
        $related = $this->related_magazines('newspaper', $newspaper->category);
        // dd($related);
        return view('customer.newspapers.relatedmagazine',compact('related'));
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
        $news = Newspaper::with('category','publication')->get();
        $catsDatas=Category::get();
        $pubsData= Publication::get();
        // dd(count($news));
        return view('customer.newspapers.index',compact('news','catsDatas','pubsData'));
    }

    public function newsListing(Request $request) {
        
        $newsDatas= Newspaper::active()
            ->with(['category','publication'])
            ->latest();

        if( $category_id = intval($request->query('category_id')) ) {
            $newsDatas->where('category_id', $category_id);
        }

        if( $publication_id = intval($request->query('publication_id')) ) {
            $newsDatas->where('publication_id', $publication_id);
        }

        if( $date = strtotime($request->query('date')) ) {
            $newsDatas->whereDate('published_date', date('Y-m-d', $date));
        }

        $newsDatas = $newsDatas->paginate(10);
        
        $catsDatas = Category::active()
            ->latest()->get();

        $pubsData = Publication::active()
            ->where('type', 'like', '%news%')
            ->latest()->get();

        $bnews = (auth()->user())?UserBookmark::where('user_id',auth()->user()->id)->where('type','newspaper')->pluck('pid')->all() : [];
        // $catsDatas=Category::get();
        //  $pubsData= Publication::get();
        return view('customer.newspapers.news_search',compact('newsDatas','catsDatas','pubsData','bnews'));
    }

    public function newsByCategory(Category $category){
    // dd($category);
        $newsDatas=Newspaper::active()->with('category','publication')->where('category_id',$category->id)->latest()->get();
        $news = Newspaper::active()->with('category','publication')->latest()->get(['category_id','publication_id']);
        $newsCat = $news->pluck('category_id')->unique();
        $newsPubs = $newsDatas->pluck('publication_id')->unique();
        $catsDatas=Category::active()->whereIn('id',$newsCat)->latest()->get();
        $pubsData= Publication::active()->whereIn('id',$newsPubs)->latest()->get();
        // $catsDatas=Category::get();
        $category_details=Category::where('id',$category->id)->first();
        $bnews = (auth()->user())?UserBookmark::where('user_id',auth()->user()->id)->where('type','newspaper')->pluck('pid')->all() : [];
        // dd($mags);
        // $pubsData= Publication::get();
       return view('customer.newspapers.news_search',compact('newsDatas','catsDatas','category_details','pubsData','category','bnews'));
    }


    public function searchFilterNews(Request $request){
        // dd($request->all());
        $newspapers = Magazine::get();
        if($request->has('publication_id') && !empty($request->get('publication_id'))){
           $newspapers = $newspapers->where('publication_id',$request->publication_id);  
        }
        if($request->has('from') || $request->has('to')){
            if($request->from){
                $from = Carbon::parse($request->from)->format('Y-m-d');
                // DB::enableQueryLog();
                $newspapers = $newspapers->whereBetween('published_date',[$from, now()->format('Y-m-d')]);
                // dd(count($magazines));
            }
        }
        // $catsDatas=Category::get();
        // $pubsData= Publication::get();
        return response()->json($newspapers);
    }

    public function newspapersByCategory(Category $category){
    // dd($category);
        $news=Newspaper::with('category','publication')->where('category_id',$category->id)->get();
        $catsDatas=Category::get();
        $category_details=Category::where('id',$category->id)->first();
        // dd($mags);
        $pubsData= Publication::get();
       return view('customer.newspapers.index',compact('news','catsDatas','category_details','pubsData'));
    }


    public function searchFilterNewspaper(Request $request){
        // dd($request->all());
        $newspapers = Newspaper::get();
        if($request->has('publication_id') && !empty($request->get('publication_id'))){
           $newspapers = $newspapers->where('publication_id',$request->publication_id);  
        }
        if($request->has('from') || $request->has('to')){
            if($request->from){
                $from = Carbon::parse($request->from)->format('Y-m-d');
                // DB::enableQueryLog();
                $newspapers = $newspapers->whereBetween('published_date',[$from, now()->format('Y-m-d')]);
                // dd(count($magazines));
            }
        }
        return response()->json($newspapers);
    }


    //  public function download(Request $request){
    //     // dd($request->all());
    //     $file = Newspaper::find($request->newsid);
    //     return Storage::download("public/".$file->file,$file->title);

    // }
    // public function download(Request $request){
    //     // dd($request->all());
    //     $file = Newspaper::find($request->newsid);
    //     $user =User::where('id',auth()->user()->id)->first();

    //     if($request->subscribed == 0){

    //         if($user->download_status == '1'){
    //            return redirect()->back()
    //             ->with('error','You  already download ');  
    //         }else{
    //             User::where('id',auth()->user()->id)->update(['download_status'=>1]);
    //             return Storage::download("public/".$file->file,$file->title); 
    //         }
            
           
    //     }else{
    //         // User::where('id',auth()->user()->id)->update(['download_status'=>0]);
    //        return redirect()->back()
    //             ->with('error','You have to Subscribe First');
    //     }

    //     // if($user == 1){

    //     //     dd();
    //     // }
    //     // return Storage::download("public/".$file->file,$file->title);
    // }

    public function markFileAsDownloaded($content_id,$subscribed)
    {
        // dd($content_id);
        $user = $this->user();
        $file = Newspaper::findOrFail($content_id);

        return redirect()->route('newspaper.pdfviewer', ['newspaper' => $file->id]);

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
            if($file->file !="" && $file->file_type == "pdf"){
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
            // dd($file->file);
            if($file->file != ''){
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

    }
    else{
            return redirect()->back()->with("error","You have to subscribe first ");  
    }
        DB::rollBack();

        return redirect()->back()->with('error','Something went wrong');
    }

    // @helper - magazine or news
    protected function get_content_instance($content_id)
    {
        return $this->get_content_type() === 'magazine'
            ? \App\Models\Magazine::find($content_id)
            : \App\Models\Newspaper::find($content_id);
    }

    // @helper - magazine or news
    protected function get_content_type($uppercase = false)
    {
        if( ! $this->content_type ) {
            $this->content_type = request()->is('*/magazine/*')
                ? 'magazine'
                : 'newspaper';
        }

        return $uppercase
            ? \ucwords($this->content_type)
            : $this->content_type;
    }

    public function download(Request $request){
        $content_id = $request->newsid;
        $subscribed = $request->subscribed;
        return $this->markFileAsDownloaded($content_id,$subscribed);
    }


}
