<?php

namespace Thoughtco\StatamicCacheTracker\Tests;

use Illuminate\Encryption\Encrypter;
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
    }
}
