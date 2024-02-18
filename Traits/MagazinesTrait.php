<?php

namespace App\Traits;

use App\Models\Tag;
use App\Models\Category;
use App\Models\Magazine;
use App\Models\Publication;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PDF;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Crypt;
use App\Traits\Notification;
use App\Vars\FileProcessing;
use App\Vars\LinkPdfFile;
use App\Vars\PushNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;

trait MagazinesTrait
{
    use FileProcessingTrait, Notification, GridViewForPContent;

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        if ($this->user()->isVendor()) {
            $magazines = $this->user()->magazines()->with('publication')->orderBy('id', 'desc')->get();
        } else {
            $magazines = Magazine::with('publication')->orderBy('id', 'desc');

            if ($request->get('missing_apple_id') == '1') {
                $magazines->whereNull('apple_product_id');
            }

            $magazines = $magazines->get();
        }
        $view = $this->view_file . '.magazines.index';
        return view($view, compact('magazines'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        [$categories, $publications] = $this->getDataForForm();
        $route = $this->view_file . '.magazines.create';
        return view(
            $route,
            compact('categories', 'publications')
        );
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        // $request->dd();
        # Validate form inputs
        $rules = [
            'title' => [
                'required', 'string', 'unique:magazines', 'min:3', 'max:200'
            ],
            'price' =>  ['required', 'numeric'],
            'copyright_owner'   => ['required', 'string'],
            'edition_number'    => ['required', 'string'],
            'tags' => ['required'],
            'category_id' => ['required'],
            'publication_id' => ['required'],
            'published_date' => ['required'],
            'blog_post_linking_date' => ['nullable', 'date_format:Y-m-d'],
            'cover_image' => [
                'required',
                'mimes:jpg,jpeg,png',
                'dimensions:min_width=1000',
                'max:600'
            ],
            'file_type' => [
                // 'required',
                // 'mimetypes:application/pdf,application/epub+zip'
            ],
            'short_description' => ['required', 'string'],
            'apple_product_id' => ['nullable', 'max:191', 'unique:magazines,apple_product_id']
        ];
        if ($request->ajax()) {
            $validator = Validator::make(
                $request->all(),
                $rules
            );

            if ($validator->fails()) {
                return response()
                    ->json($validator->errors()->toArray(), 422);
            }

            $validated = $validator->validated();
        } else {
            $validated = $request->validate($rules);
        }

        if (empty($publication = Publication::find($request->get('publication_id')))) {
            return back()->withError('Publication not found in the db');
        }

        $validated['is_free'] = $request->get('is_free') == '1';

        if ($cover_image = $request->file('cover_image')) {
            $cover_image_path = $cover_image->store('magazines', 'public');
            $validated['cover_image'] = $cover_image_path;
        }

        if ($thumbnail_image = $request->file('thumbnail_image')) {
            $thumbnail_image_path = $thumbnail_image->store('magazines', 'public');
            $validated['thumbnail_image'] = $thumbnail_image_path;
        }
        // make thumbnail out of cover
        else if ($cover_image) {
            $thumbnail_image_path = \pathinfo($cover_image_path, PATHINFO_FILENAME);
            $thumbnail_image_path .= '-thumbnail.' . $cover_image->getClientOriginalExtension();

            $cover_image_path = storage_path('app/public/' . $cover_image_path);
            $thumbnail_image_path = storage_path('app/public/magazines/' . $thumbnail_image_path);

            // convert cover to thumb
            if ($thumbnail_image_path = \App\Vars\Image::thumbnail($cover_image_path, $thumbnail_image_path)) {
                $validated['thumbnail_image'] = 'magazines/' . $thumbnail_image_path;
            }
        }

        if ($request->hasFile('pdf_file')) {
            $file_path = $request->pdf_file->store('magazines', 'public');
            $validated['file'] = $file_path;
            $validated['file_type'] = "pdf";
        }

        // epub files need to be converted first
        if ($request->hasFile('epub_file')) {
            $file_path = $request->epub_file->store('magazines', 'public');
            $validated['file'] = $file_path;
            $validated['file_type'] = "epub";

            $epub_file_name =
                \Illuminate\Support\Str::random(22) . '.pdf';

            $to_pdf = FileProcessing::epub_to_pdf(
                storage_path("app/public/magazines/$file_path"),
                storage_path('app/public/' . $epub_file_name)
            );

            if ($to_pdf) {
                $validated['file_converted'] = "magazines/$epub_file_name";
            }
        }

        // admin has created this epub on admin panel
        // store and convert it to pdf
        if (!empty($created_epub = $request->get('created_epub'))) {
            $epub_from = storage_path('epub_temp/' . $created_epub);

            if (file_exists($epub_from)) {
                $epub_to = storage_path("app/public/magazines/$created_epub");

                if (rename($epub_from, $epub_to)) {
                    $validated['file'] = "magazines/$created_epub";
                    $validated['file_type'] = "epub";

                    // convert to pdf
                    $epub_file_name =
                        \Illuminate\Support\Str::random(22) . '.pdf';

                    $to_pdf = FileProcessing::epub_to_pdf(
                        $epub_to,
                        storage_path('app/public/magazines/' . $epub_file_name)
                    );

                    if ($to_pdf) {
                        $validated['file_converted'] = "magazines/$epub_file_name";
                    }
                }
            }
        }

        $file = $validated['file_converted'] ?? $validated['file'];

        $pub_date = $request->get('blog_post_linking_date')
            ?? $request->get('published_date');

        // process file for preview, watermark, and headlines linking
        [$preview_file, $processed_file] = FileProcessing::process_pdf_file(
            storage_path('app/public/' . $file),
            $pub_date
        );

        if( $preview_file ) {
            $validated['file_preview'] = 'magazines/'.basename($preview_file);
        }

        if( $processed_file ) {
            $validated['file'] = 'magazines/'.basename($processed_file);
        }

        if( !isset($validated['file']) || empty($validated['file']) ) {
            return back()
                ->withError('File processing failed for some reason. Please check logs for more information.')
                ->withInput();
        }

        # Store Magazine Data
        DB::beginTransaction();
        try {
            $validated['slug'] = Str::random(8).'-'.Str::slug(strtolower($validated['title']), '-');

            # Add magazine data
            $magazine = $this->user()->magazines()->create(
                collect($validated)->except('tags')->toArray()
            );

            # Add tags data
            $magazine->tags()->sync($this->tags_to_array($validated['tags']));

            # Commit and return
            DB::commit();

            PushNotification::push_pc($publication, $magazine, 'magazine');

            if ($request->get('file_type') == 'grid') {
                $route = route('vendor.content_make_grid', [
                    'content' => $magazine->id,
                    'type' => 'magazine'
                ]);
            } else {
                $route = route($this->route_path . '.magazines.index');
            }

            if ($request->ajax()) {
                $request->session()->flash('success', 'Magazine Added Successfully');

                return response()->json(['redirect' => $route]);
            }

            return redirect($route)
                ->withSuccess('Magazine Added Successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            logger($e->getMessage());
        }
        return back()->withError('Something went wrong!');
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
        // echo "shiv";
        $magDatas = Magazine::with('category', 'publication')->findOrFail($id);
        // dd($magDatas);
        $view = $this->view_file . '.magazines.view';
        return view($view, compact('magDatas'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(Magazine $magazine)
    {
        [$categories, $publications] = $this->getDataForForm();
        $route = $this->view_file . '.magazines.edit';
        return view(
            $route,
            compact('magazine', 'categories', 'publications')
        );
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Magazine $magazine)
    {
        // dd($request->all());
        if ($request->has('change_status')) {
            # Change the status of the Magazine
            $magazine->status = $magazine->status ? 0 : 1;
            $magazine->save();
            $message = $magazine->status ? 'Magazine Activated Successfully' : 'Magazine Deactivated Successfully';
            return back()->withSuccess($message);
        }
        # Update Magazine Logic
        # Validate form inputs
        $validated = $request->validate([
            'title' => [
                'required', 'string', "unique:magazines,title,{$magazine->id},id", 'min:3', 'max:200'
            ],
            'price' =>  ['required', 'numeric'],
            'copyright_owner'   => ['required', 'string'],
            'edition_number'    => ['required', 'string'],
            'tags' => ['required'],
            'category_id' => ['required'],
            'publication_id' => ['required'],
            'published_date' => ['required'],
            'blog_post_linking_date' => ['nullable', 'date_format:Y-m-d'],
            'thumbnail_image' => [
                'nullable',
                'mimes:jpg,jpeg,png',
                'dimensions:min_width=187'
            ],
            'cover_image' => [
                'nullable',
                'mimes:jpg,jpeg,png',
                'dimensions:min_width=1000',
                'max:600'
            ],
            'short_description' => ['required', 'string'],
            'apple_product_id' => ['nullable', 'max:191', 'unique:magazines,apple_product_id,' . $magazine->id]
        ]);

        $validated['is_free'] = $request->get('is_free') == '1';

        if ($request->hasFile('thumbnail_image')) {
            $thumbnail_image_path =
                $request->thumbnail_image->storeAs(
                    'magazines',
                    basename($magazine->thumbnail_image),
                    'public'
                );
            $validated['thumbnail_image'] = $thumbnail_image_path;
        } else {
            $validated['thumbnail_image'] = $magazine->thumbnail_image;
        }

        if ($cover_image = $request->file('cover_image')) {
            $cover_image_path =
                $cover_image->storeAs(
                    'magazines',
                    basename($magazine->cover_image . time()),
                    'public'
                );
            $validated['cover_image'] = $cover_image_path;

            // make a cover out of it
            if (!isset($thumbnail_image_path)) {
                $thumbnail_image_path = \pathinfo($cover_image_path, PATHINFO_FILENAME);
                $thumbnail_image_path .= '-thumbnail.' . $cover_image->getClientOriginalExtension();

                $cover_image_path = storage_path('app/public/' . $cover_image_path);
                $thumbnail_image_path = storage_path('app/public/magazines/' . $thumbnail_image_path);

                // convert cover to thumb
                if ($thumbnail_image_path = \App\Vars\Image::thumbnail($cover_image_path, $thumbnail_image_path)) {
                    $validated['thumbnail_image'] = 'magazines/' . $thumbnail_image_path;
                }
            }
        } else {
            $validated['cover_image'] = $magazine->cover_image;
        }

        if ($request->hasFile('file')) {
            $file_path =
                $request->file->storeAs('magazines', basename($magazine->file), 'public');
            $validated['file'] = $file_path;
        } else {
            $validated['file'] = $magazine->file;
        }

        if ($request->hasFile('pdf_file')) {
            $file_path = $request->pdf_file->store('magazines', 'public');
            $validated['file'] = $file_path;
            $validated['file_type'] = "pdf";
        }

        // convert to pdf
        if ($request->hasFile('epub_file')) {

            $file_path = $request->epub_file->store('magazines', 'public');
            $validated['file'] = $file_path;
            $validated['file_type'] = "epub";

            $epub_file_name =
                \Illuminate\Support\Str::random(22) . '.pdf';

            $to_pdf = FileProcessing::epub_to_pdf(
                storage_path("app/public/magazines/$file_path"),
                storage_path('app/public/' . $epub_file_name)
            );

            if ($to_pdf) {
                $validated['file_converted'] = "magazines/$epub_file_name";
            }
        }

        $final_file = $validated['file_converted'] ?? $validated['file'] ?? null;

        if ($final_file && file_exists(storage_path('app/public/' . $final_file))) {

            $pub_date = $request->get('blog_post_linking_date')
                ?? $request->get('published_date');

            // process file for preview, watermark, and headlines linking
            [$preview_file, $processed_file] = FileProcessing::process_pdf_file(
                storage_path('app/public/' . $final_file),
                $pub_date
            );

            if( $preview_file ) {
                $validated['file_preview'] = 'magazines/'.basename($preview_file);
            }

            if( $processed_file ) {
                $validated['file'] = 'magazines/'.basename($processed_file);
            }
        }

        # Store Magazine Data
        DB::beginTransaction();
        try {
            # Add magazine data
            $magazine->update(
                collect($validated)->except('tags')->toArray()
            );
            # Add tags data
            $magazine->tags()->sync($this->tags_to_array($validated['tags']));

            # Commit and return
            DB::commit();

            $route = $this->route_path . '.magazines.index';

            return redirect()->route($route)
                ->withSuccess('Magazine Updated Successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            logger($e->getMessage());
        }
        return back()->withError('Something went wrong!');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Magazine $magazine)
    {
        //
        // dd($magazine);
        $magDatas = Magazine::where('id', $magazine->id)->delete();
        Cache::forget('home_mags0');
        Cache::forget('home_mags1');
        if ($magDatas) {
            $route = $this->route_path . '.magazines.index';
            return redirect()->route($route)
                ->withSuccess('Magazine Deleted Successfully');
        } else {
            return redirect()->back()
                ->with('error', 'Something went wrong');
        }
    }

    private function getDataForForm($for = 'magazine')
    {
        $categories     = Category::active()->latest()->get();
        $publications   = Publication::active()->latest()
            ->get()
            ->filter(function ($publication) use ($for) {
                return \strpos($publication->type, $for) > -1;
            });

        return [$categories, $publications];
    }

    private function tags_to_array($string)
    {
        $tags = explode(',', $string);

        // Create an empty collection
        $result = collect();

        foreach ($tags as $tagname) {
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
