<?php

namespace Thoughtco\StatamicCacheTracker\Http\Controllers;

use Statamic\Contracts\Entries\Entry;
use Statamic\Contracts\Taxonomies\Term;
use Statamic\Facades\Data;
use Statamic\Http\Controllers\Controller;
use Statamic\Taxonomies\LocalizedTerm;
use Thoughtco\StatamicCacheTracker\Facades\Tracker;

class GetUrlsController extends Controller
{
    public function __invoke(): array
    {
        if (! $url = request()->input('url')) {
            return [];
        }

        if (! $item = Data::find($url)) {
            return [];
        }

        if ($item instanceof Entry) {
            $item = $item->collectionHandle().':'.$item->id();
        }

        if ($item instanceof Term) {
            $item = 'term:'.$item->id();
        }

        return collect(Tracker::all())
            ->filter(fn ($tracked) => in_array($item, $tracked['tags']))
            ->all();
    }
}
