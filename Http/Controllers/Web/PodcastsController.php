<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Podcast;
class PodcastsController extends Controller
{
    //
    public function listing(){
        $pod_datas = Podcast::active()->latest()->get();
        return view('customer.audios.index',compact('pod_datas'));
    }

    public function view(Podcast $podcast)
    {
        return view('customer.audios.view',compact('podcast'));
    }
}
