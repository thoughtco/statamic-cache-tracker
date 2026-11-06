<?php

namespace Thoughtco\StatamicCacheTracker\Tests;

use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Redis;
use Statamic\Facades;
use Statamic\Testing\AddonTestCase;
use Statamic\Testing\Concerns\PreventsSavingStacheItemsToDisk;
use Thoughtco\StatamicCacheTracker\ServiceProvider;

class TestCase extends AddonTestCase
{
    use PreventsSavingStacheItemsToDisk;

    protected string $addonServiceProvider = ServiceProvider::class;

    protected $shouldFakeVersion = true;

    protected function resolveApplicationConfiguration($app)
    {
        parent::resolveApplicationConfiguration($app);

        $app['config']->set('app.key', 'base64:'.base64_encode(Encrypter::generateKey($app['config']['app.cipher'])));

        // Assume the pro edition within tests
        $app['config']->set('statamic.editions.pro', true);

        // enable caching
        $app['config']->set('statamic.static_caching.strategy', 'half');

        // Run the whole suite against redis with CACHE_TRACKER_TEST_REDIS=1
        static::configureRedis($app);

        if (static::usesRedis()) {
            $app['config']->set('cache.stores.static_cache', [
                'driver' => 'redis',
                'connection' => 'cache_tracker_tests',
                'lock_connection' => 'cache_tracker_tests',
            ]);
        }

        // views for front end routing tests
        $app['config']->set('view.paths', [
            __DIR__.'/__fixtures__/resources/views',
        ]);
    }

    public static function usesRedis(): bool
    {
        return (bool) getenv('CACHE_TRACKER_TEST_REDIS');
    }

    public static function configureRedis($app): void
    {
        $app['config']->set('database.redis.cache_tracker_tests', [
            'host' => getenv('REDIS_HOST') ?: '127.0.0.1',
            'port' => getenv('REDIS_PORT') ?: 6379,
            'database' => 15,
        ]);
    }

    protected function flushRedis(): void
    {
        Redis::connection('cache_tracker_tests')->flushdb();
    }

    protected function setUp(): void
    {
        parent::setUp();

        if (static::usesRedis()) {
            $this->flushRedis();
        }

        Facades\Collection::make('pages')
            ->title('Pages')
            ->routes('{parent_uri}/{slug}')
            ->save();

        Facades\Entry::make()
            ->id('home')
            ->collection('pages')
            ->save();
    }
}
