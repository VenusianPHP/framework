<?php

namespace Tests\Collections\Fixtures;

/**
 * Ported from the instance methods `sortByRating`/`sortByUrl` that upstream
 * defines directly on SupportCollectionTest, used as `[$this, 'sortByRating']`
 * array-callables for Collection::groupBy(). Pest tests are plain closures,
 * not test-case methods, so these live on a fixture instead and are wired up
 * as `[GroupBySorters::class, 'sortByRating']` — still an array callable,
 * still exercising the same is_callable() branch in groupBy().
 */
class GroupBySorters
{
    public static function sortByRating(array $value)
    {
        return $value['rating'];
    }

    public static function sortByUrl(array $value)
    {
        return $value['url'];
    }
}
