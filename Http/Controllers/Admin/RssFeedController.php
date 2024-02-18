<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\RssFeed;
use App\Vars\Rss;
use Illuminate\Http\Request;

class RssFeedController extends Controller
{
    public function index()
    {
        $rss_feeds = RssFeed::query()
            ->with(['blog_category'])
            ->get();

        return view('admin.rss_feed.index', compact('rss_feeds'));
    }

    public function create()
    {
        $categories = Category::active()->get();

        return view('admin.rss_feed.create', compact('categories'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'category_id' => ['required', 'exists:categories,id'],
            'url' => ['bail', 'required', 'max:1000', 'url']
        ]);

        // fetch data for it
        $articles = Rss::read($request->url);

        if( empty( $articles ) ) {
            return back()
                ->withInput()
                ->withError('No data could be retrieved on the URL');
        }

        $rss = RssFeed::firstWhere('url', $request->get('url'));

        if( ! $rss ) {
            $rss = RssFeed::create([
                'blog_category_id' => $request->get('category_id'),
                'url' => $request->get('url'),
                'last_synced' => date('Y-m-d H:i:s')
            ]);
        }

        $inserCount = Rss::sync( $rss, $articles, $request );

        if( $inserCount > 0 ) {
            return redirect()->route('admin.rss_feed_mgt.index')
                ->withSuccess('Successfully Added Link and Posts Synced');
        }

        return redirect()->route('admin.rss_feed_mgt.index')
                ->withInfo('Successfully Added Link and Posts Synced');
    }

    public function destroy(RssFeed $rss_feed_mgt)
    {
        $rss_feed_mgt->delete();

        return back()->withSuccess('Feed Link Removed');
    }

    public function resync(RssFeed $rss_feed_mgt)
    {
        $inserCount = Rss::sync( $rss_feed_mgt );

        if( $inserCount > 0 ) {
            return redirect()->route('admin.rss_feed_mgt.index')
                ->withSuccess('Posts Synced Successfully');
        }

        return redirect()->route('admin.rss_feed_mgt.index')
            ->withInfo('No new posts were there');
    }
}
