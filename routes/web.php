<?php

use App\Http\Controllers\AboutController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AdminPlanController;
use App\Http\Controllers\AdminReportController;
use App\Http\Controllers\AdminSubscriptionController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\CheckerController;
use App\Http\Controllers\HistoryController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MidtransWebhookController;
use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
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
Route::post('/checker/trial', [CheckerController::class, 'trial'])
    ->middleware(['guest', 'throttle:5,1,guest-trial'])
    ->name('checker.trial');
Route::middleware('auth')->prefix('checker/analyses')->name('checker.analyses.')->group(function () {
    Route::post('/', [CheckerController::class, 'store'])->middleware('throttle:10,1')->name('store');
    Route::get('/{analysisId}', [CheckerController::class, 'show'])->whereNumber('analysisId')->name('show');
    Route::get('/{analysisId}/image', [CheckerController::class, 'image'])->whereNumber('analysisId')->name('image');
});
Route::get('/about', [AboutController::class, 'index'])->name('about');
Route::view('/terms', 'legal.terms')->name('legal.terms');
Route::view('/privacy', 'legal.privacy')->name('legal.privacy');
Route::get('/plans', [BillingController::class, 'plans'])->name('plans.index');
Route::post('/webhooks/midtrans', MidtransWebhookController::class)
    ->withoutMiddleware(ValidateCsrfToken::class)
    ->name('webhooks.midtrans');

Route::middleware('auth')->group(function () {
    Route::get('/checkout/{plan}', [BillingController::class, 'checkoutSummary'])
        ->whereNumber('plan')
        ->name('checkout.show');
    Route::post('/checkout/{plan}', [BillingController::class, 'checkout'])
        ->whereNumber('plan')
        ->name('checkout.store');
    Route::get('/transactions', [BillingController::class, 'transactions'])->name('transactions.index');
    Route::get('/transactions/{paymentId}', [BillingController::class, 'showTransaction'])
        ->whereNumber('paymentId')
        ->name('transactions.show');
    Route::post('/transactions/{paymentId}/status', [BillingController::class, 'refreshTransaction'])
        ->whereNumber('paymentId')
        ->middleware('throttle:6,1')
        ->name('transactions.refresh');

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
Route::middleware('auth')->prefix('admin/users')->name('admin.users.')->group(function () {
    Route::get('/', [AdminController::class, 'users'])->name('index');
    Route::get('/create', [AdminController::class, 'createUser'])->name('create');
    Route::post('/', [AdminController::class, 'storeUser'])->middleware('throttle:20,1')->name('store');
    Route::get('/{userId}', [AdminController::class, 'showUser'])->whereNumber('userId')->name('show');
    Route::get('/{userId}/edit', [AdminController::class, 'editUser'])->whereNumber('userId')->name('edit');
    Route::put('/{userId}', [AdminController::class, 'updateUser'])->whereNumber('userId')->name('update');
    Route::delete('/{userId}', [AdminController::class, 'destroyUser'])->whereNumber('userId')->name('destroy');
    Route::patch('/{userId}/enable', [AdminController::class, 'enableUser'])->whereNumber('userId')->name('enable');
});
Route::get('/admin/subscriptions', [AdminSubscriptionController::class, 'index'])
    ->middleware('auth')->name('admin.subscriptions.index');
Route::middleware('auth')->prefix('admin/analyses')->name('admin.analyses.')->group(function () {
    Route::get('/', [AdminController::class, 'analyses'])->name('index');
    Route::get('/{analysisId}', [AdminController::class, 'showAnalysis'])->whereNumber('analysisId')->name('show');
    Route::delete('/{analysisId}', [HistoryController::class, 'destroyAsAdmin'])->whereNumber('analysisId')->name('destroy');
});
Route::middleware('auth')->prefix('admin/transactions')->name('admin.transactions.')->group(function () {
    Route::get('/', [AdminController::class, 'transactions'])->name('index');
    Route::get('/{paymentId}', [AdminController::class, 'showTransaction'])->whereNumber('paymentId')->name('show');
});
Route::middleware('auth')->prefix('admin/plans')->name('admin.plans.')->group(function () {
    Route::get('/', [AdminPlanController::class, 'index'])->name('index');
    Route::get('/create', [AdminPlanController::class, 'create'])->name('create');
    Route::post('/', [AdminPlanController::class, 'store'])->name('store');
    Route::get('/gift', [AdminSubscriptionController::class, 'createGift'])->name('gift.create');
    Route::post('/gift', [AdminSubscriptionController::class, 'storeGift'])->middleware('throttle:20,1')->name('gift.store');
    Route::get('/{plan}', [AdminPlanController::class, 'show'])->whereNumber('plan')->name('show');
    Route::get('/{plan}/edit', [AdminPlanController::class, 'edit'])->whereNumber('plan')->name('edit');
    Route::put('/{plan}', [AdminPlanController::class, 'update'])->whereNumber('plan')->name('update');
    Route::patch('/{plan}/active', [AdminPlanController::class, 'setActive'])->whereNumber('plan')->name('active');
});
Route::middleware('auth')->prefix('admin/reports')->name('admin.reports.')->group(function () {
    Route::get('/', [AdminReportController::class, 'index'])->name('index');
    Route::get('/export/{type}', [AdminReportController::class, 'export'])
        ->whereIn('type', ['users', 'analyses', 'transactions'])
        ->name('export');
});
