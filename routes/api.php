<?php

use App\Http\Controllers\Api\SalesPerformanceMtdController;
use App\Http\Controllers\Api\CommissionSlipController;
use Illuminate\Support\Facades\Route;

Route::get('/hris/sales-performance-mtd', SalesPerformanceMtdController::class)
    ->name('api.hris.sales-performance-mtd');

Route::get('/hris/commission-slip', CommissionSlipController::class)
    ->name('api.hris.commission-slip');
