<?php

namespace Thoughtco\StatamicCacheTracker\Tracker\Stores;

interface Store
{
    /**
     * Get the record for a URL key, as ['url' => string, 'tags' => string[]].
     */
    public function get(string $key): ?array;

    /**
     * Store the tags for a URL, replacing any existing record for it.
     */
    public function put(string $key, string $url, array $tags): void;

    /**
     * Bulk import records keyed by URL key. Existing records are left untouched.
     */
    public function import(array $records): void;

    /**
     * Remove the records for the given URL keys.
     */
    public function forget(array $keys): void;

    /**
     * Get every record, keyed by URL key.
     */
    public function all(): array;

    /**
     * Get the records with at least one tag matching the given tags (which may
     * contain wildcards), keyed by URL key.
     */
    public function matching(array $tags): array;

    /**
     * Remove every record.
     */
    public function flush(): void;
}
