<?php

namespace Thoughtco\StatamicCacheTracker\Http\Controllers;

use Statamic\Http\Controllers\Controller;
use Thoughtco\StatamicCacheTracker\Facades\Tracker;

class GetUrlsController extends Controller
{
    public function __invoke(): array
    {
        $item = request()->input('item');

        return collect(Tracker::all())
            ->filter(fn ($tracked) => in_array($item, $tracked['tags']))
            ->all();
    }
}
