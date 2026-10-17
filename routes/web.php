<?php

use App\Http\Controllers\DonationReceiptController;
use App\Http\Controllers\PublicDonationController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// 1. Portal Awam Dana
Route::get('/', [PublicDonationController::class, 'index'])->name('home');
Route::get('/dana/{slug}', [PublicDonationController::class, 'show'])->name('dana.show');
Route::post('/dana/{slug}/donate', [PublicDonationController::class, 'processDonation'])->name('dana.donate');

// 2. ToyyibPay Return & Callback
Route::get('/toyyibpay/return', [PublicDonationController::class, 'toyyibpayReturn'])->name('toyyibpay.return');
Route::post('/toyyibpay/callback', [PublicDonationController::class, 'toyyibpayReturn'])->name('toyyibpay.callback');

// 3. Kumpulan Laluan yang Dilindungi Autentikasi
Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', [DonationReceiptController::class, 'index'])->name('dashboard');
    
    // Admin Hub Routes (Pengurusan Kempen & Tandatangan)
    Route::post('/admin/campaigns', [PublicDonationController::class, 'storeCampaign'])->name('campaigns.store');
    Route::put('/admin/campaigns/{id}', [PublicDonationController::class, 'updateCampaign'])->name('campaigns.update');
    Route::delete('/admin/campaigns/{id}', [PublicDonationController::class, 'destroyCampaign'])->name('campaigns.destroy');
    Route::post('/admin/signature', [DonationReceiptController::class, 'uploadSignature'])->name('admin.upload-signature');

    // Super Admin Routes (Pengurusan Peranan Pengguna)
    Route::post('/admin/users/{id}/role', [UserController::class, 'updateRole'])->name('users.update-role');

    // Resit Derma Routes
    Route::get('/receipts', [DonationReceiptController::class, 'index'])->name('receipts.index');
    Route::get('/receipts/create', [DonationReceiptController::class, 'create'])->name('receipts.create');
    Route::post('/receipts', [DonationReceiptController::class, 'store'])->name('receipts.store');
    
    // Papar & Muat Turun PDF
    Route::get('/receipts/{id}/view', [DonationReceiptController::class, 'viewPdf'])->name('receipts.view-pdf');
    Route::get('/receipts/{id}/pdf', [DonationReceiptController::class, 'downloadPdf'])->name('receipts.pdf');

    // Tindakan Admin
    Route::post('/receipts/{id}/remind-email', [DonationReceiptController::class, 'sendReminderEmail'])->name('receipts.remind-email');
    Route::post('/receipts/{id}/verify-paid', [DonationReceiptController::class, 'markAsPaid'])->name('receipts.mark-paid');

    // API Carian Penderma
    Route::get('/api/donors/search', [DonationReceiptController::class, 'searchDonors'])->name('donors.search');
});

require __DIR__.'/auth.php';