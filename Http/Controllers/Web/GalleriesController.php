<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Gallery;
use App\Models\AlbumGallery;
use App\Models\Albums;

class GalleriesController extends Controller
{
    //

    //  public function listing(){
    //     $all_gallery = Gallery::active()->latest()->get();
    //     return view('customer.gallery.index',compact('all_gallery'));
    // }

     public function listing(){
      $all_gallery = Albums::active()->latest()->get();
        // echo "<pre>";
      // dd($all_gallery);
        // print_r($all_gallery->first()->gallary_images->last()->image);
        return view('customer.gallery.index',compact('all_gallery'));
    }

     public function AlbumGalleryListing(Albums $album){
      // dd($album);
      $galleries = Albums::active()->latest()->get();
      $album_id = $album->id;
      $all_gallery =  AlbumGallery::where('album_id',$album->id)->latest()->get();
        return view('customer.gallery.album',compact('all_gallery','album_id','galleries'));
    }
}
