<?php

namespace Thoughtco\StatamicCacheTracker\Tracker\Stores;

use Illuminate\Redis\Connections\Connection;
use Illuminate\Redis\Connections\PhpRedisClusterConnection;
use Illuminate\Redis\Connections\PredisClusterConnection;
use Thoughtco\StatamicCacheTracker\Tracker\TagMatcher;

/**
 * Uses native Redis hashes and sets.
 *
 * Layout (under the cache store's prefix):
 *  - cache-tracker:urls        hash of key => json {url, tags}
 *  - cache-tracker:tag:{tag}   set of URL keys tracked with the tag
 *  - cache-tracker:tags        set of every tag seen (used for wildcard lookups)
 *
 * Every command touches a single key, so this also works on Redis Cluster.
 */
class RedisStore implements Store
{
    private const CHUNK = 1000;

    public function __construct(private Connection $redis, private string $prefix) {}

    public function get(string $key): ?array
    {
        return $this->decode($this->redis->hget($this->urlsKey(), $key));
    }

    public function put(string $key, string $url, array $tags): void
    {
        $old = $this->get($key);
        $removed = $old ? array_diff($old['tags'], $tags) : [];

        $this->atomically(function ($redis) use ($key, $url, $tags, $removed) {
            $redis->hset($this->urlsKey(), $key, $this->encode($url, $tags));

            foreach ($tags as $tag) {
                $redis->sadd($this->tagKey($tag), $key);
            }

            if (! empty($tags)) {
                $redis->sadd($this->tagsKey(), ...$tags);
            }

            foreach ($removed as $tag) {
                $redis->srem($this->tagKey($tag), $key);
            }
        });
    }

    public function import(array $records): void
    {
        foreach (array_chunk($records, self::CHUNK, true) as $chunk) {
            $existing = $this->many(array_keys($chunk));
            $chunk = array_diff_key($chunk, $existing);

            if (empty($chunk)) {
                continue;
            }

            $byTag = [];
            foreach ($chunk as $key => $record) {
                foreach ($record['tags'] as $tag) {
                    $byTag[$tag][] = $key;
                }
            }

            $this->batch(function ($redis) use ($chunk, $byTag) {
                foreach ($chunk as $key => $record) {
                    $redis->hset($this->urlsKey(), $key, $this->encode($record['url'], $record['tags']));
                }

                foreach ($byTag as $tag => $keys) {
                    $redis->sadd($this->tagKey($tag), ...$keys);
                }

                if (! empty($byTag)) {
                    $redis->sadd($this->tagsKey(), ...array_map('strval', array_keys($byTag)));
                }
            });
        }
    }

    public function forget(array $keys): void
    {
        foreach (array_chunk(array_values($keys), self::CHUNK) as $chunk) {
            $records = $this->many($chunk);

            $byTag = [];
            foreach ($records as $key => $record) {
                foreach ($record['tags'] as $tag) {
                    $byTag[$tag][] = $key;
                }
            }

            $this->atomically(function ($redis) use ($chunk, $byTag) {
                $redis->hdel($this->urlsKey(), ...$chunk);

                foreach ($byTag as $tag => $keys) {
                    $redis->srem($this->tagKey($tag), ...$keys);
                }
            });
        }
    }

    public function all(): array
    {
        $records = [];

        foreach ($this->redis->hgetall($this->urlsKey()) ?: [] as $key => $value) {
            if ($record = $this->decode($value)) {
                $records[$key] = $record;
            }
        }

        return $records;
    }

    public function matching(array $tags): array
    {
        [$exact, $prefixes] = TagMatcher::split($tags);

        if (! empty($prefixes)) {
            foreach ($this->redis->smembers($this->tagsKey()) ?: [] as $known) {
                foreach ($prefixes as $prefix) {
                    if (str_starts_with($known, $prefix)) {
                        $exact[] = $known;
                        break;
                    }
                }
            }
        }

        $keys = [];
        foreach ($this->membersOf(array_unique($exact)) as $members) {
            foreach ($members as $key) {
                $keys[$key] = true;
            }
        }

        $records = $this->many(array_keys($keys));

        return array_filter($records, fn ($record) => TagMatcher::matches($tags, $record['tags']));
    }

    public function flush(): void
    {
        $tags = $this->redis->smembers($this->tagsKey()) ?: [];

        foreach (array_chunk($tags, self::CHUNK) as $chunk) {
            $this->batch(function ($redis) use ($chunk) {
                foreach ($chunk as $tag) {
                    $redis->del($this->tagKey($tag));
                }
            });
        }

        $this->redis->del($this->urlsKey());
        $this->redis->del($this->tagsKey());
    }

    /**
     * Get the URL keys in each tag's set, pipelining the lookups so a long
     * list of tags doesn't cost one round trip per tag.
     *
     * @return array<int, array> one list of URL keys per tag
     */
    private function membersOf(array $tags): array
    {
        $results = [];

        foreach (array_chunk(array_values($tags), self::CHUNK) as $chunk) {
            if ($this->isCluster()) {
                foreach ($chunk as $tag) {
                    $results[] = $this->redis->smembers($this->tagKey($tag)) ?: [];
                }

                continue;
            }

            $replies = $this->redis->pipeline(function ($redis) use ($chunk) {
                foreach ($chunk as $tag) {
                    $redis->smembers($this->tagKey($tag));
                }
            });

            foreach ($replies as $members) {
                $results[] = is_array($members) ? $members : [];
            }
        }

        return $results;
    }

    private function many(array $keys): array
    {
        $records = [];

        foreach (array_chunk(array_values($keys), self::CHUNK) as $chunk) {
            $values = $this->redis->hmget($this->urlsKey(), $chunk);

            foreach (array_values($values) as $i => $value) {
                if ($record = $this->decode($value)) {
                    $records[$chunk[$i]] = $record;
                }
            }
        }

        return $records;
    }

    /**
     * Pipeline the commands where the connection supports it. Cluster
     * connections don't pipeline across slots, so send them one by one.
     */
    private function batch(callable $callback): void
    {
        if ($this->isCluster()) {
            $callback($this->redis);

            return;
        }

        $this->redis->pipeline($callback);
    }

    /**
     * Apply a record change and its tag set changes as one MULTI/EXEC, so two
     * writes to the same URL can't interleave and leave the record listing a
     * tag whose set no longer contains the URL. Cluster connections can't run
     * a transaction across slots, so there the commands are sent one by one.
     */
    private function atomically(callable $callback): void
    {
        if ($this->isCluster()) {
            $callback($this->redis);

            return;
        }

        $this->redis->transaction($callback);
    }

    private function isCluster(): bool
    {
        return $this->redis instanceof PhpRedisClusterConnection || $this->redis instanceof PredisClusterConnection;
    }

    private function encode(string $url, array $tags): string
    {
        return json_encode(['url' => $url, 'tags' => array_values($tags)], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function decode(mixed $value): ?array
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        $record = json_decode($value, true);

        return is_array($record) ? $record : null;
    }

    private function urlsKey(): string
    {
        return $this->prefix.'urls';
    }

    private function tagsKey(): string
    {
        return $this->prefix.'tags';
    }

    private function tagKey(string $tag): string
    {
        return $this->prefix.'tag:'.$tag;
    }
}
