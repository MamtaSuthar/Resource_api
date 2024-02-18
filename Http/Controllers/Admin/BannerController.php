<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use Illuminate\Http\Request;

class BannerController extends Controller
{
    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $now = date('Y-m-d H:i:s');

        $banner = Banner::firstOrCreate(
            ['id' => $id],
            ['created_at' => $now, 'updated_at' => $now]
        );

        return view('admin.banner.edit', compact('banner'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $banner = Banner::findOrFail($id);

        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:191'],
            'short_description' => ['nullable', 'string', 'max:2500'],
            'image' => ['nullable', 'file', 'mimes:png,jpeg,jpg'],
            'url' => ['nullable', 'url']
        ]);

        if( $image = $request->file('image') ) {
            if( $image = $image->store('banner', 'public') ) {
                $validated['image'] = $image;
            }
        }

        $banner->update($validated);

        return back()->withSuccess('Banner updated');
    }
}
