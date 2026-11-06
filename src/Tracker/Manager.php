<?php

namespace Thoughtco\StatamicCacheTracker\Tracker;

use Closure;
use Illuminate\Cache\RedisStore as IlluminateRedisStore;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use Statamic\StaticCaching\Cacher;
use Thoughtco\StatamicCacheTracker\Events\ContentTracked;
use Thoughtco\StatamicCacheTracker\Tracker\Stores\CacheStore;
use Thoughtco\StatamicCacheTracker\Tracker\Stores\RedisStore;
use Thoughtco\StatamicCacheTracker\Tracker\Stores\Store;

class Manager
{
    /**
     * Older versions kept every URL in one array under this key.
     */
    public const LEGACY_CACHE_KEY = 'tracker::urls';

    private array $pipelines = [];

    private ?Store $store = null;

    public function add(string $url, array $tags = [])
    {
        $this->store()->put(md5($url), $url, collect($tags)->unique()->values()->all());

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
        return $this->store()->all();
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

    public function store(): Store
    {
        if ($this->store) {
            return $this->store;
        }

        $this->store = $this->resolveStore();

        $this->migrateLegacyData();

        return $this->store;
    }

    private function resolveStore(): Store
    {
        $cache = $this->cacheStore();
        $store = $cache->getStore();

        if ($store instanceof IlluminateRedisStore) {
            return new RedisStore($store->connection(), $store->getPrefix().'cache-tracker:');
        }

        return new CacheStore($cache);
    }

    public function get(string $url)
    {
        return $this->store()->get(md5($url));
    }

    public function getAdditionalTrackers()
    {
        return $this->pipelines;
    }

    public function has(string $url)
    {
        return $this->get($url) !== null;
    }

    /**
     * Get the tracked records with a tag matching any of the given tags.
     */
    public function findByTags(array $tags): array
    {
        return $this->store()->matching($tags);
    }

    public function invalidate(array $tags = [])
    {
        $matches = $this->store()->matching($tags);

        if (empty($matches)) {
            return $this;
        }

        // With background recaching the page is refreshed in place rather than
        // deleted, and the recache request re-tracks the URL with fresh tags. We
        // keep the existing entry so that if the recache job fails or is dropped
        // the URL stays tracked and future saves will retry it, instead of being
        // orphaned in the static cache with no tags.
        if (! config('statamic.static_caching.background_recache', false)) {
            $this->store()->forget(array_keys($matches));
        }

        $this->invalidateUrls(array_values(array_column($matches, 'url')));

        return $this;
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

        $this->store()->flush();
    }

    public function remove(string $url)
    {
        $this->invalidateUrls([$url]);

        $this->store()->forget([md5($url)]);
    }

    /**
     * Move data from the old single-key format into the current store.
     */
    private function migrateLegacyData(): void
    {
        $cache = $this->cacheStore();

        if (! $cache->has(self::LEGACY_CACHE_KEY)) {
            return;
        }

        $migrate = function () use ($cache) {
            $records = collect($cache->get(self::LEGACY_CACHE_KEY) ?? [])
                ->filter(fn ($record) => is_array($record) && isset($record['url'], $record['tags']))
                ->map(fn ($record) => ['url' => $record['url'], 'tags' => array_values($record['tags'])])
                ->all();

            $this->store->import($records);

            $cache->forget(self::LEGACY_CACHE_KEY);
        };

        if (! $cache->getStore() instanceof LockProvider) {
            $migrate();

            return;
        }

        // Only one process migrates; the others carry on without waiting.
        $cache->getStore()->lock(self::LEGACY_CACHE_KEY.':migrating', 600)->get($migrate);
    }
}
