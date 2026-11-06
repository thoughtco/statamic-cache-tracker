<?php

namespace Thoughtco\StatamicCacheTracker\Tracker;

class TagMatcher
{
    /**
     * Split tags into exact tags and wildcard prefixes (tags ending in *).
     *
     * @return array{0: string[], 1: string[]}
     */
    public static function split(array $tags): array
    {
        $exact = [];
        $prefixes = [];

        foreach ($tags as $tag) {
            if (str_ends_with($tag, '*')) {
                $prefixes[] = substr($tag, 0, -1);
            } else {
                $exact[] = $tag;
            }
        }

        return [array_values(array_unique($exact)), array_values(array_unique($prefixes))];
    }

    public static function matches(array $tagsToInvalidate, array $storeTags): bool
    {
        foreach ($tagsToInvalidate as $tag) {
            if (str_ends_with($tag, '*')) {
                $prefix = substr($tag, 0, -1);
                foreach ($storeTags as $storeTag) {
                    if (str_starts_with($storeTag, $prefix)) {
                        return true;
                    }
                }
            } elseif (in_array($tag, $storeTags)) {
                return true;
            }
        }

        return false;
    }
}
