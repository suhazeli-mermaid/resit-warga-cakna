<?php

namespace Tests\Feature;

use App\Models\DonationReceipt;
use App\Models\DonationReceiptAudit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ReceiptAdminManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_soft_delete_with_reason_and_restore_a_pending_record(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $receipt = $this->receipt();

        $this->actingAs($admin)->delete(route('receipts.destroy', $receipt), [
            'deletion_reason' => 'Rekod pendua untuk ujian.',
        ])->assertRedirect(route('receipts.index'));

        $this->assertSoftDeleted($receipt);
        $this->assertDatabaseHas('donation_receipt_audits', [
            'donation_receipt_id' => $receipt->id,
            'action' => 'delete',
            'actor_id' => $admin->id,
            'reason' => 'Rekod pendua untuk ujian.',
        ]);

        $this->actingAs($admin)
            ->patch(route('receipts.restore', $receipt))
            ->assertRedirect(route('receipts.index', ['filter' => 'deleted']));

        $this->assertNotSoftDeleted($receipt);
        $this->assertDatabaseHas('donation_receipt_audits', [
            'donation_receipt_id' => $receipt->id,
            'action' => 'restore',
            'actor_id' => $admin->id,
        ]);
    }

    public function test_delete_requires_a_reason_and_completed_receipt_cannot_be_deleted(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $pending = $this->receipt();
        $completed = $this->receipt(['status' => DonationReceipt::STATUS_COMPLETED]);

        $this->actingAs($admin)->from(route('receipts.index'))
            ->delete(route('receipts.destroy', $pending), [])
            ->assertSessionHasErrors('deletion_reason');
        $this->assertNotSoftDeleted($pending);

        $this->actingAs($admin)->delete(route('receipts.destroy', $completed), [
            'deletion_reason' => 'Tidak patut dibenarkan.',
        ])->assertSessionHas('error');
        $this->assertNotSoftDeleted($completed);
    }

    public function test_completed_rejected_and_failed_records_can_be_archived_and_unarchived(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        foreach ([DonationReceipt::STATUS_COMPLETED, DonationReceipt::STATUS_REJECTED, DonationReceipt::STATUS_FAILED] as $status) {
            $receipt = $this->receipt(['status' => $status]);

            $this->actingAs($admin)->patch(route('receipts.archive', $receipt))->assertSessionHas('success');
            $this->assertNotNull($receipt->fresh()->archived_at);
            $this->assertDatabaseHas('donation_receipt_audits', ['donation_receipt_id' => $receipt->id, 'action' => 'archive']);

            $this->actingAs($admin)->patch(route('receipts.unarchive', $receipt))->assertSessionHas('success');
            $this->assertNull($receipt->fresh()->archived_at);
            $this->assertDatabaseHas('donation_receipt_audits', ['donation_receipt_id' => $receipt->id, 'action' => 'unarchive']);
        }
    }

    public function test_pending_record_cannot_be_archived(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $receipt = $this->receipt();

        $this->actingAs($admin)->patch(route('receipts.archive', $receipt))->assertSessionHas('error');
        $this->assertNull($receipt->fresh()->archived_at);
    }

    public function test_regular_user_cannot_manage_delete_restore_or_archive(): void
    {
        $user = User::factory()->create(['role' => 'penderma']);
        $pending = $this->receipt(['user_id' => $user->id]);
        $completed = $this->receipt(['status' => DonationReceipt::STATUS_COMPLETED, 'user_id' => $user->id]);

        $this->actingAs($user)->delete(route('receipts.destroy', $pending), ['deletion_reason' => 'x'])->assertForbidden();
        $this->actingAs($user)->patch(route('receipts.archive', $completed))->assertForbidden();

        $pending->delete();
        $this->actingAs($user)->patch(route('receipts.restore', $pending))->assertForbidden();
    }

    public function test_filters_exclude_deleted_and_archived_records_from_operational_list(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $active = $this->receipt(['donor_name' => 'Rekod Operasi']);
        $archived = $this->receipt(['donor_name' => 'Rekod Arkib', 'status' => DonationReceipt::STATUS_COMPLETED, 'archived_at' => now()]);
        $deleted = $this->receipt(['donor_name' => 'Rekod Sampah']);
        DonationReceiptAudit::create(['donation_receipt_id' => $deleted->id, 'receipt_no' => $deleted->receipt_no, 'action' => 'delete', 'actor_id' => $admin->id, 'reason' => 'Ujian']);
        $deleted->delete();

        $this->actingAs($admin)->get(route('receipts.index'))
            ->assertSeeText($active->donor_name)
            ->assertDontSeeText($archived->donor_name)
            ->assertDontSeeText($deleted->donor_name);

        $this->actingAs($admin)->get(route('receipts.index', ['filter' => 'archived']))
            ->assertSeeText($archived->donor_name)->assertDontSeeText($active->donor_name);
        $this->actingAs($admin)->get(route('receipts.index', ['filter' => 'deleted']))
            ->assertSeeText($deleted->donor_name)->assertSeeText('Ujian');
    }

    public function test_action_matrix_distinguishes_manual_and_toyyibpay_pending_records(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $manual = $this->receipt(['receipt_no' => 'REC-MANUAL']);
        $gateway = $this->receipt(['receipt_no' => 'REC-GATEWAY', 'payment_method' => 'toyyibpay', 'toyyibpay_bill_code' => 'bill-1']);

        $html = $this->actingAs($admin)->get(route('receipts.index'))->assertOk()->getContent();
        $this->assertSame(2, substr_count($html, 'Sahkan Pembayaran'), 'Manual record renders once in mobile and once on desktop.');
        $this->assertStringContainsString($manual->receipt_no, $html);
        $this->assertStringContainsString($gateway->receipt_no, $html);
        $this->assertStringContainsString('Sahkan Penghapusan', $html);
        $this->assertStringContainsString('name="_token"', $html);
    }

    public function test_management_routes_keep_web_csrf_and_auth_middleware_without_force_delete(): void
    {
        $destroyRoute = Route::getRoutes()->getByName('receipts.destroy');

        $this->assertNotNull($destroyRoute);
        $this->assertContains('web', $destroyRoute->gatherMiddleware());
        $this->assertContains('auth', $destroyRoute->gatherMiddleware());
        $this->assertNull(Route::getRoutes()->getByName('receipts.force-delete'));
    }

    private function receipt(array $overrides = []): DonationReceipt
    {
        return DonationReceipt::create(array_merge([
            'receipt_no' => 'REC-'.strtoupper(substr(uniqid(), -8)),
            'donor_name' => 'Penderma Ujian',
            'donor_email' => 'donor@example.test',
            'donor_phone' => '0191234567',
            'amount' => 50,
            'payment_method' => 'bank_transfer',
            'payment_reference' => 'REF-1',
            'status' => DonationReceipt::STATUS_PENDING,
            'donated_at' => '2026-07-16',
        ], $overrides));
    }
}
