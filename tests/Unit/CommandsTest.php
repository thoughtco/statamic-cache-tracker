<?php

namespace Thoughtco\StatamicCacheTracker\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Thoughtco\StatamicCacheTracker\Facades\Tracker;
use Thoughtco\StatamicCacheTracker\Tests\TestCase;

class CommandsTest extends TestCase
{
    #[Test]
    public function flush_command_clears_all_tracked_urls()
    {
        Tracker::add('/page1', ['products:1']);
        Tracker::add('/page2', ['products:2']);

        $this->assertCount(2, Tracker::all());

        $this->artisan('cache-tracker:flush')
            ->expectsOutput('Flushed 2 tracked URL(s).')
            ->assertExitCode(0);

        $this->assertCount(0, Tracker::all());
    }

    #[Test]
    public function flush_command_reports_zero_when_nothing_tracked()
    {
        $this->artisan('cache-tracker:flush')
            ->expectsOutput('Flushed 0 tracked URL(s).')
            ->assertExitCode(0);
    }

    #[Test]
    public function invalidate_command_removes_url()
    {
        Tracker::add('/page1', ['products:1']);
        Tracker::add('/page2', ['products:2']);

        $this->artisan('cache-tracker:invalidate', ['url' => '/page1'])
            ->expectsOutput('Invalidated: /page1')
            ->assertExitCode(0);

        $this->assertCount(1, Tracker::all());
        $this->assertNull(Tracker::get('/page1'));
        $this->assertNotNull(Tracker::get('/page2'));
    }

    #[Test]
    public function invalidate_command_warns_when_url_not_found()
    {
        $this->artisan('cache-tracker:invalidate', ['url' => '/not-tracked'])
            ->expectsOutput('URL not found in tracker: /not-tracked')
            ->assertExitCode(1);
    }

    #[Test]
    public function list_command_shows_all_tracked_urls()
    {
        Tracker::add('/page1', ['products:1', 'category:electronics']);
        Tracker::add('/page2', ['products:2']);

        $this->artisan('cache-tracker:list')
            ->expectsTable(['URL', 'Tags'], [
                ['/page1', 'products:1, category:electronics'],
                ['/page2', 'products:2'],
            ])
            ->expectsOutput('Total: 2 URL(s)')
            ->assertExitCode(0);
    }

    #[Test]
    public function list_command_shows_message_when_nothing_tracked()
    {
        $this->artisan('cache-tracker:list')
            ->expectsOutput('No tracked URLs found.')
            ->assertExitCode(0);
    }

    #[Test]
    public function list_command_filters_by_url_prefix()
    {
        Tracker::add('/blog/post-1', ['entries:1']);
        Tracker::add('/blog/post-2', ['entries:2']);
        Tracker::add('/about', ['entries:3']);

        $this->artisan('cache-tracker:list', ['--url' => '/blog/*'])
            ->expectsTable(['URL', 'Tags'], [
                ['/blog/post-1', 'entries:1'],
                ['/blog/post-2', 'entries:2'],
            ])
            ->expectsOutput('Total: 2 URL(s)')
            ->assertExitCode(0);
    }

    #[Test]
    public function list_command_filter_returns_no_results_message()
    {
        Tracker::add('/page1', ['products:1']);

        $this->artisan('cache-tracker:list', ['--url' => '/blog/*'])
            ->expectsOutput('No tracked URLs found.')
            ->assertExitCode(0);
    }
}
