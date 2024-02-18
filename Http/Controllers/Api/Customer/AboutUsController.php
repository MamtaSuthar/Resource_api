<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Api\ApiResponse;
use App\Models\Content;
use App\Traits\CommonTrait;

class AboutUsController extends ApiResponse
{
    use CommonTrait;
    public function index(){
        $content = Content::where('slug','about-us')->first();
        if(!empty($content)){
            $htmlData =view('customer.privacy-policy.privacyPolicy', compact('content'))->render();
            return ApiResponse::ok(__($content->title), $htmlData);
        }else{
            $htmlData =view('customer.aboutus.aboutus')->render();
            return ApiResponse::ok(__('About Us'), $htmlData);
        }
        


        // $htmlData =view('customer.aboutus.aboutus')->render();
        // return ApiResponse::ok(__('About Us'), $htmlData);
    }
    public function faq(){
        $content = Content::where('slug','faq')->first();
        if(!empty($content)){
            $htmlData =view('customer.privacy-policy.privacyPolicy', compact('content'))->render();
            return ApiResponse::ok(__($content->title), $htmlData);
        }else{
            $htmlData =view('customer.aboutus.aboutus')->render();
            return ApiResponse::ok(__('About Us'), $htmlData);
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
            $htmlData =view('customer.privacy-policy.privacyPolicy', compact('content'))->render();
            return ApiResponse::ok(__($content->title), $htmlData);
        }else{
            $htmlData =view('customer.aboutus.aboutus')->render();
            return ApiResponse::ok(__('Privacy Policy'), $htmlData);
        }
    }

    public function courtesies(){
        $content = Content::where('slug','courtesies')->first();
        if(!empty($content)){
            $htmlData =view('customer.privacy-policy.privacyPolicy', compact('content'))->render();
            return ApiResponse::ok(__($content->title), $htmlData);
        }else{
            $htmlData =view('customer.privacy-policy.courtesies')->render();
            return ApiResponse::ok(__('Courtesies from GDPR & Other Laws'), $htmlData);
        }
    }

    public function policiesandlicences(){
        $content = Content::where('slug','policies-and-licenses')->first();
        if(!empty($content)){
            $htmlData =view('customer.privacy-policy.privacyPolicy', compact('content'))->render();
            return ApiResponse::ok(__($content->title), $htmlData);
        }else{
            $htmlData =view('customer.privacy-policy.policiesandlicences')->render();
            return ApiResponse::ok(__('Policies & Licenses'), $htmlData);
        }
    }
    
    public function instagramData(){
        $data = $this->instagramApi();
        return ApiResponse::ok("Instagram Feeds",$data->data);
    }
}
