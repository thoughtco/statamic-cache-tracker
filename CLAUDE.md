# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

```bash
# Run all tests
vendor/bin/pest

# Run a single test file
vendor/bin/pest tests/Unit/TrackerTest.php

# Run a specific test by name
vendor/bin/pest --filter "test name"

# Lint (Laravel Pint)
vendor/bin/pint

# Build CP assets
npm run build

# Dev CP assets
npm run dev
```

## Architecture

This is a Statamic addon (`thoughtco/statamic-cache-tracker`) that tracks which content items are rendered on each page, then automatically clears those pages from the static cache when content is updated.

### Core Flow

1. **Tracking** — `Http/Middleware/CacheTracker` runs on every GET request. It hooks into Statamic's augmentation lifecycle (via `Asset::hook`, `Entry::hook`, `LocalizedTerm::hook`, etc.) to collect "tags" for each content item rendered during the request. After a successful response, it stores a `URL → [tags]` mapping in cache.

2. **Invalidation** — `Listeners/Subscriber` subscribes to Statamic save/delete events (entries, assets, terms, navs, forms, globals). When content changes, it dispatches `Jobs/InvalidateTags` which calls `Tracker::invalidate($tags)` to find all URLs containing those tags and clear them from the static cache.

3. **Tag format** — Tags follow patterns like `asset:{id}`, `collection:{handle}:{id}`, `term:{id}`, `form:{handle}`, `nav:{handle}`, `global:{handle}`. Wildcard matching is supported via `*` prefix notation (e.g., `collection:blog:*`).

### Key Classes

- **`Tracker/Manager`** — Core logic. Stores `URL → tags[]` in cache under key `tracker::urls`. Handles `add`, `get`, `invalidate`, and `flush`. Tag matching supports wildcards.
- **`Http/Middleware/CacheTracker`** — Hooks into Statamic augmentation to collect tags during rendering. Also fires `TrackContentTags` event for custom integrations.
- **`Listeners/Subscriber`** — Maps Statamic events to tag generation and queues invalidation.
- **`Facades/Tracker`** — Facade for `Manager` (bound as `cache-tracker`).

### Extending

Custom tag sources can be registered via `Tracker::addAdditionalTracker()`, which accepts a closure or class with an `__invoke(Request $request, array &$tags)` signature and runs in a pipeline.

The `TrackContentTags` event can be fired from custom code to inject additional tags into the current request's tracking.

### CP Features

- **Utility** — "Cache Tracker" utility in the CP for manually clearing URLs or wildcards.
- **Bulk Actions** — "View Cache Tags" and "Clear Cache" actions available on entry/term listings.
- **Permissions** — `view cache tracker tags` and `clear cache tracker tags`.

### Testing Notes

Tests use Pest with an `AddonTestCase` base that:
- Enables static caching with `half` strategy
- Creates a default `pages` collection with a `home` entry
- Disables stache disk writes
- Uses SQLite in-memory database

Fixture views live in `tests/__fixtures__/resources/views/`.
