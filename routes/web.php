<?php

declare(strict_types=1);

use App\Http\Controllers\ScanController;
use App\Http\Controllers\ScanImageController;
use Illuminate\Support\Facades\Route;

Route::get('/', [ScanController::class, 'home'])->name('home');
Route::get('/scan', [ScanController::class, 'create'])->name('scan.create');
Route::post('/scan', [ScanController::class, 'store'])
    ->middleware('throttle:ai')
    ->name('scan.store');
Route::post('/scan/label', [ScanController::class, 'storeLabel'])
    ->middleware('throttle:ai')
    ->name('scan.storeLabel');
Route::get('/scan/{scan}/confirm', [ScanController::class, 'confirm'])
    ->name('scan.confirm')
    ->where('scan', '[a-f0-9-]{36}');
Route::get('/scan/{scan}/image', [ScanImageController::class, 'show'])
    ->name('scan.image')
    ->where('scan', '[a-f0-9-]{36}');
Route::post('/scan/{scan}/confirm', [ScanController::class, 'confirmStore'])
    ->name('scan.confirm.store')
    ->where('scan', '[a-f0-9-]{36}');
Route::post('/scan/{scan}/rescan', [ScanController::class, 'rescan'])
    ->middleware('throttle:ai')
    ->where('scan', '[a-f0-9-]{36}')
    ->name('scan.rescan');
Route::get('/species/{species}', [ScanController::class, 'show'])
    ->name('species.show')
    ->where('species', '[\w\-]+');
Route::post('/species/{species}/explain', [ScanController::class, 'explain'])
    ->middleware('throttle:ai')
    ->where('species', '[\w\-]+')
    ->name('species.explain');
