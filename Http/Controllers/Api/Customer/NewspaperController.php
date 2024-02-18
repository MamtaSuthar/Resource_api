<?php

namespace App\Http\Controllers\Api\Customer;

// use App\Http\Controllers\Controller;
use App\Api\ApiResponse;
use App\Http\Controllers\Api\Customer\PremiumContentController as Controller;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\NewspaperPdfResource;
use App\Http\Resources\NewspaperResource;
use App\Http\Resources\PublicationResource;
use App\Models\UserSubscription;
use App\Models\Category;
use App\Models\Newspaper;
use App\Models\Publication;
use App\Traits\NewspaperTrait;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class NewspaperController extends Controller
{
    use NewspaperTrait;

    private $limits = 15;

    public function __construct()
    {
        $this->content_type = 'newspaper';

        parent::__construct();
    }

    public function index(Request $request)
    {
        if( empty($request->get('type')) ) {
            $request->merge(['type' => 'newspaper']);
        }

        $data = $this->filterByType($request);
        return $data;
    }

    public function view(Newspaper $newspaper)
    {
        $user = $this->user();

        [$total_downloads, $todays_downloads] = $user
            ? $this->get_total_downloads($newspaper, 'newspaper')
            : [0, 0];

        $post = new NewspaperResource($newspaper);

        // is either subscribed or bought this magazine
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
        
        $plan_id = env('FREE_PLAN_ID')??null;
        $free_plan = false;
        if(!empty($plan_id)){
            if(!empty($user))
                $free_plan = UserSubscription::where('plan_id',$plan_id)->where('user_id',$user->id)->exists();
        }

        // has plan associated with its publication
        $has_plans = \App\Models\PlanPublication::query()
            ->where('publication_id', $newspaper->publication->id)
            ->exists();

        $post->additional([
            'total_downloads' => $total_downloads ?? 0,
            'todays_downloads' => $todays_downloads ?? 0,
            'subscribed' => $subscribed == '1',
            'has_plans' => $has_plans,
            'free_plan_enable' => $free_plan
        ]);

        $cc = app(\App\Http\Controllers\Api\CommonController::class);
        #flip pdf  data
        $newsDatas = $newspaper;
        // return response()->view('flip_link.api.file_type_link', compact('magDatas'));
        $htmlData = view('vendoruser.newspapers.flip_link.api.flip', ['newsDatas' => json_decode($newspaper)])->render();
        #pdf send with type

        $newspaper->subscribed = $subscribed;

        $pdf_details = new NewspaperPdfResource($newspaper);

        $pdf_file = null;

        if( $pdf_details->file_type === 'pdf' ) {
            $pdf_file = $pdf_details->file;
        }
        else if( $pdf_details->file_type === 'epub' && $pdf_details->file_converted ) {
            $pdf_file = $pdf_details->file_converted;
        }

        $totalPages = $pdf_file && \is_file(storage_path('app/public/'.$pdf_file))
            ? $this->countPdfPages(storage_path('app/public/'.$pdf_file))
            : 0;

        $related = $cc->related_magazines('newspapers', $newspaper->category)->all();
        $rel = collect($related)->toArray();
        array_multisort($rel,SORT_DESC);
        $related = collect($rel)->where('status',1)->all();  

        $web_url = url("single/newspaper/{$newspaper->id}?al_7624109=");

        if( $user ) {
            $web_url .= encrypt($user->id);
        }

        return ApiResponse::ok('Newspaper Detail', [
            'post' => $post,
            'related' => collect($related)->where('publication_id',$newspaper->publication_id)->where('id','<>',$newspaper->id)->values()->take(6),
            'top_stories' => $cc->top_stories(),
            'Flip Data' => $htmlData,
            'Pdf' => $pdf_details,
            'PdfCount'=>$totalPages,
            'is_old' => $is_sub == -1,
            'web_url' => $web_url
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, post-check=0, pre-check=0');
    }

     public function countPages($path) {
         try {

            $pdftext = file_get_contents($path);
            $num = preg_match_all("/\/Page\W/", $pdftext, $dummy);
            return intval($num);

         } catch(\Exception $e) {
            logger($e->getMessage());
         }
         return 0;
    } 

    public function sendPdf(Newspaper $newspaper)
    {
        $user = $this->user();

        $subscribed = $newspaper->is_free == 1
            || $user->isSubToPublication($newspaper->publication)
            || $user->hasBoughtNewspaper($newspaper);

        $newspaper->subscribed = $subscribed;

        $pdf_details = new NewspaperPdfResource($newspaper);
        
        return ApiResponse::ok('Newspaper Pdf & Epub Details', $pdf_details);
    }

    public function send_link(Newspaper $newspaper)
    {
        $newsDatas = $newspaper;
        // return response()->view('flip_link.api.file_type_link', compact('magDatas'));
        $htmlData = view('vendoruser.newspapers.flip_link.api.flip', ['newsDatas' => json_decode($newspaper)])->render();
        // return response()->view('flip_link.api.flip',['magDatas'=>json_decode($magazine)]);

        # $htmlData=URL::to('/vendor/magazines/').'/'.$magDatas->id;
        return ApiResponse::ok(__('Flip Data'), $htmlData);
    }

    // public function pdf_flip_views(Newspaper $newspaper)
    // {
    //     // dd($magazine);
    //     // return view('vendoruser.newspapers.flip_link.api.flip_pdf', ['newsDatas' => json_decode($newspaper)]);
    //     $file_url = '';

    //     if( $newspaper->file_converted ) {
    //         $file_url = Storage::url( $newspaper->file_converted );
    //     }
    //     else if( $newspaper->file ) {
    //         $file_url = Storage::url( $newspaper->file );
    //     }

    //     if( !empty($file_url) ) {
    //         $file_url = url( $file_url );
    //     } else {
    //         $file_url = url('pdf/pdf_file_doesnt_exist.pdf');
    //     }

    //     $just_viewer = true;

    //     return view('pdfviewer.pdf-viewer', compact('file_url','just_viewer'));
    // }
    
    public function pdf_flip_views($newspaper, Request $request)
    {

        $newsDatas = Newspaper::query();

        $newsDatas = $newsDatas->find($newspaper);

        if (empty($newsDatas)) {
            return back()->withError('Sorry! No newspaper found');
        }

        $file_url = '';
        // dd($newsDatas->file);

        if ($newsDatas->file_converted) {
            $file_url = Storage::url($newsDatas->file_converted);
        } else if ($newsDatas->file) {
            $file_url = Storage::url($newsDatas->file);
        }else{
            $file_url = Storage::url( $newsDatas->file_preview );
        }

        if (!empty($file_url)) {
            $file_url = url($file_url);
        } else {
            $file_url = url('pdf/pdf_file_doesnt_exist.pdf');
        }
        $just_viewer = true;

        return view('pdfviewer.pdf-viewer', compact('newsDatas', 'file_url','just_viewer'));
    }
    
}
