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

        $this->assertSame(['test::tag', 'pages:home', 'collection:pages'], collect(Tracker::all())->first()['tags']);

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

        $this->assertSame(['test::tag', 'pages:home', 'collection:pages'], collect(Tracker::all())->first()['tags']);

        $this->get('/');

        $this->assertSame(['test::tag', 'pages:home', 'collection:pages'], collect(Tracker::all())->first()['tags']);

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

        $this->assertSame(['test::tag', 'pages:home', 'collection:pages'], collect(Tracker::all())->first()['tags']);

        $this->assertCount(1, Tracker::all());

        Tracker::flush();

        $this->assertCount(0, Tracker::all());
        Event::assertDispatched(UrlInvalidated::class);
    }

    #[Test]
    public function it_invalidates_by_exact_tag_match()
    {
        Tracker::add('/page1', ['products:1', 'category:electronics']);
        Tracker::add('/page2', ['products:2', 'category:books']);
        Tracker::add('/page3', ['products:3', 'category:electronics']);

        $this->assertCount(3, Tracker::all());

        Tracker::invalidate(['products:1']);

        $this->assertCount(2, Tracker::all());
        $this->assertNull(Tracker::get('/page1'));
        $this->assertNotNull(Tracker::get('/page2'));
        $this->assertNotNull(Tracker::get('/page3'));
    }

    #[Test]
    public function it_invalidates_by_wildcard_tag()
    {
        Tracker::add('/page1', ['products:1', 'category:electronics']);
        Tracker::add('/page2', ['products:2', 'category:books']);
        Tracker::add('/page3', ['products:3', 'category:electronics']);

        $this->assertCount(3, Tracker::all());

        Tracker::invalidate(['products:*']);

        $this->assertCount(0, Tracker::all());
    }

    #[Test]
    public function it_invalidates_by_wildcard_tag_with_prefix()
    {
        Tracker::add('/page1', ['products:1', 'category:electronics']);
        Tracker::add('/page2', ['products:2', 'category:books']);
        Tracker::add('/page3', ['articles:1', 'category:electronics']);

        $this->assertCount(3, Tracker::all());

        Tracker::invalidate(['products:*']);

        $this->assertCount(1, Tracker::all());
        $this->assertNull(Tracker::get('/page1'));
        $this->assertNull(Tracker::get('/page2'));
        $this->assertNotNull(Tracker::get('/page3'));
    }

    #[Test]
    public function it_invalidates_by_multiple_wildcard_tags()
    {
        Tracker::add('/page1', ['products:1', 'category:electronics']);
        Tracker::add('/page2', ['products:2', 'category:books']);
        Tracker::add('/page3', ['articles:1', 'author:john']);
        Tracker::add('/page4', ['videos:1', 'author:jane']);

        $this->assertCount(4, Tracker::all());

        Tracker::invalidate(['products:*', 'author:*']);

        $this->assertCount(0, Tracker::all());
    }

    #[Test]
    public function it_invalidates_by_mixed_exact_and_wildcard_tags()
    {
        Tracker::add('/page1', ['products:1', 'category:electronics']);
        Tracker::add('/page2', ['products:2', 'category:books']);
        Tracker::add('/page3', ['articles:1', 'featured']);

        $this->assertCount(3, Tracker::all());

        Tracker::invalidate(['products:*', 'featured']);

        $this->assertCount(0, Tracker::all());
    }

    #[Test]
    public function it_doesnt_invalidate_when_wildcard_doesnt_match()
    {
        Tracker::add('/page1', ['products:1', 'category:electronics']);
        Tracker::add('/page2', ['articles:1', 'category:books']);

        $this->assertCount(2, Tracker::all());

        Tracker::invalidate(['videos:*']);

        $this->assertCount(2, Tracker::all());
    }
}
