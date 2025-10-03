<?php

namespace Thoughtco\StatamicCacheTracker\Http\Controllers;

use Statamic\Http\Controllers\Controller;
use Statamic\Support\Str;
use Thoughtco\StatamicCacheTracker\Facades\Tracker;

class GetTagsController extends Controller
{
    public function __invoke(): array
    {
        if (! $url = request()->input('url')) {
            return [];
        }

        if (Str::endsWith($url, '/')) {
            $url = Str::beforeLast($url, '/');
        }

        if ($data = Tracker::get($url)) {
            return $data['tags'];
        }

        return [];
    }
}
