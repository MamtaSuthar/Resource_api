<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Api\ApiResponse;
use Illuminate\Http\Request;
use App\Http\Resources\VideoResource;
use App\Models\Video;
use Illuminate\Support\Facades\Validator;

class VideosController extends Controller
{
    //

    private $limits = 15;

    public function index()
    {
     
        # Get Latest Videos
        $videos = Video::active()->latest()
            ->paginate($this->limits);
        
        return ApiResponse::ok(
            'Videos Data',
            VideoResource::collection($videos) 
        );
    }

    public function detail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => ['required', 'exists:videos,id']
        ]);

        if($validator->fails()){
            return $this->validation_error_response($validator);
        }

        return ApiResponse::ok(
            'Podcasts Data',
            new VideoResource(Video::find($request->post('id')))
        );
    }
}
