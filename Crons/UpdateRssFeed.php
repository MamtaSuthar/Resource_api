<?php

namespace App\Crons;

use App\Models\RssFeed;
use App\Vars\Rss;

class UpdateRssFeed
{
    public function __invoke()
    {
        $feeds = RssFeed::all();
        
        foreach( $feeds as $feed ) {
            Rss::sync($feed);
        }
    }
}