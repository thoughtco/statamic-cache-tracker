<?php

use Illuminate\Support\Facades\Route;
use Thoughtco\StatamicCacheTracker\Http\Controllers;

Route::name('cache-tracker.')->prefix('cache-tracker')->group(function () {
    Route::post('tags', [Controllers\GetTagsController::class, '__invoke'])->name('tags');
    Route::post('urls', [Controllers\GetUrlsController::class, '__invoke'])->name('url');
});
