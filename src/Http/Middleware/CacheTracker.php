<?php

namespace Thoughtco\StatamicCacheTracker\Http\Middleware;

use Closure;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Statamic\Contracts\Assets\Asset;
use Statamic\Contracts\Entries\Entry;
use Statamic\Contracts\Globals\Variables;
use Statamic\Facades\URL;
use Statamic\Forms;
use Statamic\StaticCaching\Cacher;
use Statamic\Structures\Page;
use Statamic\Support\Str;
use Statamic\Tags;
use Statamic\Taxonomies\LocalizedTerm;
use Thoughtco\StatamicCacheTracker\Events\TrackContentTags;
use Thoughtco\StatamicCacheTracker\Facades\Tracker;

class CacheTracker
{
    private array $content = [];

    public function __construct(Cacher $cacher)
    {
        $this->cacher = $cacher;
    }

    public function addContentTag($tag)
    {
        $tags = Arr::wrap($tag);

        foreach ($tags as $tag) {
            $this->content[] = $tag;
        }

        return $this;
    }

    public function handle($request, Closure $next)
    {
        if (! $this->isEnabled($request)) {
            return $next($request);
        }

        $url = $this->url();

        if (Tracker::has($url)) {
            return $next($request);
        }

        $this
            ->setupTagHooks()
            ->setupAugmentationHooks($url)
            ->setupAdditionalTracking();

        Event::listen(function (TrackContentTags $event) {
            $this->content = array_merge($this->content, $event->tags ?? []);
        });

        $response = $next($request);

        if ($this->cacher->hasCachedPage($request) && $this->content) {
            Tracker::add($url, array_unique($this->content));
        }

        return $response;
    }

    private function isEnabled($request)
    {
        if (! config('statamic.static_caching.strategy')) {
            return false;
        }

        if (! config('statamic-cache-tracker.enabled', true)) {
            return false;
        }

        // Only GET requests. This disables the cache during live preview.
        return $request->method() === 'GET' && ! Str::startsWith($request->path(), config('statamic.routes.action', '!').'/');
    }

    private function setupAdditionalTracking()
    {
        $pipelines = Tracker::getAdditionalTrackers();

        if (empty($pipelines)) {
            return $this;
        }

        (new Pipeline)
            ->send($this)
            ->through($pipelines)
            ->thenReturn();

        return $this;
    }

    private function setupAugmentationHooks(string $url)
    {
        $self = $this;

        app(Asset::class)::hook('augmented', function ($augmented, $next) use ($self) {
            $self->addContentTag('asset:'.$this->id());

            return $next($augmented);
        });

        app(Entry::class)::hook('augmented', function ($augmented, $next) use ($self, $url) {
            if ($this->absoluteUrl() != $url) {
                $self->addContentTag($this->collection()->handle().':'.$this->id());
            }

            return $next($augmented);
        });

        Page::hook('augmented', function ($augmented, $next) use ($self, $url) {
            $entry = $this->entry();

            if ($entry && $entry->absoluteUrl() != $url) {
                $self->addContentTag($entry->collection()->handle().':'.$entry->id());
            }

            return $next($augmented);
        });

        LocalizedTerm::hook('augmented', function ($augmented, $next) use ($self, $url) {
            if ($this->absoluteUrl() != $url) {
                $self->addContentTag('term:'.$this->id());
            }

            return $next($augmented);
        });

        app(Variables::class)::hook('augmented', function ($augmented, $next) use ($self) {
            $self->addContentTag('global:'.$this->globalSet()->handle());

            return $next($augmented);
        });

        return $this;
    }

    private function setupTagHooks()
    {
        $self = $this;

        Forms\Tags::hook('init', function ($value, $next) use ($self) {
            if (in_array($this->method, ['errors', 'success', 'submission'])) {
                return $next($value);
            }

            if ($form = $this->params->get('in')) {
                $form = is_string($form) ? $form : $form->handle();
                $self->addContentTag('form:'.$form);

                return $next($value);
            }

            $self->addContentTag($this->tag);

            return $next($value);
        });

        Tags\Nav::hook('init', function ($value, $next) use ($self) {
            $handle = $this->params->get('handle') ? 'nav:'.$this->params->get('handle') : $this->tag;
            $self->addContentTag($handle);

            return $next($value);
        });

        Tags\Partial::hook('init', function ($value, $next) use ($self) {
            $handle = $this->params->get('src') ?? str_replace('partial:', '', $this->tag);
            $self->addContentTag('partial:'.$handle);

            return $next($value);
        });

        return $this;
    }

    private function url()
    {
        return URL::makeAbsolute(class_exists(Livewire::class) ? Livewire::originalUrl() : URL::getCurrent());
    }
}
