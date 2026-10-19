<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\DonationReceipt;
use App\Models\HomepageContent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_home_uses_dynamic_hero_campaigns_quick_actions_and_mobile_navigation(): void
    {
        HomepageContent::create([
            'section_key' => 'hero',
            'title' => 'Bantu komuniti dengan telus',
            'subtitle' => 'Warga Cakna',
            'body' => 'Kandungan sebenar daripada CMS.',
            'cta_text' => 'Lihat Program',
            'cta_link' => '#program-aktif',
            'is_published' => true,
        ]);
        Campaign::create([
            'title' => 'Dana Komuniti Ujian',
            'slug' => 'dana-komuniti-ujian',
            'description' => 'Kempen sebenar daripada pangkalan data.',
            'target_amount' => 1000,
            'collected_amount' => 250,
            'is_active' => true,
        ]);

        $this->get(route('public.index'))
            ->assertOk()
            ->assertSeeText('Bantu komuniti dengan telus')
            ->assertSeeText('Dana Komuniti Ujian')
            ->assertSeeText('Sumbang Sekarang')
            ->assertSeeText('Program Aktif')
            ->assertSeeText('Resit Saya')
            ->assertSeeText('Senarai Dana')
            ->assertSeeText('Akaun Saya')
            ->assertSee('env(safe-area-inset-bottom)', false)
            ->assertDontSeeText('RM48,750')
            ->assertDontSeeText('327 penyumbang');
    }

    public function test_receipt_list_has_separate_mobile_card_and_desktop_table_presentations(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        DonationReceipt::create([
            'receipt_no' => 'REC-MOBILE-1',
            'donor_name' => 'Penderma Mobile',
            'amount' => 50,
            'payment_method' => 'cash',
            'status' => DonationReceipt::STATUS_PENDING,
            'donated_at' => '2026-07-16',
        ]);

        $this->actingAs($admin)->get(route('receipts.index'))
            ->assertOk()
            ->assertSee('data-testid="mobile-receipt-list"', false)
            ->assertSee('md:hidden', false)
            ->assertSee('hidden overflow-x-auto md:block', false)
            ->assertSeeText('Tindakan');
    }

    public function test_only_content_managers_can_update_dynamic_homepage_hero(): void
    {
        $member = User::factory()->create(['role' => 'penderma']);
        $admin = User::factory()->create(['role' => 'admin']);
        $payload = [
            'title' => 'Hero yang disahkan',
            'subtitle' => 'Portal komuniti',
            'body' => 'Kandungan CMS.',
            'cta_text' => 'Sumbang',
            'cta_link' => '#program-aktif',
            'is_published' => '1',
        ];

        $this->actingAs($member)->put(route('admin.homepage.hero.update'), $payload)->assertForbidden();
        $this->actingAs($admin)->put(route('admin.homepage.hero.update'), $payload)->assertSessionHas('success');

        $this->assertDatabaseHas('homepage_contents', [
            'section_key' => 'hero',
            'title' => 'Hero yang disahkan',
            'is_published' => true,
        ]);
    }
}
