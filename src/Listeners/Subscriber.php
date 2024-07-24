<?php

namespace Thoughtco\StatamicCacheTracker\Listeners;

use Statamic\Events;
use Thoughtco\StatamicCacheTracker\Facades\Tracker;
use Thoughtco\StatamicCacheTracker\Jobs\InvalidateTags;

class Subscriber
{
    protected $events = [
        Events\AssetDeleted::class => 'invalidateAsset',
        Events\AssetSaved::class => 'invalidateAsset',

        Events\EntryDeleted::class => 'invalidateAndDeleteEntry',
        Events\EntrySaved::class => 'invalidateEntry',

        Events\FormDeleted::class => 'invalidateForm',
        Events\FormSaved::class => 'invalidateForm',

        Events\GlobalSetDeleted::class => 'invalidateGlobal',
        Events\GlobalVariablesSaved::class => 'invalidateGlobal',

        Events\NavDeleted::class => 'invalidateNav',
        Events\NavTreeSaved::class => 'invalidateNav',
        Events\CollectionTreeSaved::class => 'invalidateNav',

        Events\TermDeleted::class => 'invalidateAndDeleteTerm',
        Events\TermSaved::class => 'invalidateTerm',
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

    public function invalidateAndDeleteEntry($event)
    {
        $this->invalidateEntry($event);

        if ($url = $event->entry->absoluteUrl()) {
            Tracker::remove($url);
        }
    }

    public function invalidateForm($event)
    {
        $tags = [
            'form:'.$event->form->handle(),
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

    public function invalidateAndDeleteTerm($event)
    {
        $this->invalidateTerm($event);

        if ($url = $event->term->absoluteUrl()) {
            Tracker::remove($url);
        }
    }

    private function invalidateContent($tags)
    {
        InvalidateTags::dispatch($tags);
    }
}
