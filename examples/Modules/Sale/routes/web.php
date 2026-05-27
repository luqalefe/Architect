<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::middleware('web')
    ->prefix('sale')
    ->name('sale.')
    ->group(function () {
        // Web routes for the Sale module live here.
    });
