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

    public function test_admin_has_all_five_permissions_implicitly(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'can_create_receipt' => false,
            'can_create_voucher' => false,
            'can_approve_voucher' => false,
            'can_confirm_payment' => false,
            'can_manage_favourites' => false,
        ]);

        $this->assertTrue($admin->canCreateReceipt());
        $this->assertTrue($admin->canCreateVoucher());
        $this->assertTrue($admin->canApproveVoucher());
        $this->assertTrue($admin->canConfirmPayment());
        $this->assertTrue($admin->canManageFavourites());

        $this->actingAs($admin)->get(route('receipts.create'))->assertOk();
        $this->actingAs($admin)->post(route('receipts.store'), $this->receiptPayload())->assertRedirect(route('receipts.index'));
        $this->actingAs($admin)->get(route('vouchers.create'))->assertOk();
        $this->actingAs($admin)->post(route('vouchers.store'), $this->voucherPayload())->assertRedirect();
        $this->actingAs($admin)->get(route('admin.favourite-contacts.index'))->assertOk();

        $this->assertDatabaseCount('donation_receipts', 1);
        $this->assertDatabaseCount('payment_vouchers', 1);
    }

    public function test_financial_permissions_default_false_and_are_not_derived_from_roles(): void
    {
        foreach (['penderma', 'ajk', 'setiausaha', 'bendahari'] as $role) {
            $user = User::factory()->create(['role' => $role])->fresh();

            $this->assertFalse($user->can_create_receipt, $role);
            $this->assertFalse($user->can_create_voucher, $role);
            $this->assertFalse($user->can_approve_voucher, $role);
            $this->assertFalse($user->can_confirm_payment, $role);
            $this->assertFalse($user->can_manage_favourites, $role);
            $this->assertFalse($user->canCreateReceipt(), $role);
            $this->assertFalse($user->canCreateVoucher(), $role);
            $this->assertFalse($user->canApproveVoucher(), $role);
            $this->assertFalse($user->canConfirmPayment(), $role);
            $this->assertFalse($user->canManageFavourites(), $role);
        }
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
        $this->actingAs($user)->post(route('receipts.store'), array_merge($this->receiptPayload(), [
            'save_as_favourite' => '1',
        ]))->assertForbidden();

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
        $this->actingAs($user)->post(route('vouchers.store'), array_merge($this->voucherPayload(), [
            'save_as_favourite' => '1',
        ]))->assertForbidden();

        $voucher = PaymentVoucher::query()->sole();
        $this->assertSame($user->id, $voucher->applicant_id);
        $this->actingAs($user)->post(route('vouchers.submit', $voucher))
            ->assertRedirect()
            ->assertSessionHas('success');
        $this->assertSame(PaymentVoucher::PENDING_APPROVAL, $voucher->fresh()->status);
        $this->actingAs($user)->get(route('admin.favourite-contacts.index'))->assertForbidden();
    }

    public function test_approve_permission_works_independently_for_another_users_voucher(): void
    {
        $applicant = User::factory()->create(['role' => 'penderma']);
        $approver = User::factory()->create([
            'role' => 'penderma',
            'organization_position' => 'Ahli Jawatankuasa Kelulusan',
            'can_approve_voucher' => true,
        ]);
        $this->signature($approver);
        $voucher = $this->voucherWithStatus($applicant, PaymentVoucher::PENDING_APPROVAL);

        $this->actingAs($approver)->get(route('vouchers.show', $voucher))
            ->assertOk()
            ->assertSeeText('Luluskan')
            ->assertSeeText('Tolak')
            ->assertDontSeeText('Sahkan Bayaran');
        $this->actingAs($approver)->post(route('vouchers.approve', $voucher))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(PaymentVoucher::APPROVED, $voucher->fresh()->status);
        $this->assertSame($approver->id, $voucher->fresh()->approver_id);
        $this->actingAs($approver)->get(route('vouchers.create'))->assertForbidden();
        $this->actingAs($approver)->get(route('admin.favourite-contacts.index'))->assertForbidden();
    }

    public function test_voucher_creator_cannot_approve_or_reject_their_own_voucher(): void
    {
        $secretary = User::factory()->create([
            'role' => 'setiausaha',
            'organization_position' => 'Setiausaha',
            'can_create_voucher' => true,
            'can_approve_voucher' => true,
        ]);
        $this->signature($secretary);

        $this->actingAs($secretary)->post(route('vouchers.store'), $this->voucherPayload())->assertRedirect();
        $voucher = PaymentVoucher::query()->sole();
        $this->actingAs($secretary)->post(route('vouchers.submit', $voucher))->assertRedirect();
        $this->actingAs($secretary)->post(route('vouchers.approve', $voucher))->assertForbidden();
        $this->actingAs($secretary)->post(route('vouchers.reject', $voucher), [
            'reason' => 'Tidak boleh menolak Baucar sendiri',
        ])->assertForbidden();

        $this->assertSame(PaymentVoucher::PENDING_APPROVAL, $voucher->fresh()->status);
        $this->assertNull($voucher->fresh()->approver_id);
    }

    public function test_confirm_payment_permission_works_independently(): void
    {
        $applicant = User::factory()->create(['role' => 'penderma']);
        $confirmer = User::factory()->create([
            'role' => 'penderma',
            'can_confirm_payment' => true,
        ]);
        $voucher = $this->voucherWithStatus($applicant, PaymentVoucher::ISSUED);
        $pending = $this->voucherWithStatus($applicant, PaymentVoucher::PENDING_APPROVAL);

        $this->actingAs($confirmer)->get(route('vouchers.show', $voucher))
            ->assertOk()
            ->assertSeeText('Sahkan Bayaran')
            ->assertDontSeeText('Luluskan');
        $this->actingAs($confirmer)->post(route('vouchers.confirm-payment', $voucher), [
            'paid_amount' => '125.50',
            'transaction_date' => today()->toDateString(),
            'transaction_reference' => 'PERMISSION-TXN-001',
        ])->assertRedirect();

        $voucher->refresh();
        $this->assertSame(PaymentVoucher::AWAITING_ACKNOWLEDGEMENT, $voucher->status);
        $this->assertSame($confirmer->id, $voucher->payment_confirmed_by);
        $this->assertDatabaseCount('voucher_acknowledgements', 1);
        $this->actingAs($confirmer)->post(route('vouchers.approve', $pending))->assertForbidden();
        $this->actingAs($confirmer)->get(route('vouchers.create'))->assertForbidden();
        $this->actingAs($confirmer)->get(route('admin.favourite-contacts.index'))->assertForbidden();
    }

    public function test_manage_favourites_permission_works_independently(): void
    {
        $manager = User::factory()->create([
            'role' => 'penderma',
            'can_manage_favourites' => true,
        ]);

        $this->actingAs($manager)->get(route('admin.favourite-contacts.index'))
            ->assertOk()
            ->assertSeeText('+ Favourite Baharu');
        $this->actingAs($manager)->post(route('admin.favourite-contacts.store'), [
            'name' => 'Favourite Diurus Pengguna',
            'phone' => '0112233445',
            'email' => 'managed-favourite@example.test',
            'is_donor_favourite' => '1',
            'is_voucher_recipient_favourite' => '0',
            'is_active' => '1',
        ])->assertRedirect();

        $contact = FavouriteContact::query()->sole();
        $this->actingAs($manager)->put(route('admin.favourite-contacts.update', $contact), [
            'name' => 'Favourite Kedua-duanya',
            'phone' => '0112233445',
            'email' => 'managed-favourite@example.test',
            'is_donor_favourite' => '1',
            'is_voucher_recipient_favourite' => '1',
            'is_active' => '1',
        ])->assertRedirect();
        $this->actingAs($manager)->patch(route('admin.favourite-contacts.toggle', $contact), [
            'is_active' => '0',
        ])->assertRedirect();

        $contact->refresh();
        $this->assertTrue($contact->is_donor_favourite);
        $this->assertTrue($contact->is_voucher_recipient_favourite);
        $this->assertFalse($contact->is_active);
        $this->actingAs($manager)->get(route('receipts.create'))->assertForbidden();
        $this->actingAs($manager)->get(route('vouchers.create'))->assertForbidden();
    }

    public function test_only_admin_can_grant_and_revoke_all_financial_permissions(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $member = User::factory()->create(['role' => 'penderma']);

        $this->actingAs($admin)->get(route('users.index'))
            ->assertOk()
            ->assertSeeText('Kebenaran Kewangan')
            ->assertSee('name="can_create_receipt"', false)
            ->assertSee('name="can_create_voucher"', false)
            ->assertSee('name="can_approve_voucher"', false)
            ->assertSee('name="can_confirm_payment"', false)
            ->assertSee('name="can_manage_favourites"', false);
        $this->actingAs($admin)->patch(route('users.update-role', $member), [
            'role' => 'ajk',
            'organization_position' => 'AJK Program',
            'can_create_receipt' => '1',
            'can_create_voucher' => '1',
            'can_approve_voucher' => '1',
            'can_confirm_payment' => '1',
            'can_manage_favourites' => '1',
        ])->assertRedirect();

        $member->refresh();
        $this->assertTrue($member->can_create_receipt);
        $this->assertTrue($member->can_create_voucher);
        $this->assertTrue($member->can_approve_voucher);
        $this->assertTrue($member->can_confirm_payment);
        $this->assertTrue($member->can_manage_favourites);

        $this->actingAs($member)->patch(route('users.update-role', $member), [
            'role' => 'ajk',
            'can_create_receipt' => '0',
            'can_create_voucher' => '0',
            'can_approve_voucher' => '0',
            'can_confirm_payment' => '0',
            'can_manage_favourites' => '0',
        ])->assertForbidden();
        $this->assertTrue($member->fresh()->can_create_receipt);
        $this->assertTrue($member->fresh()->can_create_voucher);
        $this->assertTrue($member->fresh()->can_approve_voucher);
        $this->assertTrue($member->fresh()->can_confirm_payment);
        $this->assertTrue($member->fresh()->can_manage_favourites);

        $this->actingAs($admin)->patch(route('users.update-role', $member), [
            'role' => 'ajk',
            'organization_position' => 'AJK Program',
        ])->assertRedirect();
        $member->refresh();
        $this->assertFalse($member->can_create_receipt);
        $this->assertFalse($member->can_create_voucher);
        $this->assertFalse($member->can_approve_voucher);
        $this->assertFalse($member->can_confirm_payment);
        $this->assertFalse($member->can_manage_favourites);
        $this->actingAs($member)->get(route('receipts.create'))->assertForbidden();
        $this->actingAs($member)->get(route('admin.favourite-contacts.index'))->assertForbidden();
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

        $manager = User::factory()->create(['role' => 'penderma', 'can_manage_favourites' => true]);
        $this->actingAs($manager)->get(route('public.index'))
            ->assertOk()
            ->assertSee(route('admin.favourite-contacts.index'), false)
            ->assertDontSee(route('receipts.create'), false)
            ->assertDontSee(route('vouchers.dashboard'), false);

        $approver = User::factory()->create(['role' => 'penderma', 'can_approve_voucher' => true]);
        $this->actingAs($approver)->get(route('public.index'))
            ->assertOk()
            ->assertSee(route('vouchers.dashboard'), false)
            ->assertDontSee(route('vouchers.create'), false);
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

    private function voucherWithStatus(User $applicant, string $status): PaymentVoucher
    {
        return PaymentVoucher::query()->create([
            'voucher_no' => 'B-PERM-'.strtoupper(substr(uniqid(), -8)),
            'voucher_date' => today(),
            'status' => $status,
            'applicant_id' => $applicant->id,
            'recipient_name' => 'Penerima Kebenaran',
            'recipient_phone' => '0199988877',
            'recipient_email' => 'permission-voucher@example.test',
            'purpose' => 'Ujian kebenaran tindakan Baucar',
            'amount' => '125.50',
            'submitted_at' => $status === PaymentVoucher::PENDING_APPROVAL ? now() : null,
            'issued_at' => $status === PaymentVoucher::ISSUED ? now() : null,
        ]);
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
