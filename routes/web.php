<?php

declare(strict_types=1);

use App\Http\Controllers\ScanController;
use Illuminate\Support\Facades\Route;

Route::get('/', [ScanController::class, 'home'])->name('home');
Route::get('/scan', [ScanController::class, 'create'])->name('scan.create');
Route::post('/scan', [ScanController::class, 'store'])->name('scan.store');
Route::get('/scan/{scan}/confirm', [ScanController::class, 'confirm'])->name('scan.confirm');
Route::post('/scan/{scan}/confirm', [ScanController::class, 'confirmStore'])->name('scan.confirm.store');
Route::get('/species/{species}', [ScanController::class, 'show'])->name('species.show');
