<?php

namespace App\Services\Updates;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class VerifiedDatabaseBackup
{
    public function __construct(private readonly UpdateCommandRunner $runner) {}

    /** @return array{path: string, checksum: string, verified: bool} */
    public function create(string $operationId): array
    {
        $readiness = $this->preflight();
        if (! $readiness['enabled']) {
            throw new RuntimeException('Backup pangkalan data belum diaktifkan untuk seni bina pelayan ini.');
        }
        if (! $readiness['supported']) {
            throw new RuntimeException("Backup pangkalan data {$readiness['driver']} belum disokong dan pemasangan disekat.");
        }
        if (! $readiness['configuration']['available']) {
            throw new RuntimeException('Konfigurasi pangkalan data untuk backup tidak lengkap.');
        }
        if (! $readiness['destination']['writable']) {
            throw new RuntimeException('Destinasi backup pangkalan data tidak boleh ditulis.');
        }
        if (! $readiness['storage']['sufficient']) {
            throw new RuntimeException('Ruang storan tidak mencukupi untuk backup pangkalan data.');
        }
        if (! $readiness['binary']['available']) {
            throw new RuntimeException('Binary mysqldump allowlisted tidak ditemui atau tidak boleh dilaksanakan.');
        }

        $operationId = $this->safeOperationId($operationId);

        return match ($readiness['driver']) {
            'mysql' => $this->mysql($operationId),
            'sqlite' => $this->sqlite($operationId),
            default => throw new RuntimeException('Driver backup pangkalan data tidak disokong.'),
        };
    }

    /**
     * Inspect backup prerequisites without creating directories, files or dumps.
     *
     * @return array<string, mixed>
     */
    public function preflight(): array
    {
        $config = $this->connectionConfig();
        $driver = (string) ($config['driver'] ?? 'unknown');
        $supported = in_array($driver, ['mysql', 'sqlite'], true);
        $enabled = (bool) config('update-manager.database_backup.enabled', false);
        $binaryPath = trim((string) config('update-manager.database_backup.mysqldump_binary'));
        $binaryRequired = $driver === 'mysql';
        $binaryExists = ! $binaryRequired || ($binaryPath !== '' && is_file($binaryPath));
        $binaryExecutable = ! $binaryRequired || ($binaryExists && is_executable($binaryPath));

        $directory = $this->backupDirectory();
        $existingDirectory = $this->nearestExistingDirectory($directory);
        $destinationWritable = $existingDirectory !== null && is_writable($existingDirectory);
        $freeBytes = $existingDirectory === null ? false : @disk_free_space($existingDirectory);
        $minimumFreeBytes = max(0, (int) config('update-manager.minimum_free_bytes', 0));
        $spaceSufficient = (is_int($freeBytes) || is_float($freeBytes)) && $freeBytes >= $minimumFreeBytes;
        $missing = $this->missingConfiguration($driver, $config);

        return [
            'passed' => $supported
                && $enabled
                && $binaryExists
                && $binaryExecutable
                && $destinationWritable
                && $spaceSufficient
                && $missing === [],
            'driver' => $driver,
            'supported' => $supported,
            'enabled' => $enabled,
            'binary' => [
                'required' => $binaryRequired,
                'path' => $binaryPath,
                'exists' => $binaryExists,
                'executable' => $binaryExecutable,
                'available' => $binaryExists && $binaryExecutable,
            ],
            'destination' => [
                'path' => 'update-backups',
                'writable' => $destinationWritable,
            ],
            'storage' => [
                'free_bytes' => $freeBytes,
                'minimum_free_bytes' => $minimumFreeBytes,
                'sufficient' => $spaceSufficient,
            ],
            'configuration' => [
                'available' => $missing === [],
                'missing' => $missing,
            ],
        ];
    }

    private function mysql(string $operationId): array
    {
        $binary = trim((string) config('update-manager.database_backup.mysqldump_binary'));
        if ($binary === '' || ! is_file($binary) || ! is_executable($binary)) {
            throw new RuntimeException('Binary mysqldump allowlisted tidak ditemui atau tidak boleh dilaksanakan.');
        }

        $config = $this->connectionConfig();
        $directory = $this->backupDirectory();
        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RuntimeException('Direktori backup pangkalan data tidak dapat dicipta.');
        }

        $optionFile = $directory.'/mysql-'.$operationId.'.cnf';
        $output = $directory.'/database-'.$operationId.'.sql';
        $optionContents = "[client]\nuser=".$this->optionValue((string) $config['username'])
            ."\npassword=".$this->optionValue((string) $config['password'])."\n";
        if (filled($config['unix_socket'] ?? null)) {
            $optionContents .= 'socket='.$this->optionValue((string) $config['unix_socket'])."\n";
        } else {
            $optionContents .= 'host='.$this->optionValue((string) $config['host'])."\n"
                .'port='.(int) ($config['port'] ?? 3306)."\n";
        }
        $written = @file_put_contents($optionFile, $optionContents, LOCK_EX);
        if ($written !== strlen($optionContents)) {
            $this->deleteFile($optionFile);

            throw new RuntimeException('Fail pilihan MySQL peribadi tidak dapat ditulis dengan selamat.');
        }
        if (PHP_OS_FAMILY !== 'Windows' && ! @chmod($optionFile, 0600)) {
            $this->deleteFile($optionFile);

            throw new RuntimeException('Kebenaran fail pilihan MySQL peribadi tidak dapat diketatkan.');
        }

        if (is_file($output) && ! @unlink($output)) {
            $this->deleteFile($optionFile);

            throw new RuntimeException('Fail backup lama tidak dapat digantikan dengan selamat.');
        }

        $command = [
            $binary,
            '--defaults-extra-file='.$optionFile,
            '--single-transaction',
            '--quick',
            '--skip-lock-tables',
        ];
        if ((bool) config('update-manager.database_backup.no_tablespaces', false)) {
            $command[] = '--no-tablespaces';
        }
        $command[] = '--result-file='.$output;
        $command[] = (string) $config['database'];

        try {
            $result = $this->runner->run($command, 600);
        } catch (Throwable) {
            $this->deleteFile($output);

            throw new RuntimeException('mysqldump gagal dilaksanakan; backup pangkalan data disekat.');
        } finally {
            $this->deleteFile($optionFile);
        }

        if (! $result['successful'] || $result['exit_code'] !== 0 || ! $this->validMysqlDump($output)) {
            $this->deleteFile($output);

            throw new RuntimeException('mysqldump tidak menghasilkan backup lengkap yang boleh disahkan.');
        }

        try {
            return $this->verifiedResult($output);
        } catch (Throwable) {
            $this->deleteFile($output);

            throw new RuntimeException('Checksum backup pangkalan data gagal dan pemasangan disekat.');
        }
    }

    private function sqlite(string $operationId): array
    {
        $connection = DB::connection($this->connectionName());
        $connection->statement('PRAGMA wal_checkpoint(FULL)');
        $source = $connection->getDatabaseName();
        if ($source === ':memory:' || ! is_file($source)) {
            throw new RuntimeException('SQLite dalam memori tidak boleh dijadikan backup pemasangan.');
        }

        $relative = 'update-backups/database-'.$operationId.'.sqlite';
        $contents = file_get_contents($source);
        if (! is_string($contents) || ! str_starts_with($contents, 'SQLite format 3')) {
            throw new RuntimeException('Fail sumber SQLite tidak sah.');
        }

        if (! Storage::disk('local')->put($relative, $contents)) {
            throw new RuntimeException('Salinan SQLite gagal ditulis.');
        }

        return $this->verifiedResult(Storage::disk('local')->path($relative));
    }

    private function verifiedResult(string $absolutePath): array
    {
        if (! is_file($absolutePath) || filesize($absolutePath) <= 0) {
            throw new RuntimeException('Fail backup pangkalan data kosong atau tidak ditemui.');
        }

        $checksum = hash_file('sha256', $absolutePath);
        if (! is_string($checksum) || strlen($checksum) !== 64) {
            throw new RuntimeException('Checksum backup pangkalan data gagal.');
        }

        $privateRoot = rtrim(Storage::disk('local')->path(''), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
        if (! str_starts_with($absolutePath, $privateRoot)) {
            throw new RuntimeException('Lokasi backup pangkalan data tidak sah.');
        }
        $relative = str_replace('\\', '/', substr($absolutePath, strlen($privateRoot)));

        return ['path' => $relative, 'checksum' => $checksum, 'verified' => true];
    }

    /** @return array<string, mixed> */
    private function connectionConfig(): array
    {
        $config = config('database.connections.'.$this->connectionName());

        return is_array($config) ? $config : [];
    }

    private function connectionName(): string
    {
        return (string) (config('update-manager.database_backup.connection') ?: config('database.default'));
    }

    /** @param array<string, mixed> $config */
    private function missingConfiguration(string $driver, array $config): array
    {
        if ($driver === 'sqlite') {
            return filled($config['database'] ?? null) ? [] : ['database'];
        }
        if ($driver !== 'mysql') {
            return ['driver'];
        }

        $missing = [];
        foreach (['database', 'username'] as $key) {
            if (! filled($config[$key] ?? null)) {
                $missing[] = $key;
            }
        }
        if (! filled($config['host'] ?? null) && ! filled($config['unix_socket'] ?? null)) {
            $missing[] = 'host_or_unix_socket';
        }
        if (! array_key_exists('password', $config)) {
            $missing[] = 'password';
        }

        return $missing;
    }

    private function backupDirectory(): string
    {
        return Storage::disk('local')->path('update-backups');
    }

    private function nearestExistingDirectory(string $path): ?string
    {
        $candidate = $path;
        while (! is_dir($candidate)) {
            $parent = dirname($candidate);
            if ($parent === $candidate) {
                return null;
            }
            $candidate = $parent;
        }

        return $candidate;
    }

    private function validMysqlDump(string $path): bool
    {
        if (! is_file($path)) {
            return false;
        }

        clearstatcache(true, $path);
        $size = filesize($path);
        if (! is_int($size) || $size < 100) {
            return false;
        }

        $head = file_get_contents($path, false, null, 0, min($size, 8192));
        $tail = file_get_contents($path, false, null, max(0, $size - 8192));
        if (! is_string($head) || ! is_string($tail)) {
            return false;
        }

        $hasHeader = preg_match('/^-- (?:MySQL|MariaDB) dump\b/im', $head) === 1;
        $hasSql = str_contains($head.$tail, ';');
        $hasCompletionMarker = preg_match('/^-- Dump completed on .+$/im', $tail) === 1;

        return $hasHeader && $hasSql && $hasCompletionMarker;
    }

    private function safeOperationId(string $operationId): string
    {
        if (! preg_match('/\A[a-zA-Z0-9-]{1,100}\z/', $operationId)) {
            throw new RuntimeException('Pengecam operasi backup tidak sah.');
        }

        return $operationId;
    }

    private function deleteFile(string $path): void
    {
        if (is_file($path)) {
            @unlink($path);
        }
    }

    private function optionValue(string $value): string
    {
        return '"'.str_replace(['\\', '"', "\r", "\n"], ['\\\\', '\\"', '', ''], $value).'"';
    }
}
