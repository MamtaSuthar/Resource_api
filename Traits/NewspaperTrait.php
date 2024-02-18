<?php

namespace App\Traits;

use App\Api\ApiResponse;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\MagazineResource;
use App\Http\Resources\NewspaperResource;
use App\Http\Resources\PublicationResource;
use App\Models\Newspaper;
use App\Models\Category;
use App\Models\Publication;
use App\Models\Magazine;
use App\Models\Tag;
use App\Models\User;
use App\Vars\FileProcessing;
use App\Vars\LinkPdfFile;
use App\Vars\PushNotification;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;
use PDF;

trait NewspaperTrait
{
    use FileProcessingTrait, Notification;

    public function index(Request $request)
    {
        // dd('shiv');
        if ($this->user()->isVendor()) {
            $newspaper = $this->user()->newspapers()->with('publication')->orderBy('id', 'desc')->get();
        } else {
            $newspaper = Newspaper::with('publication')->orderBy('id', 'desc');

            if ($request->get('missing_apple_id') == '1') {
                $newspaper->whereNull('apple_product_id');
            }

            $newspaper = $newspaper->get();
        }
        $view = $this->view_file . '.newspapers.index';
        return view($view, compact('newspaper'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        [$categories, $publications] = $this->getDataForForm();
        $route = $this->view_file . '.newspapers.create';
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
        $rules = [
            'title' => [
                'required', 'string', 'unique:newspapers', 'min:3', 'max:200'
            ],
            'price' =>  ['nullable', 'numeric'],
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
                // 'mimetypes:application/pdf'
            ],
            'short_description' => ['required', 'string'],
            'apple_product_id' => ['nullable', 'max:191', 'unique:newspapers,apple_product_id']
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
            return $request->ajax()
                ? response()->json(['error' => 'Publication not found'])
                : back()->withError('Publication not found');
        }

        if (empty($publication->newspaper_price_ghs)) {
            return $request->ajax()
                ? response()->json(['error' => 'Invalid publication price. Please set a valid price for the publication "' . $publication->name . '".'])
                : back()->withError('Invalid publication price. Please set a valid price for the publication "' . $publication->name . '".');
        }

        $validated['price'] = $publication->newspaper_price_ghs ?? 0.0;

        $validated['is_free'] = $request->get('is_free') == '1';

        if ($cover_image = $request->file('cover_image')) {
            $cover_image_path = $cover_image->store('newspapers', 'public');
            $cover_image_path = $cover_image->storeAs(
                'newspapers',
                basename($cover_image_path . time()),
                'public'
            );
            $validated['cover_image'] = $cover_image_path;
        }

        if ($thumbnail_image = $request->file('thumbnail_image')) {
            $thumbnail_image_path = $thumbnail_image->store('newspapers', 'public');
            $validated['thumbnail_image'] = $thumbnail_image_path;
        }
        // make thumbnail out of cover
        else if ($cover_image) {
            $thumbnail_image_path = \pathinfo($cover_image_path, PATHINFO_FILENAME);
            $thumbnail_image_path .= '-thumbnail.' . $cover_image->getClientOriginalExtension();

            $cover_image_path = storage_path('app/public/' . $cover_image_path);
            $thumbnail_image_path = storage_path('app/public/newspapers/' . $thumbnail_image_path);

            // convert cover to thumb
            if ($thumbnail_image_path = \App\Vars\Image::thumbnail($cover_image_path, $thumbnail_image_path)) {
                $validated['thumbnail_image'] = 'newspapers/' . $thumbnail_image_path;
            }
        }

        // convert file to pdf
        if ($request->hasFile('file')) {  // this input is invalid
            $extension = $request->file->extension();
            $file_path = $request->file->store('newspapers', 'public');
            $validated['file'] = $file_path;

            // convert epub file
            $file = $request->file('file');
            if ($file->getMimeType() === 'application/epub+zip') {
                $file_name = \Illuminate\Support\Str::random(22);

                $to_pdf = FileProcessing::epub_to_pdf(
                    storage_path("app/public/newspapers/$file_path"),
                    storage_path('app/public/' . $file_name . '.pdf')
                );

                if ($to_pdf) {
                    $validated['file_converted'] = "newspapers/$file_name";
                }
            }
        }

        if ($request->hasFile('pdf_file')) {
            $file_path = $request->pdf_file->store('newspapers', 'public');
            $validated['file'] = $file_path;
            $validated['file_type'] = "pdf";
        }

        // convert to pdf file
        if ($request->hasFile('epub_file')) {
            $file_path = $request->epub_file->store('newspapers', 'public');
            $validated['file'] = $file_path;
            $validated['file_type'] = "epub";

            $epub_file_name =
                \Illuminate\Support\Str::random(22) . '.pdf';

            $to_pdf = FileProcessing::epub_to_pdf(
                storage_path("app/public/newspapers/$file_path"),
                storage_path('app/public/' . $epub_file_name)
            );

            if ($to_pdf) {
                $validated['file_converted'] = "newspapers/$epub_file_name";
            }
        }

        // admin has created this epub on admin panel
        // store and convert it to pdf
        if (!empty($created_epub = $request->get('created_epub'))) {
            $epub_from = storage_path('epub_temp/' . $created_epub);

            if (file_exists($epub_from)) {
                $epub_to = storage_path("app/public/newspapers/$created_epub");

                if (rename($epub_from, $epub_to)) {
                    $validated['file'] = "newspapers/$created_epub";
                    $validated['file_type'] = "epub";

                    // convert to pdf
                    $epub_file_name =
                        \Illuminate\Support\Str::random(22) . '.pdf';

                    $to_pdf = FileProcessing::epub_to_pdf(
                        $epub_to,
                        storage_path('app/public/newspapers/' . $epub_file_name)
                    );

                    if ($to_pdf) {
                        $validated['file_converted'] = "newspapers/$epub_file_name";
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
            $validated['file_preview'] = 'newspapers/'.basename($preview_file);
        }

        if( $processed_file ) {
            $validated['file'] = 'newspapers/'.basename($processed_file);
        }

        if( !isset($validated['file']) || empty($validated['file']) ) {
            return back()
                ->withError('File processing failed for some reason. Please check logs for more information.')
                ->withInput();
        }

        # Store newspaper Data
        DB::beginTransaction();
        try {
            # Update form data
            $validated['slug'] = Str::random(8).'-'.Str::slug(strtolower($validated['title']), '-');

            # Add newspaper data
            $newspaper = $this->user()->newspapers()->create(
                collect($validated)->except('tags')->toArray()
            );
            # Add tags data
            $newspaper->tags()->sync($this->tags_to_array($validated['tags']));

            # Commit and return
            DB::commit();

            PushNotification::push_pc($publication, $newspaper, 'newspaper');

            if ($request->get('file_type') == 'grid') {
                $route = route('vendor.content_make_grid', [
                    'content' => $newspaper->id,
                    'type' => 'newspaper'
                ]);
            } else {
                $route = route($this->route_path . '.newspapers.index');
            }

            if ($request->ajax()) {
                $request->session()->flash('success', 'Newspaper Added Successfully');

                return response()->json(['redirect' => $route]);
            }

            return redirect($route)
                ->withSuccess('Newspaper Added Successfully');
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
        $newsDatas = Newspaper::with('category', 'publication')->findOrFail($id);
        // dd($newsDatas);
        $view = $this->view_file . '.newspapers.view';
        return view($view, compact('newsDatas'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(Newspaper $newspaper)
    {
        [$categories, $publications] = $this->getDataForForm();
        $route = $this->view_file . '.newspapers.edit';
        return view(
            $route,
            compact('newspaper', 'categories', 'publications')
        );
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Newspaper $newspaper)
    {
        if ($request->has('change_status')) {
            # Change the status of the Newspaper
            $newspaper->status = $newspaper->status ? 0 : 1;
            $newspaper->save();
            $message = $newspaper->status ? 'Newspaper Activated Successfully' : 'Newspaper Deactivated Successfully';
            return back()->withSuccess($message);
        }
        # Update Newspaper Logic
        # Validate form inputs
        $validated = $request->validate([
            'title' => [
                'required', 'string', "unique:newspapers,title,{$newspaper->id},id", 'min:3', 'max:200'
            ],
            'price' =>  ['nullable', 'numeric'],
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
            'apple_product_id' => ['nullable', 'max:191', 'unique:newspapers,apple_product_id,' . $newspaper->id]
        ]);

        $validated['is_free'] = $request->get('is_free') == '1';

        if ($request->hasFile('thumbnail_image')) {
            $thumbnail_image_path =
                $request->thumbnail_image->storeAs(
                    'newspapers',
                    basename($newspaper->thumbnail_image),
                    'public'
                );
            $validated['thumbnail_image'] = $thumbnail_image_path;
        } else {
            $validated['thumbnail_image'] = $newspaper->thumbnail_image;
        }

        if ($cover_image = $request->file('cover_image')) {
            $cover_image_path =
                $cover_image->storeAs(
                    'newspapers',
                    basename($newspaper->cover_image . time()),
                    'public'
                );
            $validated['cover_image'] = $cover_image_path;

            // make a thumbnail out of it
            if (!isset($thumbnail_image_path)) {
                $thumbnail_image_path = \pathinfo($cover_image_path, PATHINFO_FILENAME);
                $thumbnail_image_path .= '-thumbnail.' . $cover_image->getClientOriginalExtension();

                $cover_image_path = storage_path('app/public/' . $cover_image_path);
                $thumbnail_image_path = storage_path('app/public/newspapers/' . $thumbnail_image_path);

                // convert cover to thumb
                if ($thumbnail_image_path = \App\Vars\Image::thumbnail($cover_image_path, $thumbnail_image_path)) {
                    $validated['thumbnail_image'] = 'newspapers/' . $thumbnail_image_path;
                }
            }
        } else {
            $validated['cover_image'] = $newspaper->cover_image;
        }

        if ($request->hasFile('file')) {
            $file_path =
                $request->file->storeAs('newspapers', basename($newspaper->file), 'public');
            $validated['file'] = $file_path;
        } else {
            $validated['file'] = $newspaper->file;
        }

        if ($request->hasFile('pdf_file')) {
            $file_path = $request->pdf_file->store('newspapers', 'public');
            $validated['file'] = $file_path;
            $validated['file_type'] = "pdf";
        }

        if ($request->hasFile('epub_file')) {
            $file_path = $request->epub_file->store('newspapers', 'public');
            $validated['file'] = $file_path;
            $validated['file_type'] = "epub";

            $epub_file_name =
                \Illuminate\Support\Str::random(22) . '.pdf';

            $to_pdf = FileProcessing::epub_to_pdf(
                storage_path("app/public/newspapers/$file_path"),
                storage_path('app/public/' . $epub_file_name)
            );

            if ($to_pdf) {
                $validated['file_converted'] = "newspapers/$epub_file_name";
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
                $validated['file_preview'] = 'newspapers/'.basename($preview_file);
            }

            if( $processed_file ) {
                $validated['file'] = 'newspapers/'.basename($processed_file);
            }
        }

        # Store NewsPaper Data
        DB::beginTransaction();
        try {
            # Add Newspaper data
            $newspaper->update(
                collect($validated)->except('tags')->toArray()
            );
            # Add tags data
            $newspaper->tags()->sync($this->tags_to_array($validated['tags']));

            # Commit and return
            DB::commit();
            $route = $this->route_path . '.newspapers.index';
            return redirect()->route($route)
                ->withSuccess('NewsPaper Updated Successfully');
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
    public function destroy(Newspaper $newspaper)
    {
        //
        // dd($magazine);
        $newsDatas = Newspaper::where('id', $newspaper->id)->delete();
        Cache::forget('home_news0');
        Cache::forget('home_news1');
        if ($newsDatas) {
            $route = $this->route_path . '.newspapers.index';
            return redirect()->route($route)
                ->withSuccess('Newspaper Deleted Successfully');
        } else {
            return redirect()->back()
                ->with('error', 'Something went wrong');
        }
    }

    private function getDataForForm($for = 'news')
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

    private function getDataForNews($publication_ids, $type)
    {
        // dd($type);
        $categories = [];
        if ($type == "newspaper") {
            // dump('news');
            $categories = Newspaper::active()->latest()->pluck('category_id')->unique()->all();
        } elseif ($type == 'magazine') {
            // dump('magazine');
            $categories = Magazine::active()->latest()->pluck('category_id')->unique()->all();
        } else {
            // dump('all');
            $categories = Category::pluck('id')->toArray();
        }

        $data['all_category']     = Category::active()->whereIn('id', $categories)->latest()->get();
        $data['all_publications']   = Publication::whereIn('id', $publication_ids)->active()->latest()->get();
        // $data['all_publications']   = Publication::active()->latest()->get();
        // dd($data);
        return $data;
    }

    public function filterByType($request)
    {
        $content = null;
        $content_type = in_array($request->get('type'), ['newspaper', 'magazine'])
            ? $request->get('type')
            : 'newspaper';

        if ($content_type == 'magazine') {
            $content = Magazine::query();
        } else {
            $content = Newspaper::query();
        }

        if ($category_id = intval($request->get('category_id'))) {
            $content->where('category_id', $category_id);
        }
        if ($publication_id = intval($request->get('publication_id'))) {
            $content->where('publication_id', $publication_id);
        }

        if ($form = strtotime($request->get('from'))) {
            // $content->whereDate('created_at', '>=', date('Y-m-d', $form));
            $content->whereDate('published_date', date('Y-m-d', $form));
        }

        if ($to = strtotime($request->get('to'))) {
            // $content->whereDate('created_at', '<=', date('Y-m-d', $to));
            $content->whereDate('published_date', '<=', date('Y-m-d', $to));
        }

        $content = $content->active()->latest()->paginate($this->limits ?? 15)->getCollection();

        $publication_ids = Publication::active()->get()->pluck('id')->toArray();

        $resourcedata = ['all_category' => [], 'all_publications' => []];

        if (!empty($publication_ids)) {
            $resourcedata = $this->getDataForNews($publication_ids, $content_type);
        }

        return ApiResponse::ok(
            \ucfirst($content_type) . ' Data',
            [
                'all_category' => CategoryResource::collection($resourcedata['all_category']),
                'all_publications' => PublicationResource::collection($resourcedata['all_publications']),
                "newspaperdata" => $content_type == 'magazine'
                    ? MagazineResource::collection($content)
                    : NewspaperResource::collection($content),
            ]
        );
    }
}
