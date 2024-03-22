<?php

namespace Thoughtco\StatamicCacheTracker\Listeners;

use Statamic\Events;
use Thoughtco\StatamicCacheTracker\Facades\Tracker;

class Subscriber
{
    protected $events = [
        Events\AssetDeleted::class => 'invalidateAsset',
        Events\AssetSaved::class => 'invalidateAsset',

        Events\EntryDeleted::class => 'invalidateEntry',
        Events\EntrySaved::class => 'invalidateEntry',

        Events\GlobalSetDeleted::class => 'invalidateGlobal',
        Events\GlobalVariablesSaved::class => 'invalidateGlobal',

        Events\NavDeleted::class => 'invalidateNav',
        Events\NavTreeSaved::class => 'invalidateNav',
        Events\CollectionTreeSaved::class => 'invalidateNav',
    ];

    public function subscribe($dispatcher): void
    {
        foreach ($this->events as $event => $method) {
            if (class_exists($event)) {
                $dispatcher->listen($event, [self::class, $method]);
            }
        }
    }

    public function invalidateAsset($event)
    {
        $tags = [
            'asset:'.$event->asset->id(),
        ];

        $this->invalidateContent($tags);
    }

    public function invalidateEntry($event)
    {
        $entry = $event->entry;

        $collectionHandle = strtolower($entry->collection()->handle());

        $tags = [
            $collectionHandle.':'.$entry->id(),
        ];

        $this->invalidateContent($tags);
    }

    public function invalidateGlobal($event)
    {
        $tags = [
            'global:'.($event->globals ?? $event->variables->globalSet())->handle(),
        ];

        $this->invalidateContent($tags);
    }

    public function invalidateNav($event)
    {
        $tags = [
            'nav:'.($event->nav ?? $event->tree)->handle(),
        ];

        $this->invalidateContent($tags);
    }

    public function invalidateTerm($event)
    {
        $tags = [
            'term:'.$event->term->id(),
        ];

        $this->invalidateContent($tags);
    }

    private function invalidateContent($tags)
    {
        Tracker::invalidate($tags);
    }
}
