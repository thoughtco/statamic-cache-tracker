<?php

namespace Thoughtco\StatamicCacheTracker\Tests\Unit;

use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Facades\Antlers;
use Thoughtco\StatamicCacheTracker\Events\TrackContentTags;
use Thoughtco\StatamicCacheTracker\Tests\TestCase;

class CacheTrackerTagTest extends TestCase
{
    #[Test]
    public function it_tracks_a_tag_via_the_tag_parameter()
    {
        Event::fake([TrackContentTags::class]);

        Antlers::parse('{{ cache_tracker tag="custom:dependency" }}', [], true);

        $this->assertTracked(['custom:dependency']);
    }

    #[Test]
    public function it_tracks_a_tag_via_the_wildcard_method()
    {
        Event::fake([TrackContentTags::class]);

        Antlers::parse('{{ cache_tracker:custom:dependency }}', [], true);

        $this->assertTracked(['custom:dependency']);
    }

    #[Test]
    public function it_does_not_track_when_no_tag_is_given()
    {
        Event::fake([TrackContentTags::class]);

        Antlers::parse('{{ cache_tracker }}', [], true);

        Event::assertNotDispatched(TrackContentTags::class);
    }

    private function assertTracked(array $tags): void
    {
        Event::assertDispatched(TrackContentTags::class, fn (TrackContentTags $event) => $event->tags === $tags);
    }
}
