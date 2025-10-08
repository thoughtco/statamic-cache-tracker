<?php

namespace Thoughtco\StatamicCacheTracker\Http\Controllers;

use Statamic\Http\Controllers\Controller;
use Statamic\Support\Str;
use Thoughtco\StatamicCacheTracker\Facades\Tracker;

class UtilityController extends Controller
{
    public function __invoke(): array
    {
        $urls = request()->input('urls');

        if ($urls == '*') {
            Tracker::flush();

            return [
                'message' => __('Cache flushed'),
            ];
        }

        $urls = collect(explode(PHP_EOL, $urls));

        $wildcards = $urls->filter(fn ($url) => Str::endsWith($url, '*'));

        // remove any non-wildcards first
        $urls->reject(fn ($url) => Str::endsWith($url, '*'))
            ->each(fn ($url) => Tracker::remove($url));

        collect(Tracker::all())
            ->each(function ($data) use ($wildcards) {
                $wildcards->each(function ($wildcard) use ($data) {
                    if (Str::startsWith($data['url'], Str::beforeLast($wildcard, '*'))) {
                        Tracker::remove($data['url']);
                    }
                });
            });

        return [
            'message' => __('URLs cleared from cache'),
        ];
    }
}
