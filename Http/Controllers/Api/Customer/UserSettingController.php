<?php

namespace App\Http\Controllers\Api\Customer;

use App\Api\ApiResponse;
use App\Http\Controllers\ApiController as Controller;
use App\Models\Category;
use App\Models\UserInfo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class UserSettingController extends Controller
{
    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'push_notification' => ['nullable', 'in:1,0']
        ]);

        if($validator->fails()){
            return $this->validation_error_response($validator);
        }

        $data = $validator->validated();

        $user = $this->user();

        $user->setting()
            ->updateOrCreate(['user_id' => $user->id], $data);

        return ApiResponse::ok('Settings updated');
    }

    public function profile_update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'first_name' => ['nullable', 'max:191'],
            'last_name' => ['nullable', 'max:191'],
            'country' => ['nullable'],
            'dob' => ['nullable', 'date_format:Y-m-d'],
            'gender' => ['nullable', 'in:m,f,o']
        ]);

        if($validator->fails()){
            return $this->validation_error_response($validator);
        }

        $data = $validator->validated();

        $user = $this->user();

        if( !empty($data) ) {
            $user->update($data);
        }

        return ApiResponse::ok('Profile updated', [
            'user' => $user->format()
        ]);
    }

    public function getPreferences(){
        $user = auth()->user();
        $pref = $this->getTags($user->id);

        return ApiResponse::ok('Preferences',$pref);
    }

    public function getTags($id){
        # Get Magazines
        $tags = Category::active()->latest()->get();
        // $tags = TagResource::collection($tags);
        $ft = UserInfo::where('user_id',$id)->first()->favourite_topics ?? [];
        $topics = !empty($ft)?json_decode($ft):[];
        $tags = $tags->except(['created_at', 'updated_at'])->map(function($item) use ($topics){
            $item['selected'] = (in_array($item['id'],$topics))?true:((empty($topics))?true:false);
            unset($item['created_at'],$item['updated_at']);
            return $item;
        });

        // dd($tags);
        return $tags;
    }

    public function delete_account()
    {
        $user = $this->user();
        
        DB::beginTransaction();

        try {

            DB::statement('SET FOREIGN_KEY_CHECKS=0;');

            DB::table('users')
                ->where('id', $user->id)
                ->delete();

            DB::statement('SET FOREIGN_KEY_CHECKS=1;');

            DB::commit();

            return ApiResponse::ok('Account and related data removed.');

        } catch(\Exception $e) {
            DB::rollBack();

            logger($e->getMessage());
        }

        return ApiResponse::error('Could not delete account. Something went wrong.');
    }
}
