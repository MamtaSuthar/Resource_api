<?php

namespace App\Http\Controllers\Api\Customer;

use App\Api\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\PodcastResource;
use App\Models\Podcast;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PodcastController extends Controller
{
    //
    private $limits = 15;

    public function index()
    {
        // dd();
        # Get Latest Podcast
        $podcasts = Podcast::active()->latest()
            ->paginate($this->limits);
        // $podcasts = Podcast::active()->latest()
        //     ->get();
        
        return ApiResponse::ok(
            'Podcasts Data',
            PodcastResource::collection($podcasts) 
        );
    }

    public function detail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => ['required', 'exists:podcasts,id']
        ]);

        if($validator->fails()){
            return $this->validation_error_response($validator);
        }

        return ApiResponse::ok(
            'Podcasts Data',
            new PodcastResource(Podcast::find($request->post('id')))
        );
    }
}
