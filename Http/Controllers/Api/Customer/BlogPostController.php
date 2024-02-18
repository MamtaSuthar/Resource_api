<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Api\ApiResponse;
use App\Http\Resources\BlogResource;
use App\Models\Blog;
use Illuminate\Contracts\Session\Session;

class BlogPostController extends Controller
{
    //
    private $limits = 100;

    public function promoted_content()
    {
        $user = $this->user();

        # Get Latest Promoted Content Listing
        $promoted_content =  Blog::where('promoted',1)->active()->latest();

        if( $user && !empty($categories = $user->get_info('favourite_topics')) ) {
            $categories = \json_decode($categories, true);
            $categories = \array_filter($categories);

            $promoted_content = $promoted_content->whereIn('blog_category_id', $categories);
        }

        $promoted_content = $promoted_content->paginate(15);

        //
        session()->put('type', 'popular_content');

        return ApiResponse::ok(
            'Promoted Content Listing ',
            BlogResource::collection($promoted_content) 
        );
    }

    public function view($id)
    {
        session()->put('type', 'popular_content');
        $promote_data=Blog::findorFail($id);
        $promote_details = new BlogResource($promote_data);

        $needs_sub = false;

        if( isset($this->_e_data) && isset($this->_e_data['user_blog_subscription']) ) {

            if( ! $this->_e_data['user_blog_subscription'] && $promote_data->is_premium == '1' ) {
                $content = \strip_tags($promote_data->content);
                $content = \strlen($content) > 200
                    ? \substr($content,0,200)."..." : $content;

                $promote_details->additional([
                    'content' => $content,
                    'needs_subscription' => true
                ]);

                $needs_sub = true;
            }
        }

        if( !$needs_sub ) {
            # c asked for combining short_desc and content for some reason
            $promote_details->additional([
                'content' =>
                    $promote_data->short_description.'<br/><hr/><br/>'.$promote_data->content
            ]);
        }

        $cc = app(\App\Http\Controllers\Api\CommonController::class);
       // dd($id);
        // $blog =  Blog::where('id',$id)->first();
        $blog = $promote_data;
        $related = $cc->related_blogs('popular_content', $blog->blog_category)->all();
        $ids=Blog::where('promoted',1)->get();
        $pro_ids =[];
        foreach ($ids as  $value) {
            // $list =implode(',', $value['id']);
            $concat = "$value->id";
            array_push($pro_ids,$concat);
        }
        // $List = implode(', ', $pro_ids);

        // dd($List);
        return ApiResponse::ok('Promoted Content Detail', [
            'promoted_content' => $promote_details,
            'related'=>collect($related)->where('id','<>',$blog->id)->values()->all(),
            'promoted_ids'=>$pro_ids
           
        ]);
        
    }

    
    public function top_story()
    {
        $user = $this->user();

        # Get Latest Top Story Listing
        $top_story = Blog::where('top_story',1)->active()->latest();

        if( $user && !empty($categories = $user->get_info('favourite_topics')) ) {
            $categories = \json_decode($categories, true);
            $categories = \array_filter($categories);

            $top_story = $top_story->whereIn('blog_category_id', $categories);
        }

        $top_story = $top_story->paginate(15);

        session()->put('type', 'top_story');

        $topstories = BlogResource::collection($top_story);
        return ApiResponse::ok(
            'Top Story Listing ',
            $topstories
        );
    }

    public function viewTopStory($id)
    {
        $top_story_data=Blog::findorFail($id);
        session()->put('type', 'top_story');
        $top_story = new BlogResource($top_story_data);

        $needs_sub = false;

        if( isset($this->_e_data) && isset($this->_e_data['user_blog_subscription']) ) {

            if( ! $this->_e_data['user_blog_subscription'] && $top_story_data->is_premium == '1' ) {
                $content = \strip_tags($top_story_data->content);
                $content = \strlen($content) > 200
                    ? \substr($content,0,200)."..." : $content;

                $top_story->additional([
                    'content' => $content,
                    'needs_subscription' => true
                ]);

                $needs_sub = true;
            }
        }

        if( !$needs_sub ) {
            # c asked for combining short_desc and content for some reason
            $top_story->additional([
                'content' =>
                    $top_story_data->short_description.'<br/><hr/><br/>'.$top_story_data->content
            ]);
        }

        $cc = app(\App\Http\Controllers\Api\CommonController::class);
        // $blog =  Blog::where('id',$id)->first();
        $blog = $top_story_data;
        $related = $cc->related_blogs('top_story', $blog->blog_category)->all();
        $ids=Blog::where('top_story',1)->get();
        $top_ids = [];
          foreach ($ids as  $value) {
            // $list =implode(',', $value['id']);
            $concat = "$value->id";
            array_push($top_ids,$concat);
        }

        return ApiResponse::ok('Top Story Detail', [
            'promoted_content' => $top_story,
            'related'=>collect($related)->where('id','<>',$blog->id)->values()->all(),
            'promoted_ids'=>$top_ids
        ]);
    }

}
