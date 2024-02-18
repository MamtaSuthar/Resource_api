<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\Category;
use App\Http\Controllers\Controller;

class CategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $cats = Category::latest()->get();
        return view('admin.magcats.index', compact('cats'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('admin.magcats.create');
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
        # Validate Request Inputs
        $request->validate([
            'name' => ['required','min:3','string','unique:categories'],
            // 'promoted' => ['nullable', 'in:0,1'],
        ]);

        # Create Category Data
        Category::create([
            'name' => $request->input('name'),
            'slug' => Str::slug($request->input('name')),
            'popular'=>$request->popular ? $request->popular : 0
        ]);

        return redirect()->route('admin.magcats.index')->withSuccess('Category Create Successfully');
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
    public function edit(Request $request, Category $magcat)
    {
        return view('admin.magcats.edit', compact('magcat'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Category $magcat)
    {
        # Validate Request Inputs
        $request->validate([
            'name' => [
                'required','min:3','string',
                "unique:categories,name,{$magcat->id},id"
            ]
        ]);
        # Update Category data
        $magcat->update([
            'name' => $request->input('name'),
            'slug' => Str::slug($request->input('name')),
            'popular'=>$request->popular ? $request->popular : 0
        ]);

        return redirect()->route('admin.magcats.index')->withSuccess('Category Updated Successfully');
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

    public function changestatus(Category $magcat)
    {
        # Change the status of the category
        $magcat->status = $magcat->status ? 0 : 1;
        $magcat->save();
        $message = $magcat->status ? 'Category Activated Successfully' : 'Category Deactivated Successfully';
        return back()->withSuccess($message);
    }
}
