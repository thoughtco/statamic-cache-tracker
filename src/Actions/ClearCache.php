<?php

namespace Thoughtco\StatamicCacheTracker\Actions;

use Statamic\Actions\Action;
use Statamic\Contracts\Entries\Entry;
use Statamic\Contracts\Taxonomies\Term;
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

    public function icon(): string
    {
        return 'rewind';
    }

    public static function title()
    {
        return __('Clear cache');
    }

    public function confirmationText()
    {
        return __('Are you sure you want to clear the static cache for the url: :url ?', ['url' => $this->items->first()->absoluteUrl()]);
    }

    public function visibleTo($item)
    {
        if (! auth()->user()->can('clear cache tracker tags')) {
            return false;
        }

        if (! ($item instanceof Entry || $item instanceof Term)) {
            return false;
        }

        return ! Blink::once(
            'cache-action::'.$item->collectionHandle.'::'.$item->locale(),
            fn () => is_null($item->collection()->route($item->locale()))
        );
    }
}
