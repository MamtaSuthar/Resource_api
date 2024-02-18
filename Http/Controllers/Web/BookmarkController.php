<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\UserBookmark;
use Illuminate\Support\Facades\Validator;
use URL;
use App\Models\Magazine;
use App\Models\Newspaper;
use App\Models\Blog;
use App\Traits\CommonTrait  ;


class BookmarkController extends Controller
{
    //
    use CommonTrait;
    public function set_bookmark(Request $request){
        // dd($request->all());
        $user = $this->user();
        $bookmark = UserBookmark::where('user_id',$user->id)
                    ->where('pid',$request->id)            
                    ->where('type',$request->type)            
                    ->first();  
        $bookData = [];
        if(!empty($bookmark)){
            UserBookmark::where('id',$bookmark->id)->delete();
            return response()->json([
                'success' => 'Bookmark removed successfully!'
            ]);
        }else{
            $book_mark['user_id'] = $user->id;
            $book_mark['pid'] = $request->id;
            $book_mark['type'] = $request->type;
            UserBookmark::create($book_mark);
            return response()->json([
                'success' => 'Bookmark added successfully!'
            ]);
            // return response()->json('Bookmark added successfully');

        }

    }
    public function getbookmarksCommondata($data,$type){
        $ndata['id'] = $data->id;
        $ndata['title'] = $data->title;
        $ndata['price'] = $data->price;
        $ndata['thumbnail_image'] = asset("storage/{$data->cover_image}");
        $ndata['cover_image'] = asset("storage/{$data->cover_image}");
        $ndata['bookmark_type'] = $type;
        $ndata['currency'] = auth()->user()->my_currency;
        return $ndata;
    }
    public function bookmarksList()
    {
        // $bookmarks = UserBookmark::where('user_id',auth()->user()->id)->latest()->get();
        // $json = [];
        // if($bookmarks->isNotEmpty()){
        //     foreach ($bookmarks as $key => $value) {
        //         switch($value->type){
        //             case 'newspaper' :
        //                 $news = Newspaper::where('id',$value->pid)->first();
        //                 if(!empty($news)){
        //                     $news = $this->getbookmarksCommondata($news,'newspaper');
        //                     $json[] = $news;
        //                 }
        //                 break;

        //             case 'magazine' :
        //                 $magz = Magazine::where('id',$value->pid)->first();
        //                 if(!empty($magz)){
        //                     $magz = $this->getbookmarksCommondata($magz,'magazine');
        //                     $json[] = $magz;
        //                 }
        //                 break;
                    
        //             default:
        //                 break;
        //         }
        //     }
        // }
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

        $bnews = (auth()->user())?UserBookmark::where('user_id',auth()->user()->id)->where('type','newspaper')->pluck('pid')->all() : [];
        $bmags = (auth()->user())?UserBookmark::where('user_id',auth()->user()->id)->where('type','magazine')->pluck('pid')->all() : [];

        $btopstory = (auth()->user())?UserBookmark::where('user_id',auth()->user()->id)->where('type','top_story')->pluck('pid')->all() : [];
        $bpromoted = (auth()->user())?UserBookmark::where('user_id',auth()->user()->id)->where('type','popular_content')->pluck('pid')->all() : [];
        
        // $bookDatas[] = collect($json);
        // dd(collect($bdata)->values());
        return view('customer.bookmark.index')->with(['bookDatas'=>collect($bdata)->values(),'bpromoted'=>$bpromoted,'btopstory'=>$btopstory,'bmags'=>$bmags,'bnews'=>$bnews]);
        // return ApiResponse::ok('Bookmarks Listing',collect($json));
    }
}
