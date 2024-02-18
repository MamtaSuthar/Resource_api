<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Content;

class AboutUsController extends Controller
{
    //

     public function index(){
        $content = Content::where('slug','about-us')->first();
        if(!empty($content)){
            return view('customer.pages.privacypolicy', compact('content'));
            // return ApiResponse::ok(__($content->title), $htmlData);
        }else{
        return view('customer.pages.aboutus');
            // return ApiResponse::ok(__('About Us'), $htmlData);
        }
   
    }

     public function faq(){
        $content = Content::where('slug','faq')->first();
        if(!empty($content)){
            return view('customer.pages.privacypolicy', compact('content'));
        }else{
           return view('customer.pages.faq');
        }
    }

     public function terms(){
        $content = Content::where('slug','web_terms')->first();
        if(!empty($content)){
            return view('customer.pages.privacypolicy', compact('content'));
        }else{
           return view('customer.pages.aboutus');
        }
    }

    public function privacyPolicy(){
        // $htmlData =view('customer.privacy-policy.privacyPolicy')->render();
        // return ApiResponse::ok(__('Privacy Policy'), $htmlData);
        // $content = Content::where('slug','privacy-policy')->first();
        // $htmlData =view('customer.privacy-policy.privacyPolicy', compact('content'))->render();
        // return ApiResponse::ok(__($content->title), $htmlData);

        $content = Content::where('slug','privacy-policy')->first();
        if(!empty($content)){
            return view('customer.pages.privacypolicy', compact('content'));
        }else{
           return view('customer.pages.aboutus');
        }
    }

    public function courtesies(){
        $content = Content::where('slug','courtesies')->first();
        if(!empty($content)){
            return view('customer.pages.privacypolicy', compact('content'));
        }else{
            return view('customer.privacy-policy.courtesies');
            
        }
    }

    public function policiesandlicences(){
        $content = Content::where('slug','policies-and-licenses')->first();
        if(!empty($content)){
            return view('customer.pages.privacypolicy', compact('content'));
            
        }else{
            return view('customer.pages.policiesandlicences');
            
        }
    }
}
