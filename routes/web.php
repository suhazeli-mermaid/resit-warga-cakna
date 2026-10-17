<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DonationReceiptController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\PublicDonationController;

// Halaman Utama / Portal Awam Sumbangan
Route::get('/', [PublicDonationController::class, 'index'])->name('public.index');
Route::get('/campaigns/{id}', [PublicDonationController::class, 'show'])->name('public.show');

Route::middleware(['auth'])->group(function () {

    // Dashboard Utama Sistem
    Route::get('/dashboard', [DonationReceiptController::class, 'index'])->name('dashboard');

    // Admin Hub / Pengurusan AJK & Pengguna
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::patch('/users/{id}/role', [UserController::class, 'updateRole'])->name('users.update-role');

    // Pengurusan Profil
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Pengurusan Resit Sumbangan
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

});

require __DIR__.'/auth.php';