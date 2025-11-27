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

## Documentation

Documentation for this addon is available at [https://www.docs.tc/cache-tracker](https://www.docs.tc/cache-tracker).
