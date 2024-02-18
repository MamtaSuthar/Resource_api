<?php

namespace App\Http\Controllers\Admin;

use App\Models\Tag;
use App\Models\Blog;
use Illuminate\Support\Str;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Vars\Rss;
use Illuminate\Support\Facades\Cache;
class BlogController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        
        $blogs = Blog::latest()->get();
        return view('admin.blogs.index', compact('blogs'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        [$categories] = $this->getDataForForm();
        return view('admin.blogs.create', compact('categories'));
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
            'title'  => ['required','string','unique:blogs'],
            'blog_category_id'  => ['required'],
            'tags' => ['nullable','string'],
            'short_description' => ['nullable','string'],
            'content' => ['required','string'],
            'content_image' => [
                'required',
                'mimes:jpg,jpeg,png',
                'dimensions:min_width=1000'
            ],
            // 'thumbnail_image' => [
            //     'required',
            //     'mimes:jpg,jpeg,png',
            //     'dimensions:min_width=440,min_height=276'
            // ],
            // 'slider_image' => [
            //     'required_if:use_for_slider,1',
            //     'mimes:jpg,jpeg,png',
            //     'dimensions:min_width=916,min_height=486'
            // ],
            'promoted' => ['nullable', 'in:0,1'],
            'top_story' => ['nullable', 'in:0,1'],
            'is_premium' => ['nullable', 'in:0,1']
        ]);

        if($content_image = $request->file('content_image')){
            $content_image_path = $content_image->store('blogs', 'public');
            $validated['content_image'] = $content_image_path;
            $validated['slider_image'] = $content_image_path;
        }
        if($request->hasFile('thumbnail_image')){
            $thumbnail_image_path = $request->thumbnail_image->store('blogs', 'public');
            $validated['thumbnail_image'] = $thumbnail_image_path;
        }
        else if( $content_image ) {
            $thumbnail_image_path = \pathinfo($content_image_path, PATHINFO_FILENAME);
            $thumbnail_image_path .= '-thumbnail.' . $content_image->getClientOriginalExtension();

            $content_image_path = storage_path('app/public/'.$content_image_path);
            $thumbnail_image_path = storage_path('app/public/blogs/'.$thumbnail_image_path);

            // convert cover to thumb
            if($thumbnail_image_path = \App\Vars\Image::thumbnail($content_image_path, $thumbnail_image_path) ) {
                $validated['thumbnail_image'] = 'blogs/'.$thumbnail_image_path;
            }
        }
        // if($request->hasFile('slider_image')){
        //     $slider_image_path = $request->slider_image->store('blogs', 'public');
        //     $validated['slider_image'] = $slider_image_path;
        // }

        $blogCategory = Category::findOrFail($request->get('blog_category_id'));

        DB::beginTransaction();
        try {
            # Update Request Data
            $validated['slug'] = Str::slug(strtolower($validated['title']), '-');
            # Add Blog Image and Return
            $blog = Blog::create($validated);

            # Add tags data
            if( $tags = $request->get('tags') ) {
                $blog->tags()->sync($this->tags_to_array($tags));
            }

            DB::commit();

            // send notification
            if( $request->get('push_notification') == '1' ) {
                try {
                    Rss::push_notification($blogCategory, $blog);
                } catch(\Exception $e) {}
            }

            return redirect()->route('admin.blogs.index')
                ->withSuccess('Blog post Added Successfully');    
        } catch (\Exception $e) {
            DB::rollBack();
            logger($e);
        }
        Cache::forget('home_news1');
        Cache::forget('home_news0');
        return redirect()->route('admin.blogs.index')
            ->withError('Something went wrong!');
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
    public function edit(Blog $blog)
    {
        [$categories] = $this->getDataForForm();
        return view('admin.blogs.edit', compact('categories','blog'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Blog $blog)
    {
        # Active | Deactivate Category
        if($request->has('change_status')){
            # Change the status of the Category
            $blog->status = $blog->status ? 0 : 1;
            $blog->save();
            $message = $blog->status ? 'Blog post Activated Successfully' : 'Blog post Deactivated Successfully';
            return back()->withSuccess($message);
        }

        # Validate Form Inputs
        $validated = $request->validate([
            'title'  => ['required','string',"unique:blogs,title,{$blog->id},id"],
            'blog_category_id'  => ['required'],
            'tags' => ['nullable','string'],
            'short_description' => ['nullable','string'],
            'content' => ['required','string'],
            'content_image' => [
                'nullable',
                'mimes:jpg,jpeg,png',
                'dimensions:min_width=1000'
            ],
            // 'thumbnail_image' => [
            //     'nullable',
            //     'mimes:jpg,jpeg,png',
            //     'dimensions:min_width=440,min_height=276'
            // ],
            // 'slider_image' => [
            //     'nullable',
            //     'mimes:jpg,jpeg,png',
            //     'dimensions:min_width=440,min_height=276'
            // ],
            'promoted' => ['nullable', 'in:0,1'],
            'top_story' => ['nullable', 'in:0,1'],
            'is_premium' => ['nullable', 'in:0,1']
        ]);

        if($content_image = $request->file('content_image')){
            $content_image_path = $content_image->store('blogs', 'public');
            $validated['content_image'] = $content_image_path;
        }
        if($request->hasFile('thumbnail_image')){
            $thumbnail_image_path = $request->thumbnail_image->store('blogs', 'public');
            $validated['thumbnail_image'] = $thumbnail_image_path;
        }
        else if( $content_image ) {
            $thumbnail_image_path = \pathinfo($content_image_path, PATHINFO_FILENAME);
            $thumbnail_image_path .= '-thumbnail.' . $content_image->getClientOriginalExtension();

            $content_image_path = storage_path('app/public/'.$content_image_path);
            $thumbnail_image_path = storage_path('app/public/blogs/'.$thumbnail_image_path);

            // convert cover to thumb
            if($thumbnail_image_path = \App\Vars\Image::thumbnail($content_image_path, $thumbnail_image_path) ) {
                $validated['thumbnail_image'] = 'blogs/'.$thumbnail_image_path;
            }
        }
        // if($request->hasFile('slider_image')){
        //     $slider_image_path = $request->slider_image->store('blogs', 'public');
        //     $validated['slider_image'] = $slider_image_path;
        // }
        DB::beginTransaction();
        try {
            # Update Request Data
            $validated['slug'] = Str::slug(strtolower($validated['title']), '-');
            $validated['promoted'] = $validated['promoted'] ?? 0;
            $validated['top_story'] = $validated['top_story'] ?? 0;
            

            # Add Blog Image and Return
            $blog->update($validated);

            # Add tags data
            if( $tags = $request->get('tags') ) {
                $blog->tags()->sync($this->tags_to_array($tags));
            }

            DB::commit();

            // send notification
            if( $request->get('push_notification') == '1' ) {
                try {
                    if( $blog->wasChanged() ) {
                        Rss::push_notification($blog->blog_category, $blog);
                    }
                } catch(\Exception $e) {}
            }

            return redirect()->route('admin.blogs.index')
                ->withSuccess('Blog post Updated Successfully');    
        } catch (\Exception $e) {
            DB::rollBack();
            logger($e);
        }
        return redirect()->route('admin.blogs.index')
            ->withError('Something went wrong!');
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

    private function getDataForForm()
    {
        $categories     = Category::latest()->get();

        return [$categories];
    }

    private function tags_to_array($string)
    {
        $tags = explode(',', $string);

        // Create an empty collection
        $result = collect();

        foreach($tags as $tagname){
            $tagname = strtolower(trim($tagname));
            $slug   = Str::slug($tagname, '-');
            $tag    = Tag::firstOrCreate(
                ['slug' => $slug],
                ['name' => $tagname]
            );
            // Create a new tag if it doesn't exist and push it to the collection
            $result->push($tag->id); 
        }

        return $result;
    }
}
