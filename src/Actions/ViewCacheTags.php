<?php

namespace Thoughtco\StatamicCacheTracker\Actions;

use Statamic\Actions\Action;
use Statamic\Contracts\Entries\Entry;
use Statamic\Facades\Blink;
use Thoughtco\StatamicCacheTracker\Facades\Tracker;

class ViewCacheTags extends Action
{
    protected $component = 'cache-tracker-modal';

    protected $runnable = false;

    public function run($items, $values)
    {
        // no running in the corridor
    }

    public function icon(): string
    {
        return 'taxonomies';
    }

    public static function title()
    {
        return __('View cache tags');
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

    public function visibleToBulk($items)
    {
        return false;
    }

    public function buttonText()
    {
        /** @translation */
        return __('Clear cache');
    }
}
