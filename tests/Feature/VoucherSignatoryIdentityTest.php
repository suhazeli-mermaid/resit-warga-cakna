<?php

namespace Tests\Feature;

use App\Models\PaymentVoucher;
use App\Models\Setting;
use App\Models\User;
use App\Services\PaymentVoucherWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VoucherSignatoryIdentityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_user_can_update_full_name_and_salutation_but_not_organization_position(): void
    {
        $user = User::factory()->create([
            'role' => 'ajk',
            'organization_position' => 'AJK Kebajikan',
        ]);

        $this->actingAs($user)->patch(route('profile.update'), [
            'name' => 'Nur Aisyah Abdullah',
            'salutation' => 'Dr',
            'email' => $user->email,
            'organization_position' => 'Pengerusi Tidak Sah',
        ])->assertRedirect(route('profile.edit'))
            ->assertSessionHas('status', 'profile-updated');

        $user->refresh();
        $this->assertSame('Nur Aisyah Abdullah', $user->name);
        $this->assertSame('Dr', $user->salutation);
        $this->assertSame('AJK Kebajikan', $user->organization_position);

        $this->actingAs($user)->patch(route('profile.update'), [
            'name' => 'Warga Cakna',
            'salutation' => 'Tiada',
            'email' => $user->email,
        ])->assertSessionHasErrors('name');

        $this->assertSame('Nur Aisyah Abdullah', $user->fresh()->name);
    }

    public function test_only_admin_can_maintain_user_organization_position(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $ajk = User::factory()->create(['role' => 'ajk']);

        $this->actingAs($ajk)->patch(route('users.update-role', $ajk), [
            'role' => 'ajk',
            'organization_position' => 'Setiausaha Tidak Sah',
        ])->assertForbidden();

        $this->actingAs($admin)->patch(route('users.update-role', $ajk), [
            'role' => 'ajk',
            'organization_position' => 'AJK Kebajikan',
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertSame('AJK Kebajikan', $ajk->fresh()->organization_position);
    }

    public function test_incomplete_applicant_identity_blocks_submission_without_server_error(): void
    {
        $applicant = $this->signedUser([
            'role' => 'ajk',
            'name' => 'Warga Cakna',
            'organization_position' => 'AJK Pemohon',
            'can_create_voucher' => true,
        ]);
        $voucher = $this->voucher($applicant);

        $this->actingAs($applicant)->get(route('vouchers.show', $voucher))
            ->assertOk()
            ->assertSeeText('Nama Penuh Pemohon: ⚠ Belum Lengkap')
            ->assertSeeText('Lengkapkan Profil Pengguna');

        $this->actingAs($applicant)->post(route('vouchers.submit', $voucher))
            ->assertRedirect()
            ->assertSessionHasErrors('applicant_identity');

        $this->assertSame(PaymentVoucher::DRAFT, $voucher->fresh()->status);
    }

    public function test_incomplete_approver_identity_blocks_approval_without_server_error(): void
    {
        $applicant = $this->signedUser([
            'role' => 'ajk',
            'name' => 'Siti Aminah Hassan',
            'organization_position' => 'AJK Kebajikan',
        ]);
        $approver = $this->signedUser([
            'role' => 'admin',
            'name' => 'Warga Cakna',
            'organization_position' => 'Pengerusi',
        ]);
        $voucher = app(PaymentVoucherWorkflow::class)->submit($this->voucher($applicant), $applicant);

        $this->actingAs($approver)->post(route('vouchers.approve', $voucher))
            ->assertRedirect()
            ->assertSessionHasErrors('approver_identity');

        $this->assertSame(PaymentVoucher::PENDING_APPROVAL, $voucher->fresh()->status);
    }

    public function test_profile_that_becomes_incomplete_blocks_issue_without_server_error(): void
    {
        $applicant = $this->signedUser([
            'role' => 'ajk',
            'name' => 'Siti Aminah Hassan',
            'organization_position' => 'AJK Kebajikan',
        ]);
        $approver = $this->signedUser([
            'role' => 'admin',
            'name' => 'Mohd Rahman Ali',
            'organization_position' => 'Pengerusi',
        ]);
        $workflow = app(PaymentVoucherWorkflow::class);
        $voucher = $workflow->approve($workflow->submit($this->voucher($applicant), $applicant), $approver);
        $approver->update(['organization_position' => null]);
        $this->officialStamp();

        $this->actingAs($approver)->post(route('vouchers.issue', $voucher))
            ->assertRedirect()
            ->assertSessionHasErrors('approver_position');

        $this->assertSame(PaymentVoucher::APPROVED, $voucher->fresh()->status);
        $this->assertNull($voucher->fresh()->document_snapshot);
    }

    public function test_issued_document_renders_snapshotted_title_name_position_and_never_live_profile(): void
    {
        $applicant = $this->signedUser([
            'role' => 'ajk',
            'name' => 'Nur Aisyah Abdullah',
            'salutation' => 'Dr',
            'organization_position' => 'AJK Kebajikan',
        ]);
        $approver = $this->signedUser([
            'role' => 'admin',
            'name' => 'Mohd Rahman Ali',
            'salutation' => 'Tiada',
            'organization_position' => 'Pengerusi',
        ]);
        $this->officialStamp();
        $workflow = app(PaymentVoucherWorkflow::class);
        $voucher = $workflow->issue(
            $workflow->approve($workflow->submit($this->voucher($applicant), $applicant), $approver),
            $approver,
        );

        $snapshot = $voucher->document_snapshot;
        $this->assertSame('Dr', $snapshot['applicant_salutation']);
        $this->assertSame('Nur Aisyah Abdullah', $snapshot['applicant_full_name']);
        $this->assertSame('AJK Kebajikan', $snapshot['applicant_organization_position']);
        $this->assertSame('Tiada', $snapshot['approver_salutation']);
        $this->assertSame('Mohd Rahman Ali', $snapshot['approver_full_name']);
        $this->assertSame('Pengerusi', $snapshot['approver_organization_position']);
        Storage::disk('local')->assertExists($voucher->applicant_signature_snapshot_path);
        Storage::disk('local')->assertExists($voucher->approver_signature_snapshot_path);

        $applicant->update([
            'name' => 'Nama Pemohon Baharu',
            'salutation' => 'Puan',
            'organization_position' => 'Jawatan Pemohon Baharu',
        ]);
        $approver->update([
            'name' => 'Nama Pelulus Baharu',
            'salutation' => 'Dato\'',
            'organization_position' => 'Jawatan Pelulus Baharu',
        ]);

        $this->actingAs($applicant)->get(route('vouchers.document', $voucher))
            ->assertOk()
            ->assertSeeText('Dr Nur Aisyah Abdullah')
            ->assertSeeText('AJK Kebajikan')
            ->assertSeeText('Mohd Rahman Ali')
            ->assertSeeText('Pengerusi')
            ->assertDontSeeText('Tiada Mohd Rahman Ali')
            ->assertDontSeeText('Nama Pemohon Baharu')
            ->assertDontSeeText('Nama Pelulus Baharu')
            ->assertDontSeeText('En/Pn');

        $this->actingAs($applicant)->get(route('vouchers.pdf', $voucher))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->assertSame($snapshot, $voucher->fresh()->document_snapshot);
    }

    private function signedUser(array $attributes): User
    {
        $user = User::factory()->create($attributes);
        $path = "users/{$user->id}/signatures/identity.png";
        Storage::disk('local')->put($path, $this->png());
        $user->update([
            'digital_signature_path' => $path,
            'digital_signature_checksum' => hash('sha256', $this->png()),
            'digital_signature_uploaded_at' => now(),
        ]);

        return $user->fresh();
    }

    private function officialStamp(): void
    {
        Storage::disk('local')->put('organization/stamps/current.png', $this->png());
        Setting::query()->updateOrCreate(
            ['key' => 'organization_official_stamp_path'],
            ['value' => 'organization/stamps/current.png'],
        );
    }

    private function voucher(User $applicant): PaymentVoucher
    {
        return PaymentVoucher::query()->create([
            'voucher_no' => 'B-2026-1028-900',
            'voucher_date' => '2026-10-28',
            'status' => PaymentVoucher::DRAFT,
            'applicant_id' => $applicant->id,
            'recipient_name' => 'Penerima UAT',
            'recipient_phone' => '0191234567',
            'recipient_email' => 'recipient@example.test',
            'purpose' => 'Ujian identiti penandatangan',
            'amount' => '250.00',
        ]);
    }

    private function png(): string
    {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
    }
}
