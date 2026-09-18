<?php

namespace Thoughtco\StatamicCacheTracker\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Statamic\Entries\Entry;
use Statamic\Facades;
use Thoughtco\StatamicCacheTracker\Facades\Tracker;
use Thoughtco\StatamicCacheTracker\Tests\TestCase;

class CustomEntryClassTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Facades\Collection::make('articles')
            ->title('Articles')
            ->routes('/articles/{slug}')
            ->entryClass(CustomEntry::class)
            ->save();
    }

    #[Test]
    public function it_tracks_entries_of_a_collection_with_a_custom_entry_class()
    {
        $entry = Facades\Entry::make()
            ->id('article-1')
            ->collection('articles')
            ->slug('one')
            ->data(['title' => 'One']);

        $entry->save();

        $this->assertInstanceOf(CustomEntry::class, $entry->fresh());

        $this->get($entry->url())->assertOk();

        $tags = collect(Tracker::all())->firstWhere('url', $entry->absoluteUrl())['tags'];

        $this->assertContains('articles:article-1', $tags);
        $this->assertContains('collection:articles', $tags);
    }

    #[Test]
    public function it_invalidates_a_tracked_url_when_a_custom_entry_class_entry_is_saved()
    {
        $entry = Facades\Entry::make()
            ->id('article-1')
            ->collection('articles')
            ->slug('one')
            ->data(['title' => 'One']);

        $entry->save();

        $this->get($entry->url())->assertOk();

        $this->assertNotNull(Tracker::get($entry->absoluteUrl()));

        $entry->fresh()->set('title', 'Two')->save();

        $this->assertNull(Tracker::get($entry->absoluteUrl()));
    }
}

class CustomEntry extends Entry {}
