<?php
namespace App\Vars;

use App\Models\Blog;
use App\Models\Category;
use App\Models\RssFeed;
use App\Models\User;
use App\Vars\OneSignalNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class Rss
{
    // cache
    protected static $_notif_template = null;
    protected static $_notif_user_ids =[];
    protected static $_categoires = [];

    // format: https://www.graphic.com.gh/news.feed?type=rss
    /**title" => ""
        "link" => "https://www.graphic.com.gh/news/general-news/ghana-news-register-your-training-centres-before-june-31-2022-ctvet-boss-directs-technical-vocational-institutions. ▶"
        "guid" => "https://www.graphic.com.gh/news/general-news/ghana-news-register-your-training-centres-before-june-31-2022-ctvet-boss-directs-technical-vocational-institutions. ▶"
        "description" => "<p><img src="https://www.graphic.com.gh/images/2021/nov/16/Dr_Fred_Kyei.png" /></p><h2>Any new technical and vocational institution or centre coming into force  ▶"
        "category" => "General News"
        "pubDate" => "Tue, 16 Nov 2021 10:15:52 +0000"
     */
    public static function read($url = '', $format = '')
    {
        if( ! filter_var($url, FILTER_SANITIZE_URL) ) {
            return false;
        }

        $result = [];

        try {

            $query_str = parse_url($url, PHP_URL_QUERY);
            parse_str($query_str, $query_params);

            if( !empty($query_params) && isset($query_params['_test_99809233']) ) {

                $context = stream_context_create(['http' => ['ignore_errors' => true]]);

                var_dump( @file_get_contents($url, false, $context) );

                exit;

            }

            $context = stream_context_create(
                array(
                    "http" => array(
                        "header" => "User-Agent: Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/50.0.2661.102 Safari/537.36"
                    )
                )
            );

            $xml_string = file_get_contents($url, false, $context);

            if( empty($xml_string) ) {
                throw new \Exception('RSS-FEED: No response from url');
            }

            $responseXml = (object)simplexml_load_string( $xml_string, "SimpleXMLElement", LIBXML_NOCDATA );

            $responseXml = $responseXml->channel;

            foreach( $responseXml->item as $item ) {
                $content = null; $author = null;
                try {
                    $content = (string)$item->children('http://purl.org/rss/1.0/modules/content/')->encoded;
                    $author  = (string)$item->children('http://purl.org/dc/elements/1.1/');
                } catch(\Exception $e) {
                    logger($e->getMessage());
                }

                $item = (array)$item;

                if( !empty($item) ) {

                    if( empty($content) ) {
                        $content = $item['description'] ?? '';
                    }

                    if( !empty($content) && $content instanceof \SimpleXMLElement ) {
                        $content = (string)$content;
                    }

                    // get an image from this description
                    preg_match('/<img\s.*?\bsrc="(.*?)".*?>/si', $content, $matches);

                    $item['main_image_url'] = NULL;

                    if( !empty($matches) && isset($matches[1]) ) {
                        $item['main_image_url'] = $matches[1];
                    }

                    // try to get image from enclosure - and use it if available
                    if( isset($item['enclosure']) ) {
                        $enclosures = (array)$item['enclosure'];
                        if( isset($enclosures['url']) ) {
                            $item['main_image_url'] = $enclosures['url'];
                        } else {
                            foreach( $enclosures as $enclosure ) {
                                if( is_array($enclosure) && isset($enclosure['url']) ) {
                                    $item['main_image_url'] = $enclosure['url'];
                                } else if( is_object($enclosure) ) {
                                    $type = (string)$enclosure->attributes()->type;
                                    if( \strpos($type, 'image') > -1 ) {
                                        $item['main_image_url'] = (string)$enclosure->attributes()->url;
                                    }
                                }
                            }
                        }
                    }

                    $item['description'] = trim( preg_replace("/<img[^>]+\>/i",'',$content) );

                    // unique remote hash
                    $item['rss_ustamp'] = strtotime($item['pubDate']);

                    $item['pubDate'] = date('Y-m-d H:i:s', $item['rss_ustamp']);

                    if( empty($item['description']) ) {
                        $item['description'] = $item['title'] ?? '';
                    }

                    $item['author'] = $author;

                    $result[] = $item;
                }
            }

        } catch(\Exception $e) {
            logger($e->getMessage());
        }

        return $result;
    }
    
    public static function sync(RssFeed $rss, $articles = [], Request $request = null)
    {
        DB::beginTransaction();

        try {

            $blog_category_id = $rss->blog_category_id;

            if( isset(static::$_categoires[$blog_category_id]) ) {
                
                $category = static::$_categoires[$blog_category_id];

            } else {
                
                $category = Category::find($blog_category_id);

                static::$_categoires[$blog_category_id] = $category;
            }

            if( empty($category) ) {
                throw new \Exception('Invalid category');
            }

            if( empty($articles) ) {
                $articles = self::read($rss->url);
            }

            if( empty($articles) ) {
                throw new \Exception('No data could be retrieved on the URL');
            }
    
            $articles = \array_map(function($article) use($category, &$rss, &$request) {
                $slug = $article['rss_ustamp'] . '-' . \Illuminate\Support\Str::slug($article['title'], '-');

                // remove empty p tags
                $description = \preg_replace("/<p[^>]*>(?:\s|&nbsp;)*<\/p>/", '', $article['description']);

                $short_description = \strip_tags($description);
                $short_description = \strlen($short_description) > 200
                    ? \substr($short_description,0,200)."..." : $short_description;

                $short_description = \preg_replace('/[\x00-\x1F\x7F]/u', '', $short_description);

                $arr = [
                    'title' => $article['title'],
                    'blog_category_id' => $category->id,
                    'slug' => $slug,
                    'author' => $article['author'] ?? null,
                    'short_description' => $short_description,
                    'content' => $description,
                    'content_image' => $article['main_image_url'] ?? '',
                    'rss_feed_id' => $rss->id,
                    'rss_ustamp' => $article['rss_ustamp'],
                    'promoted' => 0,
                    'top_story' => 1,
                    'created_at' => $article['pubDate'],
                    'updated_at' => $article['pubDate']
                ];

                if( $request ) {
                    // if( $request->has('promoted') ) {
                    //     $arr['promoted'] = 1;
                    // }
                    // if( $request->has('top_story') ) {
                    //     $arr['top_story'] = 1;
                    // }
                    if( $request->has('banner_slider') ) {
                        $arr['banner_slider'] = 1;
                    }
                }

                return $arr;
            }, $articles);
    
            $inserted_count = Blog::upsert($articles,
                ['rss_ustamp'],
                [
                    'created_at' => DB::raw('created_at'),
                    'updated_at' => DB::raw('updated_at')
                ]);

            $rss->last_synced = date('Y-m-d H:i:s');
            $rss->update();

            DB::commit();

            // push notification
            // if( $inserted_count > 0 ) {
            //     $blogs = Blog::query()
            //         ->latest()
            //         ->take($inserted_count)
            //         ->get();

            //     foreach( $blogs as $blog ) {
            //         static::push_notification($category, $blog);
            //     }
            // }

            return $inserted_count;

        } catch(\Exception $e) {
            logger($e->getMessage());
        }

        DB::rollBack();

        return false;
    }

    public static function push_notification(Category $category, Blog $blog)
    {
        $notifManager = new OneSignalNotification();

        if( empty($notif_template = static::$_notif_template) ) {

            $notif_template = \App\Models\NotifTemplate::query()
                ->where('event', 'new_blogs')
                ->with(['restrictions'])
                ->first();

            static::$_notif_template = $notif_template;
        }

        if( empty($users = static::$_notif_user_ids) ) {
            $age_group = 'all';
            $gender = 'all';

            if( $notif_template ) {
                $restriction = $notif_template->restrictions
                    ->where('category_id', $category->id)
                    ->first();

                if( $restriction ) {
                    $age_group = $restriction->age_group;
                    $gender = $restriction->gender;
                }
            }

            $users = User::PushEnabled();

            if( $age_group !== 'all' && ($age_group = intval($age_group)) ) {
                $users->whereRaw("TIMESTAMPDIFF(YEAR, dob, CURDATE()) >= ?", [$age_group]);
            }

            if( $gender !== 'all' ) {
                $users->where('gender', $gender);
            }

            $users = $users
                ->get()
                ->pluck('id')
                ->toArray();

            static::$_notif_user_ids = $users;
        }

        logger("pushing notif to:  ". \json_encode($users));

        if( ! empty($users) ) {
            try {
                $notifManager->setData([
                    'n_id' => $blog->id,
                    'n_type' => $blog->promoted == 1
                        ? 'promoted' : 'top_story'
                ]);

                $response = $notifManager->send(
                    $users,
                    ($blog->title ?? '') . ' ['.$category->name.']',
                    $notif_template->content ?? ''
                );

                logger("pushing response:  ". \json_encode($response));

                if( is_array($response) && isset($response['errors']) ) {
                    foreach((array)$response['errors'] as $error) {
                        logger('One signal: '.$error . ': all users');
                    }
                }
            } catch(\Exception $e) {
                logger('Push Notification: '.$e->getMessage());
            }
        }
    }
}