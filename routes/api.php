<?php

use Illuminate\Support\Facades\Route;
use NuxbeRemoteDirectory\Http\Controllers\DirectorySearchController;

Route::prefix('api/remote-directory')
    ->middleware('throttle:' . config('remote-directory.throttle'))
    ->group(function (): void {
        // Phone clients GET this with ?q=<search> and render the returned phonebook XML.
        Route::get('search', DirectorySearchController::class);
    });
