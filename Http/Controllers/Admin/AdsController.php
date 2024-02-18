<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityCount;
use App\Models\Ad;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdsController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
        $ads = Ad::all();

        $adActivityCount = 0;

        try {
            $adActivityCount = ActivityCount::query()
                ->selectRaw('SUM(JSON_EXTRACT(type, "$.ads")) as ads')
                ->pluck('ads')
                ->first();

            $adActivityCount = intval($adActivityCount);

        } catch(\Exception $e) {
            logger($e->getMessage());
        }

        return view('admin.adsviews/index', compact('ads', 'adActivityCount'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
        // dd("shiv");
        $appgoogle = Ad::where('preffered_type', 'Google')
            ->where('ads_type', 'App')->first() ?? new Ad();
        // dd($appgoogle);
        $appcustom = Ad::where('preffered_type', 'Custom')->where('ads_type', 'App')
            ->first() ?? new Ad();
        $webcustom = Ad::where('ads_type', 'Web')->first() ?? new Ad();

        $web_ads_screens = DB::table('web_ad_screens')->get()
            ->reduce(function($acc, $ad) {
                $acc[$ad->page] = \explode(',', $ad->ads);
                return $acc;
            }, []);

        return view('admin.adsviews/submenu', compact('appgoogle', 'appcustom', 'webcustom', 'web_ads_screens'));

    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'c_banner_ads' => [
                'mimes:jpg,jpeg,png',
                'dimensions:min_width=720,min_height=100',
            ],
            'c_medium_ads' => [
                'mimes:jpg,jpeg,png',
                'dimensions:min_width=350,min_height=300',
            ],

            'c_full_ads' => [
                'mimes:jpg,jpeg,png',
                'dimensions:min_width=1000,min_height=720',
            ],

        ],
            ['c_banner_ads.dimensions' => "The banner ad has invalid dimensions",
                'c_medium_ads.dimensions' => "The medium ad has invalid dimensions",
                'c_full_ads.dimensions' => "The full ad has invalid dimensions"],
        );
        if ($request->ad_type == "appads" && $request->app_google == "Google") {

            // echo "maa";
            // die;
            if ($request->gid == '') {
                // dd($request->gid);

                $validated['ads_type'] = $request->ad_type ? 'App' : null;
                $validated['preffered_type'] = $request->app_google;
                $validated['g_ads_id'] = $request->app_ad_id;
                $validated['g_banner_ads'] = $request->app_banner_id;
                $validated['g_medium_ads'] = $request->app_medium_id;
                $validated['g_full_ads'] = $request->app_full_id;
                $validated['g_banner_ads_ios'] = $request->app_banner_id_ios;
                $validated['g_medium_ads_ios'] = $request->app_medium_id_ios;
                $validated['g_full_ads_ios'] = $request->app_full_id_ios;
                $validated['enable_ads'] = '1';

                if ($request->cid) {
                    Ad::where('id', $request->cid)->update(['enable_ads' => 0]);
                }

                Ad::create($validated);

            } else {
                // dd($request->all());
                $data = [
                    "g_ads_id" => $request->app_ad_id,
                    "g_banner_ads" => $request->app_banner_id,
                    "g_medium_ads" => $request->app_medium_id,
                    "g_full_ads" => $request->app_full_id,
                    "g_banner_ads_ios" => $request->app_banner_id_ios,
                    "g_medium_ads_ios" => $request->app_medium_id_ios,
                    "g_full_ads_ios" => $request->app_full_id_ios,
                    "enable_ads" => '1',

                ];
                if ($request->cid) {
                    Ad::where('id', $request->cid)->update(['enable_ads' => 0]);
                }

                if ($request->gid) {
                    Ad::where('id', $request->gid)->update($data);
                }
            }
        }

        if ($request->ad_type == "appads" && $request->app_google == "Custom") {
            // echo "shiv";
            // die;

            if ($request->cid == '') {

                $validated['ads_type'] = $request->ad_type ? 'App' : null;
                $validated['preffered_type'] = $request->app_google;

                if ($request->hasFile('c_banner_ads')) {
                    $c_banner_ads = $request->c_banner_ads->store('ads/app/custom', 'public');
                    $validated['c_banner_ads'] = $c_banner_ads;
                }
                if ($request->hasFile('c_medium_ads')) {
                    $c_medium_ads = $request->c_medium_ads->store('ads/app/custom', 'public');
                    $validated['c_medium_ads'] = $c_medium_ads;
                }

                if ($request->hasFile('c_full_ads')) {
                    $c_full_ads = $request->c_full_ads->store('ads/app/custom', 'public');
                    $validated['c_full_ads'] = $c_full_ads;
                }
                // $validated['c_banner_ads'] = $request->app_banner_ad;
                $validated['c_banner_ads_name'] = $request->app_banner_ad_name;
                // $validated['c_medium_ads'] = $request->app_medium_ad;
                $validated['c_medium_ads_name'] = $request->app_medium_ad_name;
                // $validated['c_full_ads'] = $request->app_full_ad;
                $validated['c_full_ads_name'] = $request->app_full_ad_name;
                $validated['banner_ads_url'] = $request->banner_ads_url;
                $validated['medium_ads_url'] = $request->medium_ads_url;
                $validated['full_ads_url'] = $request->full_ads_url;
                // dd('maa');
                $validated['enable_ads'] = '1';
                if ($request->gid) {
                    Ad::where('id', $request->gid)->update(['enable_ads' => 0]);
                }

                Ad::create($validated);

            } else {
                // echo "shiv";
                // echo $request->cid;
                // die;
                $validated['ads_type'] = $request->ad_type ? 'App' : null;
                $validated['preffered_type'] = $request->app_google;

                if ($request->hasFile('c_banner_ads')) {
                    $c_banner_ads = $request->c_banner_ads->store('ads/app/custom', 'public');
                    $validated['c_banner_ads'] = $c_banner_ads;
                }
                if ($request->hasFile('c_medium_ads')) {
                    $c_medium_ads = $request->c_medium_ads->store('ads/app/custom', 'public');
                    $validated['c_medium_ads'] = $c_medium_ads;
                }

                if ($request->hasFile('c_full_ads')) {
                    $c_full_ads = $request->c_full_ads->store('ads/app/custom', 'public');
                    $validated['c_full_ads'] = $c_full_ads;
                }
                // $validated['c_banner_ads'] = $request->app_banner_ad;
                $validated['c_banner_ads_name'] = $request->app_banner_ad_name;
                // $validated['c_medium_ads'] = $request->app_medium_ad;
                $validated['c_medium_ads_name'] = $request->app_medium_ad_name;
                // $validated['c_full_ads'] = $request->app_full_ad;
                $validated['c_full_ads_name'] = $request->app_full_ad_name;
                $validated['banner_ads_url'] = $request->banner_ads_url;
                $validated['medium_ads_url'] = $request->medium_ads_url;
                $validated['full_ads_url'] = $request->full_ads_url;
                $validated['enable_ads'] = 1;
                if ($request->gid) {
                    Ad::where('id', $request->gid)->update(['enable_ads' => 0]);
                }
                if ($request->cid) {
                    Ad::where('id', $request->cid)->update($validated);
                }
            }

        }

        if($request->web_ad_type == "webads"){
          if ($request->wwid == "") {
                $validated['ads_type'] = $request->web_ad_type ? 'Web' : null;
                $validated['g_ads_id'] = $request->web_ad_id;

                Ad::create($validated);
            
          }else{
                $data = [
                    "g_ads_id" => $request->web_ad_id,
                ];

                if ($request->wwid) {
                    Ad::where('id', $request->wwid)->update($data);
                }
          }   
        }

        if ($request->web_ad_type == "webads" && $request->web_google = "Custom") {
            // dd('shiv');
            // dd($request->all());

            if ($request->wwid == "") {

                // dd('maaa');
                $validated['ads_type'] = $request->web_ad_type ? 'Web' : null;
                $validated['preffered_type'] = ucfirst($request->web_google);
                /*$validated['g_ads_id'] = $request->web_ad_id;
                $validated['g_banner_ads'] = $request->web_banner_id;
                $validated['g_medium_ads'] = $request->web_medium_id;
                $validated['g_full_ads'] = $request->web_full_id;*/
                // $validated['c_banner_ads'] = $request->web_banner_ad;

                if ($request->hasFile('c_banner_ads')) {
                    $c_banner_ads = $request->c_banner_ads->store('ads/web/custom', 'public');
                    $validated['c_banner_ads'] = $c_banner_ads;
                }
                if ($request->hasFile('c_medium_ads')) {
                    $c_medium_ads = $request->c_medium_ads->store('ads/web/custom', 'public');
                    $validated['c_medium_ads'] = $c_medium_ads;
                }

                if ($request->hasFile('c_full_ads')) {
                    $c_full_ads = $request->c_full_ads->store('ads/web/custom', 'public');
                    $validated['c_full_ads'] = $c_full_ads;
                }

                $validated['c_banner_ads_name'] = $request->web_banner_ad_name;
                // $validated['c_medium_ads'] = $request->web_medium_ad;
                $validated['c_medium_ads_name'] = $request->web_medium_ad_name;
                // $validated['c_full_ads'] = $request->web_full_ad;
                $validated['c_full_ads_name'] = $request->web_full_ad_name;
                Ad::create($validated);
            } else {

                $validated['ads_type'] = $request->web_ad_type ? 'Web' : null;
                $validated['preffered_type'] = ucfirst($request->web_google);
                /*$validated['g_ads_id'] = $request->web_ad_id;
                $validated['g_banner_ads'] = $request->web_banner_id;
                $validated['g_medium_ads'] = $request->web_medium_id;
                $validated['g_full_ads'] = $request->web_full_id;*/
                // $validated['c_banner_ads'] = $request->web_banner_ad;

                if ($request->hasFile('c_banner_ads')) {
                    $c_banner_ads = $request->c_banner_ads->store('ads/web/custom', 'public');
                    $validated['c_banner_ads'] = $c_banner_ads;
                }
                if ($request->hasFile('c_medium_ads')) {
                    $c_medium_ads = $request->c_medium_ads->store('ads/web/custom', 'public');
                    $validated['c_medium_ads'] = $c_medium_ads;
                }

                if ($request->hasFile('c_full_ads')) {
                    $c_full_ads = $request->c_full_ads->store('ads/web/custom', 'public');
                    $validated['c_full_ads'] = $c_full_ads;
                }

                $validated['c_banner_ads_name'] = $request->web_banner_ad_name;
                // $validated['c_medium_ads'] = $request->web_medium_ad;
                $validated['c_medium_ads_name'] = $request->web_medium_ad_name;
                // $validated['c_full_ads'] = $request->web_full_ad;
                $validated['c_full_ads_name'] = $request->web_full_ad_name;

                if ($request->wwid) {
                    Ad::where('id', $request->wwid)->update($validated);
                }

                // dd('shiv');
            }
        }

        if( ! empty($ad_types = $request->get('web_page_ad')) ) {
            $web_ad_pages = ['home', 'content_detail', 'blog_detail'];
            foreach( $web_ad_pages as $page ) {

                $data = $ad_types[$page] ?? [];

                DB::table('web_ad_screens')
                    ->updateOrInsert(
                        ['page' => $page],
                        [
                            'page' => $page,
                            'ads' => \implode(',', \array_keys($data)),
                            'created_at' => date('Y-m-d H:i'),
                            'updated_at' => date('Y-m-d H:i')
                        ]
                    );
            }

        }

        return response()->json('Ads Added Successfully');

    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
        $adsData = Ad::where('id', $id)->first();
        return view('admin.adsviews.edit', compact('adsData'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        if ($request->appgoogle == "appgoogle") {

            $data = [
                "g_ads_id" => $request->app_ad_id,
                "g_banner_ads" => $request->app_banner_id,
                "g_medium_ads" => $request->app_medium_id,
                "g_full_ads" => $request->app_full_id,
            ];

            Ad::where('id', $id)->update($data);
            return redirect()->route('admin.ads.index')->withSuccess('Ads Updated Successfully');
        }

        if ($request->webgoogle == "webgoogle") {
            $data = [
                "g_ads_id" => $request->web_ad_id,
                "g_banner_ads" => $request->web_banner_id,
                "g_medium_ads" => $request->web_medium_id,
                "g_full_ads" => $request->web_full_id,
            ];

            Ad::where('id', $id)->update($data);
            return redirect()->route('admin.ads.index')->withSuccess('Ads Updated Successfully');
        }

        if ($request->appcustom == "appcustom") {
            // echo "appcustom";

            $validated = $request->validate([
                'c_banner_ads' => [
                    'mimes:jpg,jpeg,png',
                    'dimensions:min_width=720,min_height=100',
                ],
                'c_medium_ads' => [
                    'mimes:jpg,jpeg,png',
                    'dimensions:min_width=350,min_height=300',
                ],

                'c_full_ads' => [
                    'mimes:jpg,jpeg,png',
                    'dimensions:min_width=1000,min_height=720',
                ],

            ],
                ['c_banner_ads.dimensions' => "The banner ad has invalid dimensions",
                    'c_medium_ads.dimensions' => "The medium ad has invalid dimensions",
                    'c_full_ads.dimensions' => "The full ad has invalid dimensions"],
            );

            if ($request->hasFile('c_banner_ads')) {
                $c_banner_ads = $request->c_banner_ads->store('ads/app/custom', 'public');
                $validated['c_banner_ads'] = $c_banner_ads;
            }
            // dd($validated['c_banner_ads']);
            if ($request->hasFile('c_medium_ads')) {
                $c_medium_ads = $request->c_medium_ads->store('ads/app/custom', 'public');
                $validated['c_medium_ads'] = $c_medium_ads;
            }

            if ($request->hasFile('c_full_ads')) {
                $c_full_ads = $request->c_full_ads->store('ads/app/custom', 'public');
                $validated['c_full_ads'] = $c_full_ads;
            }

            $validated['c_banner_ads_name'] = $request->app_banner_ad_name;
            $validated['c_medium_ads_name'] = $request->app_medium_ad_name;
            $validated['c_full_ads_name'] = $request->app_full_ad_name;

            Ad::where('id', $id)->update($validated);
            return redirect()->route('admin.ads.index')->withSuccess('Ads Updated Successfully');

        }
        if ($request->webcustom == "webcustom") {

            $validated = $request->validate([
                'c_banner_ads' => [
                    'mimes:jpg,jpeg,png',
                    'dimensions:min_width=720,min_height=100',
                ],
                'c_medium_ads' => [
                    'mimes:jpg,jpeg,png',
                    'dimensions:min_width=350,min_height=300',
                ],

                'c_full_ads' => [
                    'mimes:jpg,jpeg,png',
                    'dimensions:min_width=1000,min_height=720',
                ],

            ],
                ['c_banner_ads.dimensions' => "The banner ad has invalid dimensions",
                    'c_medium_ads.dimensions' => "The medium ad has invalid dimensions",
                    'c_full_ads.dimensions' => "The full ad has invalid dimensions"],
            );

            if ($request->hasFile('c_banner_ads')) {
                $c_banner_ads = $request->c_banner_ads->store('ads/app/custom', 'public');
                $validated['c_banner_ads'] = $c_banner_ads;
            }
            // dd($validated['c_banner_ads']);
            if ($request->hasFile('c_medium_ads')) {
                $c_medium_ads = $request->c_medium_ads->store('ads/app/custom', 'public');
                $validated['c_medium_ads'] = $c_medium_ads;
            }

            if ($request->hasFile('c_full_ads')) {
                $c_full_ads = $request->c_full_ads->store('ads/app/custom', 'public');
                $validated['c_full_ads'] = $c_full_ads;
            }

            $validated['c_banner_ads_name'] = $request->web_banner_ad_name;
            $validated['c_medium_ads_name'] = $request->web_medium_ad_name;
            $validated['c_full_ads_name'] = $request->web_full_ad_name;

            Ad::where('id', $id)->update($validated);
            return redirect()->route('admin.ads.index')->withSuccess('Ads Updated Successfully');
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
    public function changeShowStatus(Request $request)
    {
        // dd($request->status);
        // $adscreen = AdScreen::find($request->screen_id);
        // $adscreen->status = $request->status;
        // $adscreen->save();
        DB::table('ads')->update(['is_enable' => $request->status]);

        // $arr = array('message' => 'Status Updated Successfully', 'title' => 'Status');
        // return json_encode($arr);

        // return response()->json(['success'=>'Status change successfully.']);
    }
}