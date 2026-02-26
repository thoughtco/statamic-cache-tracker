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
    public function it_tracks_nav_tag_with_shorthand_handle()
    {
        // Create a navigation structure
        Facades\Nav::make('footer')
            ->title('Footer')
            ->expectsRoot(true)
            ->collections(['pages'])
            ->save();

        $view = <<<'BLADE'
{{ nav:footer }}
    {{ title }}
{{ /nav:footer }}
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

    #[Test]
    public function it_tracks_nav_tag_when_handle_is_nav_instance()
    {
        // Create a navigation structure
        $nav = tap(Facades\Nav::make('sidebar')
            ->title('Sidebar')
            ->expectsRoot(true)
            ->collections(['pages']))
            ->save();

        // The template uses a blueprint field that returns a Nav instance
        $view = <<<'BLADE'
{{ nav :handle="my_nav" }}
    {{ title }}
{{ /nav }}
BLADE;

        file_put_contents($this->viewPath('nav-test.antlers.html'), $view);

        // Create entry with nav field that returns Nav instance
        Facades\Entry::make()
            ->id('nav-test-page')
            ->slug('nav-test')
            ->collection('pages')
            ->data([
                'template' => 'nav-test',
                'my_nav' => $nav,
            ])
            ->save();

        $this->get('/nav-test');

        $tags = collect(Tracker::all())->first()['tags'] ?? [];

        $this->assertContains('nav:sidebar', $tags);
    }

    protected function viewPath($name)
    {
        return __DIR__.'/../__fixtures__/resources/views/'.$name;
    }
}
