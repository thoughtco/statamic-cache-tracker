<?php

namespace Thoughtco\StatamicCacheTracker\Http\Middleware;

use Closure;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Arr;
use Livewire\Livewire;
use Statamic\Contracts\Assets\Asset;
use Statamic\Contracts\Entries\Entry;
use Statamic\Contracts\Globals\Variables;
use Statamic\Facades\URL;
use Statamic\Support\Str;
use Statamic\Tags;
use Statamic\Taxonomies\LocalizedTerm;
use Thoughtco\StatamicCacheTracker\Facades\Tracker;

class CacheTracker
{
    private array $content = [];

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

        $this
            ->setupNavHooks()
            ->setupAugmentationHooks($url)
            ->setupAdditionalTracking();

        $response = $next($request);

        if ($this->content) {
            Tracker::add($url, $this->content);
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
            if (URL::makeAbsolute(url($this->url())) != $url) {
                $self->addContentTag($this->collection()->handle().':'.$this->id());
            }

            return $next($augmented);
        });

        LocalizedTerm::hook('augmented', function ($augmented, $next) use ($self) {
            $self->addContentTag('term:'.$this->id());

            return $next($augmented);
        });

        app(Variables::class)::hook('augmented', function ($augmented, $next) use ($self) {
            $self->addContentTag('global:'.$this->globalSet()->handle());

            return $next($augmented);
        });

        return $this;
    }

    private function setupNavHooks()
    {
        $self = $this;

        Tags\Nav::hook('init', function ($value, $next) use ($self) {
            $handle = $this->params->get('handle') ? 'nav:'.$this->params->get('handle') : $this->tag;
            $self->addContentTag($handle);

            return $next($value);
        });

        return $this;
    }

    private function url()
    {
        return URL::makeAbsolute(class_exists(Livewire::class) ? Livewire::originalUrl() : URL::getCurrent());
    }
}
