<?php

namespace Thoughtco\StatamicCacheTracker\Events;

use Statamic\Events\Event;

class ContentTracked extends Event
{
    public function __construct(public string $url, public array $tags) {}
}
