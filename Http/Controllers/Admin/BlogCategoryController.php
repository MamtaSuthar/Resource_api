<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Support\Str;
use App\Models\BlogCategory;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class BlogCategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $blogcats = BlogCategory::latest()->get();
        return view('admin.blogcats.index', compact('blogcats'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('admin.blogcats.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        # Validate Request Inputs
        $request->validate([
            'name' => ['required','min:3','string','unique:blog_categories']
        ]);

        # Create Category Data
        BlogCategory::create([
            'name' => $request->input('name'),
            'slug' => Str::slug($request->input('name'))
        ]);

        return redirect()->route('admin.blogcats.index')->withSuccess('Category Create Successfully');
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
    public function edit(Request $request, BlogCategory $blogcat)
    {
        return view('admin.blogcats.edit', compact('blogcat'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, BlogCategory $blogcat)
    {
        # Active | Deactivate Category
        if($request->has('change_status')){
            # Change the status of the Category
            $blogcat->status = $blogcat->status ? 0 : 1;
            $blogcat->save();
            $message = $blogcat->status ? 'Category Activated Successfully' : 'Category Deactivated Successfully';
            return back()->withSuccess($message);
        }
        
        # Validate Request Inputs
        $request->validate([
            'name' => [
                'required','min:3','string',
                "unique:blog_categories,name,{$blogcat->id},id"
            ]
        ]);
        # Update Category data
        $blogcat->update([
            'name' => $request->input('name'),
            'slug' => Str::slug($request->input('name'))
        ]);

        return redirect()->route('admin.blogcats.index')->withSuccess('Category Updated Successfully');
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
}
