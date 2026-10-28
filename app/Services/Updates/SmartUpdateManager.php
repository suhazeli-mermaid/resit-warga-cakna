<?php

namespace App\Services\Updates;

use App\Models\UpdateOperation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
use ZipArchive;

class SmartUpdateManager
{
    public function __construct(
        private readonly UpdateCommandRunner $runner,
        private readonly VerifiedDatabaseBackup $databaseBackup,
    ) {}

    public function check(User $actor): UpdateOperation
    {
        $this->ensureEnabled();
        $remote = (string) config('update-manager.remote');
        $branch = (string) config('update-manager.branch');

        foreach ([$remote, $branch] as $value) {
            if (! preg_match('/\A[a-zA-Z0-9._\/-]+\z/', $value)) {
                throw new RuntimeException('Konfigurasi sumber kemas kini tidak sah.');
            }
        }

        $remoteUrl = $this->mustRun(['git', 'remote', 'get-url', $remote])['output'];
        if (! preg_match('~(?:github\.com[:/])~i', $remoteUrl)) {
            throw new RuntimeException('Remote kemas kini mesti menunjuk ke repositori GitHub yang diluluskan.');
        }

        $current = $this->mustRun(['git', 'rev-parse', 'HEAD'])['output'];
        $this->mustRun(['git', 'fetch', '--no-tags', $remote, $branch], 300);
        $target = $this->mustRun(['git', 'rev-parse', 'FETCH_HEAD'])['output'];
        $this->assertCommit($current);
        $this->assertCommit($target);

        $message = $this->mustRun(['git', 'show', '-s', '--format=%s', $target])['output'];
        $files = $this->changedFiles($current, $target);
        $commits = $this->lines($this->mustRun([
            'git', 'log', '--format=%h%x09%an%x09%ad%x09%s', '--date=iso-strict', "{$current}..{$target}",
        ])['output']);
        $migrations = collect($files)
            ->filter(fn ($file) => $file['status'] !== 'deleted' && str_starts_with($file['path'], 'database/migrations/'))
            ->pluck('path')->values()->all();
        $protected = $this->protectedFiles($files);

        return UpdateOperation::query()->create([
            'public_id' => (string) Str::uuid(),
            'initiated_by' => $actor->id,
            'status' => $current === $target ? 'no_update' : 'checked',
            'current_commit' => $current,
            'target_commit' => $target,
            'target_message' => Str::limit($message, 500, ''),
            'changed_files_count' => count($files),
            'details' => [
                'remote' => $remote,
                'branch' => $branch,
                'commits' => $commits,
                'files' => $files,
                'migrations' => $migrations,
                'current_database_pending' => $this->pendingMigrationFiles(),
                'protected_files' => $protected,
                'update_available' => $current !== $target,
            ],
        ]);
    }

    public function preflight(UpdateOperation $operation): UpdateOperation
    {
        $this->ensureEnabled();
        if (! in_array($operation->status, ['checked', 'preflight_failed', 'backup_failed'], true)) {
            throw new RuntimeException('Operasi ini tidak bersedia untuk preflight.');
        }

        $checks = [];
        $allDirty = $this->lines($this->runner->run(['git', 'status', '--porcelain', '--untracked-files=normal'])['output']);
        $dirty = $this->sourceDirtyLines($allDirty);
        $checks[] = $this->checkResult('git_clean', $dirty === [], $dirty === [] ? 'Repositori bersih.' : 'Perubahan tempatan dikesan.', $dirty);
        $checks[] = $this->checkResult('runtime_state', true, 'Artefak runtime tidak dianggap perubahan source dan tidak akan disentuh.', array_values(array_diff($allDirty, $dirty)));

        $ancestor = $this->runner->run(['git', 'merge-base', '--is-ancestor', $operation->current_commit, $operation->target_commit]);
        $checks[] = $this->checkResult('fast_forward', $ancestor['successful'], 'Kemas kini mesti fast-forward sahaja.', $ancestor['output']);

        $remoteUrl = $this->runner->run(['git', 'remote', 'get-url', (string) config('update-manager.remote')]);
        $github = $remoteUrl['successful'] && preg_match('~(?:github\.com[:/])~i', $remoteUrl['output']);
        $checks[] = $this->checkResult('approved_remote', (bool) $github, 'Remote GitHub allowlisted.', $remoteUrl['output']);

        $checks[] = $this->checkResult('php_version', version_compare(PHP_VERSION, '8.2.0', '>='), 'PHP '.PHP_VERSION.'; minimum 8.2.');
        $platform = $this->runner->run(['composer', 'check-platform-reqs', '--no-dev'], 300);
        $checks[] = $this->checkResult('platform_requirements', $platform['successful'], 'Semakan keperluan Composer.', $platform['output']);

        foreach ([storage_path(), storage_path('framework'), storage_path('logs'), base_path('bootstrap/cache')] as $path) {
            $checks[] = $this->checkResult('writable:'.$path, is_dir($path) && is_writable($path), 'Laluan Laravel mesti boleh ditulis.');
        }

        $free = @disk_free_space(storage_path());
        $checks[] = $this->checkResult(
            'storage_space',
            is_int($free) || is_float($free) ? $free >= config('update-manager.minimum_free_bytes') : false,
            'Ruang bebas: '.($free === false ? 'tidak dapat dibaca' : number_format($free).' bait'),
        );

        try {
            DB::select('select 1');
            $databaseOk = true;
            $databaseDetail = DB::connection()->getDriverName().' / '.$this->databaseVersion();
        } catch (Throwable) {
            $databaseOk = false;
            $databaseDetail = 'Sambungan pangkalan data gagal; butiran sensitif tidak direkodkan.';
        }
        $checks[] = $this->checkResult('database', $databaseOk, 'Sambungan dan versi pangkalan data.', $databaseDetail);

        $details = $operation->details ?? [];
        if (($details['migrations'] ?? []) !== []) {
            try {
                $databaseBackup = $this->databaseBackup->preflight();
            } catch (Throwable) {
                $databaseBackup = [
                    'supported' => false,
                    'enabled' => false,
                    'binary' => ['required' => true, 'path' => '', 'exists' => false, 'executable' => false, 'available' => false],
                    'destination' => ['path' => 'update-backups', 'writable' => false],
                    'storage' => ['free_bytes' => false, 'minimum_free_bytes' => (int) config('update-manager.minimum_free_bytes'), 'sufficient' => false],
                    'configuration' => ['available' => false, 'missing' => ['unavailable']],
                ];
            }
            $checks[] = $this->checkResult(
                'database_backup_enabled',
                $databaseBackup['enabled'],
                'Backup pangkalan data mesti diaktifkan apabila kemas kini mengandungi migrasi.',
            );
            $checks[] = $this->checkResult(
                'mysqldump_binary',
                $databaseBackup['binary']['available'],
                $databaseBackup['binary']['required']
                    ? 'Binary mysqldump mesti wujud dan boleh dilaksanakan.'
                    : 'Binary mysqldump tidak diperlukan untuk driver ini.',
                $databaseBackup['binary'],
            );
            $checks[] = $this->checkResult(
                'database_backup_destination',
                $databaseBackup['destination']['writable'],
                'Destinasi backup pangkalan data mesti boleh ditulis.',
                $databaseBackup['destination'],
            );
            $checks[] = $this->checkResult(
                'database_backup_space',
                $databaseBackup['storage']['sufficient'],
                'Ruang bebas mesti melepasi had minimum backup.',
                $databaseBackup['storage'],
            );
            $checks[] = $this->checkResult(
                'database_backup_configuration',
                $databaseBackup['supported'] && $databaseBackup['configuration']['available'],
                'Konfigurasi pangkalan data yang diperlukan mesti tersedia.',
                $databaseBackup['configuration'],
            );
        }

        $protected = $details['protected_files'] ?? [];
        $checks[] = $this->checkResult('protected_files', $protected === [], 'Fail terlindung tidak boleh diubah.', $protected);

        $composerChanges = collect($details['files'] ?? [])->pluck('path')->intersect(['composer.json', 'composer.lock'])->values()->all();
        $checks[] = $this->checkResult('dependencies_unchanged', $composerChanges === [], 'Perubahan dependency memerlukan proses pelepasan manual.', $composerChanges);

        $schema = $this->inspectTargetMigrations($details['migrations'] ?? [], (string) $operation->target_commit);
        $checks[] = $this->checkResult('migration_schema_risk', $schema['risks'] === [], 'Analisis migrasi dan skema sebenar.', $schema);

        $currentPending = $this->pendingMigrationFiles();
        $unapprovedPending = array_values(array_diff($currentPending, $details['migrations'] ?? []));
        $checks[] = $this->checkResult(
            'unapproved_pending_migrations',
            $unapprovedPending === [],
            'Migrasi sedia Pending mesti termasuk dalam set kemas kini yang diluluskan.',
            ['all_pending' => $currentPending, 'unapproved' => $unapprovedPending],
        );

        $pretend = $this->runner->run([PHP_BINARY, 'artisan', 'migrate', '--pretend', '--no-interaction'], 300);
        $checks[] = $this->checkResult('current_pending_migrations', $pretend['successful'], 'SQL pretend untuk migrasi yang sudah wujud dalam working tree.', $pretend['output']);

        $passed = collect($checks)->every(fn ($check) => $check['passed']);
        $operation->forceFill([
            'status' => $passed ? 'preflight_passed' : 'preflight_failed',
            'preflight_passed_at' => $passed ? now() : null,
            'details' => array_merge($details, ['preflight' => $checks, 'schema_inspection' => $schema]),
            'failure_message' => $passed ? null : 'Satu atau lebih semakan preflight gagal. Pemasangan disekat.',
        ])->save();

        return $operation->fresh();
    }

    public function backup(UpdateOperation $operation): UpdateOperation
    {
        $this->ensureEnabled();
        if ($operation->status !== 'preflight_passed') {
            throw new RuntimeException('Backup hanya boleh dibuat selepas preflight lulus.');
        }

        try {
            $relative = 'update-backups/code-'.$operation->public_id.'.zip';
            $absolute = Storage::disk(config('update-manager.backup_disk'))->path($relative);
            $directory = dirname($absolute);
            if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
                throw new RuntimeException('Direktori backup kod tidak dapat dicipta.');
            }

            $archive = $this->runner->run(['git', 'archive', '--format=zip', '--output='.$absolute, $operation->current_commit], 300);
            if (! $archive['successful'] || ! is_file($absolute) || filesize($absolute) < 100) {
                throw new RuntimeException('Backup kod gagal dihasilkan.');
            }

            $zip = new ZipArchive;
            $zipOk = $zip->open($absolute, ZipArchive::CHECKCONS) === true && $zip->numFiles > 0;
            if ($zipOk) {
                $zip->close();
            }
            if (! $zipOk) {
                throw new RuntimeException('Arkib backup kod gagal pengesahan integriti.');
            }

            $codeChecksum = hash_file('sha256', $absolute);
            $database = null;
            if (($operation->details['migrations'] ?? []) !== []) {
                $database = $this->databaseBackup->create($operation->public_id);
            }

            $details = $operation->details ?? [];
            $details['backup'] = [
                'code' => ['verified' => true, 'path' => $relative, 'checksum' => $codeChecksum],
                'database' => $database ?? ['verified' => false, 'not_required' => true],
            ];
            $operation->forceFill([
                'status' => 'backup_verified',
                'code_backup_path' => $relative,
                'code_backup_checksum' => $codeChecksum,
                'database_backup_path' => $database['path'] ?? null,
                'database_backup_checksum' => $database['checksum'] ?? null,
                'backup_verified_at' => now(),
                'failure_message' => null,
                'details' => $details,
            ])->save();

            return $operation->fresh();
        } catch (Throwable) {
            if (isset($absolute) && is_file($absolute)) {
                @unlink($absolute);
            }
            $details = $operation->details ?? [];
            $details['backup'] = [
                'code' => ['verified' => false],
                'database' => ['verified' => false],
                'failure' => 'Backup kod atau pangkalan data gagal pengesahan.',
            ];
            $operation->forceFill([
                'status' => 'backup_failed',
                'code_backup_path' => null,
                'code_backup_checksum' => null,
                'database_backup_path' => null,
                'database_backup_checksum' => null,
                'preflight_passed_at' => null,
                'backup_verified_at' => null,
                'failure_message' => 'Backup kod atau pangkalan data gagal pengesahan. Pemasangan disekat.',
                'details' => $details,
            ])->save();

            throw new RuntimeException('Backup kod atau pangkalan data gagal pengesahan. Pemasangan disekat.');
        }
    }

    public function install(UpdateOperation $operation, User $actor): UpdateOperation
    {
        $this->ensureEnabled();
        if (! in_array($operation->status, ['backup_verified', 'recovery_required'], true)) {
            throw new RuntimeException('Operasi belum mempunyai preflight dan backup yang sah.');
        }
        if (! $this->backupStillValid($operation)) {
            throw new RuntimeException('Checksum backup tidak lagi sah. Pemasangan disekat.');
        }

        $details = $operation->details ?? [];
        $logs = $details['installation_logs'] ?? [];
        $operation->forceFill([
            'status' => 'installing',
            'confirmed_by' => $actor->id,
            'confirmed_at' => now(),
            'started_at' => now(),
            'failure_message' => null,
        ])->save();

        try {
            $current = $this->mustRun(['git', 'rev-parse', 'HEAD'])['output'];
            if (! in_array($current, [$operation->current_commit, $operation->target_commit], true)) {
                throw new RuntimeException('Commit semasa berubah selepas backup. Jalankan semakan baharu.');
            }
            $dirty = $this->sourceDirtyLines($this->lines($this->mustRun(['git', 'status', '--porcelain', '--untracked-files=normal'])['output']));
            if ($dirty !== []) {
                throw new RuntimeException('Perubahan source dikesan selepas backup. Pemasangan disekat.');
            }
            if ($current !== $operation->target_commit) {
                $this->logRun($logs, [PHP_BINARY, 'artisan', 'down', '--retry=60'], 60, true);
                $this->logRun($logs, ['git', 'merge', '--ff-only', $operation->target_commit], 300, true);
            } else {
                $this->logRun($logs, [PHP_BINARY, 'artisan', 'down', '--retry=60'], 60, true);
            }

            $unexpected = array_diff($this->pendingMigrationFiles(), $details['migrations'] ?? []);
            if ($unexpected !== []) {
                throw new RuntimeException('Migrasi belum diluluskan dikesan: '.implode(', ', $unexpected));
            }

            $pretend = $this->logRun($logs, [PHP_BINARY, 'artisan', 'migrate', '--pretend', '--no-interaction'], 300, true);
            if (preg_match('/Specified key was too long|maximum key length|drop column|drop foreign/i', $pretend['output'])) {
                throw new RuntimeException('SQL pretend menunjukkan risiko skema atau indeks.');
            }

            $this->logRun($logs, [PHP_BINARY, 'artisan', 'migrate', '--force', '--no-interaction'], 600, true);
            $this->logRun($logs, [PHP_BINARY, 'artisan', 'optimize:clear', '--no-interaction'], 300, true);
            $verification = $this->verify($operation);
            if (! $verification['passed']) {
                throw new RuntimeException('Pengesahan selepas pemasangan gagal.');
            }

            $details['installation_logs'] = $logs;
            $details['verification'] = $verification;
            $operation->forceFill([
                'status' => 'successful',
                'details' => $details,
                'finished_at' => now(),
            ])->save();
        } catch (Throwable $e) {
            $details['installation_logs'] = $logs;
            $operation->forceFill([
                'status' => 'recovery_required',
                'details' => $details,
                'failure_message' => $e->getMessage(),
                'finished_at' => now(),
            ])->save();
        } finally {
            $this->runner->run([PHP_BINARY, 'artisan', 'up'], 60);
        }

        return $operation->fresh();
    }

    public function verify(UpdateOperation $operation): array
    {
        $checks = [];
        $head = $this->runner->run(['git', 'rev-parse', 'HEAD']);
        $checks[] = $this->checkResult('expected_commit', $head['successful'] && trim($head['output']) === $operation->target_commit, 'Commit terpasang.', $head['output']);
        $checks[] = $this->checkResult('migrations', $this->pendingMigrationFiles() === [], 'Semua migrasi dilaksanakan.', $this->pendingMigrationFiles());
        $checks[] = $this->checkResult('laravel_bootstrap', app()->bound('router') && app()->environment() !== '', 'Laravel berjaya bootstrap.');

        foreach (config('update-manager.critical_routes', []) as $route) {
            $checks[] = $this->checkResult('route:'.$route, Route::has($route), 'Route kritikal tersedia.');
        }

        $probe = 'update-probe-'.Str::uuid();
        $written = Storage::disk('local')->put($probe, 'ok');
        $storageOk = $written && Storage::disk('local')->get($probe) === 'ok';
        Storage::disk('local')->delete($probe);
        $checks[] = $this->checkResult('storage', $storageOk, 'Storan peribadi boleh baca/tulis.');

        return ['passed' => collect($checks)->every(fn ($check) => $check['passed']), 'checks' => $checks];
    }

    public function protectedFiles(array $files): array
    {
        return collect($files)->filter(function ($file) {
            return collect(config('update-manager.protected_patterns', []))
                ->contains(fn ($pattern) => Str::is($pattern, $file['path']));
        })->values()->all();
    }

    private function changedFiles(string $current, string $target): array
    {
        $output = $this->mustRun(['git', 'diff', '--name-status', '--find-renames', $current, $target])['output'];

        return collect($this->lines($output))->map(function ($line) {
            $parts = explode("\t", $line);
            $code = substr($parts[0], 0, 1);

            return [
                'status' => match ($code) {
                    'A' => 'added', 'D' => 'deleted', 'R' => 'renamed', default => 'modified'
                },
                'path' => $code === 'R' ? (string) end($parts) : ($parts[1] ?? ''),
                'from' => $code === 'R' ? ($parts[1] ?? null) : null,
            ];
        })->filter(fn ($file) => $file['path'] !== '')->values()->all();
    }

    private function inspectTargetMigrations(array $paths, string $target): array
    {
        $risks = [];
        $tables = [];
        $files = [];

        foreach ($paths as $path) {
            $show = $this->runner->run(['git', 'show', $target.':'.$path]);
            if (! $show['successful']) {
                $risks[] = ['file' => $path, 'risk' => 'Kandungan migrasi sasaran tidak dapat dibaca.'];

                continue;
            }

            $content = $show['output'];
            preg_match_all('/Schema::(?:create|table)\([\'\"]([^\'\"]+)/', $content, $matches);
            $fileTables = array_values(array_unique($matches[1] ?? []));
            $tables = array_values(array_unique(array_merge($tables, $fileTables)));

            if (preg_match('/->(?:drop|dropColumn|dropForeign|dropIndex|renameColumn|change)\s*\(/i', $content)) {
                $risks[] = ['file' => $path, 'risk' => 'Operasi skema merosakkan/ubah ditemui; semakan manual diperlukan.'];
            }
            if (preg_match('/->string\([^,\)]+\)\s*->(?:unique|index)\s*\(/i', $content)) {
                $risks[] = ['file' => $path, 'risk' => 'String berindeks tanpa panjang eksplisit berisiko MySQL 1071.'];
            }
            if (preg_match_all('/->string\([^,\)]+,\s*(\d+)\)\s*->(?:unique|index)\s*\(/i', $content, $lengthMatches)) {
                foreach ($lengthMatches[1] as $length) {
                    if ((int) $length > 191) {
                        $risks[] = ['file' => $path, 'risk' => "String berindeks panjang {$length} melebihi had konservatif 191 untuk utf8mb4."];
                    }
                }
            }

            preg_match_all('/\$table->(?:string|char|text|mediumText|longText|integer|unsignedInteger|bigInteger|unsignedBigInteger|foreignId|date|dateTime|timestamp|decimal|boolean|json|uuid)\(\s*[\'\"]([^\'\"]+)/i', $content, $columnMatches);
            $files[] = ['path' => $path, 'tables' => $fileTables, 'columns' => array_values(array_unique($columnMatches[1] ?? []))];
        }

        $actual = [];
        foreach ($tables as $table) {
            try {
                $actual[$table] = Schema::hasTable($table) ? [
                    'exists' => true,
                    'columns' => Schema::getColumns($table),
                    'indexes' => Schema::getIndexes($table),
                    'foreign_keys' => Schema::getForeignKeys($table),
                ] : ['exists' => false, 'columns' => [], 'indexes' => [], 'foreign_keys' => []];
            } catch (Throwable $e) {
                $risks[] = ['table' => $table, 'risk' => 'Metadata skema sebenar gagal dibaca: '.$e->getMessage()];
            }
        }

        foreach ($files as $file) {
            foreach ($file['tables'] as $table) {
                if (! ($actual[$table]['exists'] ?? false)) {
                    continue;
                }
                $actualColumns = array_column($actual[$table]['columns'] ?? [], 'name');
                foreach ($file['columns'] as $column) {
                    if (in_array($column, $actualColumns, true)) {
                        $risks[] = [
                            'file' => $file['path'],
                            'table' => $table,
                            'column' => $column,
                            'risk' => 'Kolum sasaran sudah wujud dalam skema sebenar; kemungkinan migrasi separa atau konflik.',
                        ];
                    }
                }
            }
        }

        return ['files' => $files, 'database' => ['driver' => DB::connection()->getDriverName(), 'version' => $this->databaseVersion()], 'actual_schema' => $actual, 'risks' => $risks];
    }

    private function pendingMigrationFiles(): array
    {
        $ran = Schema::hasTable('migrations') ? DB::table('migrations')->pluck('migration')->all() : [];

        return collect(glob(database_path('migrations/*.php')) ?: [])
            ->filter(fn ($path) => ! in_array(pathinfo($path, PATHINFO_FILENAME), $ran, true))
            ->map(fn ($path) => 'database/migrations/'.basename($path))
            ->values()->all();
    }

    private function backupStillValid(UpdateOperation $operation): bool
    {
        if (! $operation->code_backup_path || ! Storage::disk(config('update-manager.backup_disk'))->exists($operation->code_backup_path)) {
            return false;
        }
        $code = hash_file('sha256', Storage::disk(config('update-manager.backup_disk'))->path($operation->code_backup_path));
        if (! hash_equals((string) $operation->code_backup_checksum, (string) $code)) {
            return false;
        }
        if (($operation->details['migrations'] ?? []) !== []) {
            if (! $operation->database_backup_path || ! Storage::disk('local')->exists($operation->database_backup_path)) {
                return false;
            }
            $database = hash_file('sha256', Storage::disk('local')->path($operation->database_backup_path));

            return hash_equals((string) $operation->database_backup_checksum, (string) $database);
        }

        return true;
    }

    private function logRun(array &$logs, array $command, int $timeout, bool $mustSucceed = false): array
    {
        $result = $this->runner->run($command, $timeout);
        $logs[] = ['operation' => implode(' ', array_map(fn ($part) => basename((string) $part), $command)), 'successful' => $result['successful'], 'output' => Str::limit($result['output'], 20000)];
        if ($mustSucceed && ! $result['successful']) {
            throw new RuntimeException('Operasi pemasangan tetap gagal: '.Str::limit($result['output'], 1000));
        }

        return $result;
    }

    private function mustRun(array $command, int $timeout = 120): array
    {
        $result = $this->runner->run($command, $timeout);
        if (! $result['successful']) {
            throw new RuntimeException(Str::limit($result['output'] ?: 'Arahan tetap gagal.', 1000));
        }

        return $result;
    }

    private function checkResult(string $name, bool $passed, string $message, mixed $details = null): array
    {
        return ['name' => $name, 'passed' => $passed, 'message' => $message, 'details' => $details];
    }

    private function databaseVersion(): string
    {
        try {
            $row = DB::selectOne('select version() as version');

            return (string) ($row->version ?? 'unknown');
        } catch (Throwable) {
            return DB::connection()->getDriverName();
        }
    }

    private function lines(string $output): array
    {
        return $output === '' ? [] : (preg_split('/\r\n|\r|\n/', trim($output)) ?: []);
    }

    private function sourceDirtyLines(array $lines): array
    {
        return collect($lines)->reject(function (string $line) {
            $path = trim(substr($line, 3));
            if (str_contains($path, ' -> ')) {
                $path = (string) Str::afterLast($path, ' -> ');
            }
            $path = str_replace('\\', '/', trim($path, '"'));

            return Str::startsWith($path, ['storage/framework/', 'storage/logs/', 'bootstrap/cache/']);
        })->values()->all();
    }

    private function assertCommit(string $commit): void
    {
        if (! preg_match('/\A[0-9a-f]{40}\z/i', trim($commit))) {
            throw new RuntimeException('Git commit yang diterima tidak sah.');
        }
    }

    private function ensureEnabled(): void
    {
        if (! config('update-manager.enabled')) {
            throw new RuntimeException('Smart Update Manager belum diaktifkan pada konfigurasi pelayan.');
        }
    }
}
