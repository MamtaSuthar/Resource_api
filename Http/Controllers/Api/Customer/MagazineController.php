<?php

namespace App\Http\Controllers\Api\Customer;

use App\Api\ApiResponse;
// use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Customer\PremiumContentController as Controller;
use App\Http\Resources\MagazineResource;
use App\Http\Resources\MagazinePdfResource;
use Illuminate\Http\Request;
use App\Models\Magazine;
use Illuminate\Support\Facades\Storage;

// premium content - Magazine/ Newspaper
class MagazineController extends Controller
{
    private $limits = 15;

    public function __construct()
    {
        $this->content_type = 'magazine';

        parent::__construct();
    }

    public function index()
    {
        # Get Latest Magazines
        $magazines = Magazine::active()->latest()
            ->paginate($this->limits);
        
        return ApiResponse::ok(
            'Magazines Data',
            MagazineResource::collection($magazines) 
        );
    }
    
    public function view(Magazine $magazine)
    {
        $user = $this->user();

        [$total_downloads, $todays_downloads] = $user
            ? $this->get_total_downloads($magazine, 'magazine')
            : [0, 0];
        
        $post = new MagazineResource($magazine);

        // is either subscribed or bought this magazine
        $is_sub = null;

        if($user){
            $is_sub = $user->isSubToPublication($magazine->publication, $magazine->published_date);

            $subscribed = $magazine->is_free == 1
                || $is_sub === true
                || $user->hasBoughtMagazine($magazine);

            $subscribed= $subscribed == false ? '0' : '1';
        } else {
            $subscribed= ''; 
        }

        // has plan associated with its publication
        $has_plans = \App\Models\PlanPublication::query()
            ->where('publication_id', $magazine->publication->id)
            ->exists();

        $post->additional([
            'total_downloads' => $total_downloads ?? 0,
            'todays_downloads' => $todays_downloads ?? 0,
            'subscribed' => $subscribed == '1',
            'has_plans' => $has_plans
        ]);

        $cc = app(\App\Http\Controllers\Api\CommonController::class);

        #flip pdff
        $magDatas = $magazine;

        $magazine->subscribed = $subscribed;

        // return response()->view('flip_link.api.file_type_link', compact('magDatas'));
        $htmlData =view('vendoruser.magazines.flip_link.api.flip',['magDatas'=>json_decode($magazine)])->render();
         #pdf send with type
        $pdf_details = new MagazinePdfResource($magazine);

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

        $related = $cc->related_magazines('magazine', $magazine->category)->all();

        $web_url = url("single/magazine/{$magazine->id}?al_7624109=");

        if( $user ) {
            $web_url .= encrypt($user->id);
        }

        return ApiResponse::ok('Magazine Detail', [
            'post' => $post,
            'related' => collect($related)->where('id','<>',$magazine->id)->where('status',1)->values()->all(),
            'top_stories' => $cc->top_stories(),
            'Flip Data'=>$htmlData,
            'Pdf'=>$pdf_details,
            'PdfCount'=>$totalPages,
            'is_old' => $is_sub == -1,
            'web_url' => $web_url
        ]);
    }

    public function sendPdf(Magazine $magazine){
        $user = $this->user();

        $subscribed = $magazine->is_free == 1
            || $user->isSubToPublication($magazine->publication)
            || $user->hasBoughtMagazine($magazine);

        $magazine->subscribed = $subscribed;

        $pdf_details = new MagazinePdfResource($magazine);

        return ApiResponse::ok('Magazine Pdf & Epub  Details',$pdf_details);
    }

    public function send_link(Magazine $magazine){
        $magDatas = $magazine;
        // return response()->view('flip_link.api.file_type_link', compact('magDatas'));
        $htmlData =view('vendoruser.magazines.flip_link.api.flip',['magDatas'=>json_decode($magazine)])->render();   
        // return response()->view('flip_link.api.flip',['magDatas'=>json_decode($magazine)]);


        # $htmlData=URL::to('/vendor/magazines/').'/'.$magDatas->id;
            return ApiResponse::ok(__('Flip Data'), $htmlData);
    }

    // public function pdf_flip_views(Magazine $magazine){
    //     // dd($magazine);
    //     // return view('vendoruser.magazines.flip_link.api.flip_pdf',['magDatas'=>json_decode($magazine)]);
    //     $file_url = '';

    //     if( $magazine->file_converted ) {
    //         $file_url = Storage::url( $magazine->file_converted );
    //     }
    //     else if( $magazine->file ) {
    //         $file_url = Storage::url( $magazine->file );
    //     }

    //     if( ! empty($file_url) ) {
    //         $file_url = url( $file_url );
    //     } else {
    //         $file_url = url('pdf/pdf_file_doesnt_exist.pdf');
    //     }

    //     $just_viewer = true;

    //     return view('pdfviewer.pdf-viewer', compact('file_url','just_viewer'));
    // }
    
    public function pdf_flip_views($magazine, Request $request){
        $magDatas = Magazine::query();

        $magDatas =$magDatas->find($magazine);

        if( empty($magDatas) ) {
            return back()->withError('Sorry! No magazine found');
        }

        $file_url = '';
        
        if( $magDatas->file_converted ) {
            $file_url = Storage::url( $magDatas->file_converted );
        }
        else if( $magDatas->file ) {
            $file_url = Storage::url( $magDatas->file );
        }else{
            $file_url = Storage::url( $magDatas->file_preview );
        }

        if( !empty($file_url) ) {
            $file_url = url( $file_url );
        } else {
            $file_url = url('pdf/pdf_file_doesnt_exist.pdf');
        }
        $just_viewer = true;

        return view('pdfviewer.pdf-viewer',compact('magDatas', 'just_viewer', 'file_url'));
    }
}
