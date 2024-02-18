<?php
namespace App\Traits;

use App\Models\ContentGridView;
use App\Models\Magazine;
use App\Models\Newspaper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

trait GridViewForPContent
{
    public function content_make_grid_listing(Request $request)
    {
        $slides = ContentGridView::query();

        if( $content_id = intval($request->query('content_id')) ) {
            $slides->where('content_id', $content_id);
        }

        if( $content_type = $request->query('content_type') ) {
            if( \in_array($content_type, ['magazine', 'newspaper']) ) {
                $slides->where('content_type', $content_type);
            }
        }

        $slides = $slides->orderBy('slider_page_no','ASC')
            ->get()
            ->reduce(function($acc, $gridBlock) use($content_type) {

                if( !isset($acc[$gridBlock->slider_page_no]) ) {
                    $acc[$gridBlock->slider_page_no] = [
                        'slide' => $gridBlock->slider_page_no,
                        'content_id' => $gridBlock->content_id,
                        'content_type' => $gridBlock->content_type,
                        'layout' => $gridBlock->layout
                    ];

                    if( empty($content_type) ) {
                        $content_type = $gridBlock->content_type;
                    }
                }

                return $acc;
            }, []);

        $content = $content_type == 'magazine'
            ? Magazine::find($content_id)
            : Newspaper::find($content_id);

        return view('vendoruser.make_grid.listing', compact('slides', 'content', 'content_type'));
    }

    public function content_make_grid(Request $request)
    {
        if( $request->isMethod('post') ) {
            return $this->post_content_make_grid($request);
        }

        if( empty($content_id = intval($request->query('content'))) ) {
            abort(404);
        }

        $type = $request->query('type');

        if( !in_array($type, ['magazine','newspaper']) ) {
            abort(404);
        }

        $content = $type == 'magazine'
            ? Magazine::findOrFail($content_id)
            : Newspaper::findOrFail($content_id);

        $slide = intval($request->get('slide'));
        $slide = $slide > 0 ? $slide : 1;

        $gridBlocks = ContentGridView::where([
                'slider_page_no' => $slide,
                'content_type' => $type,
                'content_id' => $content->id
            ])
            ->get()
            ->map(function($gridBlock) {
                $gridBlock->thumbnail_image = $gridBlock->thumbnail_image
                    ? asset("storage/{$gridBlock->thumbnail_image}")
                    : null;

                $gridBlock->cover_image = $gridBlock->cover_image
                    ? asset("storage/{$gridBlock->cover_image}")
                    :null;

                return $gridBlock;
            });

        // if layout is set show layout page
        if( $layout = trim($request->query('layout')) ) {
            if( view()->exists("vendoruser.make_grid.layouts.{$layout}") ) {
                return view("vendoruser.make_grid.layouts.{$layout}", compact('content', 'gridBlocks'));
            }
        }

        return view('vendoruser.make_grid.choose_layout', compact('content'));
    }

    protected function post_content_make_grid(Request $request)
    {
        if( $request->get('submit_type') == 'cancel' ) {
            return redirect()
                ->route('vendor.magazines.index')
                ->withSuccess('Slides saved');
        }

        $validated = $request->validate([
            // query params
            'slide' => ['required', 'numeric'],
            'type' => ['required', 'in:magazine,newspaper'],
            'content' => ['required', 'numeric'],
            'layout' => ['required'],

            'order' => ['required', 'array'],
            'block_title' => ['required', 'array'],
            'block_title.*' => ['max:191'],
            'block_description' => ['required', 'array'],
            'block_description.*' => ['max:5000'],
            'block_thumbnail_image' => ['array'],
            'block_thumbnail_image.*' => ['mimes:jpg,jpeg,png'],
            'block_cover_image' => ['array'],
            'block_cover_image.*' => ['mimes:jpg,jpeg,png'],

            'submit_type' => ['required', 'in:new_slide,finish'],
        ]);

        $route = $validated['type'] == 'magazine'
            ? 'magazines'
            : 'newspapers';

        $content = $validated['type'] == 'magazine'
            ? Magazine::findOrFail($validated['content'])
            : Newspaper::findOrFail($validated['content']);

        if( empty($content) ) {
            return back()->withError('Invalid content type');
        }

        $block_titles = (array)$validated['block_title'];
        $block_descriptions = (array)$validated['block_description'];
        $block_thumbnail_images = $validated['block_thumbnail_image']??[];
        $block_cover_images = $validated['block_cover_image']??[];
        $orders = (array)$validated['order'];

        $content_type = \strtolower($content->type);

        foreach( $orders as $coords => $order) {
            $title = $block_titles[$coords] ?? null;
            $description = $block_descriptions[$coords] ?? null;

            [$mainAxisCount, $crossAxisCount] = \explode('.', \explode('_', $coords)[1]);

            $data = [
                'title' => $title,
                'description' => $description,
                'order' => intval($order),
                'crossAxisCount' => intval($crossAxisCount),
                'mainAxisCount' => intval($mainAxisCount),
                'content_type' => $content_type,
                'content_id' => $content->id,
                'slider_page_no' => intval($validated['slide']),
                'layout' => $validated['layout']
            ];

            if( isset($block_cover_images[$coords]) ) {
                if($path = $block_cover_images[$coords]->store('magazines', 'public')) {
                    $data['cover_image'] = $path;
                }
            }

            if( isset($block_thumbnail_images[$coords]) ) {
                if($path = $block_thumbnail_images[$coords]->store('magazines', 'public')) {
                    $data['thumbnail_image'] = $path;
                }
            }

            ContentGridView::updateOrCreate([
                'content_id' => $content->id,
                'content_type' => $content_type,
                'crossAxisCount' => intval($crossAxisCount),
                'mainAxisCount' => intval($mainAxisCount),
                'order' => intval($order),
                'slider_page_no' => intval($validated['slide'])
            ], $data);
        }

        if( $validated['submit_type'] === 'new_slide' ) {
            $qp = [
                'slide' => intval($validated['slide']) + 1,
                'type' => $validated['type'],
                'content' => $validated['content']
            ];

            return redirect()->route('vendor.content_make_grid', $qp)
                ->withSuccess('Slide saved');
        }

        if( $request->post('from_grid_listing') == '1' ) {
            $qp = [
                'content_type' => $validated['type']??'magazine',
                'content_id' => $content->id
            ];

            return redirect()->route('vendor.content_make_grid_listing',$qp)
                ->withSuccess('Slide updated');
        }

        return redirect()
            ->route("vendor.{$route}.index")
            ->withSuccess('Slides saved');
    }
}