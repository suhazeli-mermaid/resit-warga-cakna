<?php

namespace Tests\Feature;

use App\Models\UpdateOperation;
use App\Models\User;
use App\Services\Updates\SmartUpdateManager;
use App\Services\Updates\UpdateCommandRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;
use ZipArchive;

class SmartUpdateManagerTest extends TestCase
{
    use RefreshDatabase;

    private FakeUpdateRunner $runner;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config(['update-manager.enabled' => true, 'update-manager.remote' => 'origin', 'update-manager.branch' => 'main']);
        $this->runner = new FakeUpdateRunner;
        $this->app->instance(UpdateCommandRunner::class, $this->runner);
    }

    public function test_only_admin_can_access_or_run_update_manager(): void
    {
        $member = User::factory()->create(['role' => 'ajk']);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->get(route('admin.updates.index'))->assertRedirect(route('login'));
        $this->actingAs($member)->get(route('admin.updates.index'))->assertForbidden();
        $this->actingAs($member)->post(route('admin.updates.check'))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.updates.index'))->assertOk();
    }

    public function test_check_records_no_update_without_installing(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->runner->target = $this->runner->current;
        $operation = app(SmartUpdateManager::class)->check($admin);

        $this->assertSame('no_update', $operation->status);
        $this->assertFalse($operation->details['update_available']);
        $this->assertFalse($this->runner->installed);
    }

    public function test_preview_classifies_files_migrations_and_protected_warnings(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->runner->diff = "A\tdatabase/migrations/2026_10_28_000000_safe.php\nM\t.env\nD\told.txt";
        $operation = app(SmartUpdateManager::class)->check($admin);

        $this->assertSame(['added', 'modified', 'deleted'], array_column($operation->details['files'], 'status'));
        $this->assertCount(1, $operation->details['migrations']);
        $this->assertSame('.env', $operation->details['protected_files'][0]['path']);
    }

    public function test_dirty_git_state_fails_preflight_and_blocks_backup(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $operation = $this->operation($admin, 'checked');
        $this->runner->dirty = ' M app/Example.php';
        $operation = app(SmartUpdateManager::class)->preflight($operation);

        $this->assertSame('preflight_failed', $operation->status);
        $this->assertNull($operation->preflight_passed_at);
        $this->expectException(\RuntimeException::class);
        app(SmartUpdateManager::class)->backup($operation);
    }

    public function test_pending_migration_and_schema_risk_are_reported(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $path = 'database/migrations/2026_10_28_000000_risky.php';
        $this->runner->migrationContents[$path] = "Schema::table('users', function (Blueprint \$table) { \$table->string('oversized')->unique(); });";
        $operation = $this->operation($admin, 'checked', ['migrations' => [$path], 'files' => [], 'protected_files' => []]);
        $operation = app(SmartUpdateManager::class)->preflight($operation);

        $this->assertSame('preflight_failed', $operation->status);
        $this->assertStringContainsString('1071', $operation->details['schema_inspection']['risks'][0]['risk']);
    }

    public function test_simulated_fast_forward_update_verifies_and_records_success(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $manager = app(SmartUpdateManager::class);
        $operation = $manager->preflight($this->operation($admin, 'checked'));
        $this->assertSame('preflight_passed', $operation->status);
        $operation = $manager->backup($operation);
        $this->assertSame('backup_verified', $operation->status);
        $operation = $manager->install($operation, $admin);

        $this->assertSame('successful', $operation->status);
        $this->assertTrue($operation->details['verification']['passed']);
        $this->assertNotNull($operation->finished_at);
    }

    public function test_partial_migration_failure_can_resume_without_rollback(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $manager = app(SmartUpdateManager::class);
        $operation = $manager->backup($manager->preflight($this->operation($admin, 'checked')));
        $this->runner->failMigration = true;
        $operation = $manager->install($operation, $admin);
        $this->assertSame('recovery_required', $operation->status);
        $this->assertStringContainsString('migration failed', $operation->failure_message);

        $this->runner->failMigration = false;
        $operation = $manager->install($operation, $admin);
        $this->assertSame('successful', $operation->status);
        $this->assertFalse(collect($this->runner->commands)->contains(fn ($command) => str_contains($command, 'migrate:rollback')));
    }

    public function test_update_history_is_visible_without_exposing_advanced_logs_by_default(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $operation = $this->operation($admin, 'checked');

        $this->actingAs($admin)->get(route('admin.updates.index'))->assertOk()->assertSee($operation->public_id);
        $this->actingAs($admin)->get(route('admin.updates.show', $operation))->assertOk()->assertSeeText('Preview');
    }

    private function operation(User $admin, string $status, array $details = []): UpdateOperation
    {
        return UpdateOperation::create([
            'public_id' => (string) Str::uuid(), 'initiated_by' => $admin->id,
            'status' => $status, 'current_commit' => $this->runner->current,
            'target_commit' => $this->runner->target, 'target_message' => 'Approved update',
            'details' => array_merge(['files' => [], 'migrations' => [], 'protected_files' => [], 'update_available' => true], $details),
        ]);
    }
}

class FakeUpdateRunner implements UpdateCommandRunner
{
    public string $current = '1111111111111111111111111111111111111111';

    public string $target = '2222222222222222222222222222222222222222';

    public string $diff = '';

    public string $dirty = '';

    public bool $installed = false;

    public bool $failMigration = false;

    public array $migrationContents = [];

    public array $commands = [];

    public function run(array $command, int $timeout = 120): array
    {
        $joined = implode(' ', $command);
        $this->commands[] = $joined;
        $ok = fn (string $output = '') => ['successful' => true, 'exit_code' => 0, 'output' => $output];
        $fail = fn (string $output) => ['successful' => false, 'exit_code' => 1, 'output' => $output];

        if ($joined === 'git remote get-url origin') {
            return $ok('https://github.com/example/warga-cakna.git');
        }
        if ($joined === 'git rev-parse HEAD') {
            return $ok($this->installed ? $this->target : $this->current);
        }
        if ($joined === 'git rev-parse FETCH_HEAD') {
            return $ok($this->target);
        }
        if (str_starts_with($joined, 'git fetch ')) {
            return $ok();
        }
        if (str_starts_with($joined, 'git show -s ')) {
            return $ok('Approved update');
        }
        if (str_starts_with($joined, 'git diff ')) {
            return $ok($this->diff);
        }
        if (str_starts_with($joined, 'git log ')) {
            return $ok('2222222 Admin 2026-10-28T00:00:00+08:00 Approved update');
        }
        if ($joined === 'git status --porcelain --untracked-files=normal') {
            return $ok($this->dirty);
        }
        if (str_starts_with($joined, 'git merge-base ')) {
            return $ok();
        }
        if (str_starts_with($joined, 'composer check-platform-reqs')) {
            return $ok('All platform requirements satisfied.');
        }
        if (str_starts_with($joined, 'git show ') && str_contains($joined, ':database/migrations/')) {
            $path = substr($joined, strpos($joined, ':database/migrations/') + 1);

            return isset($this->migrationContents[$path]) ? $ok($this->migrationContents[$path]) : $fail('missing migration');
        }
        if (str_starts_with($joined, 'git archive ')) {
            $outputArg = collect($command)->first(fn ($part) => str_starts_with((string) $part, '--output='));
            $path = substr((string) $outputArg, 9);
            $zip = new ZipArchive;
            $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
            $zip->addFromString('README.md', 'backup');
            $zip->close();

            return $ok();
        }
        if (str_starts_with($joined, 'git merge --ff-only ')) {
            $this->installed = true;

            return $ok('Fast-forward');
        }
        if (str_contains($joined, 'artisan migrate --force') && $this->failMigration) {
            return $fail('migration failed');
        }
        if (str_contains($joined, 'artisan ')) {
            return $ok('ok');
        }

        return $ok();
    }
}
