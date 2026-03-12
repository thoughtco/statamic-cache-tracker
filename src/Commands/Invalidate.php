<?php

namespace Thoughtco\StatamicCacheTracker\Commands;

use Illuminate\Console\Command;
use Thoughtco\StatamicCacheTracker\Facades\Tracker;

class Invalidate extends Command
{
    protected $signature = 'cache-tracker:invalidate {url : The URL to remove from the cache tracker}';

    protected $description = 'Remove a URL from the cache tracker and invalidate it from the static cache';

    public function handle()
    {
        $url = $this->argument('url');

        if (! Tracker::has($url)) {
            $this->warn("URL not found in tracker: {$url}");

            return 1;
        }

        Tracker::remove($url);

        $this->info("Invalidated: {$url}");
    }
}
