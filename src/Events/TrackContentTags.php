<?php

namespace Thoughtco\StatamicCacheTracker\Events;

use Statamic\Events\Event;

class TrackContentTags extends Event
{
    public function __construct(public array $tags) {}
}
