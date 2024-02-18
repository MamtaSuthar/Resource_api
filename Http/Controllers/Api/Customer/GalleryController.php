<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Api\ApiResponse;
use App\Http\Resources\AlbumGalleryResource;
use App\Http\Resources\AlbumResource;
use App\Http\Resources\GalleryResource;
use App\Models\AlbumGallery;
use App\Models\Albums;
use App\Models\Gallery;
use Illuminate\Support\Facades\Validator;

class GalleryController extends Controller
{
    //
    private $limits = 15;

    public function index()
    {
        # Get Latest Gallery Listing
        $gallery_data =  Gallery::active()->latest()
            ->paginate($this->limits);
        
        return ApiResponse::ok(
            'Gallery Listing ',
            GalleryResource::collection($gallery_data) 
        );
    }

    public function galleryListingforAlbum(Request $request){
        
        # Get Latest Gallery Listing
        $validator = Validator::make($request->all(), [
            'album_id' => ['required', 'integer'],
        ]);

        if ($validator->fails()) {
            return $this->validation_error_response($validator);
        }

        $gallery_data =  AlbumGallery::where('album_id',$request->get('album_id'))
            ->latest()
            ->paginate($this->limits)
            ->getCollection();
        
        return ApiResponse::ok(
            'Gallery Listing ',
            AlbumGalleryResource::collection($gallery_data) 
        );
    }

    public function albumListing(){
        
        # Get Latest Gallery Listing
        $albums = Albums::active()->latest()
            ->paginate($this->limits ?? 15)
            ->getCollection();

        return ApiResponse::ok(
            'Album Listing',
            AlbumResource::collection($albums)
        );
        
    }

}
