<?php

namespace App\Http\Controllers\Api\Customer;

use App\Api\ApiResponse;
use App\Http\Controllers\ApiController as Controller;
use App\Http\Resources\MagazineResource;
use App\Http\Resources\NewspaperResource;
use App\Http\Resources\PublicationResource;
use App\Models\Magazine;
use App\Models\Newspaper;
use App\Models\Publication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ArchiveCotroller extends Controller
{
    public function listing(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => ['nullable', 'in:magazine,newspaper']
        ]);

        if ($validator->fails()) {
            return $this->validation_error_response($validator);
        }

        $data = [
            'magazines' => [],
            'newspapers' => [],
            'publications' => []
        ];

        $type = $request->get('type') ?? 'magazine';

        if( $type == 'magazine' ) {
            $content = Magazine::query();
        } else {
            $content = Newspaper::query();
        }

        if( $from_date = strtotime($request->get('date')) ) {
            $content->whereDate('published_date', date('Y-m-d', $from_date));
        } else {
            // week old data only
            $content->whereDate('published_date', '<',  now()->subWeek()->format('Y-m-d'));
        }

        if( $publication_id = intval($request->get('publication')) ) {
            $content->where('publication_id', $publication_id);
        }

        $content = $content->latest()->paginate(15);

        $data['publications'] = PublicationResource::collection(Publication::active()->get());

        if( $type == 'magazine' ) {
            $data['magazines']  = MagazineResource::collection($content);
        } else {
            $data['newspapers']  = NewspaperResource::collection($content);
        }

        foreach( $data as $key => $value ) {
            if( empty($value) ) {
                $data[$key] = (object)[];
            }
        }

        return ApiResponse::ok('Archives', $data);
    }
}
