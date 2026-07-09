<?php

use App\Http\Controllers\Api\SalesPerformanceMtdController;
use Illuminate\Support\Facades\Route;

Route::get('/hris/sales-performance-mtd', SalesPerformanceMtdController::class)
    ->name('api.hris.sales-performance-mtd');
