<?php

namespace Thoughtco\StatamicCacheTracker\Tests\Unit;

use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Events\UrlInvalidated;
use Thoughtco\StatamicCacheTracker\Events\ContentTracked;
use Thoughtco\StatamicCacheTracker\Facades\Tracker;
use Thoughtco\StatamicCacheTracker\Tests\TestCase;

class TrackerTest extends TestCase
{
    #[Test]
    public function it_tracks_uncached_pages()
    {
        Event::fake();

        Tracker::addAdditionalTracker(function ($tracker, $next) {
            $tracker->addContentTag('test::tag');
        });

        $this->get('/');

        $this->assertSame(['test::tag', 'pages:home'], collect(Tracker::all())->first()['tags']);

        Event::assertDispatched(ContentTracked::class, 1);
    }

    #[Test]
    public function it_doesnt_track_already_cached_pages()
    {
        Event::fake();

        Tracker::addAdditionalTracker(function ($tracker, $next) {
            $tracker->addContentTag('test::tag');
        });

        $this->get('/');

        $this->assertSame(['test::tag', 'pages:home'], collect(Tracker::all())->first()['tags']);

        $this->get('/');

        $this->assertSame(['test::tag', 'pages:home'], collect(Tracker::all())->first()['tags']);

        Event::assertDispatched(ContentTracked::class, 1);
    }

    #[Test]
    public function it_doesnt_track_pages_already_in_the_manifest()
    {
        Event::fake();

        Tracker::add('/', ['some:thing']);

        Tracker::addAdditionalTracker(function ($tracker, $next) {
            $tracker->addContentTag('test::tag');
        });

        $this->get('/');

        $this->assertSame(['some:thing'], collect(Tracker::all())->first()['tags']);
    }

    #[Test]
    public function it_doesnt_track_404_pages()
    {
        $this->get('/i-dont-exist');

        $this->assertCount(0, Tracker::all());
    }

    #[Test]
    public function it_flushes()
    {
        Event::fake();

        Tracker::addAdditionalTracker(function ($tracker, $next) {
            $tracker->addContentTag('test::tag');
        });

        $this->get('/');

        $this->assertSame(['test::tag', 'pages:home'], collect(Tracker::all())->first()['tags']);

        $this->assertCount(1, Tracker::all());

        Tracker::flush();

        $this->assertCount(0, Tracker::all());
        Event::assertDispatched(UrlInvalidated::class);
    }
}
