<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Sale\Infrastructure\Http\Controllers\SaleOrderController;

Route::middleware('api')
    ->prefix('api/sale')
    ->name('api.sale.')
    ->group(function () {
        Route::post('orders', [SaleOrderController::class, 'store'])->name('orders.store');
    });
