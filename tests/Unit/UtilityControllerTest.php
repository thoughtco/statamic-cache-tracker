<?php

namespace Thoughtco\StatamicCacheTracker\Tests\Unit;

use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Thoughtco\StatamicCacheTracker\Facades\Tracker;
use Thoughtco\StatamicCacheTracker\Http\Controllers\UtilityController;
use Thoughtco\StatamicCacheTracker\Tests\TestCase;

class UtilityControllerTest extends TestCase
{
    private function callUtility(string $urls): array
    {
        $this->app->instance('request', Request::create('/clear', 'POST', ['urls' => $urls]));

        return (new UtilityController())();
    }

    #[Test]
    public function it_returns_message_when_no_urls_provided()
    {
        $this->app->instance('request', Request::create('/clear', 'POST', []));

        $result = (new UtilityController())();

        $this->assertSame('No URLs provided', $result['message']);
    }

    #[Test]
    public function it_flushes_all_when_star_is_provided()
    {
        Tracker::add('http://localhost/page1', ['products:1']);
        Tracker::add('http://localhost/page2', ['products:2']);

        $this->assertCount(2, Tracker::all());

        $result = $this->callUtility('*');

        $this->assertSame('Cache flushed', $result['message']);
        $this->assertCount(0, Tracker::all());
    }

    #[Test]
    public function it_removes_an_exact_url()
    {
        Tracker::add('http://localhost/page1', ['products:1']);
        Tracker::add('http://localhost/page2', ['products:2']);

        $this->callUtility('http://localhost/page1');

        $this->assertCount(1, Tracker::all());
        $this->assertNull(Tracker::get('http://localhost/page1'));
        $this->assertNotNull(Tracker::get('http://localhost/page2'));
    }

    #[Test]
    public function it_removes_urls_matching_path_wildcard()
    {
        Tracker::add('http://localhost/category/foo', ['products:1']);
        Tracker::add('http://localhost/category/bar', ['products:2']);
        Tracker::add('http://localhost/blog/post', ['products:3']);

        $this->callUtility('category/*');

        $this->assertCount(1, Tracker::all());
        $this->assertNull(Tracker::get('http://localhost/category/foo'));
        $this->assertNull(Tracker::get('http://localhost/category/bar'));
        $this->assertNotNull(Tracker::get('http://localhost/blog/post'));
    }

    #[Test]
    public function it_removes_urls_matching_path_wildcard_anywhere_in_path()
    {
        Tracker::add('http://localhost/category/foo', ['products:1']);
        Tracker::add('http://localhost/s/category/xxx', ['products:2']);
        Tracker::add('http://localhost/blog/post', ['products:3']);

        $this->callUtility('category/*');

        $this->assertCount(1, Tracker::all());
        $this->assertNull(Tracker::get('http://localhost/category/foo'));
        $this->assertNull(Tracker::get('http://localhost/s/category/xxx'));
        $this->assertNotNull(Tracker::get('http://localhost/blog/post'));
    }

    #[Test]
    public function it_removes_urls_matching_absolute_url_wildcard()
    {
        Tracker::add('http://localhost/category/foo', ['products:1']);
        Tracker::add('http://localhost/category/bar', ['products:2']);
        Tracker::add('http://localhost/blog/post', ['products:3']);

        $this->callUtility('http://localhost/category/*');

        $this->assertCount(1, Tracker::all());
        $this->assertNull(Tracker::get('http://localhost/category/foo'));
        $this->assertNull(Tracker::get('http://localhost/category/bar'));
        $this->assertNotNull(Tracker::get('http://localhost/blog/post'));
    }

    #[Test]
    public function it_does_not_remove_other_site_urls_with_absolute_url_wildcard()
    {
        Tracker::add('http://site-one.com/category/foo', ['products:1']);
        Tracker::add('http://site-two.com/category/bar', ['products:2']);

        $this->callUtility('http://site-one.com/category/*');

        $this->assertNull(Tracker::get('http://site-one.com/category/foo'));
        $this->assertNotNull(Tracker::get('http://site-two.com/category/bar'));
    }
}
