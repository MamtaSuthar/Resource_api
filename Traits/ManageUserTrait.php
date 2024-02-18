<?php
namespace App\Traits;

use App\Http\Resources\TagResource;
use App\Models\Category;
use App\Models\CouponCode;
use App\Models\Tag;
use App\Models\User;
use App\Models\UserInfo;
use Illuminate\Support\Str;

trait ManageUserTrait
{
    public function getReferralCode($name){
        $subPart = substr($name,0,4).$this->randStr(4);

        if(User::where('refer_code','LIKE','%'.$subPart.'%')->exists()){
            $subPart =  $this->getReferralCode($name);
        }
        return strtoupper($subPart);
    }

    // This function will return a random
    // string of specified length
    public function randStr($no_of_char)
    {
    
        // String of all alphanumeric character
        $str = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
    
        // Shuffle the $str_result and returns substring
        // of specified length
        return substr(str_shuffle($str), 
                        0, $no_of_char);
    }
    Public function getUserByRefercode($refercode){
        $user = User::where('refer_code',$refercode)->first();
        if($user){
            return $user->id;
        }else{
            return 0;
        }
    }
    public function getTags($id){
        # Get Magazines
        $tags = Category::latest()->get();
        // $tags = TagResource::collection($tags);
        $ft = UserInfo::where('user_id',$id)->first()->favourite_topics ?? [];
        $topics = !empty($ft)?json_decode($ft):[];
        $tags = $tags->map(function($item) use ($topics){
            $item['selected'] = (in_array($item['id'],$topics))?true:((empty($topics))?true:false);
            unset($item['created_at'],$item['updated_at']);
            return $item;
        });

        // dd($tags);
        return $tags;
    }

    public function generateCouponCode($userid){
        $code = $this->randStr(8);
        $data = CouponCode::where('code',strtoupper($code))->first();
        if(empty($data)){
            CouponCode::create([
                'user_id'   =>$userid,
                'code'      =>Str::upper($code),
                'type'      =>1,
                'status'    =>0,
                'title'     =>"Discount for referral",
                'discount'  =>2,
                'used_times'=>1,
                'valid_for' =>5,
                'created_at'=>now()
            ]);
        }else{
            return $this->generateCouponCode($userid);
        }
    }
}