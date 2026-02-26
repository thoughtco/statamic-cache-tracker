<?php

namespace Thoughtco\StatamicCacheTracker\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Statamic\Facades;
use Thoughtco\StatamicCacheTracker\Facades\Tracker;
use Thoughtco\StatamicCacheTracker\Tests\TestCase;

class NavTagTest extends TestCase
{
    #[Test]
    public function it_tracks_nav_tag_with_explicit_handle()
    {
        // Create a navigation structure
        Facades\Nav::make('footer')
            ->title('Footer')
            ->expectsRoot(true)
            ->collections(['pages'])
            ->save();

        $view = <<<'BLADE'
{{ nav handle="footer" }}
    {{ title }}
{{ /nav }}
BLADE;

        file_put_contents($this->viewPath('nav-test.antlers.html'), $view);

        Facades\Entry::make()
            ->id('nav-test-page')
            ->slug('nav-test')
            ->collection('pages')
            ->data(['template' => 'nav-test'])
            ->save();

        $this->get('/nav-test');

        $tags = collect(Tracker::all())->first()['tags'] ?? [];

        $this->assertContains('nav:footer', $tags);
    }

    #[Test]
    public function it_tracks_nav_tag_without_handle_using_default()
    {
        $view = <<<'BLADE'
{{ nav }}
    {{ title }}
{{ /nav }}
BLADE;

        file_put_contents($this->viewPath('nav-default.antlers.html'), $view);

        Facades\Entry::make()
            ->id('nav-default-page')
            ->slug('nav-default')
            ->collection('pages')
            ->data(['template' => 'nav-default'])
            ->save();

        $this->get('/nav-default');

        $tags = collect(Tracker::all())->first()['tags'] ?? [];

        $this->assertContains('nav:collection::pages', $tags);
    }

    protected function viewPath($name)
    {
        return __DIR__.'/../__fixtures__/resources/views/'.$name;
    }
}
