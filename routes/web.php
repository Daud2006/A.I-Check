<?php

use App\Http\Controllers\AboutController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CheckerController;
use App\Http\Controllers\HistoryController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

// Rute halaman umum.
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::middleware('guest')->group(function () {
    Route::get('/auth', [AuthController::class, 'index'])->name('auth');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1')->name('login');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:3,1')->name('register');
    Route::post('/password/otp', [AuthController::class, 'sendPasswordResetOtp'])
        ->middleware('throttle:5,1')
        ->name('password.otp.send');
    Route::post('/password/reset', [AuthController::class, 'resetPassword'])
        ->middleware('throttle:10,1')
        ->name('password.otp.reset');
});
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');
Route::get('/checker', [CheckerController::class, 'index'])->name('checker');
Route::middleware('auth')->prefix('checker/analyses')->name('checker.analyses.')->group(function () {
    Route::post('/', [CheckerController::class, 'store'])->middleware('throttle:10,1')->name('store');
    Route::get('/{analysisId}', [CheckerController::class, 'show'])->whereNumber('analysisId')->name('show');
    Route::get('/{analysisId}/image', [CheckerController::class, 'image'])->whereNumber('analysisId')->name('image');
});
Route::get('/about', [AboutController::class, 'index'])->name('about');

Route::middleware('auth')->group(function () {
    Route::get('/history', [HistoryController::class, 'index'])->name('history');
    Route::delete('/history', [HistoryController::class, 'destroyAll'])->name('history.destroy-all');
    Route::delete('/history/{analysisId}', [HistoryController::class, 'destroy'])
        ->whereNumber('analysisId')
        ->name('history.destroy');
    Route::get('/profile', [ProfileController::class, 'index'])->name('profile');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::get('/admin', [AdminController::class, 'index'])
    ->middleware('auth')
    ->name('admin');
