<?php

namespace Thoughtco\StatamicCacheTracker;

use Statamic\Providers\AddonServiceProvider;

class ServiceProvider extends AddonServiceProvider
{
    protected $middlewareGroups = [
        'web' => [
            Http\Middleware\CacheTracker::class,
        ],
    ];

    protected $subscribe = [
        Listeners\Subscriber::class,
    ];

    public function boot()
    {
        parent::boot();

        $this->mergeConfigFrom($config = __DIR__.'/../config/statamic-cache-tracker.php', 'statamic-cache-tracker');

        $this->publishes([
            $config => config_path('statamic-cache-tracker.php'),
        ], 'statamic-cache-tracker-config');
    }
}
