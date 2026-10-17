<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DonationReceiptController;

Route::get('/', function () {
    return redirect()->route('receipts.index');
});

Route::middleware(['auth'])->group(function () {
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