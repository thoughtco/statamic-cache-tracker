<?php

namespace Thoughtco\StatamicCacheTracker\Tests\Unit;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Thoughtco\StatamicCacheTracker\Facades\Tracker;
use Thoughtco\StatamicCacheTracker\Tests\TestCase;
use Thoughtco\StatamicCacheTracker\Tracker\Manager;
use Thoughtco\StatamicCacheTracker\Tracker\Stores\CacheStore;
use Thoughtco\StatamicCacheTracker\Tracker\Stores\RedisStore;
use Thoughtco\StatamicCacheTracker\Tracker\Stores\Store;

class StoreTest extends TestCase
{
    public static function drivers(): array
    {
        return [
            'cache' => ['cache'],
            'redis' => ['redis'],
        ];
    }

    private function useDriver(string $driver): Store
    {
        if ($driver === 'redis') {
            try {
                Redis::connection('cache_tracker_tests')->ping();
            } catch (\Throwable $e) {
                $this->markTestSkipped('Redis is not available: '.$e->getMessage());
            }

            $this->flushRedis();

            config(['cache.stores.static_cache' => [
                'driver' => 'redis',
                'connection' => 'cache_tracker_tests',
                'lock_connection' => 'cache_tracker_tests',
            ]]);
        } else {
            config(['cache.stores.static_cache' => ['driver' => 'array']]);
        }

        Cache::forgetDriver('static_cache');
        $this->app->forgetInstance(Manager::class);
        Tracker::clearResolvedInstance(Manager::class);

        return Tracker::store();
    }

    #[Test]
    #[DataProvider('drivers')]
    public function it_resolves_the_driver_from_the_cache_store(string $driver)
    {
        $store = $this->useDriver($driver);

        $this->assertInstanceOf($driver === 'redis' ? RedisStore::class : CacheStore::class, $store);
    }

    #[Test]
    #[DataProvider('drivers')]
    public function it_stores_and_forgets_urls(string $driver)
    {
        $store = $this->useDriver($driver);

        $store->put('a', '/a', ['one', 'two']);
        $store->put('b', '/b', ['two']);

        $this->assertSame(['url' => '/a', 'tags' => ['one', 'two']], $store->get('a'));
        $this->assertCount(2, $store->all());

        $store->forget(['a']);

        $this->assertNull($store->get('a'));
        $this->assertSame(['b'], array_keys($store->all()));
        $this->assertSame(['b'], array_keys($store->matching(['two'])));
        $this->assertSame([], $store->matching(['one']));
    }

    #[Test]
    #[DataProvider('drivers')]
    public function it_does_not_match_tags_a_url_no_longer_has(string $driver)
    {
        $store = $this->useDriver($driver);

        $store->put('a', '/a', ['old', 'kept']);
        $store->put('a', '/a', ['new', 'kept']);

        $this->assertSame([], $store->matching(['old']));
        $this->assertSame(['a'], array_keys($store->matching(['new'])));
        $this->assertSame(['a'], array_keys($store->matching(['kept'])));

        // re-tracking with the old tag again matches again
        $store->put('a', '/a', ['old']);
        $this->assertSame(['a'], array_keys($store->matching(['old'])));
    }

    #[Test]
    #[DataProvider('drivers')]
    public function it_matches_wildcards_on_any_prefix(string $driver)
    {
        $store = $this->useDriver($driver);

        $store->put('a', '/a', ['products:1']);
        $store->put('b', '/b', ['product-lines:2']);
        $store->put('c', '/c', ['articles:1']);

        $this->assertEqualsCanonicalizing(['a'], array_keys($store->matching(['products:*'])));
        $this->assertEqualsCanonicalizing(['a', 'b'], array_keys($store->matching(['product*'])));
        $this->assertEqualsCanonicalizing(['a', 'b', 'c'], array_keys($store->matching(['*'])));
        $this->assertEqualsCanonicalizing(['b', 'c'], array_keys($store->matching(['product-*', 'articles:1'])));
    }

    #[Test]
    #[DataProvider('drivers')]
    public function it_imports_without_overwriting_existing_records(string $driver)
    {
        $store = $this->useDriver($driver);

        $store->put('a', '/a', ['fresh']);

        $store->import([
            'a' => ['url' => '/a', 'tags' => ['stale']],
            'b' => ['url' => '/b', 'tags' => ['imported']],
        ]);

        $this->assertSame(['fresh'], $store->get('a')['tags']);
        $this->assertSame([], $store->matching(['stale']));
        $this->assertSame(['b'], array_keys($store->matching(['imported'])));
    }

    #[Test]
    #[DataProvider('drivers')]
    public function it_flushes_everything(string $driver)
    {
        $store = $this->useDriver($driver);

        $store->put('a', '/a', ['one']);
        $store->put('b', '/b', ['two']);

        $store->flush();

        $this->assertSame([], $store->all());
        $this->assertSame([], $store->matching(['one', 'two', '*']));

        if ($driver === 'redis') {
            $this->assertSame([], Redis::connection('cache_tracker_tests')->keys('*'));
        } else {
            $this->assertNull(Cache::store('static_cache')->get('tracker::tag:'.md5('one')));
        }
    }

    #[Test]
    #[DataProvider('drivers')]
    public function it_migrates_the_legacy_single_key_format(string $driver)
    {
        $this->useDriver($driver);

        Cache::store('static_cache')->forever(Manager::LEGACY_CACHE_KEY, [
            md5('http://localhost/a') => ['url' => 'http://localhost/a', 'tags' => ['products:1']],
            md5('http://localhost/b') => ['url' => 'http://localhost/b', 'tags' => ['products:2']],
        ]);

        // a fresh manager migrates on first use
        $this->app->forgetInstance(Manager::class);
        Tracker::clearResolvedInstance(Manager::class);

        $this->assertCount(2, Tracker::all());
        $this->assertNotNull(Tracker::get('http://localhost/a'));
        $this->assertFalse(Cache::store('static_cache')->has(Manager::LEGACY_CACHE_KEY));

        Tracker::invalidate(['products:1']);

        $this->assertNull(Tracker::get('http://localhost/a'));
        $this->assertNotNull(Tracker::get('http://localhost/b'));
    }
}
