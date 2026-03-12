<?php

namespace Thoughtco\StatamicCacheTracker\Commands;

use Illuminate\Console\Command;
use Thoughtco\StatamicCacheTracker\Facades\Tracker;

class Flush extends Command
{
    protected $signature = 'cache-tracker:flush';

    protected $description = 'Flush all tracked URLs from the cache tracker';

    public function handle()
    {
        $count = count(Tracker::all());

        Tracker::flush();

        $this->info("Flushed {$count} tracked URL(s).");
    }
}
