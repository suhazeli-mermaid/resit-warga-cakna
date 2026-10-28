<?php

namespace Tests\Feature;

use App\Models\UpdateOperation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class SmartUpdateManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admin_can_view_historical_update_records(): void
    {
        $member = User::factory()->create(['role' => 'ajk']);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->get(route('admin.updates.index'))->assertRedirect(route('login'));
        $this->actingAs($member)->get(route('admin.updates.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.updates.index'))->assertOk();
    }

    public function test_update_manager_is_inactive_and_has_no_deployment_actions(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get(route('admin.updates.index'))
            ->assertOk()
            ->assertSeeText('Smart Update Manager — Tidak Aktif')
            ->assertSeeText('Deployment automatik tidak digunakan')
            ->assertDontSeeText('bash deploy.sh')
            ->assertDontSeeText('Check Update')
            ->assertDontSeeText('Install')
            ->assertDontSeeText('Cipta & Sahkan Backup');

        $this->assertFalse((bool) config('update-manager.enabled'));
        $this->assertFalse(Route::has('admin.updates.check'));
        $this->assertFalse(Route::has('admin.updates.preflight'));
        $this->assertFalse(Route::has('admin.updates.backup'));
        $this->assertFalse(Route::has('admin.updates.install'));
    }

    public function test_system_navigation_leads_to_email_log_independently(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get(route('admin.updates.index'))
            ->assertOk()
            ->assertSee(route('admin.email-logs.index'), false)
            ->assertSeeText('Log E-mel');
    }

    public function test_legacy_update_operation_remains_available_as_read_only_history(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $operation = UpdateOperation::create([
            'public_id' => (string) Str::uuid(),
            'initiated_by' => $admin->id,
            'status' => 'successful',
            'current_commit' => str_repeat('1', 40),
            'target_commit' => str_repeat('2', 40),
            'target_message' => 'Historical update',
            'details' => [
                'commits' => ['2222222 Historical update'],
                'migrations' => ['database/migrations/historical.php'],
                'backup' => ['code' => ['verified' => true, 'checksum' => str_repeat('a', 64)]],
            ],
        ]);

        $this->actingAs($admin)->get(route('admin.updates.show', $operation))
            ->assertOk()
            ->assertSeeText('Rekod audit baca sahaja')
            ->assertSeeText('Historical update')
            ->assertSeeText('database/migrations/historical.php')
            ->assertDontSeeText('Install')
            ->assertDontSeeText('bash deploy.sh');
    }
}
