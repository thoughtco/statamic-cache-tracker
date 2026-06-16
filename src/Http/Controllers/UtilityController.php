<?php

namespace Thoughtco\StatamicCacheTracker\Http\Controllers;

use Statamic\Http\Controllers\Controller;
use Statamic\Support\Str;
use Thoughtco\StatamicCacheTracker\Facades\Tracker;

class UtilityController extends Controller
{
    public function __invoke(): array
    {
        if (! $urls = request()->input('urls')) {
            return [
                'message' => __('No URLs provided'),
            ];
        }

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
            ->each(function ($url) {
                if (Str::endsWith($url, '/')) {
                    $url = substr($url, 0, -1);
                }

                Tracker::remove($url);
            });

        collect(Tracker::all())
            ->each(function ($data) use ($wildcards) {
                $wildcards->each(function ($wildcard) use ($data) {
                    $prefix = Str::beforeLast($wildcard, '*');

                    if (Str::startsWith($prefix, ['http://', 'https://'])) {
                        $matches = Str::startsWith($data['url'], $prefix);
                    } else {
                        $prefix = '/'.ltrim($prefix, '/');
                        $urlPath = parse_url($data['url'], PHP_URL_PATH) ?? $data['url'];
                        $matches = str_contains($urlPath, $prefix);
                    }

                    if ($matches) {
                        Tracker::remove($data['url']);
                    }
                });
            });

        return [
            'message' => __('URLs cleared from cache'),
        ];
    }
}
