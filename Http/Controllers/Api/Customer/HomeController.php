<?php

namespace App\Http\Controllers\Api\Customer;

use App\Api\ApiResponse;
use App\Http\Controllers\ApiController;
use App\Http\Resources\AlbumResource;
use App\Http\Resources\BlogResource;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\GalleryResource;
use App\Http\Resources\MagazineResource;
use App\Http\Resources\NewspaperResource;
use App\Http\Resources\PodcastResource;
use App\Http\Resources\TagResource;
use App\Http\Resources\VideoResource;
use App\Models\Ad;
use App\Models\AdScreen;
use App\Models\Albums;
use App\Models\Blog;
use App\Models\Category;
use App\Models\CouponCode;
use App\Models\Gallery;
use App\Models\Magazine;
use App\Models\Newspaper;
use App\Models\Podcast;
use App\Models\Position;
use App\Models\Tag;
use App\Models\User;
use App\Models\UserBookmark;
use App\Models\UserInfo;
use App\Models\UserUsedCoupon;
use App\Models\Video;
use App\Traits\CommonTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use URL;

class HomeController extends ApiController
{
    use CommonTrait;

    const HOMPAGE_DATA_PAGE_LIMIT = 12;

    private function getNewsPapers()
    {
        $magazines = Magazine::active()->latest()->get()
            ->map(function ($magazines) {
                return [
                    'id' => $magazines->id,
                    'title' => $magazines->title ?? null,
                    'thumbnail_image' => asset("storage/{$magazines->thumbnail_image}"),
                ];
            });

        return $magazines;
    }

    private function getContents($is_story)
    {
        $titles = ['Gov\'t donate GH¢470,000.00 to DOL Clubs', 'Gov\'t donate GH¢730,000.00 to DOL Clubs', 'Gov\'t donate GH¢222,000.00 to DOL Clubs', 'Gov’t donate GH¢777,000.00 to DOL Clubs', 'Gov\'t donate GH¢334,000.00 to DOL Clubs', 'Gov\'t donate GH¢120,000.00 to DOL Clubs'];
        $categories = ['Football', 'Business', 'Lifestyle', 'News', 'Fashion', 'Health', 'Entertainment', 'Art', 'Travel', 'Technology'];

        $stories = [];
        for ($i = 1; $i < 7; $i++) {
            $number = rand(1, 3);
            $stories[] = [
                'title' => $titles[rand(0, 5)],
                'category' => $categories[rand(0, 9)],
                'date' => now()->format('d-m-Y'),
                'image' => !$is_story
                ? asset("assets/frontend/img/p{$number}.jpg")
                : asset("assets/frontend/img/ts{$number}.jpg"),
                'link' => null,
            ];
        }

        return $stories;
    }

    public function setBookmark(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => ['required'],
            'type' => ['required'],
        ]);

        if ($validator->fails()) {
            return $this->validation_error_response($validator);
        }
        $user = $this->user();
        $bookmark = UserBookmark::where('user_id', $user->id)
            ->where('pid', $request->id)
            ->where('type', $request->type)
            ->first();
        $bookData = [];
        if (!empty($bookmark)) {
            UserBookmark::where('id', $bookmark->id)->delete();
            return ApiResponse::simple('Bookmark Removed');
        } else {
            $bm['user_id'] = $user->id;
            $bm['pid'] = $request->id;
            $bm['type'] = $request->type;
            UserBookmark::create($bm);
            return ApiResponse::simple('Bookmark Added');
        }
    }

    public function savePreferences(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'topics' => ['required'],
        ]);

        if ($validator->fails()) {
            return $this->validation_error_response($validator);die;
        }
        // $user = UserInfo::where('user_id',auth()->user()->id);
        $topics = $request->topics;
        // dd($user);
        UserInfo::where('user_id', auth()->user()->id)->update(['favourite_topics' => $topics]);
        // $user->favourite_topics = $topics;
        // $user->save();
        return ApiResponse::ok('Preferences Saved');
    }

    // ===================================================
    // Testing functions
    // ===================================================

    public function index()
    {
        $user = $this->user();
        # Get Image Galleries
        $galleries = Gallery::active()->latest()->take(self::HOMPAGE_DATA_PAGE_LIMIT)->get();
        $albums = Albums::active()->latest()->take(self::HOMPAGE_DATA_PAGE_LIMIT)->get();

        # Get Podcasts
        $podcasts = Podcast::active()->latest()->take(self::HOMPAGE_DATA_PAGE_LIMIT)->get();

        # Get Video Galleries
        // $videos = $user
        //     ? Video::active()->latest()->take(self::HOMPAGE_DATA_PAGE_LIMIT)->get()
        //     : [];
        
         $videos =  Video::active()->latest()->take(self::HOMPAGE_DATA_PAGE_LIMIT)->get();

        # Get Categories
        $categories = [];
        if( $user ) {
            $topics = $this->getUserPreferances($user->id);
        } else {
            $topics = [];
        }
        if (!empty($topics)) {
            $categories = Category::where('popular', 1)->whereIn('id', $topics);
            // $tags = Tag::whereIn('id',$topics)->latest()->get();
        } else {
            $categories = Category::where('popular', 1);
        }

        $categories = $categories->active()->latest()->get();

        $categories_new = collect([]);

        foreach( \App\Models\Blog::latest_category_ids() as $cat_id ) {
            if( $cat = $categories->firstWhere('id', $cat_id) ) {
                $categories_new->push($cat);
            }
        }

        if( $categories_new->isNotEmpty() ) {
            $categories = $categories_new;
        }

        # Get Unpopular Categories
        $unpopular_categories = Category::where('popular', 0)->active()->latest()->get();

        # Get Magazines
        if(!empty($topics)){
        $magazines = Magazine::whereIn('category_id',$topics)->active()->take(self::HOMPAGE_DATA_PAGE_LIMIT)->latest()->get();
         }else{
        $magazines = Magazine::active()->take(self::HOMPAGE_DATA_PAGE_LIMIT)->latest()->get();

         }
        $favouritePost = $magazines->position = "2";
        $magazines->union($favouritePost);

        // $instadata = $this->instaDataWeb();

        # Get Newspapers
        if(!empty($topics))
        $newspapers = Newspaper::whereIn('category_id',$topics)->active()->take(self::HOMPAGE_DATA_PAGE_LIMIT)->latest()->get();
        else
        $newspapers = Newspaper::active()->take(self::HOMPAGE_DATA_PAGE_LIMIT)->latest()->get();

    // dd($topics);
        # Get Popular Content
        if(!empty($topics)){
        $promoted_content = Blog::whereIn('blog_category_id',$topics)->where('promoted', 1)->active()->take(self::HOMPAGE_DATA_PAGE_LIMIT)->latest()->get();
        }
        else{
        $promoted_content = Blog::where('promoted', 1)->active()->take(self::HOMPAGE_DATA_PAGE_LIMIT)->latest()->get();
        }

        # Get Top Story
        if(!empty($topics)){
        $top_story = Blog::whereIn('blog_category_id',$topics)->where('top_story', 1)->active()->take(self::HOMPAGE_DATA_PAGE_LIMIT)->latest()->get();
        }else{
            $top_story =Blog::where('top_story', 1)->active()->take(self::HOMPAGE_DATA_PAGE_LIMIT)->latest()->get();
        }
        $trend_news = $top_story->first();
        $trend_news = $trend_news ? ['title' => $trend_news->title] : [];


        # Get Magazines
        $tags = Tag::latest()->get();
        // $googleAds = Ad::getGoogleAds();
        // $customAds = Ad::getCustomAds();
        $custom = Ad::where('ads_type', 'App')->where('preffered_type', 'Custom')->where('enable_ads', 1)->select('id as id', 'preffered_type as type', 'c_banner_ads as banner_ads', 'c_medium_ads as medium_ads', 'c_full_ads as full_ads', 'is_enable as is_enable', 'banner_ads_url', 'medium_ads_url', 'full_ads_url')->first();

        if ($custom) {
            $custom->banner_ads = isset($custom['banner_ads']) ? (URL::to('/') . '/storage/' . $custom['banner_ads']) : null;

            $custom->medium_ads = isset($custom['medium_ads']) ? (URL::to('/') . '/storage/' . $custom['medium_ads']) : null;

            $custom->full_ads = isset($custom['full_ads']) ? (URL::to('/') . '/storage/' . $custom['full_ads']) : null;
            $custom->is_enable = ($custom['is_enable']);
            $custom->banner_ads_url = isset($custom['banner_ads_url']) ? $custom['banner_ads_url'] : route('home');
            $custom->medium_ads_url = isset($custom['medium_ads_url']) ? $custom['medium_ads_url'] : route('home');
            $custom->full_ads_url = isset($custom['full_ads_url']) ? $custom['full_ads_url'] : route('home');
        }

        $google = Ad::where('ads_type', 'App')->where('preffered_type', 'Google')->where('enable_ads', 1)->select('id as id', 'preffered_type as type', 'g_banner_ads as banner_ads', 'g_medium_ads as medium_ads', 'g_full_ads as full_ads', 'is_enable as is_enable', 'banner_ads_url', 'medium_ads_url', 'full_ads_url','g_banner_ads_ios as banner_ads_ios', 'g_medium_ads_ios as medium_ads_ios', 'g_full_ads_ios as full_ads_ios')->first();
        if ($google) {
            // $google->banner_ads_ios = 'ca-app-pub-3940256099942544/2934735716';
            // $google->medium_ads_ios = '';
            // $google->full_ads_ios = 'ca-app-pub-3940256099942544/4411468910';
            $google->is_enable = ($google->is_enable);
            $google->banner_ads_url = route('home');
            $google->medium_ads_url = route('home');
            $google->full_ads_url = route('home');
        }

        #slider Api for top and promoted content
        $blogs = [];
        $lastBlogRec = Blog::orwhere('promoted',1)->orwhere('top_story',1)->orderBy('id', 'desc')->take(6)->active()->latest()->get();
        foreach ($lastBlogRec as $blog) {
            // $blog['content_image']=isset($blog['content_image']) ? (URL::to('/').'/storage/'.$blog['content_image']) : null;

            $blog['content_image'] = strpos($blog['content_image'], 'http') !== 0
            ? asset("storage/" . $blog['content_image'])
            : $blog['content_image'];

            array_push($blogs, $blog);

        }

        #Ads Screen Api
        #for fullads Datas
        $fullads = AdScreen::where('type', 'full_ads')->where('status', 1)->get();
        $fulladsData = [];
        foreach ($fullads as $value) {
            // array_push($fulladsData,implode('',$value->name));
            // $fulladsData = $fulladsData .''. $value->name.',';
            array_push($fulladsData, $value->name);

        }
         #for Mediumads Datas
        $mediumads = AdScreen::where('type', 'medium_ads')->where('status', 1)->get();
        $mediumadsData = [];
        foreach ($mediumads as $value) {
            // array_push($fulladsData,implode('',$value->name));
            // $fulladsData = $fulladsData .''. $value->name.',';
            array_push($mediumadsData, $value->name);

        }
        // $rem_comma = substr_replace($fulladsData ,"", -1);
        // $fulls_datas =explode(" ",$rem_comma);

        #for bannerads datas
        $banneradsData = [];
        $bannerads = AdScreen::where('type', 'banner_ads')->where('status', 1)->get();
        foreach ($bannerads as $value) {
            // array_push($fulladsData,implode('',$value->name));
            // $banneradsData = $banneradsData .''. $value->name.',';
            array_push($banneradsData, $value->name);

        }
        // $rem_comma_banner = substr_replace($banneradsData ,"", -1);
        // $banners_datas =explode(" ",$rem_comma_banner);
        #Position
        $positions = Position::all();
        $filterd = $positions->sortBy('position');
        # Finally Return Data
        $data = [
            'trending_news' => $trend_news,
            'positions' => $filterd->values(),
            'galleries' => GalleryResource::collection($galleries),
            'albums' => AlbumResource::collection($albums),
            'podcasts' => PodcastResource::collection($podcasts),
            'videos' => VideoResource::collection($videos),
            'categories' => CategoryResource::collection($categories),
            'unpopular_categories' => CategoryResource::collection($unpopular_categories),
            'newspapers' => NewspaperResource::collection($newspapers),
            'instagramFeed' => collect($this->instaDataWeb())->take(10),
            'magazines' => MagazineResource::collection($magazines),
            'popular_contents' => BlogResource::collection($promoted_content),
            'top_stories' => BlogResource::collection($top_story),
            'topics' => TagResource::collection($tags),
            'subscribe' => false ?? true,
            'ads' => $custom ?? $google,
            'adsScreens' => ['mediumads'=> array_values($mediumadsData),'fullads' => array_values($fulladsData), 'bannerads' => array_values($banneradsData)],

            // 'ads'                     => ["Google"=>$googleAds,"Custom"=>$customAds],
            'slider' => $blogs,

        ];
        # // 'ads'                     => ["Google"=>$googleAds,"Custom"=>$customAds],
        return ApiResponse::ok('Home Page Data', $data);
    }
    
    public function index_dev()
    {
        $user = $this->user();
        # Get Image Galleries
        $galleries = Gallery::active()->latest()->take(self::HOMPAGE_DATA_PAGE_LIMIT)->get();
        $albums = Albums::active()->latest()->take(self::HOMPAGE_DATA_PAGE_LIMIT)->get();

        # Get Podcasts
        $podcasts = Podcast::active()->latest()->take(self::HOMPAGE_DATA_PAGE_LIMIT)->get();

        # Get Video Galleries
        // $videos = $user
        //     ? Video::active()->latest()->take(self::HOMPAGE_DATA_PAGE_LIMIT)->get()
        //     : [];
        
         $videos =  Video::active()->latest()->take(self::HOMPAGE_DATA_PAGE_LIMIT)->get();

        # Get Categories
        $categories = [];
        if( $user ) {
            $topics = $this->getUserPreferances($user->id);
        } else {
            $topics = [];
        }
        if (!empty($topics)) {
            $categories = Category::where('popular', 1)->whereIn('id', $topics);
            // $tags = Tag::whereIn('id',$topics)->latest()->get();
        } else {
            $categories = Category::where('popular', 1);
        }

        $categories = $categories->active()->latest()->get();

        $categories_new = collect([]);

        foreach( \App\Models\Blog::latest_category_ids() as $cat_id ) {
            if( $cat = $categories->firstWhere('id', $cat_id) ) {
                $categories_new->push($cat);
            }
        }

        if( $categories_new->isNotEmpty() ) {
            $categories = $categories_new;
        }

        # Get Unpopular Categories
        $unpopular_categories = Category::where('popular', 0)->active()->latest()->get();

        # Get Magazines
        if(!empty($topics)){
        $magazines = Magazine::whereIn('category_id',$topics)->active()->take(self::HOMPAGE_DATA_PAGE_LIMIT)->latest()->get();
         }else{
        $magazines = Magazine::active()->take(self::HOMPAGE_DATA_PAGE_LIMIT)->latest()->get();

         }
        $favouritePost = $magazines->position = "2";
        $magazines->union($favouritePost);

        // $instadata = $this->instaDataWeb();

        # Get Newspapers
        if(!empty($topics))
        $newspapers = Newspaper::whereIn('category_id',$topics)->active()->take(self::HOMPAGE_DATA_PAGE_LIMIT)->latest()->get();
        else
        $newspapers = Newspaper::active()->take(self::HOMPAGE_DATA_PAGE_LIMIT)->latest()->get();

        # Get Popular Content
        if(!empty($topics)){
        $promoted_content = Blog::whereIn('blog_category_id',$topics)->where('promoted', 1)->active()->take(self::HOMPAGE_DATA_PAGE_LIMIT)->latest()->get();
        }
        else{
        $promoted_content = Blog::where('promoted', 1)->active()->take(self::HOMPAGE_DATA_PAGE_LIMIT)->latest()->get();
        }

        # Get Top Story
        if(!empty($topics)){
        $top_story = Blog::whereIn('blog_category_id',$topics)->where('top_story', 1)->active()->take(self::HOMPAGE_DATA_PAGE_LIMIT)->latest()->get();
        }else{
            $top_story =Blog::where('top_story', 1)->active()->take(self::HOMPAGE_DATA_PAGE_LIMIT)->latest()->get();
        }
        $trend_news = $top_story->first();
        $trend_news = $trend_news ? ['title' => $trend_news->title] : [];


        # Get Magazines
        $tags = Tag::latest()->get();
        // $googleAds = Ad::getGoogleAds();
        // $customAds = Ad::getCustomAds();
        $custom = Ad::where('ads_type', 'App')->where('preffered_type', 'Custom')->where('enable_ads', 1)->select('id as id', 'preffered_type as type', 'c_banner_ads as banner_ads', 'c_medium_ads as medium_ads', 'c_full_ads as full_ads', 'is_enable as is_enable', 'banner_ads_url', 'medium_ads_url', 'full_ads_url')->first();

        if ($custom) {
            $custom->banner_ads = isset($custom['banner_ads']) ? (URL::to('/') . '/storage/' . $custom['banner_ads']) : null;

            $custom->medium_ads = isset($custom['medium_ads']) ? (URL::to('/') . '/storage/' . $custom['medium_ads']) : null;

            $custom->full_ads = isset($custom['full_ads']) ? (URL::to('/') . '/storage/' . $custom['full_ads']) : null;
            $custom->is_enable = ($custom['is_enable']);
            $custom->banner_ads_url = isset($custom['banner_ads_url']) ? $custom['banner_ads_url'] : route('home');
            $custom->medium_ads_url = isset($custom['medium_ads_url']) ? $custom['medium_ads_url'] : route('home');
            $custom->full_ads_url = isset($custom['full_ads_url']) ? $custom['full_ads_url'] : route('home');
        }

        $google = Ad::where('ads_type', 'App')->where('preffered_type', 'Google')->where('enable_ads', 1)->select('id as id', 'preffered_type as type', 'g_banner_ads as banner_ads', 'g_medium_ads as medium_ads', 'g_full_ads as full_ads', 'is_enable as is_enable', 'banner_ads_url', 'medium_ads_url', 'full_ads_url','g_banner_ads_ios as banner_ads_ios', 'g_medium_ads_ios as medium_ads_ios', 'g_full_ads_ios as full_ads_ios')->first();
        if ($google) {
            // $google->banner_ads_ios = 'ca-app-pub-3940256099942544/2934735716';
            // $google->medium_ads_ios = '';
            // $google->full_ads_ios = 'ca-app-pub-3940256099942544/4411468910';
            $google->is_enable = ($google->is_enable);
            $google->banner_ads_url = route('home');
            $google->medium_ads_url = route('home');
            $google->full_ads_url = route('home');
        }

        #slider Api for top and promoted content
        $blogs = [];
        $lastBlogRec = Blog::orwhere('promoted',1)->orwhere('top_story',1)->orderBy('id', 'desc')->take(6)->active()->latest()->get();
        foreach ($lastBlogRec as $blog) {
            // $blog['content_image']=isset($blog['content_image']) ? (URL::to('/').'/storage/'.$blog['content_image']) : null;

            $blog['content_image'] = strpos($blog['content_image'], 'http') !== 0
            ? asset("storage/" . $blog['content_image'])
            : $blog['content_image'];

            array_push($blogs, $blog);

        }

        #Ads Screen Api
        #for fullads Datas
        $fullads = AdScreen::where('type', 'full_ads')->where('status', 1)->get();
        $fulladsData = [];
        foreach ($fullads as $value) {
            // array_push($fulladsData,implode('',$value->name));
            // $fulladsData = $fulladsData .''. $value->name.',';
            array_push($fulladsData, $value->name);

        }
         #for Mediumads Datas
        $mediumads = AdScreen::where('type', 'medium_ads')->where('status', 1)->get();
        $mediumadsData = [];
        foreach ($mediumads as $value) {
            // array_push($fulladsData,implode('',$value->name));
            // $fulladsData = $fulladsData .''. $value->name.',';
            array_push($mediumadsData, $value->name);

        }
        // $rem_comma = substr_replace($fulladsData ,"", -1);
        // $fulls_datas =explode(" ",$rem_comma);

        #for bannerads datas
        $banneradsData = [];
        $bannerads = AdScreen::where('type', 'banner_ads')->where('status', 1)->get();
        foreach ($bannerads as $value) {
            // array_push($fulladsData,implode('',$value->name));
            // $banneradsData = $banneradsData .''. $value->name.',';
            array_push($banneradsData, $value->name);

        }
        // $rem_comma_banner = substr_replace($banneradsData ,"", -1);
        // $banners_datas =explode(" ",$rem_comma_banner);
        #Position
        $positions = Position::all();
        $filterd = $positions->sortBy('position');
        # Finally Return Data
        // echo "<pre>";print_r($newspapers);die;
        $data = [
            'trending_news' => $trend_news,
            'positions' => $filterd->values(),
            'galleries' => GalleryResource::collection($galleries),
            'albums' => AlbumResource::collection($albums),
            'podcasts' => PodcastResource::collection($podcasts),
            'videos' => VideoResource::collection($videos),
            'categories' => CategoryResource::collection($categories),
            'unpopular_categories' => CategoryResource::collection($unpopular_categories),
            'newspapers' => NewspaperResource::collection($newspapers),
            'instagramFeed' => collect($this->instaDataWeb())->take(10),
            'magazines' => MagazineResource::collection($magazines),
            'popular_contents' => BlogResource::collection($promoted_content),
            'top_stories' => BlogResource::collection($top_story),
            'topics' => TagResource::collection($tags),
            'subscribe' => false ?? true,
            'ads' => $custom ?? $google,
            'adsScreens' => ['mediumads'=> array_values($mediumadsData),'fullads' => array_values($fulladsData), 'bannerads' => array_values($banneradsData)],

            // 'ads'                     => ["Google"=>$googleAds,"Custom"=>$customAds],
            'slider' => $blogs,

        ];
        # // 'ads'                     => ["Google"=>$googleAds,"Custom"=>$customAds],
        return ApiResponse::ok('Home Page Data', $data);
    }

    public function getBookmarks_dev()
    {
        $bookmarks = UserBookmark::where('user_id', auth()->user()->id)->latest()->get();
        $json = [];
        if ($bookmarks->isNotEmpty()) {
            foreach ($bookmarks as $key => $value) {
                switch ($value->type) {
                    case 'newspaper':
                        $news = Newspaper::where('id', $value->pid)->first();
                        if (!empty($news)) {
                            $news = $this->getbookmarksCommondata($news, 'newspaper');
                            $json[] = $news;
                        }
                        break;

                    case 'magazine':
                        $magz = Magazine::where('id', $value->pid)->first();
                        if (!empty($magz)) {
                            $magz = $this->getbookmarksCommondata($magz, 'magazine');
                            $json[] = $magz;
                        }
                        break;

                    default:
                        break;
                }
            }
        }
        return ApiResponse::ok('Bookmarks Listing', collect($json));
    }

    public function getBookmarks_dev2()
    {
        $bookmarks = UserBookmark::where('user_id', auth()->user()->id)->latest()->get();
        $json = [];
        $bdata = [];
        if ($bookmarks->isNotEmpty()) {
            foreach ($bookmarks as $key => $value) {
                switch ($value->type) {
                    case 'newspaper':
                        $news = Newspaper::where('id', $value->pid)->first();
                        if (!empty($news)) {
                            $news = $this->getbookmarksCommondata($news, 'newspaper');
                            $json['newspaper'][] = $news;
                        }
                        break;

                    case 'magazine':
                        $magz = Magazine::where('id', $value->pid)->first();
                        if (!empty($magz)) {
                            $magz = $this->getbookmarksCommondata($magz, 'magazine');
                            $json['magazine'][] = $magz;
                        }
                        break;
                    case 'popular_content':
                        $popular = Blog::where('id',$value->pid)->first();
                        // dd($popular);
                        if(!empty($popular)){
                            $popular = $this->getBlogsdata($popular, 'popular_content');
                            $json['popular_content'][] = $popular;
                        }
                        break;
                    case 'top_story':
                        $top_story = Blog::where('id',$value->pid)->first();
                        if(!empty($top_story)){
                            $top_story = $this->getBlogsdata($top_story, 'top_story');
                            $json['top_story'][] = $top_story;
                        }
                        break;
                    default:
                        break;
                }
            }
            $types = ['magazine'=>'Magazine','newspaper'=>'Newspaper','popular_content'=>'Popular Content','top_story'=>'Top Story'];
            $i=0;
            foreach ($types as $bkey => $type) {
                if(isset($json[$bkey])){
                    $bdata[$i]['name'] = $type;
                    $bdata[$i]['key'] = $bkey;
                    $bdata[$i]['rss_content'] = (in_array($bkey,['popular_content','top_story']))?true:false;
                    $bdata[$i]['data']=$json[$bkey];
                    $i++;
                }
                
            }
        }

        // $json['magazine'] = (isset($json['magazine']))?$json['magazine']:[];
        // $json['newspaper'] = (isset($json['newspaper']))?$json['newspaper']:[];
        // $json['popular_content'] = (isset($json['popular_content']))?$json['popular_content']:[];
        // $json['top_story'] = (isset($json['top_story']))?$json['top_story']:[];
        // dd($json);
        return ApiResponse::ok('Bookmarks Listing', collect($bdata)->values());
    }

    public function homeSearching(Request $request)
    {
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $getArr = ['id', 'title', 'price', 'thumbnail_image', 'cover_image'];
            $data = $this->getMagzineOrNewsByQuery($getArr, $search);
            $datacount = count($data);
            return ApiResponse::ok($datacount . ' Details found for search', $data);
        } else {
            return ApiResponse::ArraynotFound('Enter title of Magzines or Newspaper');
        }
    }
    public function homeSearching2(Request $request)
    {
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $getArr = ['id', 'title', 'price', 'thumbnail_image', 'cover_image'];
            $data = $this->getMagzineOrNewsByQuery2($getArr, $search);
            $datacount = count($data);
            return ApiResponse::ok($datacount . ' Details found for search', $data);
        } else {
            return ApiResponse::ArraynotFound('Enter title of Magzines or Newspaper');
        }
    }

    public function couponList()
    {
        // dd(auth()->user()->id);
        $user = $this->user();
        // $coupons = $user->myCoupons()->where('used_times', '>', 0)->get();
        $coupons = CouponCode::query()
            ->where('valid_for', '>', 0)
            ->where(function($query) use($user) {
                $query->whereNull('user_id')
                    ->orWhere('user_id', $user->id);
            })
            ->whereRaw('NOW() < DATE_ADD(`created_at`, INTERVAL `valid_for` DAY)')
            ->get();

        if ($coupons->isNotEmpty()) {
            $coupon = [];
            foreach ($coupons as $key => $value) {
                $value->type = ($value->type == 1) ? 'Percentage' : 'Amount';
                $coupon[] = $value;
            }

            if (!empty($coupon)) {
                return ApiResponse::ok('Coupons available', $coupon);
            }
        }
        return ApiResponse::simpleArraynotFound('Coupons not available');
    }

    public function applyCoupon(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code' => ['required', 'max:191'],
            'amount' => ['required', 'numeric'],
        ]);

        if ($validator->fails()) {
            return $this->validation_error_response($validator);
        }

        $couponCode = $request->get('code');

        $coupon = CouponCode::checkCode(strtoupper($couponCode));
        
        if (!empty($coupon) && ($user = $this->user())) {

            $amount = floatval($request->get('amount'));

            $_discount = $coupon['discount'] == 'amount'
                ? to_price( $amount -  floatval($coupon['discount']))
                : to_price( $amount - (($amount * floatval($coupon['discount'])) / 100) );

            $data = ['amount' => $_discount];

            $now = date('Y-m-d H:i');

            $user->user_used_coupons()->updateOrCreate(
                ['code' => $couponCode, 'user_id' => $user->id],
                ['code' => $couponCode, 'original_amt' => $amount, 'created_at' => $now, 'updated_at' => $now ]
            );

            return ApiResponse::ok('Coupon applied Successfully', $data);
        }

        return ApiResponse::notFound('Invalid Coupon code used.');
    }

    public function removeCoupon(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code' => ['required', 'max:191'],
            'amount' => ['nullable', 'numeric']
        ]);

        if ($validator->fails()) {
            return $this->validation_error_response($validator);
        }

        $couponCode = $request->code;

        if( $user = $this->user() ) {
            $user_coupon = $user->user_used_coupons()->where('code', $couponCode)
                ->first();

            if( $user_coupon ) {

                $amount = $user_coupon->original_amt;

                if( !$amount && $request->has('amount') ) {
                    $coupon = $user_coupon->coupon;
                    $amount = $amount - (($amount * floatval($coupon->discount)) / 100);
                }

                $user_coupon->delete();

                $data = ['amount' => $amount ? to_price($amount) : '0.00'];

                return ApiResponse::ok('Coupon Removed Successfully', $data);
            }
        }

        return ApiResponse::notFound('Invalid Coupon code used');
    }

    public function publication_list(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'category_id' => ['required'],
            'type' => ['required'],
        ]);
        if ($validator->fails()) {
            return $this->validation_error_response($validator);
        }
        if (in_array($request->type, ['newspaper', 'magazine'])) {
            $checktype = $this->getPublicationsByCategory($request->category_id, $request->type);
            if (!empty($checktype)) {
                return ApiResponse::ok('Publications', $checktype);
            } else {
                return ApiResponse::ArraynotFound('Publications not found');
            }

        } else {
            return ApiResponse::notFound('Invalid Type Used');
        }

    }

}