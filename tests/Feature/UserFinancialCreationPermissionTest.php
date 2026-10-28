<?php

namespace Tests\Feature;

use App\Models\DonationReceipt;
use App\Models\FavouriteContact;
use App\Models\PaymentVoucher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UserFinancialCreationPermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Storage::fake('local');
    }

    public function test_admin_always_can_create_receipts_and_vouchers(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'can_create_receipt' => false,
            'can_create_voucher' => false,
        ]);

        $this->actingAs($admin)->get(route('receipts.create'))->assertOk();
        $this->actingAs($admin)->post(route('receipts.store'), $this->receiptPayload())->assertRedirect(route('receipts.index'));
        $this->actingAs($admin)->get(route('vouchers.create'))->assertOk();
        $this->actingAs($admin)->post(route('vouchers.store'), $this->voucherPayload())->assertRedirect();

        $this->assertDatabaseCount('donation_receipts', 1);
        $this->assertDatabaseCount('payment_vouchers', 1);
    }

    public function test_user_without_permissions_cannot_create_receipts_or_vouchers(): void
    {
        $user = User::factory()->create([
            'role' => 'ajk',
            'can_create_receipt' => false,
            'can_create_voucher' => false,
        ]);

        $this->actingAs($user)->get(route('receipts.create'))->assertForbidden();
        $this->actingAs($user)->post(route('receipts.store'), $this->receiptPayload())->assertForbidden();
        $this->actingAs($user)->get(route('vouchers.create'))->assertForbidden();
        $this->actingAs($user)->post(route('vouchers.store'), $this->voucherPayload())->assertForbidden();

        $this->assertDatabaseCount('donation_receipts', 0);
        $this->assertDatabaseCount('payment_vouchers', 0);
    }

    public function test_receipt_permission_allows_creation_and_favourite_selection_but_not_payment_decisions(): void
    {
        $user = User::factory()->create(['role' => 'penderma', 'can_create_receipt' => true]);
        $contact = $this->favourite(['is_donor_favourite' => true]);

        $this->actingAs($user)->get(route('receipts.create'))
            ->assertOk()
            ->assertSeeText('Favourite Pembayar / Penderma')
            ->assertDontSeeText('Simpan / kemas kini sebagai Favourite organisasi');
        $this->actingAs($user)
            ->getJson(route('admin.favourite-contacts.prefill', $contact).'?usage=donor')
            ->assertOk()
            ->assertJsonPath('data.name', $contact->name);

        $this->actingAs($user)->post(route('receipts.store'), array_merge($this->receiptPayload(), [
            'favourite_contact_id' => $contact->id,
        ]))->assertRedirect(route('receipts.index'));

        $receipt = DonationReceipt::query()->sole();
        $this->assertSame($user->id, $receipt->user_id);
        $this->actingAs($user)->patch(route('receipts.verify', $receipt))->assertForbidden();
        $this->actingAs($user)->patch(route('receipts.reject', $receipt), [
            'rejection_reason' => 'Tidak dibenarkan',
        ])->assertForbidden();
        $this->actingAs($user)->get(route('admin.favourite-contacts.index'))->assertForbidden();
    }

    public function test_voucher_permission_allows_own_creation_submission_and_favourite_selection(): void
    {
        $user = User::factory()->create([
            'role' => 'penderma',
            'organization_position' => 'AJK Kebajikan',
            'can_create_voucher' => true,
        ]);
        $this->signature($user);
        $contact = $this->favourite([
            'is_donor_favourite' => false,
            'is_voucher_recipient_favourite' => true,
        ]);

        $this->actingAs($user)->get(route('vouchers.create'))
            ->assertOk()
            ->assertSeeText('Favourite Penerima')
            ->assertDontSeeText('Simpan / kemas kini sebagai Favourite organisasi');
        $this->actingAs($user)
            ->getJson(route('admin.favourite-contacts.prefill', $contact).'?usage=voucher')
            ->assertOk()
            ->assertJsonPath('data.name', $contact->name);

        $this->actingAs($user)->post(route('vouchers.store'), array_merge($this->voucherPayload(), [
            'favourite_contact_id' => $contact->id,
        ]))->assertRedirect();

        $voucher = PaymentVoucher::query()->sole();
        $this->assertSame($user->id, $voucher->applicant_id);
        $this->actingAs($user)->post(route('vouchers.submit', $voucher))
            ->assertRedirect()
            ->assertSessionHas('success');
        $this->assertSame(PaymentVoucher::PENDING_APPROVAL, $voucher->fresh()->status);
        $this->actingAs($user)->get(route('admin.favourite-contacts.index'))->assertForbidden();
    }

    public function test_voucher_creator_cannot_approve_their_own_voucher(): void
    {
        $secretary = User::factory()->create([
            'role' => 'setiausaha',
            'organization_position' => 'Setiausaha',
            'can_create_voucher' => true,
        ]);
        $this->signature($secretary);

        $this->actingAs($secretary)->post(route('vouchers.store'), $this->voucherPayload())->assertRedirect();
        $voucher = PaymentVoucher::query()->sole();
        $this->actingAs($secretary)->post(route('vouchers.submit', $voucher))->assertRedirect();
        $this->actingAs($secretary)->post(route('vouchers.approve', $voucher))->assertForbidden();

        $this->assertSame(PaymentVoucher::PENDING_APPROVAL, $voucher->fresh()->status);
        $this->assertNull($voucher->fresh()->approver_id);
    }

    public function test_only_admin_can_change_financial_creation_permissions(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $member = User::factory()->create(['role' => 'penderma']);

        $this->actingAs($admin)->get(route('users.index'))
            ->assertOk()
            ->assertSeeText('Kebenaran Kewangan')
            ->assertSee('name="can_create_receipt"', false)
            ->assertSee('name="can_create_voucher"', false);
        $this->actingAs($admin)->patch(route('users.update-role', $member), [
            'role' => 'ajk',
            'organization_position' => 'AJK Program',
            'can_create_receipt' => '1',
            'can_create_voucher' => '1',
        ])->assertRedirect();

        $member->refresh();
        $this->assertTrue($member->can_create_receipt);
        $this->assertTrue($member->can_create_voucher);

        $this->actingAs($member)->patch(route('users.update-role', $member), [
            'role' => 'ajk',
            'can_create_receipt' => '0',
            'can_create_voucher' => '0',
        ])->assertForbidden();
        $this->assertTrue($member->fresh()->can_create_receipt);
        $this->assertTrue($member->fresh()->can_create_voucher);
    }

    public function test_navigation_reflects_actual_creation_permissions(): void
    {
        $user = User::factory()->create(['role' => 'penderma']);

        $this->actingAs($user)->get(route('public.index'))
            ->assertOk()
            ->assertDontSee(route('receipts.create'), false)
            ->assertDontSee(route('vouchers.dashboard'), false);

        $user->update(['can_create_receipt' => true, 'can_create_voucher' => true]);

        $this->actingAs($user->fresh())->get(route('public.index'))
            ->assertOk()
            ->assertSee(route('receipts.create'), false)
            ->assertSee(route('vouchers.dashboard'), false);
        $this->actingAs($user->fresh())->get(route('vouchers.dashboard'))
            ->assertOk()
            ->assertSee(route('vouchers.create'), false);
    }

    private function receiptPayload(): array
    {
        return [
            'donor_name' => 'Penderma Kebenaran',
            'donor_email' => 'permission-receipt@example.test',
            'donor_phone' => '0191112233',
            'amount' => '50.00',
            'payment_method' => 'cash',
            'donated_at' => today()->toDateString(),
        ];
    }

    private function voucherPayload(): array
    {
        return [
            'voucher_date' => today()->toDateString(),
            'recipient_name' => 'Penerima Kebenaran',
            'recipient_phone' => '0199988877',
            'recipient_email' => 'permission-voucher@example.test',
            'purpose' => 'Ujian kebenaran cipta Baucar',
            'amount' => '125.50',
        ];
    }

    private function favourite(array $overrides = []): FavouriteContact
    {
        return FavouriteContact::query()->create(array_merge([
            'name' => 'Favourite Kebenaran',
            'phone' => '0123456789',
            'email' => 'favourite-permission@example.test',
            'bank_name' => 'Bank Ujian',
            'bank_account_no' => '1234567890',
            'is_donor_favourite' => false,
            'is_voucher_recipient_favourite' => false,
            'is_active' => true,
        ], $overrides));
    }

    private function signature(User $user): void
    {
        $path = "users/{$user->id}/signatures/permission.png";
        $contents = 'permission-signature';
        Storage::disk('local')->put($path, $contents);
        $user->forceFill([
            'digital_signature_path' => $path,
            'digital_signature_checksum' => hash('sha256', $contents),
            'digital_signature_uploaded_at' => now(),
        ])->save();
    }
}
