<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PublicDonationController;
use App\Http\Controllers\DonationReceiptController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Admin\HomepageContentController;

/*
|--------------------------------------------------------------------------
| Public Routes & Donation Gateway
|--------------------------------------------------------------------------
*/
Route::get('/', [PublicDonationController::class, 'index'])->name('public.index');
Route::get('/dana/{slug}', [PublicDonationController::class, 'show'])->name('dana.show');
Route::get('/campaigns/{id}', [PublicDonationController::class, 'show'])->name('public.show');

// ToyyibPay Process & Callbacks
Route::post('/donate/process', [PublicDonationController::class, 'processDonation'])->name('public.donate.process');
Route::get('/payment/return', [PublicDonationController::class, 'paymentReturn'])->name('payment.return');
Route::post('/payment/callback', [PublicDonationController::class, 'paymentCallback'])->name('payment.callback');

/*
|--------------------------------------------------------------------------
| Authenticated User Routes (Dashboard Redirects to Receipts List)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth'])->group(function () {

    Route::get('/dashboard', function () {
        return redirect()->route('receipts.index');
    })->name('dashboard');

    // Pengurusan Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Pengurusan Resit Sumbangan (Core Module)
    Route::get('/receipts', [DonationReceiptController::class, 'index'])->name('receipts.index');
    Route::get('/receipts/create', [DonationReceiptController::class, 'create'])->name('receipts.create');
    Route::post('/receipts', [DonationReceiptController::class, 'store'])->name('receipts.store');
    Route::get('/receipts/{id}', [DonationReceiptController::class, 'show'])->name('receipts.show');
    Route::get('/receipts/{id}/edit', [DonationReceiptController::class, 'edit'])->name('receipts.edit');
    Route::put('/receipts/{id}', [DonationReceiptController::class, 'update'])->name('receipts.update');
    Route::patch('/receipts/{id}/verify', [DonationReceiptController::class, 'verify'])->name('receipts.verify');
    Route::post('/receipts/{id}/resend-email', [DonationReceiptController::class, 'resendEmail'])->name('receipts.resend-email');
    Route::get('/receipts/{id}/download-pdf', [DonationReceiptController::class, 'downloadPdf'])->name('receipts.download-pdf');
    Route::get('/receipts/{id}/view-pdf', [DonationReceiptController::class, 'viewPdf'])->name('receipts.view-pdf');

    // Admin Hub Routes
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::patch('/users/{id}/role', [UserController::class, 'updateRole'])->name('users.update-role');

    // Homepage Content Management & Signature Upload
    Route::get('/admin/homepage', [HomepageContentController::class, 'edit'])->name('admin.homepage.edit');
    Route::put('/admin/campaigns/{id}', [HomepageContentController::class, 'updateCampaign'])->name('admin.campaigns.update');
    Route::post('/admin/signature', [HomepageContentController::class, 'uploadSignature'])->name('admin.upload-signature');

});

require __DIR__.'/auth.php';