<?php

namespace Thoughtco\StatamicCacheTracker\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Thoughtco\StatamicCacheTracker\Facades\Tracker;
use Thoughtco\StatamicCacheTracker\Tests\TestCase;

class AdditionalTrackerTest extends TestCase
{
    #[Test]
    public function tracks_additional_closures()
    {
        Tracker::addAdditionalTracker(function ($tracker, $next) {
            $tracker->addContentTag('test::tag');
        });

        $this->get('/');

        $this->assertSame(['test::tag', 'pages:home'], collect(Tracker::all())->firstWhere('url', 'http://localhost/')['tags']);
    }

    #[Test]
    public function tracks_additional_classes()
    {
        Tracker::addAdditionalTracker(AdditionalTrackerClass::class);

        $this->get('/');

        $this->assertSame(['additional::tag', 'pages:home'], collect(Tracker::all())->firstWhere('url', 'http://localhost/')['tags']);
    }
}

class AdditionalTrackerClass
{
    public function __invoke($tracker, $next)
    {
        $tracker->addContentTag('additional::tag');
    }
}
