<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Blog;
use App\Models\UserBookmark;

class BlogController extends Controller
{
    public function blogpost(Blog $blog)
    {
        if( $blog->top_story ) {
            return $this->details($blog);
        }

        return $this->detailsContent($blog);
    }

    public function details(Blog $blog){
        // if( !$blog->top_story ) abort(404);

        // $top_story = Blog::with('blog_category')->where('id',$blog->id)->where('top_story',1)->first();
        $top_story = $blog;
        $btopstory = (auth()->user())?UserBookmark::where('user_id',auth()->user()->id)->where('type','top_story')->pluck('pid')->all() : [];
        $cc = app(\App\Http\Controllers\Api\CommonController::class);
        $related = $cc->related_blogs('top_story', $blog->blog_category)->all();
        $related=collect($related)->where('id','<>',$blog->id)->values()->all();
        // $pro_ids=Blog::where('top_story',1)->take(1)->pluck('id');
        $prev_next = [
            intval(Blog::where('id', '<', $blog->id)->where('top_story',1)->max('id')),
            intval(Blog::where('id', '>', $blog->id)->where('top_story',1)->min('id'))
        ];

        return view('customer.topstory.show',compact('top_story','btopstory','related', 'blog', 'prev_next'));
    }
    public function detailsContent(Blog $blog){
        // if( !$blog->promoted ) abort(404);
        // $promoted = Blog::with('blog_category')->where('id',$blog->id)->where('promoted',1)->first();
        $promoted = $blog;
        $bpromoted = (auth()->user())?UserBookmark::where('user_id',auth()->user()->id)->where('type','popular_content')->pluck('pid')->all() : [];
        $cc = app(\App\Http\Controllers\Api\CommonController::class);

        $related = $cc->related_blogs('popular_content', $blog->blog_category)->all();
        $related = collect($related)->where('id','<>',$blog->id)->values()->all();

        $prev_next = [
            intval(Blog::where('id', '<', $blog->id)->where('promoted',1)->max('id')),
            intval(Blog::where('id', '>', $blog->id)->where('promoted',1)->min('id'))
        ];
       
        return view('customer.promoted.show',compact('promoted','bpromoted','related','prev_next', 'blog'));
    }
    public function blogDetails(Blog $blog){
        if( $blog->top_story == '1' ) {
            return $this->details($blog);
        }

        return $this->detailsContent($blog);
    }
    
    public function indexPromoted(){
        $promoted=Blog::with('blog_category')->where('promoted',1)
            ->active()->latest()->paginate(15);
        // dd(count($promoted));
        $bnews = (auth()->user())?UserBookmark::where('user_id',auth()->user()->id)->where('type','newspaper')->pluck('pid')->all() : [];
        $bmags = (auth()->user())?UserBookmark::where('user_id',auth()->user()->id)->where('type','magazine')->pluck('pid')->all() : [];
        $btopstory = (auth()->user())?UserBookmark::where('user_id',auth()->user()->id)->where('type','top_story')->pluck('pid')->all() : [];
        $bpromoted = (auth()->user())?UserBookmark::where('user_id',auth()->user()->id)->where('type','popular_content')->pluck('pid')->all() : [];
        return view('customer.promoted.index',compact('promoted','bpromoted'));
    }
    public function indexTopStory(){
        $topstory=Blog::with('blog_category')->where('top_story',1)->active()->latest()
            ->paginate(15);
        // dd(count($promoted));
        $bnews = (auth()->user())?UserBookmark::where('user_id',auth()->user()->id)->where('type','newspaper')->pluck('pid')->all() : [];
        $bmags = (auth()->user())?UserBookmark::where('user_id',auth()->user()->id)->where('type','magazine')->pluck('pid')->all() : [];
        $btopstory = (auth()->user())?UserBookmark::where('user_id',auth()->user()->id)->where('type','top_story')->pluck('pid')->all() : [];
        $bpromoted = (auth()->user())?UserBookmark::where('user_id',auth()->user()->id)->where('type','popular_content')->pluck('pid')->all() : [];
        return view('customer.topstory.index',compact('topstory','btopstory'));
    }
}
