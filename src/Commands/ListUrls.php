<?php

namespace Thoughtco\StatamicCacheTracker\Commands;

use Illuminate\Console\Command;
use Thoughtco\StatamicCacheTracker\Facades\Tracker;

class ListUrls extends Command
{
    protected $signature = 'cache-tracker:list {--url= : Filter by URL (supports * wildcard)}';

    protected $description = 'List all tracked URLs and their cache tags';

    public function handle()
    {
        $all = collect(Tracker::all());

        if ($filter = $this->option('url')) {
            $prefix = rtrim($filter, '*');
            $all = $all->filter(fn ($data) => str_starts_with($data['url'], $prefix));
        }

        if ($all->isEmpty()) {
            $this->info('No tracked URLs found.');

            return;
        }

        $this->table(
            ['URL', 'Tags'],
            $all->map(fn ($data) => [$data['url'], implode(', ', $data['tags'])])->values()
        );

        $this->info("Total: {$all->count()} URL(s)");
    }
}
