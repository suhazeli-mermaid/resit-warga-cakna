<?php

namespace Tests\Feature;

use App\Models\UpdateOperation;
use App\Models\User;
use App\Services\Updates\RepositoryState;
use App\Services\Updates\ShellDeploymentHistory;
use App\Services\Updates\UpdateRuntimeMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class SmartUpdateManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config([
            'update-manager.enabled' => true,
            'update-manager.shell_private_path' => Storage::disk('local')->path(''),
            'update-manager.shell_history_path' => Storage::disk('local')->path('update-deployments/history'),
            'update-manager.shell_runtime_path' => Storage::disk('local')->path('update-deployments/runtime'),
        ]);
    }

    public function test_only_admin_can_view_update_monitoring(): void
    {
        $member = User::factory()->create(['role' => 'ajk']);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->get(route('admin.updates.index'))->assertRedirect(route('login'));
        $this->actingAs($member)->get(route('admin.updates.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.updates.index'))->assertOk();
    }

    public function test_unavailable_process_functions_are_detected_without_executing_them(): void
    {
        $availability = array_fill_keys([
            'proc_open', 'exec', 'shell_exec', 'system', 'passthru', 'popen',
        ], true);
        $availability['proc_open'] = false;

        $status = app(UpdateRuntimeMode::class)->status($availability, 'exec, shell_exec');

        $this->assertSame('terminal', $status['mode']);
        $this->assertTrue($status['safe_mode']);
        $this->assertFalse($status['php_process_execution']);
        $this->assertSame('php_process_functions_unavailable', $status['reason']);
        $this->assertContains('proc_open', $status['unavailable_functions']);
        $this->assertContains('exec', $status['unavailable_functions']);
        $this->assertContains('shell_exec', $status['unavailable_functions']);
    }

    public function test_update_ui_is_terminal_only_and_has_no_web_install_actions(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get(route('admin.updates.index'));

        $response->assertOk()
            ->assertSeeText('cPanel Safe Mode / Terminal Deployment')
            ->assertSeeText('bash deploy.sh')
            ->assertSeeText('bash deploy.sh --self-check')
            ->assertDontSeeText('Check Update')
            ->assertDontSeeText('Confirm & Install');
        $this->assertFalse(Route::has('admin.updates.check'));
        $this->assertFalse(Route::has('admin.updates.preflight'));
        $this->assertFalse(Route::has('admin.updates.backup'));
        $this->assertFalse(Route::has('admin.updates.install'));
    }

    public function test_repository_state_reads_git_metadata_without_running_git(): void
    {
        $repository = Storage::disk('local')->path('repository');
        Storage::disk('local')->put('repository/.git/HEAD', "ref: refs/heads/main\n");
        Storage::disk('local')->put('repository/.git/refs/heads/main', str_repeat('a', 40)."\n");
        Storage::disk('local')->put('repository/.git/refs/remotes/origin/main', str_repeat('b', 40)."\n");
        config(['update-manager.repository_path' => $repository]);

        $state = app(RepositoryState::class)->inspect();

        $this->assertSame('main', $state['branch']);
        $this->assertSame(str_repeat('a', 40), $state['current_commit']);
        $this->assertSame(str_repeat('b', 40), $state['origin_main_commit']);
    }

    public function test_shell_deployment_history_is_displayed_with_safe_backup_metadata(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $deploymentId = '20261028T210000Z-12345';
        $commit = str_repeat('a', 40);
        $checksum = str_repeat('b', 64);
        Storage::disk('local')->put('update-deployments/history/'.$deploymentId.'.json', json_encode([
            'schema' => 1,
            'deployment_id' => $deploymentId,
            'mode' => 'deploy',
            'status' => 'successful',
            'started_at' => '2026-10-28T21:00:00Z',
            'finished_at' => '2026-10-28T21:01:00Z',
            'current_commit' => str_repeat('c', 40),
            'target_commit' => $commit,
            'installed_commit' => $commit,
            'code_backup_path' => '/home3/wargacakna/deployment-backups/code.tar.gz',
            'code_backup_checksum' => $checksum,
            'database_backup_path' => '/home3/wargacakna/deployment-backups/database.sql',
            'database_backup_checksum' => str_repeat('d', 64),
            'maintenance_entered' => false,
            'failure_code' => '',
        ], JSON_THROW_ON_ERROR));

        $this->actingAs($admin)->get(route('admin.updates.index'))
            ->assertOk()
            ->assertSeeText('SUCCESSFUL')
            ->assertSeeText($commit)
            ->assertSeeText($checksum)
            ->assertSeeText('database.sql');
    }

    public function test_invalid_or_oversized_shell_history_is_ignored(): void
    {
        Storage::disk('local')->put('update-deployments/history/not-valid.json', '{invalid');

        $this->assertSame([], app(ShellDeploymentHistory::class)->latest());
    }

    public function test_legacy_update_history_remains_read_only(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $operation = UpdateOperation::create([
            'public_id' => (string) Str::uuid(),
            'initiated_by' => $admin->id,
            'status' => 'backup_verified',
            'current_commit' => str_repeat('1', 40),
            'target_commit' => str_repeat('2', 40),
            'target_message' => 'Legacy update',
            'details' => ['commits' => [], 'migrations' => [], 'backup' => ['code' => ['verified' => true]]],
        ]);

        $this->actingAs($admin)->get(route('admin.updates.show', $operation))
            ->assertOk()
            ->assertSeeText('Rekod audit sahaja')
            ->assertSeeText('bash deploy.sh')
            ->assertDontSeeText('Confirm & Install')
            ->assertDontSeeText('Cipta & Sahkan Backup');
    }

    public function test_database_option_command_writes_private_files_without_printing_password(): void
    {
        $password = 'password-that-must-never-be-printed';
        config([
            'update-manager.database_backup.enabled' => true,
            'update-manager.database_backup.connection' => 'deployment_mysql',
            'database.connections.deployment_mysql' => [
                'driver' => 'mysql',
                'host' => 'database.example.test',
                'port' => 3306,
                'database' => 'warga_cakna',
                'username' => 'deployment_user',
                'password' => $password,
                'unix_socket' => '',
            ],
        ]);
        $deployment = '20261028T220000Z-67890';

        $exitCode = Artisan::call('updates:write-database-options', [
            'deployment' => $deployment,
            '--no-interaction' => true,
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertStringNotContainsString($password, Artisan::output());
        $optionFile = Storage::disk('local')->path('update-deployments/runtime/'.$deployment.'/mysql.cnf');
        $databaseFile = Storage::disk('local')->path('update-deployments/runtime/'.$deployment.'/database-name');
        $this->assertFileExists($optionFile);
        $this->assertFileExists($databaseFile);
        $this->assertStringContainsString('password="'.$password.'"', (string) file_get_contents($optionFile));
        $this->assertSame("warga_cakna\n", file_get_contents($databaseFile));
    }

    public function test_post_deployment_check_runs_without_a_process_runner(): void
    {
        $this->artisan('updates:post-deploy-check')
            ->expectsOutputToContain('Post-deployment checks passed.')
            ->assertSuccessful();
    }

    public function test_php_application_has_no_symfony_process_runner(): void
    {
        $this->assertFileDoesNotExist(app_path('Services/Updates/NativeUpdateCommandRunner.php'));
        $this->assertFileDoesNotExist(app_path('Services/Updates/UpdateCommandRunner.php'));

        $provider = (string) file_get_contents(app_path('Providers/AppServiceProvider.php'));
        $this->assertStringNotContainsString('Symfony\\Component\\Process', $provider);
        $this->assertStringNotContainsString('UpdateCommandRunner', $provider);
    }

    public function test_deploy_script_contains_required_controls_and_no_destructive_git_commands(): void
    {
        $script = (string) file_get_contents(base_path('deploy.sh'));

        foreach ([
            '--single-transaction', '--quick', '--skip-lock-tables', '--no-tablespaces',
            'merge --ff-only', 'merge-base --is-ancestor', 'updates:post-deploy-check',
            '--dry-run', '--self-check', '--others --ignored --exclude-standard',
            "GIT_CONFIG_KEY_0='core.hooksPath'", 'auth.json|.gitmodules', 'trap on_exit EXIT',
        ] as $required) {
            $this->assertStringContainsString($required, $script);
        }
        foreach (['git reset', 'git pull', 'push --force', 'source .env', 'password=', 'eval ', 'curl ', 'wget '] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $script);
        }
    }
}
