<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RssFeed extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $table = 'rss_feed_mgt';

    public function blog_category()
    {
        return $this->belongsTo(Category::class, 'blog_category_id');
    }

    public function blog_posts()
    {
        return $this->hasMany(Blog::class, 'rss_feed_id');
    }

    public function blog_posts_latest()
    {
        return $this->blog_posts()->latest();
    }
}
