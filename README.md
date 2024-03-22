# Statamic Cache Tracker

> Statamic Cache Tracker keeps a record of what items (entries, assets, terms etc) are used in the output of each page, and clears the cache (full or half) for those pages when an item is saved.


## How to Install

Run the following command from your project root:

``` bash
composer require thoughtco/statamic-cache-tracker
```

You can also optionally publish the config:

```bash
php artisan vendor:publish --tag=statamic-cache-tracker-config
```

## How it works

The addon should work auto-magically in most cases. It listens for hooks in nav tags, as well as augmentation of entries, terms, assets and globals to determine what content is being output. 

This data is then added to a cache store that is used to determine what cached data should be invalidated at what times.

The default cache is used, or if you have specified a `static_cache` store this will be used instead. This data will then be cleared when your static cache is cleared.

### Middleware
The autocache middleware will automatically be added to your `web` middleware stack. If you want to include it to other stacks simply add:

`\Thoughtco\StatamicCacheTracker\Http\Middleware\CacheTracker::class`

### Tracker Facade
The addon comes with a Facade for interacting with the Tracker:
`\Thoughtco\StatamicCacheTracker\Facades\Tracker`


### Adding tracking data
If you have your own custom tracking data, for example for one of your own tags, you can register then on the Facade. Please bear in mind tracking only happens while the response is generated, so where possible use augmentation hooks.

```php
Tracker::addAdditionalTracker(function ($tracker, $next) {
    // run your logic, for example in an augmentation hook
    // then call:
    $tracker->addContentTag('your-tag-here');

    return $next($tracker);
});
```

### Invalidating your tracked data
To invalidate your tracked data, use a listener or observer, and call:         

```
Tracker::invalidate($tags);
```

