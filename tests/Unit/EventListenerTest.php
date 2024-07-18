<?php

namespace Thoughtco\StatamicCacheTracker\Tests\Unit;

use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Thoughtco\StatamicCacheTracker\Events\TrackContentTags;
use Thoughtco\StatamicCacheTracker\Facades\Tracker;
use Thoughtco\StatamicCacheTracker\Http\Middleware\CacheTracker;
use Thoughtco\StatamicCacheTracker\Tests\TestCase;

class EventListenerTest extends TestCase
{
    #[Test]
    public function it_tracks_tags_from_events()
    {
        $request = Request::create('/');

        $next = function () {
            TrackContentTags::dispatch(['test::tag']);

            return response('');
        };

        $middleware = new CacheTracker();
        $response = $middleware->handle($request, $next);

        $this->assertSame(['test::tag'], collect(Tracker::all())->firstWhere('url', 'http://localhost/')['tags']);
    }
}
