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
use App\Models\Publication;
use Carbon\Carbon;
use DB;
use App\Models\Newspaper;
use App\Http\Resources\PublicationResource;


class ArchiveController extends Controller
{
    //

    public function archiveListing(Request $request){
        $user = auth()->user() ?? auth('api')->user();

        $type = $request->query('type');
        $type = in_array($type, ['magazine', 'newspaper']) ? $type : 'magazine';

        $instance = $type === 'magazine'
            ? Magazine::query()
            : Newspaper::query();

        if( $publication_id = intval($request->query('publication_id')) ) {
            $instance->where('publication_id', $publication_id);
        }

        if( $date = strtotime($request->get('date')) ) {
            $instance->whereDate('published_date', date('Y-m-d', $date));
        } else {
            $instance->whereDate('published_date', '<', now()->subWeek()->format('Y-m-d'));
        }

        $publications = Publication::active()->get();

        $contents = $type === 'magazine'
            ? MagazineResource::collection($instance->latest()->paginate(15))
            : NewspaperResource::collection($instance->latest()->paginate(15));
        // $bnews = $user ? UserBookmark::where('user_id',$user->id)->where('type','newspaper')->pluck('pid')->all() : [];
        // $bmags = $user ? UserBookmark::where('user_id',$user->id)->where('type','magazine')->pluck('pid')->all() : [];

        return view('customer.archive.archive',compact('contents','publications','type'));
    }

    public function filterArchive(Request $request){
        // dd($request->all());
         $magazines = Magazine::active()->latest();
        if($request->has('publication_id') && !empty($request->get('publication_id'))){
           if($request->type == "magazine"){
           $magazines = Magazine::where('publication_id',$request->publication_id)->get(); 
           }else{
            $magazines = Newspaper::where('publication_id',$request->publication_id)->get();
           } 
        }
        if($request->has('from') || $request->has('to')){
            if($request->from){
                $from = Carbon::parse($request->from)->format('Y-m-d');
                // DB::enableQueryLog();
                if($request->type == "magazine"){
                $magazines = Magazine::whereDate('created_at', $from)->get();  
                }else{
                 $magazines = Newspaper::whereDate('created_at', $from)->get();  

                }
            }
        }
        // $magazines = $magazines;

        // $catsDatas=Category::get();
        // $pubsData= Publication::get();
        return response()->json($magazines);
    }
}
