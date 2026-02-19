<?php
use App\Http\Controllers\ScanController;
use Illuminate\Support\Facades\Route;

// Callback for LIS Python Service
Route::post('/lis-callback', [ScanController::class, 'lisCallback'])->name('api.lis_callback');

// Trigger Arduino Sorting
Route::post('/trigger-sort', [ScanController::class, 'triggerSorting']);