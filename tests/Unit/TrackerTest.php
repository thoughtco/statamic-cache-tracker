<?php

namespace Thoughtco\StatamicCacheTracker\Tests\Unit;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Console\Commands\StaticWarmJob;
use Statamic\Events\UrlInvalidated;
use Statamic\Facades\StaticCache;
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
    public function it_removes_a_url()
    {
        Tracker::add('/page1', ['products:1']);
        Tracker::add('/page2', ['products:2']);

        $this->assertCount(2, Tracker::all());

        Tracker::remove('/page1');

        $this->assertCount(1, Tracker::all());
        $this->assertNull(Tracker::get('/page1'));
        $this->assertNotNull(Tracker::get('/page2'));
    }

    #[Test]
    public function it_removes_a_url_and_does_not_retrack_on_next_visit()
    {
        $this->get('/');

        $this->assertCount(1, Tracker::all());

        Tracker::remove(collect(Tracker::all())->first()['url']);

        $this->assertCount(0, Tracker::all());

        $this->get('/');

        $this->assertCount(1, Tracker::all());
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

    #[Test]
    public function it_invalidates_the_static_cache_immediately_by_default()
    {
        $this->get('/');

        Queue::fake();
        Event::fake([UrlInvalidated::class]);

        Tracker::invalidate(['collection:pages']);

        Queue::assertNotPushed(StaticWarmJob::class);
        Event::assertDispatched(UrlInvalidated::class);
        $this->assertCount(0, Tracker::all());
    }

    #[Test]
    public function it_queues_a_recache_job_instead_of_invalidating_when_background_recache_is_enabled()
    {
        config(['statamic.static_caching.background_recache' => true]);

        $this->get('/');

        Queue::fake();
        Event::fake([UrlInvalidated::class]);

        Tracker::invalidate(['collection:pages']);

        Queue::assertPushed(StaticWarmJob::class);
        Event::assertNotDispatched(UrlInvalidated::class);

        // The entry is retained so that if the recache job fails or is dropped
        // the URL stays tracked and future saves will retry it, instead of being
        // orphaned in the static cache with no tags.
        $this->assertCount(1, Tracker::all());
        $this->assertSame(['pages:home', 'collection:pages'], collect(Tracker::all())->first()['tags']);
    }

    #[Test]
    public function it_refreshes_tags_for_an_already_tracked_url_on_a_recache_request()
    {
        $this->get('/');

        $this->assertSame(['pages:home', 'collection:pages'], collect(Tracker::all())->first()['tags']);

        Tracker::addAdditionalTracker(function ($tracker, $next) {
            $tracker->addContentTag('fresh::tag');
        });

        $token = StaticCache::recacheTokenParameter().'='.StaticCache::recacheToken();

        $this->get('/?'.$token);

        $this->assertCount(1, Tracker::all());
        $this->assertSame(['fresh::tag', 'pages:home', 'collection:pages'], collect(Tracker::all())->first()['tags']);
    }

    #[Test]
    public function it_does_not_retrack_an_already_tracked_url_on_a_normal_request()
    {
        $this->get('/');

        Tracker::addAdditionalTracker(function ($tracker, $next) {
            $tracker->addContentTag('fresh::tag');
        });

        $this->get('/');

        $this->assertSame(['pages:home', 'collection:pages'], collect(Tracker::all())->first()['tags']);
    }
}
