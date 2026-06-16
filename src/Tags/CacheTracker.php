<?php

namespace Thoughtco\StatamicCacheTracker\Tags;

use Statamic\Tags\Tags;
use Thoughtco\StatamicCacheTracker\Events\TrackContentTags;

class CacheTracker extends Tags
{
    /**
     * {{ cache_tracker tag="hero" }}
     */
    public function index(): void
    {
        $this->track($this->params->get('tag'));
    }

    /**
     * {{ cache_tracker:hero }}
     */
    public function wildcard(string $method): void
    {
        $this->track($method);
    }

    private function track(?string $tag): void
    {
        if (! is_null($tag)) {
            TrackContentTags::dispatch([$tag]);
        }
    }
}
