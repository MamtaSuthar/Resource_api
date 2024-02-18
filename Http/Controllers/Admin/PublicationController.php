<?php

namespace App\Http\Controllers\Admin;

use App\Models\Publication;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Http\Controllers\Controller;

class PublicationController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $pubs = Publication::latest()->get();
        return view('admin.publications.index', compact('pubs'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('admin.publications.create');
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
        $validated = $request->validate([
            'name' => [
                'required','min:3','string',
                "unique:publications"
            ],
            'type' => ['required','array'],
            'newspaper_price_ghs' => ['nullable', 'numeric'],
            'newspaper_price_usd' => ['nullable', 'numeric'],
            'apple_product_id' => ['nullable', 'unique:publications,apple_product_id']
        ]);

        # Update Request Inputs
        $validated['type'] = implode(',', $validated['type']);
        # Create Category Data
        $publication = Publication::create($validated);

        if( $request->get('create_plan') ) {

            $request->merge([
                'title' => $publication->name,
                'desc' => $publication->name,
                'type' => 'custom',
                'pulications' => [$publication->id]
            ]);

            $result = app(\App\Http\Controllers\Admin\PlanController::class)
                ->store($request);

            if( $result->getSession()->has('error') ) {
               return $result;
            }
        }

        return redirect()->route('admin.publications.index')
            ->withSuccess('Publication Created Successfully');
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
    public function edit(Request $request, Publication $publication)
    {
        return view('admin.publications.edit', compact('publication'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Publication $publication)
    {
        # Validate Request Inputs
        $validated = $request->validate([
            'name' => [
                'required','min:3','string',
                "unique:publications,name,{$publication->id},id"
            ],
            'type' => ['required','array'],
            'newspaper_price_ghs' => ['nullable', 'numeric'],
            'newspaper_price_usd' => ['nullable', 'numeric'],
            'apple_product_id' => ['nullable', 'unique:publications,apple_product_id,'.$publication->id]
        ]);
        
        # Update Request Inputs
        $validated['type'] = implode(',', $validated['type']);

        # Update Category data
        $publication->update($validated);

        return redirect()->route('admin.publications.index')
            ->withSuccess('Publication Updated Successfully');
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

    public function changestatus(Publication $publication)
    {
        # Change the status of the category
        $publication->status = $publication->status ? 0 : 1;
        $publication->save();
        $message = $publication->status 
            ? 'Publication Activated Successfully' 
            : 'Publication Deactivated Successfully';
        return back()->withSuccess($message);
    }
}
