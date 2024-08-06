<?php

namespace Thoughtco\StatamicCacheTracker\Tests\Unit;

use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
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

        $this->assertSame(['test::tag'], collect(Tracker::all())->first()['tags']);

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

        $this->assertSame(['test::tag'], collect(Tracker::all())->first()['tags']);

        $this->get('/');

        $this->assertSame(['test::tag'], collect(Tracker::all())->first()['tags']);

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
}
