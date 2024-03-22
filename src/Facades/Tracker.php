<?php

namespace Thoughtco\StatamicCacheTracker\Facades;

use Illuminate\Support\Facades\Facade;
use Thoughtco\StatamicCacheTracker\Tracker\Manager;

class Tracker extends Facade
{
    /**
     * {@inheritdoc}
     */
    protected static function getFacadeAccessor()
    {
        return Manager::class;
    }
}
