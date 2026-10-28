<?php

use App\Http\Controllers\Admin\CampaignCmsController;
use App\Http\Controllers\Admin\HomepageContentController;
use App\Http\Controllers\Admin\OrganizationSettingsController;
use App\Http\Controllers\Admin\SmartUpdateController;
use App\Http\Controllers\Admin\VoucherAcknowledgementController as AdminVoucherAcknowledgementController;
use App\Http\Controllers\DonationReceiptController;
use App\Http\Controllers\PaymentVoucherController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicDonationController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VoucherAcknowledgementController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Routes & Donation Gateway
|--------------------------------------------------------------------------
*/
Route::get('/', [PublicDonationController::class, 'index'])->name('public.index');
Route::get('/dana/{slug}', [PublicDonationController::class, 'show'])->name('dana.show');
Route::get('/dana/{slug}/maklumat', [PublicDonationController::class, 'information'])->name('dana.information');
Route::get('/campaigns/{id}', [PublicDonationController::class, 'show'])->name('public.show');

// ToyyibPay Process & Callbacks
Route::post('/donate/process', [PublicDonationController::class, 'processDonation'])->name('public.donate.process');
Route::get('/payment/return', [PublicDonationController::class, 'paymentReturn'])->name('payment.return');
Route::post('/payment/callback', [PublicDonationController::class, 'paymentCallback'])->name('payment.callback');

// Pautan awam satu kali; token tidak disimpan dalam log aplikasi oleh controller.
Route::middleware('throttle:10,1')->group(function () {
    Route::get('/baucar/akuan/{token}', [VoucherAcknowledgementController::class, 'show'])->name('vouchers.acknowledgement.show');
    Route::post('/baucar/akuan/{token}', [VoucherAcknowledgementController::class, 'store'])->name('vouchers.acknowledgement.store');
});

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
    Route::get('/profile/digital-signature', [ProfileController::class, 'signature'])->name('profile.signature.show');
    Route::put('/profile/digital-signature', [ProfileController::class, 'updateSignature'])->name('profile.signature.update');
    Route::delete('/profile/digital-signature', [ProfileController::class, 'destroySignature'])->name('profile.signature.destroy');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Pengurusan Resit Sumbangan (Core Module)
    Route::get('/receipts', [DonationReceiptController::class, 'index'])->name('receipts.index');
    Route::get('/receipts/create', [DonationReceiptController::class, 'create'])->name('receipts.create');
    Route::post('/receipts', [DonationReceiptController::class, 'store'])->name('receipts.store');
    Route::get('/receipts/{id}', [DonationReceiptController::class, 'show'])->name('receipts.show');
    Route::get('/receipts/{id}/edit', [DonationReceiptController::class, 'edit'])->name('receipts.edit');
    Route::put('/receipts/{id}', [DonationReceiptController::class, 'update'])->name('receipts.update');
    Route::patch('/receipts/{id}/verify', [DonationReceiptController::class, 'verify'])->name('receipts.verify');
    Route::patch('/receipts/{id}/reject', [DonationReceiptController::class, 'reject'])->name('receipts.reject');
    Route::delete('/receipts/{id}', [DonationReceiptController::class, 'destroy'])->name('receipts.destroy');
    Route::patch('/receipts/{id}/restore', [DonationReceiptController::class, 'restore'])->name('receipts.restore');
    Route::patch('/receipts/{id}/archive', [DonationReceiptController::class, 'archive'])->name('receipts.archive');
    Route::patch('/receipts/{id}/unarchive', [DonationReceiptController::class, 'unarchive'])->name('receipts.unarchive');
    Route::post('/receipts/{id}/resend-email', [DonationReceiptController::class, 'resendEmail'])->name('receipts.resend-email');
    Route::get('/receipts/{id}/download-pdf', [DonationReceiptController::class, 'downloadPdf'])->name('receipts.download-pdf');
    Route::get('/receipts/{id}/view-pdf', [DonationReceiptController::class, 'viewPdf'])->name('receipts.view-pdf');

    // Admin Hub Routes
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::patch('/users/{id}/role', [UserController::class, 'updateRole'])->name('users.update-role');

    // Modul Baucar Bayaran
    Route::get('/admin/kewangan/baucar', [PaymentVoucherController::class, 'dashboard'])->name('vouchers.dashboard');
    Route::get('/admin/kewangan/baucar/senarai', [PaymentVoucherController::class, 'index'])->name('vouchers.index');
    Route::get('/admin/kewangan/baucar/arkib', [PaymentVoucherController::class, 'archiveIndex'])->name('vouchers.archive.index');
    Route::get('/admin/kewangan/baucar/akuan', [AdminVoucherAcknowledgementController::class, 'index'])->name('vouchers.acknowledgements.index');
    Route::get('/admin/kewangan/baucar/akuan/{acknowledgement}', [AdminVoucherAcknowledgementController::class, 'show'])->name('vouchers.acknowledgements.show');
    Route::get('/admin/kewangan/baucar/akuan/{acknowledgement}/document', [AdminVoucherAcknowledgementController::class, 'document'])->name('vouchers.acknowledgements.document');
    Route::get('/admin/kewangan/baucar/akuan/{acknowledgement}/pdf', [AdminVoucherAcknowledgementController::class, 'pdf'])->name('vouchers.acknowledgements.pdf');
    Route::get('/admin/kewangan/baucar/create', [PaymentVoucherController::class, 'create'])->name('vouchers.create');
    Route::post('/admin/kewangan/baucar', [PaymentVoucherController::class, 'store'])->name('vouchers.store');
    Route::get('/admin/kewangan/baucar/{voucher}', [PaymentVoucherController::class, 'show'])->name('vouchers.show');
    Route::get('/admin/kewangan/baucar/{voucher}/edit', [PaymentVoucherController::class, 'edit'])->name('vouchers.edit');
    Route::put('/admin/kewangan/baucar/{voucher}', [PaymentVoucherController::class, 'update'])->name('vouchers.update');
    Route::post('/admin/kewangan/baucar/{voucher}/submit', [PaymentVoucherController::class, 'submit'])->name('vouchers.submit');
    Route::post('/admin/kewangan/baucar/{voucher}/approve', [PaymentVoucherController::class, 'approve'])->name('vouchers.approve');
    Route::post('/admin/kewangan/baucar/{voucher}/reject', [PaymentVoucherController::class, 'reject'])->name('vouchers.reject');
    Route::post('/admin/kewangan/baucar/{voucher}/issue', [PaymentVoucherController::class, 'issue'])->name('vouchers.issue');
    Route::post('/admin/kewangan/baucar/{voucher}/confirm-payment', [PaymentVoucherController::class, 'confirmPayment'])->name('vouchers.confirm-payment');
    Route::post('/admin/kewangan/baucar/{voucher}/resend-acknowledgement', [PaymentVoucherController::class, 'resendAcknowledgement'])->name('vouchers.resend-acknowledgement');
    Route::post('/admin/kewangan/baucar/{voucher}/cancel', [PaymentVoucherController::class, 'cancel'])->name('vouchers.cancel');
    Route::post('/admin/kewangan/baucar/{voucher}/reissue', [PaymentVoucherController::class, 'reissue'])->name('vouchers.reissue');
    Route::post('/admin/kewangan/baucar/{voucher}/archive', [PaymentVoucherController::class, 'archive'])->name('vouchers.archive');
    Route::get('/admin/kewangan/baucar/{voucher}/document', [PaymentVoucherController::class, 'document'])->name('vouchers.document');
    Route::get('/admin/kewangan/baucar/{voucher}/pdf', [PaymentVoucherController::class, 'documentPdf'])->name('vouchers.pdf');
    Route::get('/admin/kewangan/baucar/{voucher}/acknowledgement-pdf', [PaymentVoucherController::class, 'acknowledgementPdf'])->name('vouchers.acknowledgement.pdf');
    Route::get('/admin/kewangan/baucar/{voucher}/payment-proof', [PaymentVoucherController::class, 'paymentProof'])->name('vouchers.payment-proof');

    // Tetapan organisasi
    Route::get('/admin/organization', [OrganizationSettingsController::class, 'edit'])->name('admin.organization.edit');
    Route::get('/admin/organization/stamp', [OrganizationSettingsController::class, 'stamp'])->name('admin.organization.stamp.show');
    Route::put('/admin/organization/stamp', [OrganizationSettingsController::class, 'updateStamp'])->name('admin.organization.stamp');

    // Smart Update Manager — tiada input arahan shell atau ref Git daripada UI.
    Route::get('/admin/system/updates', [SmartUpdateController::class, 'index'])->name('admin.updates.index');
    Route::post('/admin/system/updates/check', [SmartUpdateController::class, 'check'])->name('admin.updates.check');
    Route::get('/admin/system/updates/{operation}', [SmartUpdateController::class, 'show'])->name('admin.updates.show');
    Route::post('/admin/system/updates/{operation}/preflight', [SmartUpdateController::class, 'preflight'])->name('admin.updates.preflight');
    Route::post('/admin/system/updates/{operation}/backup', [SmartUpdateController::class, 'backup'])->name('admin.updates.backup');
    Route::post('/admin/system/updates/{operation}/install', [SmartUpdateController::class, 'install'])->name('admin.updates.install');

    // Homepage Content Management & Signature Upload
    Route::get('/admin/homepage', [HomepageContentController::class, 'edit'])->name('admin.homepage.edit');
    Route::put('/admin/homepage/hero', [HomepageContentController::class, 'updateHero'])->name('admin.homepage.hero.update');
    Route::put('/admin/campaigns/{id}', [HomepageContentController::class, 'updateCampaign'])->name('admin.campaigns.update');
    Route::post('/admin/signature', [HomepageContentController::class, 'uploadSignature'])->name('admin.upload-signature');

    /*
    |--------------------------------------------------------------------------
    | Campaign CMS — Admin Management
    |--------------------------------------------------------------------------
    | Semua fungsi CMS memerlukan authentication.
    | Pemeriksaan role Admin turut dilaksanakan
    | dalam CampaignCmsController.
    */

    Route::get(
        '/admin/campaigns/create',
        [CampaignCmsController::class, 'create']
    )->name('admin.cms.create');

    Route::post(
        '/admin/campaigns',
        [CampaignCmsController::class, 'store']
    )->name('admin.cms.store');

    Route::get(
        '/admin/campaigns/{id}/manage',
        [CampaignCmsController::class, 'edit']
    )->name('admin.cms.edit');

    Route::put(
        '/admin/campaigns/{id}/details',
        [CampaignCmsController::class, 'saveDetails']
    )->name('admin.cms.details');

    Route::post(
        '/admin/campaigns/{id}/updates',
        [CampaignCmsController::class, 'storeUpdate']
    )->name('admin.cms.updates.store');

    Route::put(
        '/admin/campaign-updates/{id}',
        [CampaignCmsController::class, 'updateActivity']
    )->name('admin.cms.updates.update');

    Route::post(
        '/admin/campaigns/{id}/media',
        [CampaignCmsController::class, 'storeMedia']
    )->name('admin.cms.media.store');

    Route::delete(
        '/admin/campaign-media/{id}',
        [CampaignCmsController::class, 'deleteMedia']
    )->name('admin.cms.media.delete');

});

require __DIR__.'/auth.php';
