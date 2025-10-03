<?php

namespace Thoughtco\StatamicCacheTracker\Http\Controllers;

use Statamic\Http\Controllers\Controller;
use Thoughtco\StatamicCacheTracker\Facades\Tracker;

class GetTagsController extends Controller
{
    public function __invoke(): array
    {
        if (! $url = request()->input('item')) {
            return [];
        }

        return Tracker::get($url) ?? []; //
    }
}
