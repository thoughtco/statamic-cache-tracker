<?php

namespace Thoughtco\StatamicCacheTracker\Tracker;

use Closure;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use Statamic\StaticCaching\Cacher;
use Thoughtco\StatamicCacheTracker\Events\ContentTracked;

class Manager
{
    private string $cacheKey = 'tracker::urls';

    private array $pipelines = [];

    public function add(string $url, array $tags = [])
    {
        $storeData = $this->all();
        $storeData[md5($url)] = [
            'url' => $url,
            'tags' => collect($tags)->unique()->values()->all(),
        ];

        $this->cacheStore()->forever($this->cacheKey, $storeData);

        ContentTracked::dispatch($url, $tags);

        return $this;
    }

    public function addAdditionalTracker(Closure|string $class)
    {
        if (is_string($class)) {
            $class = new $class;
        }

        $this->pipelines[] = $class;

        return $this;
    }

    public function all()
    {
        return $this->cacheStore()->get($this->cacheKey) ?? [];
    }

    public function cacheStore()
    {
        try {
            $store = Cache::store('static_cache');
        } catch (InvalidArgumentException $e) {
            $store = Cache::store();
        }

        return $store;
    }

    public function get(string $url)
    {
        return $this->all()[md5($url)] ?? null;
    }

    public function getAdditionalTrackers()
    {
        return $this->pipelines;
    }

    public function has(string $url)
    {
        return Arr::exists($this->all(), md5($url));
    }

    public function invalidate(array $tags = [])
    {
        $storeData = $this->all();

        // With background recaching the page is refreshed in place rather than
        // deleted, and the recache request re-tracks the URL with fresh tags. We
        // keep the existing entry so that if the recache job fails or is dropped
        // the URL stays tracked and future saves will retry it, instead of being
        // orphaned in the static cache with no tags.
        $keepEntries = config('statamic.static_caching.background_recache', false);

        $urls = [];
        foreach ($storeData as $key => $data) {
            if (! $this->tagsMatch($tags, $data['tags'])) {
                continue;
            }

            $urls[] = $data['url'];

            if (! $keepEntries) {
                unset($storeData[$key]);
            }
        }

        if (! empty($urls)) {
            if (! $keepEntries) {
                $this->cacheStore()->forever($this->cacheKey, $storeData);
            }

            $this->invalidateUrls($urls);
        }

        return $this;
    }

    private function tagsMatch(array $tagsToInvalidate, array $storeTags): bool
    {
        foreach ($tagsToInvalidate as $tag) {
            // Handle wildcard tags (ending with *)
            if (str_ends_with($tag, '*')) {
                $prefix = substr($tag, 0, -1);
                foreach ($storeTags as $storeTag) {
                    if (str_starts_with($storeTag, $prefix)) {
                        return true;
                    }
                }
            } elseif (in_array($tag, $storeTags)) {
                return true;
            }
        }

        return false;
    }

    private function invalidateUrls($urls)
    {
        $cacher = app(Cacher::class);

        if (config('statamic.static_caching.background_recache', false)) {
            $cacher->refreshUrls($urls);

            return;
        }

        $cacher->invalidateUrls($urls);
    }

    public function flush()
    {
        $urls = collect($this->all())->pluck('url');

        $this->invalidateUrls($urls);

        $this->cacheStore()->forever($this->cacheKey, []);
    }

    public function remove(string $url)
    {
        $this->invalidateUrls([$url]);

        $storeData = $this->all();
        unset($storeData[md5($url)]);
        $this->cacheStore()->forever($this->cacheKey, $storeData);
    }
}
