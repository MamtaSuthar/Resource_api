<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Video;
use Illuminate\Http\Request;

class VideoController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $videos = Video::latest()->get();
        return view('admin.videos.index', compact('videos'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
        return view('admin.videos.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        # Validate the request inputs
        $validated = $request->validate([
            'title'  => ['required','string','min:5','max:191'],
            'thumbnail_image' => [
                'nullable', 
                'mimes:jpg,jpeg,png',
                'dimensions:min_width=375'
            ],
            'video_link' => ['required','url'] 
        ]);

        if($request->hasFile('thumbnail_image')){
            $thumbnail_image_path = 
                $request->thumbnail_image->store('videos', 'public');
            $validated['thumbnail_image'] = $thumbnail_image_path;
        }


        # Add Video and Return
        Video::create($validated);
        return redirect()->route('admin.videos.index')
            ->withSuccess('Video Added Successfully');
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
    public function edit(Request $request, Video $video)
    {
        return view('admin.videos.edit', compact('video'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Video $video)
    {
        # Validate the request inputs
        $validated = $request->validate([
            'title'  => ['required','string','min:5','max:191'],
            'thumbnail_image' => [
                'nullable', 
                'mimes:jpg,jpeg,png',
                'dimensions:min_width=375'
            ],
            'video_link' => ['required','url'] 
        ]);

        if($request->hasFile('thumbnail_image')){
            $thumbnail_image_path = 
                $request->thumbnail_image->storeAs('videos', basename($video->thumbnail_image), 'public');
            $validated['thumbnail_image'] = $thumbnail_image_path;
        }else{
            $validated['thumbnail_image'] = $video->thumbnail_image;
        }


        # Add Video and Return
        $video->update($validated);
        return redirect()->route('admin.videos.index')
            ->withSuccess('Video Added Successfully');
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

    public function changestatus(Video $video)
    {
        # Change the status of the category
        $video->status = $video->status ? 0 : 1;
        $video->save();
        $message = $video->status ? 'Video Activated Successfully' : 'Video Deactivated Successfully';
        return back()->withSuccess($message);
    }
}
