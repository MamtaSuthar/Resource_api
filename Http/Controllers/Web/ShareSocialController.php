<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\Route;
use URL;
  
class ShareSocialController extends Controller
{
    public function shareSocial()
    {
        $currentURL = url()->previous();
        $socialShare = \Share::page($currentURL,
        )
        ->facebook()
        ->twitter()
        ->reddit()
        ->linkedin()
        ->whatsapp()
        ->telegram();
          
        return view('share/share-social', compact('socialShare'));
    }
}