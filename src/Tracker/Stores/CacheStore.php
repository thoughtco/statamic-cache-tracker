<?php

namespace Thoughtco\StatamicCacheTracker\Tracker\Stores;

use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Cache\Repository;
use Thoughtco\StatamicCacheTracker\Tracker\TagMatcher;

/**
 * Works with any Laravel cache store.
 *
 * Layout:
 *  - tracker::url:{key}     ['url' => ..., 'tags' => [...]] for one URL
 *  - tracker::index:{xx}    [key => url], sharded 256 ways by md5(key)
 *  - tracker::tag:{md5}     [key => true] for every URL that was tracked with the tag
 *
 * Tag buckets are only ever added to when tracking. Entries for URLs that have
 * since been removed or re-tracked without the tag are verified against the
 * URL record on lookup and pruned then, so a stale entry never causes a URL to
 * be invalidated and adding to a bucket never needs to know a URL's old tags.
 */
class CacheStore implements Store
{
    private const CHUNK = 500;

    private const LOCK_SECONDS = 10;

    private const LOCK_WAIT = 5;

    public function __construct(private Repository $cache) {}

    public function get(string $key): ?array
    {
        return $this->cache->get($this->urlKey($key));
    }

    public function put(string $key, string $url, array $tags): void
    {
        $this->cache->forever($this->urlKey($key), ['url' => $url, 'tags' => $tags]);

        $this->addToSet($this->indexKey($key), [$key => $url]);

        foreach ($tags as $tag) {
            $this->addToSet($this->tagKey($tag), [$key => true]);
        }
    }

    public function import(array $records): void
    {
        $existing = $this->many(array_keys($records));

        $index = [];
        $buckets = [];

        foreach ($records as $key => $record) {
            if (isset($existing[$key])) {
                continue;
            }

            $this->cache->forever($this->urlKey($key), $record);

            $index[$this->indexKey($key)][$key] = $record['url'];

            foreach ($record['tags'] as $tag) {
                $buckets[$this->tagKey($tag)][$key] = true;
            }
        }

        foreach ($index + $buckets as $setKey => $members) {
            $this->addToSet($setKey, $members);
        }
    }

    public function forget(array $keys): void
    {
        $byShard = [];

        foreach ($keys as $key) {
            $this->cache->forget($this->urlKey($key));
            $byShard[$this->indexKey($key)][] = $key;
        }

        foreach ($byShard as $shard => $members) {
            $this->locked($shard, function () use ($shard, $members) {
                $index = $this->cache->get($shard) ?? [];
                $remaining = array_diff_key($index, array_flip($members));

                if (count($remaining) !== count($index)) {
                    $this->cache->forever($shard, $remaining);
                }
            });
        }
    }

    public function all(): array
    {
        $keys = [];

        foreach ($this->cache->many($this->indexKeys()) as $index) {
            foreach ($index ?? [] as $key => $url) {
                $keys[] = $key;
            }
        }

        return $this->many($keys);
    }

    public function matching(array $tags): array
    {
        [$exact, $prefixes] = TagMatcher::split($tags);

        // Wildcards can't be answered from the tag buckets, so fall back to a scan.
        if (! empty($prefixes)) {
            return array_filter($this->all(), fn ($record) => TagMatcher::matches($tags, $record['tags']));
        }

        $buckets = [];
        foreach (array_chunk($exact, self::CHUNK) as $chunk) {
            $values = $this->cache->many(array_map(fn ($tag) => $this->tagKey($tag), $chunk));

            foreach ($chunk as $tag) {
                $buckets[$tag] = array_keys($values[$this->tagKey($tag)] ?? []);
            }
        }

        $records = $this->many(array_unique(array_merge(...array_values($buckets ?: [[]]))));

        foreach ($buckets as $tag => $keys) {
            $stale = array_filter($keys, fn ($key) => ! $this->recordHasTag($records[$key] ?? null, $tag));

            if (! empty($stale)) {
                $this->prune($tag, $stale);
            }
        }

        return array_filter($records, fn ($record) => TagMatcher::matches($exact, $record['tags']));
    }

    public function flush(): void
    {
        $records = $this->all();

        $tags = [];
        foreach ($records as $record) {
            foreach ($record['tags'] as $tag) {
                $tags[$tag] = true;
            }
        }

        foreach (array_keys($tags) as $tag) {
            $this->cache->forget($this->tagKey($tag));
        }

        foreach (array_keys($records) as $key) {
            $this->cache->forget($this->urlKey($key));
        }

        foreach ($this->indexKeys() as $shard) {
            $this->cache->forget($shard);
        }
    }

    /**
     * Remove stale keys from a tag bucket, re-checking each one inside the lock
     * so a URL that was re-tracked with the tag in the meantime is kept.
     */
    private function prune(string $tag, array $stale): void
    {
        $bucketKey = $this->tagKey($tag);

        $this->locked($bucketKey, function () use ($bucketKey, $tag, $stale) {
            $bucket = $this->cache->get($bucketKey) ?? [];
            $current = $this->many($stale);

            foreach ($stale as $key) {
                if (! $this->recordHasTag($current[$key] ?? null, $tag)) {
                    unset($bucket[$key]);
                }
            }

            empty($bucket)
                ? $this->cache->forget($bucketKey)
                : $this->cache->forever($bucketKey, $bucket);
        });
    }

    private function addToSet(string $setKey, array $members): void
    {
        // Most tracking is of URLs/tags already in the set, so check without
        // the lock first and skip the write entirely when nothing changes.
        $set = $this->cache->get($setKey) ?? [];

        if (array_intersect_assoc($members, $set) === $members) {
            return;
        }

        $this->locked($setKey, function () use ($setKey, $members) {
            $set = $this->cache->get($setKey) ?? [];
            $this->cache->forever($setKey, array_replace($set, $members));
        });
    }

    private function locked(string $key, callable $callback): mixed
    {
        $store = $this->cache->getStore();

        if (! $store instanceof LockProvider) {
            return $callback();
        }

        try {
            return $store->lock($key.':lock', self::LOCK_SECONDS)->block(self::LOCK_WAIT, $callback);
        } catch (LockTimeoutException) {
            // Better to risk a lost update than to fail the request.
            return $callback();
        }
    }

    /**
     * @return array<string, array> records keyed by URL key, missing ones omitted
     */
    private function many(array $keys): array
    {
        $records = [];

        foreach (array_chunk(array_values($keys), self::CHUNK) as $chunk) {
            $values = $this->cache->many(array_map(fn ($key) => $this->urlKey($key), $chunk));

            foreach ($chunk as $key) {
                if ($record = $values[$this->urlKey($key)] ?? null) {
                    $records[$key] = $record;
                }
            }
        }

        return $records;
    }

    private function recordHasTag(?array $record, string $tag): bool
    {
        return $record && in_array($tag, $record['tags']);
    }

    private function indexKeys(): array
    {
        return array_map(fn ($i) => 'tracker::index:'.sprintf('%02x', $i), range(0, 255));
    }

    private function urlKey(string $key): string
    {
        return 'tracker::url:'.$key;
    }

    private function indexKey(string $key): string
    {
        return 'tracker::index:'.substr(md5($key), 0, 2);
    }

    private function tagKey(string $tag): string
    {
        return 'tracker::tag:'.md5($tag);
    }
}
