<?php

namespace Tests\Feature;

use App\Models\UpdateOperation;
use App\Models\User;
use App\Services\Updates\SmartUpdateManager;
use App\Services\Updates\UpdateCommandRunner;
use App\Services\Updates\VerifiedDatabaseBackup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
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
        config([
            'update-manager.enabled' => true,
            'update-manager.remote' => 'origin',
            'update-manager.branch' => 'main',
            'update-manager.minimum_free_bytes' => 1,
        ]);
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
        $this->expectException(RuntimeException::class);
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

    public function test_default_mysql_backup_arguments_are_safe_and_password_is_not_in_command(): void
    {
        $password = 'database-password-must-not-leak';
        $this->configureMysqlBackup(password: $password);

        $backup = app(VerifiedDatabaseBackup::class)->create('default-arguments');
        $command = $this->runner->mysqldumpCommand();

        $this->assertContains('--single-transaction', $command);
        $this->assertContains('--quick', $command);
        $this->assertContains('--skip-lock-tables', $command);
        $this->assertNotContains('--no-tablespaces', $command);
        $this->assertStringNotContainsString($password, implode(' ', $command));
        $this->assertTrue($backup['verified']);
    }

    public function test_no_tablespaces_argument_is_added_when_enabled(): void
    {
        $this->configureMysqlBackup(noTablespaces: true);

        app(VerifiedDatabaseBackup::class)->create('no-tablespaces-enabled');

        $this->assertContains('--no-tablespaces', $this->runner->mysqldumpCommand());
    }

    public function test_no_tablespaces_argument_is_omitted_when_disabled(): void
    {
        $this->configureMysqlBackup(noTablespaces: false);

        app(VerifiedDatabaseBackup::class)->create('no-tablespaces-disabled');

        $this->assertNotContains('--no-tablespaces', $this->runner->mysqldumpCommand());
    }

    public function test_mysql_backup_preflight_rejects_missing_or_non_executable_binary(): void
    {
        $this->configureMysqlBackup(binary: storage_path('missing-mysqldump'));

        $preflight = app(VerifiedDatabaseBackup::class)->preflight();

        $this->assertFalse($preflight['passed']);
        $this->assertFalse($preflight['binary']['exists']);
        $this->assertFalse($preflight['binary']['executable']);
        $this->expectException(RuntimeException::class);
        app(VerifiedDatabaseBackup::class)->create('missing-binary');
    }

    public function test_non_zero_mysqldump_exit_rejects_and_removes_partial_backup(): void
    {
        $password = 'non-zero-secret-password';
        $this->configureMysqlBackup(password: $password);
        $this->runner->mysqldumpExitCode = 2;
        $this->runner->mysqldumpOutput = "Access denied password={$password}";
        $this->runner->mysqldumpContents = $this->validMysqlDump();

        try {
            app(VerifiedDatabaseBackup::class)->create('non-zero-exit');
            $this->fail('A non-zero mysqldump exit must fail the backup.');
        } catch (RuntimeException $exception) {
            $this->assertStringNotContainsString($password, $exception->getMessage());
        }

        $this->assertFileDoesNotExist(Storage::disk('local')->path('update-backups/database-non-zero-exit.sql'));
    }

    public function test_zero_byte_mysql_backup_is_rejected(): void
    {
        $this->configureMysqlBackup();
        $this->runner->mysqldumpContents = '';

        $this->expectException(RuntimeException::class);
        app(VerifiedDatabaseBackup::class)->create('zero-byte');
    }

    public function test_invalid_or_incomplete_mysql_backup_is_rejected(): void
    {
        $this->configureMysqlBackup();
        $this->runner->mysqldumpContents = str_repeat('partial output without SQL completion marker ', 10);

        $this->expectException(RuntimeException::class);
        app(VerifiedDatabaseBackup::class)->create('invalid-dump');
    }

    public function test_valid_mysql_backup_is_accepted_and_checksum_is_generated(): void
    {
        $this->configureMysqlBackup();

        $backup = app(VerifiedDatabaseBackup::class)->create('valid-dump');

        $this->assertTrue($backup['verified']);
        $this->assertSame(64, strlen($backup['checksum']));
        $this->assertTrue(hash_equals(
            hash_file('sha256', Storage::disk('local')->path($backup['path'])),
            $backup['checksum'],
        ));
        $this->assertGreaterThan(100, Storage::disk('local')->size($backup['path']));
    }

    public function test_migration_preflight_records_backup_requirements_and_blocks_missing_binary(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $path = 'database/migrations/2026_10_28_170000_backup_probe.php';
        $this->runner->migrationContents[$path] = "Schema::create('backup_probe', function (Blueprint \$table) { \$table->id(); });";
        $backup = \Mockery::mock(VerifiedDatabaseBackup::class);
        $backup->shouldReceive('preflight')->once()->andReturn($this->backupPreflight(['binary_available' => false]));
        $this->app->instance(VerifiedDatabaseBackup::class, $backup);

        $operation = app(SmartUpdateManager::class)->preflight($this->operation($admin, 'checked', [
            'migrations' => [$path],
        ]));
        $checks = collect($operation->details['preflight'])->keyBy('name');

        $this->assertSame('preflight_failed', $operation->status);
        $this->assertFalse($checks['mysqldump_binary']['passed']);
        $this->assertTrue($checks->has('database_backup_destination'));
        $this->assertTrue($checks->has('database_backup_space'));
        $this->assertTrue($checks->has('database_backup_configuration'));
    }

    public function test_failed_database_backup_is_sanitized_in_history_and_blocks_installation(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $password = 'history-must-never-contain-this-password';
        $backup = \Mockery::mock(VerifiedDatabaseBackup::class);
        $backup->shouldReceive('create')->once()->andThrow(new RuntimeException("mysqldump password={$password}"));
        $this->app->instance(VerifiedDatabaseBackup::class, $backup);
        $manager = app(SmartUpdateManager::class);
        $operation = $this->operation($admin, 'preflight_passed', [
            'migrations' => ['database/migrations/approved.php'],
        ]);

        try {
            $manager->backup($operation);
            $this->fail('Failed database backup must stop the update.');
        } catch (RuntimeException $exception) {
            $this->assertStringNotContainsString($password, $exception->getMessage());
        }

        $operation->refresh();
        $this->assertSame('backup_failed', $operation->status);
        $this->assertStringNotContainsString($password, json_encode($operation->toArray(), JSON_THROW_ON_ERROR));

        try {
            $manager->install($operation, $admin);
            $this->fail('Installation must remain blocked after a failed database backup.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('backup', mb_strtolower($exception->getMessage()));
        }
        $this->assertFalse(collect($this->runner->commands)->contains(
            fn (string $command) => str_contains($command, 'artisan down') || str_contains($command, 'migrate --force'),
        ));
    }

    public function test_successful_database_backup_permits_installation_stage(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $path = 'update-backups/database-success.sql';
        Storage::disk('local')->put($path, $this->validMysqlDump());
        $checksum = hash_file('sha256', Storage::disk('local')->path($path));
        $backup = \Mockery::mock(VerifiedDatabaseBackup::class);
        $backup->shouldReceive('create')->once()->andReturn([
            'path' => $path,
            'checksum' => $checksum,
            'verified' => true,
        ]);
        $this->app->instance(VerifiedDatabaseBackup::class, $backup);
        $manager = app(SmartUpdateManager::class);
        $operation = $manager->backup($this->operation($admin, 'preflight_passed', [
            'migrations' => ['database/migrations/approved.php'],
        ]));

        $this->assertSame('backup_verified', $operation->status);
        $operation = $manager->install($operation, $admin);
        $this->assertSame('successful', $operation->status);
        $this->assertTrue(collect($this->runner->commands)->contains(
            fn (string $command) => str_contains($command, 'artisan migrate --force'),
        ));
    }

    public function test_email_log_and_favourite_contact_migrations_remain_detectable_in_update_preview(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $emailLog = 'database/migrations/2026_10_28_150000_create_email_logs_table.php';
        $favourites = 'database/migrations/2026_10_28_160000_extend_donors_for_favourite_contacts.php';
        $this->runner->diff = "A\t{$emailLog}\nA\t{$favourites}";

        $operation = app(SmartUpdateManager::class)->check($admin);

        $this->assertSame([$emailLog, $favourites], $operation->details['migrations']);
        $this->assertSame(2, $operation->changed_files_count);
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

    private function configureMysqlBackup(
        ?string $binary = null,
        ?bool $noTablespaces = null,
        string $password = 'safe-test-password',
    ): void {
        $backupConfig = [
            'update-manager.database_backup.enabled' => true,
            'update-manager.database_backup.connection' => 'backup_mysql',
            'update-manager.database_backup.mysqldump_binary' => $binary ?? PHP_BINARY,
            'database.connections.backup_mysql' => [
                'driver' => 'mysql',
                'host' => 'database.example.test',
                'port' => 3306,
                'database' => 'warga_cakna_test',
                'username' => 'backup_test_user',
                'password' => $password,
                'unix_socket' => '',
            ],
        ];
        if ($noTablespaces !== null) {
            $backupConfig['update-manager.database_backup.no_tablespaces'] = $noTablespaces;
        }

        config($backupConfig);
    }

    /** @param array{binary_available?: bool} $overrides */
    private function backupPreflight(array $overrides = []): array
    {
        $binaryAvailable = $overrides['binary_available'] ?? true;

        return [
            'passed' => $binaryAvailable,
            'driver' => 'mysql',
            'supported' => true,
            'enabled' => true,
            'binary' => [
                'required' => true,
                'path' => '/usr/bin/mysqldump',
                'exists' => $binaryAvailable,
                'executable' => $binaryAvailable,
                'available' => $binaryAvailable,
            ],
            'destination' => ['path' => 'update-backups', 'writable' => true],
            'storage' => ['free_bytes' => 1000, 'minimum_free_bytes' => 1, 'sufficient' => true],
            'configuration' => ['available' => true, 'missing' => []],
        ];
    }

    private function validMysqlDump(): string
    {
        return "-- MySQL dump 10.13  Distrib 8.0.46\n"
            ."SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT;\n"
            ."CREATE TABLE `backup_probe` (`id` bigint unsigned NOT NULL);\n"
            ."INSERT INTO `backup_probe` VALUES (1);\n"
            ."-- Dump completed on 2026-10-28 18:00:00\n";
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

    public int $mysqldumpExitCode = 0;

    public string $mysqldumpOutput = '';

    public ?string $mysqldumpContents = null;

    public array $migrationContents = [];

    public array $commands = [];

    public array $commandArrays = [];

    public function run(array $command, int $timeout = 120): array
    {
        $joined = implode(' ', $command);
        $this->commands[] = $joined;
        $this->commandArrays[] = $command;
        $ok = fn (string $output = '') => ['successful' => true, 'exit_code' => 0, 'output' => $output];
        $fail = fn (string $output) => ['successful' => false, 'exit_code' => 1, 'output' => $output];

        if (collect($command)->contains(fn ($part) => str_starts_with((string) $part, '--result-file='))) {
            $outputArg = collect($command)->first(fn ($part) => str_starts_with((string) $part, '--result-file='));
            $path = substr((string) $outputArg, 14);
            $contents = $this->mysqldumpContents ?? "-- MySQL dump 10.13  Distrib 8.0.46\n"
                ."SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT;\n"
                ."CREATE TABLE `backup_probe` (`id` bigint unsigned NOT NULL);\n"
                ."INSERT INTO `backup_probe` VALUES (1);\n"
                ."-- Dump completed on 2026-10-28 18:00:00\n";
            file_put_contents($path, $contents);

            return [
                'successful' => $this->mysqldumpExitCode === 0,
                'exit_code' => $this->mysqldumpExitCode,
                'output' => $this->mysqldumpOutput,
            ];
        }

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

    public function mysqldumpCommand(): array
    {
        return collect($this->commandArrays)
            ->first(fn (array $command) => collect($command)->contains(
                fn ($part) => str_starts_with((string) $part, '--result-file='),
            ), []);
    }
}
