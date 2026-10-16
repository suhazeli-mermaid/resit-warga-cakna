<?php

use App\Http\Controllers\DonationReceiptController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DonationReceiptController::class, 'index'])->name('receipts.index');
Route::get('/receipts/create', [DonationReceiptController::class, 'create'])->name('receipts.create');
Route::post('/receipts', [DonationReceiptController::class, 'store'])->name('receipts.store');
Route::get('/receipts/{id}/pdf', [DonationReceiptController::class, 'downloadPdf'])->name('receipts.pdf');

// Route carian penderma automatik
Route::get('/api/donors/search', [DonationReceiptController::class, 'searchDonors'])->name('donors.search');