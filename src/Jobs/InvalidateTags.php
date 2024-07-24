<?php

namespace Thoughtco\StatamicCacheTracker\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Thoughtco\StatamicCacheTracker\Facades\Tracker;

class InvalidateTags implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function __construct(public array $tags) {}

    public function handle(): void
    {
        Tracker::invalidate($this->tags);
    }
}
