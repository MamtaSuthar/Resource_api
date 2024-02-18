<?php

namespace App\Http\Controllers\Admin;

use App\Models\Podcast;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class PodcastController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $podcasts = Podcast::latest()->get();
        return view('admin.podcasts.index', compact('podcasts'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('admin.podcasts.create');
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
            'title'  => ['required','string','min:5','max:40'],
            'thumbnail_image' => [
                'required', 
                'mimes:jpg,jpeg,png',
                'dimensions:min_width=120'
            ],
            'podcast_file' => [
                'required', 
                'mimetypes:audio/mpeg',
                'min:5',
                'max:20000'    
            ] 
        ]);

        if($request->hasFile('thumbnail_image')){
            $thumbnail_image_path = 
                $request->thumbnail_image->store('podcasts', 'public');
            $validated['thumbnail_image'] = $thumbnail_image_path;
        }

        if($request->hasFile('podcast_file')){
            $podcast_file_path = $request->podcast_file->store('podcasts', 'public');
            $validated['podcast_file'] = $podcast_file_path;
        }

        # Add Podcast and Return
        Podcast::create($validated);
        return redirect()->route('admin.podcasts.index')
            ->withSuccess('Podcast Added Successfully');
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
    public function edit(Podcast $podcast)
    {
        return view('admin.podcasts.edit', compact('podcast'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Podcast $podcast)
    {
        # Validate the request inputs
        $validated = $request->validate([
            'title'  => ['required','string','min:5','max:40'],
            'thumbnail_image' => [
                'nullable', 
                'mimes:jpg,jpeg,png',
                'dimensions:min_width=120'
            ],
            'podcast_file' => [
                'nullable', 
                'mimetypes:audio/mpeg',
                'min:5',
                'max:20000'    
            ] 
        ]);

        if($request->hasFile('thumbnail_image')){
            $thumbnail_image_path = 
                $request->thumbnail_image->storeAs('podcasts', basename($podcast->thumbnail_image), 'public');
            $validated['thumbnail_image'] = $thumbnail_image_path;
        }else{
            $validated['thumbnail_image'] = $podcast->thumbnail_image;
        }

        if($request->hasFile('podcast_file')){
            $podcast_file_path = $request->podcast_file->storeAs('podcasts', basename($podcast->podcast_file),'public');
            $validated['podcast_file'] = $podcast_file_path;
        }else{
            $validated['podcast_file'] = $podcast->podcast_file;
        }

        # Update Podcast and Return
        $podcast->update($validated);
        return redirect()->route('admin.podcasts.index')
            ->withSuccess('Podcast Updated Successfully');
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

    public function changestatus(Podcast $podcast)
    {
        # Change the status of the category
        $podcast->status = $podcast->status ? 0 : 1;
        $podcast->save();
        $message = $podcast->status ? 'Podcast Activated Successfully' : 'Podcast Deactivated Successfully';
        return back()->withSuccess($message);
    }
}
