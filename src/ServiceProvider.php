<?php

namespace Thoughtco\StatamicCacheTracker;

use Statamic\Providers\AddonServiceProvider;

class ServiceProvider extends AddonServiceProvider
{
    protected $actions = [
        Actions\ClearCache::class,
        Actions\ViewCacheTags::class,
    ];

    protected $middlewareGroups = [
        'web' => [
            Http\Middleware\CacheTracker::class,
        ],
    ];

    protected $subscribe = [
        Listeners\Subscriber::class,
    ];

    protected $routes = [
        'cp' => __DIR__.'/../routes/cp.php',
    ];

    protected $vite = [
        'input' => ['resources/js/cp.js'],
        'publicDirectory' => 'dist',
        'hotFile' => __DIR__.'/../dist/hot',
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
