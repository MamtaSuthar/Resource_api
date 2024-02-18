<?php

namespace App\Http\Controllers\Admin;

use App\Models\Gallery;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\AlbumGallery;
use App\Models\Albums;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class GalleryController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $albums = Albums::latest()->get();
        return view('admin.galleries.index', compact('albums'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('admin.galleries.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        // dd($request->all());
        $validated = $request->validate([
            'title'  => ['required'],
            'description'=>['required'],
            'image_title'=>['required','array'],
            'image_description' => ['nullable','array'],
            'cover_image' => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'dimensions:min_width=1000'],
            'image' => [
                'required', 'array',
            ],
            'image.*' => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'dimensions:min_width=1000']
        ]);
        // dd($request->hasFile('image'));
        $album['title'] = $validated['title'];
        $album['description'] = $validated['description'];

        if( $file = $request->file('cover_image') ) {
            if( $path = $file->store('galleries', 'public') ) {
                $album['cover_image'] = $path;
            }
        }

        # Add Gallery Image and Return
        $alb = Albums::create($album);
        
        $image_titles = $request->get('image_title');
        $image_descriptions = $request->get('image_description');

        $images = (array)$request->file('image');
        $images = \array_filter($images);

        foreach($images as $key => $image) {

            if( !isset($image_titles[$key]) || empty($image_titles[$key]) ) {
                continue;
            }

            $img['title'] = $image_titles[$key];
            $img['short_description'] = $image_descriptions[$key] ?? null;
            $img['album_id'] = $alb->id;
            $image_path = $image->store('galleries', 'public');
            $img['image'] = $image_path;

            AlbumGallery::create($img);
        }



        return redirect()->route('admin.galleries.index')->withSuccess('Gallery Image Added Successfully');
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
    public function edit(Albums $gallery)
    {
        return view('admin.galleries.edit', compact('gallery'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Albums $gallery)
    {
        $rules = [
            'title'  => ['required'],
            'description'=>['required'],
            'image_description' => ['nullable', 'array'],
            'image_title'=>['required','array'],
            'cover_image' => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'dimensions:min_width=1000'],
            'image' => [
                'nullable', 'array',
            ],
            'image.*' => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'dimensions:min_width=1000']
        ];
        if($request->hasFile('image')){
            $rules['image'] =  ['required', 'array'];
        }
        $validated = $request->validate($rules);

        $albums['title'] = $validated['title'];
        $albums['description'] = $validated['description'];

        DB::beginTransaction();
        try {

            if( $file = $request->file('cover_image') ) {
                if( $path = $file->store('galleries', 'public') ) {
                    $albums['cover_image'] = $path;
                }
            }

            # Add Gallery Image and Return
            // dd($albums);
            $gallery->update($albums);

            $image_ids = (array)$request->get('image_ids');

            $images = (array)$request->file('image');

            $image_titles = \array_filter( (array)$request->get('image_title') );
            $image_descriptions = (array)$request->get('image_description');

            foreach( $image_titles as $key => $image_title ) {
                $img = [];

                $image_id = intval($image_ids[$key]??0);

                $img['title'] = $image_title;
                if( isset($image_descriptions[$key]) ) {
                    $img['short_description'] = $image_descriptions[$key]??null;
                }
                $img['album_id'] = $gallery->id;
                if( isset($images[$key]) ) {
                    $img['image'] = $images[$key]->store('galleries', 'public');
                }

                AlbumGallery::updateOrCreate(['id' => $image_id], $img);
            }

            DB::commit();

            return redirect()->route('admin.galleries.index')->withSuccess('Album Updated Successfully');

        } catch(\Exception $e) {
            logger($e->getMessage());
        }

        DB::rollBack();

        return back()->withError('Something went wrong!');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Albums $gallery)
    {
        $gallery->delete();

        return back()->withSuccess('Gallery Removed');
    }
    ## old one 
    public function changestatus2(Gallery $gallery)
    {
        # Change the status of the category
        $gallery->status = $gallery->status ? 0 : 1;
        $gallery->save();
        $message = $gallery->status ? 'Gallery Image Activated Successfully' : 'Gallery Image Deactivated Successfully';
        return back()->withSuccess($message);
    }
    ## change the status of albums
    public function changestatus(Albums $album)
    {
        # Change the status of the category
        // dd($album);
        $album->status = $album->status ? 0 : 1;
        $album->save();
        $message = $album->status ? 'Album Activated Successfully' : 'Album Deactivated Successfully';
        return back()->withSuccess($message);
    }
}
