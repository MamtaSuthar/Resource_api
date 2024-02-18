<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class Blog extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected static $user_active_subscription = null;

    protected static $cache_cleard = false;

    ## Define Query Scopes ##
    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    public function scopeByCategory($query, int $id)
    {
        return $query->where('blog_category_id', $id);
    }

    ## Define Accessors ##
    public function getStatusTextAttribute()
    {
        $status = [
            0 => 'Deactive',
            1 => 'Active'
        ];
        return $status[$this->status];
    }

    public function getTagsStringAttribute()
    {
        return implode(',', $this->tags()->pluck('name')->toArray());
    }

    public function blog_category()
    {
        return $this->belongsTo(Category::class, 'blog_category_id');
    }

    public function tags()
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }

    public static function latest_category_ids()
    {
        return self::query()
            ->withoutGlobalScopes()
            ->select(['blog_category_id'])
            ->selectRaw('MAX(created_at) as created_at')
            ->groupby('blog_category_id')
            ->orderBy('created_at', 'DESC')
            ->get()
            ->pluck(['blog_category_id'])
            ->toArray();
    }

    protected static function booted()
    {

        $request = request();

        static::saved(function() {

            // clear topstory, promoted_content homepage cache
            if( ! self::$cache_cleard ) {
                self::$cache_cleard = true;

                Cache::forget('home_pc0');
                Cache::forget('home_pc1');
                Cache::forget('home_ts0');
                Cache::forget('home_ts1');
            }
        });

        // no filter if admin panel
        if( $request->is('admin/*') ) {
            return;
        }

        // no filter on blog post detail page - direct hit api
        if( $request->is('api/customer/blogs/promoted_content/*/view') || $request->is('api/customer/blogs/top_story/*/view') ) {
            return;
        }

        // no filter if coming from a linked magazine,newspaper file (pdf-viewer)
        try {
            if( ($key = $request->get('_88902_tan_key')) && decrypt($key) ) {
                return;
            }
        } catch(\Exception $e) {}

        // hide all blog posts that are of newspaper category
        static::addGlobalScope('hide_news_related_blogs', function($query) {
            $query->whereDoesntHave('blog_category', function($query) {
                $query->where('slug', 'like', '%news%');
            });
        });



        // // get user subscription
        // if( is_null($subscription = self::$user_active_subscription) ) {
        //     /** @var \App\Models\User $user */
        //     $user = Auth::user();

        //     if( $user ) {
        //         $subscription = $user->active_blog_subscription()->exists();
        //     } else {
        //         $subscription = false;
        //     }

        //     self::$user_active_subscription = $subscription;
        // }

        // // if an active subscription exist; then the user
        // // can see any blog content
        // if( $subscription ) {
        //     return;
        // }

        // static::addGlobalScope('blog_subscription', function($query) {
        //     $query->where('is_premium', '!=', 1);
        // });
    }

}
