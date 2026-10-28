<?php

namespace Tests\Feature;

use App\Exceptions\PotentialDuplicateFavouriteException;
use App\Models\DonationReceipt;
use App\Models\FavouriteContact;
use App\Models\PaymentVoucher;
use App\Models\User;
use App\Services\FavouriteContactService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class FavouriteContactTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_and_manage_encrypted_unified_favourite(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->post(route('admin.favourite-contacts.store'), [
            'name' => 'Nur Aisyah',
            'identity_document' => '900101-11-1234',
            'phone' => '0123456789',
            'email' => 'aisyah@example.test',
            'address' => 'Kuala Terengganu',
            'bank_name' => 'Bank Tempatan',
            'bank_account_no' => '123456789012',
            'is_donor_favourite' => '1',
            'is_voucher_recipient_favourite' => '1',
            'is_active' => '1',
        ]);

        $contact = FavouriteContact::query()->sole();
        $response->assertRedirect(route('admin.favourite-contacts.edit', $contact));
        $this->assertSame('900101-11-1234', $contact->identity_document);
        $this->assertSame('123456789012', $contact->bank_account_no);
        $raw = DB::table('donors')->where('id', $contact->id)->first();
        $this->assertNotSame('900101-11-1234', $raw->identity_document);
        $this->assertNotSame('123456789012', $raw->bank_account_no);
        $this->assertSame(64, strlen($raw->identity_lookup_hash));
        $this->assertTrue($contact->is_donor_favourite);
        $this->assertTrue($contact->is_voucher_recipient_favourite);
    }

    public function test_non_admin_cannot_manage_shared_favourites(): void
    {
        $ajk = User::factory()->create(['role' => 'ajk', 'can_create_voucher' => true]);

        $this->actingAs($ajk)->get(route('admin.favourite-contacts.index'))->assertForbidden();
        $this->actingAs($ajk)->post(route('admin.favourite-contacts.store'), [
            'name' => 'Tidak Dibenarkan',
            'is_donor_favourite' => '1',
        ])->assertForbidden();
    }

    public function test_donor_prefill_contains_only_supported_identity_and_contact_fields(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $contact = $this->contact([
            'address' => 'Alamat Favourite',
            'bank_name' => 'Bank Rahsia',
            'bank_account_no' => '8877665544',
            'is_donor_favourite' => true,
        ]);

        $this->actingAs($admin)
            ->getJson(route('admin.favourite-contacts.prefill', $contact).'?usage=donor')
            ->assertOk()
            ->assertJsonPath('data.name', 'Favourite UAT')
            ->assertJsonPath('data.identity_document', '901010-11-1010')
            ->assertJsonPath('data.address', 'Alamat Favourite')
            ->assertJsonMissingPath('data.bank_name')
            ->assertJsonMissingPath('data.bank_account_no')
            ->assertJsonMissingPath('data.amount')
            ->assertJsonMissingPath('data.payment_gateway')
            ->assertJsonMissingPath('data.payment_reference');
    }

    public function test_voucher_prefill_is_available_to_authorized_ajk_and_has_no_transaction_fields(): void
    {
        $ajk = User::factory()->create(['role' => 'ajk', 'can_create_voucher' => true]);
        $contact = $this->contact([
            'address' => 'Alamat Penerima',
            'bank_name' => 'Bank UAT',
            'bank_account_no' => '9988776655',
            'is_donor_favourite' => false,
            'is_voucher_recipient_favourite' => true,
        ]);

        $this->actingAs($ajk)
            ->getJson(route('admin.favourite-contacts.prefill', $contact).'?usage=voucher')
            ->assertOk()
            ->assertJsonPath('data.bank_name', 'Bank UAT')
            ->assertJsonPath('data.bank_account_no', '9988776655')
            ->assertJsonMissingPath('data.purpose')
            ->assertJsonMissingPath('data.amount')
            ->assertJsonMissingPath('data.voucher_no')
            ->assertJsonMissingPath('data.status');
    }

    public function test_inactive_favourite_is_not_offered_or_prefilled(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $contact = $this->contact(['is_active' => false, 'is_donor_favourite' => true]);

        $this->actingAs($admin)
            ->getJson(route('admin.favourite-contacts.search', ['usage' => 'donor']))
            ->assertOk()
            ->assertJsonCount(0, 'data');
        $this->actingAs($admin)
            ->getJson(route('admin.favourite-contacts.prefill', $contact).'?usage=donor')
            ->assertNotFound();
    }

    public function test_receipt_uses_edited_prefill_snapshot_without_mutating_favourite(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        $contact = $this->contact(['name' => 'Nama Template', 'is_donor_favourite' => true]);

        $response = $this->actingAs($admin)->post(route('receipts.store'), [
            'favourite_contact_id' => $contact->id,
            'donor_name' => 'Nama Transaksi Diubah',
            'donor_ic_passport' => 'IC-TRANSAKSI',
            'donor_phone' => '0111111111',
            'donor_email' => 'transaksi@example.test',
            'amount' => '75.50',
            'payment_method' => 'cash',
            'payment_reference' => 'CASH-UAT',
            'description' => 'Sumbangan semasa',
            'donated_at' => today()->toDateString(),
        ]);

        $response->assertRedirect(route('receipts.index'));
        $receipt = DonationReceipt::query()->sole();
        $this->assertSame('Nama Transaksi Diubah', $receipt->donor_name);
        $this->assertSame('75.50', $receipt->amount);
        $this->assertSame('Nama Template', $contact->fresh()->name);
        $this->assertSame('favourite@example.test', $contact->fresh()->email);
    }

    public function test_voucher_uses_edited_prefill_snapshot_without_mutating_favourite(): void
    {
        $ajk = User::factory()->create(['role' => 'ajk', 'can_create_voucher' => true]);
        $contact = $this->contact([
            'name' => 'Penerima Template',
            'is_donor_favourite' => false,
            'is_voucher_recipient_favourite' => true,
        ]);

        $response = $this->actingAs($ajk)->post(route('vouchers.store'), array_merge(
            $this->voucherPayload(),
            [
                'favourite_contact_id' => $contact->id,
                'recipient_name' => 'Penerima Transaksi Diubah',
                'amount' => '321.45',
            ],
        ));

        $voucher = PaymentVoucher::query()->sole();
        $response->assertRedirect(route('vouchers.show', $voucher));
        $this->assertSame('Penerima Transaksi Diubah', $voucher->recipient_name);
        $this->assertSame('321.45', $voucher->amount);
        $this->assertSame('Penerima Template', $contact->fresh()->name);
    }

    public function test_admin_must_explicitly_opt_in_to_save_transaction_contact_as_favourite(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('receipts.store'), [
            'donor_name' => 'Bukan Favourite Automatik',
            'donor_email' => 'ordinary@example.test',
            'amount' => '20.00',
            'payment_method' => 'cash',
            'donated_at' => today()->toDateString(),
        ])->assertRedirect(route('receipts.index'));
        $this->assertDatabaseCount('donors', 0);

        $this->actingAs($admin)->post(route('vouchers.store'), array_merge($this->voucherPayload(), [
            'recipient_name' => 'Favourite Eksplisit',
            'recipient_email' => 'explicit@example.test',
            'save_as_favourite' => '1',
        ]))->assertRedirect();

        $contact = FavouriteContact::query()->sole();
        $this->assertSame('Favourite Eksplisit', $contact->name);
        $this->assertFalse($contact->is_donor_favourite);
        $this->assertTrue($contact->is_voucher_recipient_favourite);
    }

    public function test_later_favourite_update_does_not_change_historical_receipt_or_voucher(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $contact = $this->contact([
            'is_donor_favourite' => true,
            'is_voucher_recipient_favourite' => true,
        ]);
        $receipt = DonationReceipt::query()->create([
            'user_id' => $admin->id,
            'receipt_no' => 'REC-SNAPSHOT-01',
            'donor_name' => 'Nama Resit Kekal',
            'amount' => 10,
            'payment_method' => 'cash',
            'status' => DonationReceipt::STATUS_PENDING,
        ]);
        $voucher = PaymentVoucher::query()->create(array_merge($this->voucherPayload(), [
            'voucher_no' => 'B-2026-1028-901',
            'status' => PaymentVoucher::DRAFT,
            'applicant_id' => $admin->id,
            'recipient_name' => 'Nama Baucar Kekal',
        ]));

        app(FavouriteContactService::class)->saveManaged([
            'name' => 'Nama Favourite Baharu',
            'identity_document' => 'BARU-123',
            'phone' => '0199999999',
            'email' => 'baharu@example.test',
            'address' => 'Alamat Baharu',
            'bank_name' => 'Bank Baharu',
            'bank_account_no' => '111122223333',
            'is_donor_favourite' => true,
            'is_voucher_recipient_favourite' => true,
            'is_active' => true,
        ], $admin, $contact);

        $this->assertSame('Nama Resit Kekal', $receipt->fresh()->donor_name);
        $this->assertSame('Nama Baucar Kekal', $voucher->fresh()->recipient_name);
    }

    public function test_strong_duplicate_reuses_existing_contact_and_partial_conflict_requires_confirmation(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $service = app(FavouriteContactService::class);
        $contact = $this->contact(['is_donor_favourite' => true]);

        $reused = $service->saveFromTransaction([
            'name' => 'Favourite UAT Dikemas Kini',
            'identity_document' => '901010-11-1010',
            'phone' => '0123456789',
            'email' => 'favourite@example.test',
        ], FavouriteContact::USAGE_DONOR, $admin);
        $this->assertSame($contact->id, $reused->id);
        $this->assertDatabaseCount('donors', 1);

        $this->expectException(PotentialDuplicateFavouriteException::class);
        $service->saveFromTransaction([
            'name' => 'Individu Berlainan',
            'phone' => '0177777777',
            'email' => 'favourite@example.test',
        ], FavouriteContact::USAGE_DONOR, $admin);
    }

    public function test_listing_masks_sensitive_values_and_forms_are_mobile_friendly(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->contact([
            'identity_document' => 'SECRET-IC-7788',
            'bank_account_no' => 'SECRET-ACCOUNT-8899',
            'is_donor_favourite' => true,
            'is_voucher_recipient_favourite' => true,
        ]);

        $this->actingAs($admin)->get(route('admin.favourite-contacts.index'))
            ->assertOk()
            ->assertDontSee('SECRET-IC-7788')
            ->assertDontSee('SECRET-ACCOUNT-8899')
            ->assertSee('md:hidden', false);
        $this->actingAs($admin)->get(route('receipts.create'))
            ->assertOk()
            ->assertSee('Favourite Pembayar / Penderma')
            ->assertSee('data-favourite-selector', false);
        $this->actingAs($admin)->get(route('vouchers.create'))
            ->assertOk()
            ->assertSee('Favourite Penerima')
            ->assertSee('Simpan / kemas kini sebagai Favourite organisasi');
    }

    public function test_non_admin_cannot_save_shared_favourite_from_voucher_flow(): void
    {
        $ajk = User::factory()->create(['role' => 'ajk', 'can_create_voucher' => true]);

        $this->actingAs($ajk)->post(route('vouchers.store'), array_merge($this->voucherPayload(), [
            'save_as_favourite' => '1',
        ]))->assertForbidden();
        $this->assertDatabaseCount('donors', 0);
        $this->assertDatabaseCount('payment_vouchers', 0);
    }

    public function test_donor_prefill_requires_receipt_permission_and_sensitive_values_are_not_in_search_results(): void
    {
        $ajk = User::factory()->create(['role' => 'ajk']);
        $admin = User::factory()->create(['role' => 'admin']);
        $contact = $this->contact([
            'identity_document' => 'PRIVATE-IDENTITY-1234',
            'bank_account_no' => 'PRIVATE-BANK-5678',
            'is_donor_favourite' => true,
        ]);

        $this->actingAs($ajk)
            ->getJson(route('admin.favourite-contacts.prefill', $contact).'?usage=donor')
            ->assertForbidden();

        $search = $this->actingAs($admin)
            ->getJson(route('admin.favourite-contacts.search', ['usage' => 'donor']))
            ->assertOk();
        $search->assertDontSee('PRIVATE-IDENTITY-1234');
        $search->assertDontSee('PRIVATE-BANK-5678');
        $search->assertJsonMissingPath('data.0.identity_document');
        $search->assertJsonMissingPath('data.0.bank_account_no');
    }

    private function contact(array $attributes = []): FavouriteContact
    {
        return FavouriteContact::query()->create(array_merge([
            'name' => 'Favourite UAT',
            'identity_document' => '901010-11-1010',
            'phone' => '0123456789',
            'email' => 'favourite@example.test',
            'is_donor_favourite' => true,
            'is_voucher_recipient_favourite' => false,
            'is_active' => true,
        ], $attributes));
    }

    private function voucherPayload(): array
    {
        return [
            'voucher_date' => today()->toDateString(),
            'recipient_name' => 'Penerima UAT',
            'recipient_phone' => '0112222333',
            'recipient_email' => 'penerima@example.test',
            'recipient_address' => 'Alamat Transaksi',
            'recipient_ic' => '880808-11-8080',
            'bank_name' => 'Bank Transaksi',
            'bank_account_no' => '123400009999',
            'purpose' => 'Tujuan transaksi khusus',
            'amount' => '100.00',
        ];
    }
}
