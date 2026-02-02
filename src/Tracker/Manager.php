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

        $urls = [];
        foreach ($storeData as $key => $data) {
            $storeTags = $data['tags'];
            $url = $data['url'];

            if ($this->tagsMatch($tags, $storeTags)) {
                $urls[] = $url;

                unset($storeData[$key]);
            }
        }

        if (! empty($urls)) {
            $this->cacheStore()->forever($this->cacheKey, $storeData);

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

        $this->cacheStore()->forget(md5($url));
    }
}
