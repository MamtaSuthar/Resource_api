<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Http\Resources\AdsResource;
use URL;
class Ad extends Model
{
    // use HasFactory;
       // protected $fillable = ['ads_type','preffered_type','c_banner_ads','c_banner_ads_name','c_medium_ads','c_medium_ads_name','c_full_ads','c_full_ads_name','g_ads_id','g_banner_ads','g_medium_ads','g_full_ads','enable_ads'];
    // protected $guarded = [];
     protected $guarded = ['id'];

    public static function getGoogleAds(){
      $googleAds=self::where('preffered_type','Google')->select('id','ads_type','preffered_type','g_ads_id','g_banner_ads','g_medium_ads','g_full_ads','created_at','updated_at')->get();
      // foreach($googleAds as $google){
      //    // $custom[]=$custom;
      //    $google['c_banner_ads']=URL::to('/').'/storage/'.$google['c_banner_ads'];
      //    $google['c_medium_ads']=URL::to('/').'/storage/'.$google['c_medium_ads'];
      //    $google['c_full_ads']=URL::to('/').'/storage/'.$google['c_full_ads'];
      // }
      
      return $googleAds;
    }
    public static function getCustomAds(){
      $customAds=self::where('preffered_type','Custom')->select('id','ads_type','preffered_type','c_banner_ads','c_banner_ads_name','c_medium_ads','c_medium_ads_name','c_full_ads','c_full_ads_name','created_at','updated_at')->get();
      foreach($customAds as $custom){
         // $custom[]=$custom;
         $custom['c_banner_ads']=URL::to('/').'/storage/'.$custom['c_banner_ads'];
         $custom['c_medium_ads']=URL::to('/').'/storage/'.$custom['c_medium_ads'];
         $custom['c_full_ads']=URL::to('/').'/storage/'.$custom['c_full_ads'];
      }
      return $customAds;

    }   

    // public static function getAds($p_type){
    //   $customAds=self::where('preffered_type','Custom')->get();
    //   return $customAds;

    // }

}
