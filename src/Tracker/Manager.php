<?php

namespace Thoughtco\StatamicCacheTracker\Tracker;

use Closure;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use Statamic\StaticCaching\Cacher;

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

        return $this;
    }

    public function get(string $url)
    {
        return $this->all()[md5($url)] ?? null;
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

    public function getAdditionalTrackers()
    {
        return $this->pipelines;
    }

    public function invalidate(array $tags = [])
    {
        $storeData = $this->all();

        $urls = [];
        foreach ($storeData as $key => $data) {
            $storeTags = $data['tags'];
            $url = $data['url'];

            if (count(array_intersect($tags, $storeTags)) > 0) {
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

    private function invalidateUrls($urls)
    {
        $cacher = app(Cacher::class);
        $cacher->invalidateUrls($urls);
    }

    public function remove(string $url)
    {
        $this->cacheStore()->forget(md5($url));
    }
}
