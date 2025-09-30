<?php

namespace Thoughtco\StatamicCacheTracker\Actions;

use Statamic\Actions\Action;
use Statamic\Contracts\Entries\Entry;
use Statamic\Facades\Blink;
use Thoughtco\StatamicCacheTracker\Facades\Tracker;

class ClearCache extends Action
{
    public function run($items, $values)
    {
        $items->filter(fn ($item) => $item->absoluteUrl())
            ->each(fn ($item) => Tracker::remove($item->absoluteUrl()));

        return __('Cache cleared');
    }

    public static function title()
    {
        return __('Clear cache');
    }

    public function visibleTo($item)
    {
        if (! $item instanceof Entry) {
            return false;
        }

        return ! Blink::once(
            'cache-action::'.$item->collectionHandle.'::'.$item->locale(),
            fn () => is_null($item->collection()->route($item->locale()))
        );
    }
}
