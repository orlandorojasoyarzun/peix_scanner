<?php

declare(strict_types=1);

use App\Http\Controllers\ScanController;
use Illuminate\Support\Facades\Route;

Route::get('/', [ScanController::class, 'home'])->name('home');
Route::get('/scan', [ScanController::class, 'create'])->name('scan.create');
Route::post('/scan', [ScanController::class, 'store'])->name('scan.store');
Route::get('/scan/{scan}/confirm', [ScanController::class, 'confirm'])
    ->name('scan.confirm')
    ->where('scan', '[a-f0-9-]{36}');
Route::post('/scan/{scan}/confirm', [ScanController::class, 'confirmStore'])
    ->name('scan.confirm.store')
    ->where('scan', '[a-f0-9-]{36}');
Route::get('/species/{species}', [ScanController::class, 'show'])
    ->name('species.show')
    ->where('species', '[\w\-]+');
