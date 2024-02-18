<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Content;
use App\Traits\CommonTrait;

class InstagramController extends Controller
{
    //
     use CommonTrait;
    public function instagramData(){
        try {
            $data = $this->instagramApi();
        } catch(\Exception $e) {
            logger($e->getMessage());
        }

        $datas = is_object($data) && isset($data->data)
            ? $data->data
            : [];
            
        return view('customer.instagram.instagram',compact('datas'));
    }
}
