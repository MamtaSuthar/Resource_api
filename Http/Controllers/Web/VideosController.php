<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Video;
class VideosController extends Controller
{
    //
    public function listing(){
        $all_videos = Video::active()->latest()->get();
        return view('customer.videos.index',compact('all_videos'));
    }

    public function view(Video $video)
    {
        return view('customer.videos.view',compact('video'));
    }
}
