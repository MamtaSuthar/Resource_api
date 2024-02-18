<?php

namespace App\Http\Controllers\Api\Customer;

use App\Api\ApiResponse;
use App\Http\Controllers\ApiController;
use App\Http\Controllers\Controller;
use App\Models\ActivityCount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ActivityCountController extends ApiController
{
    
    public function get_activity_count (Request $request){
        $validator = Validator::make($request->all(), [
            'type' => ['required',Rule::in(['ads','reading','views'])],
            'click_type' => ['required',Rule::in(['banner','medium_banner','full_banner','magazine','newspaper'])],
        ]);
        // if($validator->fails()){
        //     return $this->validation($validator);
        // }
        // dump($this->validation_error_response($validator));
        if ($validator->fails()) {
            return $this->validation_error_response($validator);
        }else{
            $user = auth()->user()->id;
            if(!empty($user)){
                $type_array = ['ads','reading','views'];
                $checkuserexist = ActivityCount::where('user_id',auth()->user()->id)->first();
                $getdata = $request->all(); // type ,field_id, click_type
                if(!empty($checkuserexist)){
                    $types = json_decode($checkuserexist->type,true);
                    $click_type = json_decode($checkuserexist->click_type,true);
                    $file_id = json_decode($checkuserexist->file_id,true);
                    $type_count = $click_type_count = 0;
                    if($types){
                        if(collect($types)->get($request->type)){
                            $types[$request->type] = collect($types)->get($request->type) + 1;
                        }else{
                            $types[$request->type] = 1;
                        }
                    }else{
                        $types[$request->type] += 1; 
                    }

                    if($click_type){
                        if(collect($click_type)->get($request->click_type)){
                            $click_type[$request->click_type] = collect($click_type)->get($request->click_type) + 1;
                        }else{
                            $click_type[$request->click_type] = 1;
                        }
                    }else{
                        $click_type[$request->click_type] += 1; 
                    }

                    $files = ['magazine','newspaper'];
                    $file_ids = [];
                    if($file_id){
                        if(in_array($request->click_type,$files)){
                            if($request->file_id && collect($file_id)->get($request->click_type)){
                                $file_id[$request->click_type] = collect($file_id[$request->click_type])->push($request->file_id);
                            }else{
                                $file_id[$request->click_type] = [$request->file_id];
                            }
                        }
                        
                    }else{
                        if($request->file_id && in_array($request->click_type,$files)){
                            $file_id[$request->click_type] = [$request->file_id];
                        }
                    }
                    $activity = [
                        'user_id' => $user,
                        'type' => collect($types)->toJson(),
                        'click_type' => collect($click_type)->toJson(),
                        'file_id' =>collect($file_id)->toJson()
                    ];
                    // $act = 
                    ActivityCount::where('user_id',$user)->update($activity);
                    return ApiResponse::ok("Activity Submitted");

                }else{
                    $types = $click_type = $file_id = [];
                    if($request->type){
                        $types[$request->type] = 1;
                    }else{
                        return ApiResponse::error('Type Required');
                    }

                    if($request->click_type){
                        $click_type[$request->click_type] = 1;
                    }else{
                        return ApiResponse::error('Type Required');
                    }
                    $files = ['magazine','newspaper'];
                    if($request->file_id && in_array($request->click_type,$files)){
                        $file_id[$request->click_type] = [$request->file_id];
                    }
                    ActivityCount::create([
                        'user_id' => $user,
                        'type' => collect($types)->toJson(),
                        'click_type' => collect($click_type)->toJson(),
                        'file_id' =>collect($file_id)->toJson()
                    ]);
                    return ApiResponse::ok('Activity Submitted');
                }
            }
        }
        // dump('tet');
        
    }




}
