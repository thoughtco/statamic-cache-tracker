<?php

namespace Thoughtco\StatamicCacheTracker;

use Statamic\Facades\Permission;
use Statamic\Facades\Utility;
use Statamic\Providers\AddonServiceProvider;
use Thoughtco\StatamicCacheTracker\Http\Controllers\UtilityController;

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

        $this->setupAddonPermissions()
            ->setupAddonUtility();
    }

    private function setupAddonPermissions()
    {
        Permission::group('cache-tracker', 'Cache Tracker', function () {
            Permission::register('view cache tracker tags')
                ->label(__('View Tags'))
                ->description(__('Enable the action on listing views to view tags'));

            Permission::register('clear cache tracker tags')
                ->label(__('Clear Tags'))
                ->description(__('Enable the action on listing views to clear tags'));
        });

        return $this;
    }

    private function setupAddonUtility()
    {
        Utility::extend(function () {
            Utility::register('static-cache-tracker')
                ->title(__('Cache Tracker'))
                ->description(__('Clear specific paths in your static cache.'))
                ->inertia('CacheTracker/ClearUtility')
                ->icon('taxonomies')
                ->routes(function ($router) {
                    $router->post('/clear', UtilityController::class)->name('clear');
                });
        });

        return $this;
    }
}
