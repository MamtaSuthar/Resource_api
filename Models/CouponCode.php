<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CouponCode extends Model
{
    use HasFactory;

    protected $fillable =['title','code','type','discount','used_times','valid_for','user_id'];
    # Define Query Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 0);
    }

    public static function checkCode($code){
        $coupon = self::whereRaw('BINARY `code` = ?', [$code])->where('used_times','>',0)->first();
        if($coupon){
            $couponLastDate = date('Y-m-d',strtotime("+{$coupon->valid_for} days",strtotime($coupon->created_at)));

            if(now() <= $couponLastDate && $coupon->used_times > 0) {
                $data['discount'] = $coupon->discount;
                $data['type'] = ($coupon->type==1) ? "percentage" : "amount";
                return $data;
            }
        }   
        return [];

    }
    public function ValidCoupons(){
        $coupons = self::where('used_times','>',0)->get();
        if($coupons->isNotEmpty){
            foreach ($coupons as $key => $coupon) {
                $couponLastDate = date('Y-m-d',strtotime("+{$coupon->valid_for} days",strtotime($coupon->created_at)));
                if(now() <= $couponLastDate){
                    if($coupon->used_times <= 0){
                        $coupons->pull($key);
                    }
                }else{
                    $coupons->pull($key);
                }
            }
            
        }   
        return $coupons;

    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function codes_used_by_users()
    {
        return $this->hasMany(UserUsedCoupon::class, 'code', 'code');
    }

    public function getIsActiveAttribute()
    {
        if( $this->valid_for <= 0 ) {
            return false;
        }

        return ! now()->gt(
            $this->created_at->addDays($this->valid_for)
        );
    }

    public function getStatusAttribute()
    {
        return $this->isActive
            ? 'Active'
            : 'Expired';
    }

}
