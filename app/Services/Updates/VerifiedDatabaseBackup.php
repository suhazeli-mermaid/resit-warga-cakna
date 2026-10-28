<?php

namespace App\Services\Updates;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class VerifiedDatabaseBackup
{
    public function __construct(private readonly UpdateCommandRunner $runner) {}

    /** @return array{path: string, checksum: string, verified: bool} */
    public function create(string $operationId): array
    {
        $connection = DB::connection();
        $driver = $connection->getDriverName();

        return match ($driver) {
            'mysql' => $this->mysql($operationId),
            'sqlite' => $this->sqlite($operationId),
            default => throw new RuntimeException("Backup pangkalan data {$driver} belum disokong dan pemasangan disekat."),
        };
    }

    private function mysql(string $operationId): array
    {
        if (! config('update-manager.database_backup.enabled')) {
            throw new RuntimeException('Backup MySQL belum diaktifkan untuk seni bina pelayan ini.');
        }

        $binary = (string) config('update-manager.database_backup.mysqldump_binary');
        if ($binary === '' || ! is_file($binary)) {
            throw new RuntimeException('Binary mysqldump allowlisted tidak ditemui.');
        }

        $config = config('database.connections.mysql');
        $directory = storage_path('app/private/update-backups');
        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RuntimeException('Direktori backup pangkalan data tidak dapat dicipta.');
        }

        $optionFile = $directory.'/mysql-'.$operationId.'.cnf';
        $output = $directory.'/database-'.$operationId.'.sql';
        $optionContents = "[client]\nuser=".$this->optionValue((string) $config['username'])
            ."\npassword=".$this->optionValue((string) $config['password'])
            ."\nhost=".$this->optionValue((string) $config['host'])
            ."\nport=".(int) $config['port']."\n";
        file_put_contents($optionFile, $optionContents, LOCK_EX);
        @chmod($optionFile, 0600);

        try {
            $result = $this->runner->run([
                $binary,
                '--defaults-extra-file='.$optionFile,
                '--single-transaction',
                '--quick',
                '--routines',
                '--events',
                '--result-file='.$output,
                (string) $config['database'],
            ], 600);
        } finally {
            if (is_file($optionFile)) {
                unlink($optionFile);
            }
        }

        if (! $result['successful'] || ! is_file($output) || filesize($output) < 100) {
            throw new RuntimeException('mysqldump tidak menghasilkan backup yang boleh disahkan.');
        }

        $tail = file_get_contents($output, false, null, max(0, filesize($output) - 4096));
        if (! preg_match('/dump completed|mariadb dump|mysql dump/i', (string) $tail)) {
            throw new RuntimeException('Penanda tamat dump tidak ditemui; backup dianggap gagal.');
        }

        return $this->verifiedResult($output);
    }

    private function sqlite(string $operationId): array
    {
        DB::statement('PRAGMA wal_checkpoint(FULL)');
        $source = DB::connection()->getDatabaseName();
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
        $checksum = hash_file('sha256', $absolutePath);
        if (! is_string($checksum) || strlen($checksum) !== 64) {
            throw new RuntimeException('Checksum backup pangkalan data gagal.');
        }

        $privateRoot = rtrim(Storage::disk('local')->path(''), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
        $relative = str_replace('\\', '/', substr($absolutePath, strlen($privateRoot)));

        return ['path' => $relative, 'checksum' => $checksum, 'verified' => true];
    }

    private function optionValue(string $value): string
    {
        return '"'.str_replace(['\\', '"', "\r", "\n"], ['\\\\', '\\"', '', ''], $value).'"';
    }
}
