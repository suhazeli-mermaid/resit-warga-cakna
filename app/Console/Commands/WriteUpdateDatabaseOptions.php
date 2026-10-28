<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use RuntimeException;

class WriteUpdateDatabaseOptions extends Command
{
    protected $signature = 'updates:write-database-options {deployment}';

    protected $description = 'Write a short-lived, private mysqldump option file for deploy.sh';

    public function handle(): int
    {
        $deployment = (string) $this->argument('deployment');
        if (preg_match('/\A[0-9]{8}T[0-9]{6}Z-[0-9]+\z/', $deployment) !== 1) {
            throw new RuntimeException('Invalid deployment identifier.');
        }
        if (! (bool) config('update-manager.database_backup.enabled', false)) {
            throw new RuntimeException('Database backup is not enabled.');
        }

        $connectionName = (string) (config('update-manager.database_backup.connection') ?: config('database.default'));
        $configuration = config('database.connections.'.$connectionName);
        if (! is_array($configuration) || ($configuration['driver'] ?? null) !== 'mysql') {
            throw new RuntimeException('The deployment database connection must use MySQL.');
        }

        $database = $this->requiredValue($configuration, 'database');
        if (preg_match('/\A[A-Za-z0-9_]+\z/', $database) !== 1) {
            throw new RuntimeException('The configured database name is not shell-deployment safe.');
        }
        $username = $this->requiredValue($configuration, 'username');
        if (! array_key_exists('password', $configuration)) {
            throw new RuntimeException('The database password configuration is unavailable.');
        }
        $password = $this->safeValue((string) $configuration['password'], 'password');
        $socket = $this->safeValue((string) ($configuration['unix_socket'] ?? ''), 'unix socket');
        $host = $this->safeValue((string) ($configuration['host'] ?? ''), 'host');
        if ($socket === '' && $host === '') {
            throw new RuntimeException('A database host or Unix socket is required.');
        }
        $port = (int) ($configuration['port'] ?? 3306);
        if ($port < 1 || $port > 65535) {
            throw new RuntimeException('The configured database port is invalid.');
        }

        $runtimeRoot = rtrim((string) config('update-manager.shell_runtime_path'), DIRECTORY_SEPARATOR);
        $privateRoot = realpath((string) config('update-manager.shell_private_path'));
        if ($runtimeRoot === '' || $privateRoot === false) {
            throw new RuntimeException('The private deployment runtime path is unavailable.');
        }
        if (! is_dir($runtimeRoot) && ! mkdir($runtimeRoot, 0700, true) && ! is_dir($runtimeRoot)) {
            throw new RuntimeException('The private deployment runtime path cannot be created.');
        }
        $resolvedRuntimeRoot = realpath($runtimeRoot);
        if ($resolvedRuntimeRoot === false || is_link($runtimeRoot) || ! str_starts_with($resolvedRuntimeRoot, $privateRoot.DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('The private deployment runtime path is outside private storage.');
        }
        $directory = $resolvedRuntimeRoot.DIRECTORY_SEPARATOR.$deployment;
        if (is_link($directory)) {
            throw new RuntimeException('The private deployment runtime directory is unsafe.');
        }
        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RuntimeException('The private deployment runtime directory cannot be created.');
        }
        if (PHP_OS_FAMILY !== 'Windows' && ! @chmod($directory, 0700)) {
            throw new RuntimeException('The private deployment runtime directory cannot be secured.');
        }

        $options = "[client]\nuser=".$this->optionValue($username)."\npassword=".$this->optionValue($password)."\n";
        $options .= $socket !== ''
            ? 'socket='.$this->optionValue($socket)."\n"
            : 'host='.$this->optionValue($host)."\nport={$port}\n";

        $this->writePrivateFile($directory.'/mysql.cnf', $options);
        $this->writePrivateFile($directory.'/database-name', $database."\n");

        return self::SUCCESS;
    }

    /** @param array<string, mixed> $configuration */
    private function requiredValue(array $configuration, string $key): string
    {
        $value = $this->safeValue((string) ($configuration[$key] ?? ''), $key);
        if ($value === '') {
            throw new RuntimeException("The database {$key} configuration is unavailable.");
        }

        return $value;
    }

    private function safeValue(string $value, string $label): string
    {
        if (str_contains($value, "\0") || str_contains($value, "\r") || str_contains($value, "\n")) {
            throw new RuntimeException("The database {$label} configuration contains invalid control characters.");
        }

        return $value;
    }

    private function optionValue(string $value): string
    {
        return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
    }

    private function writePrivateFile(string $path, string $contents): void
    {
        if (is_file($path) || is_link($path)) {
            throw new RuntimeException('A private deployment credential file already exists.');
        }
        $written = @file_put_contents($path, $contents, LOCK_EX);
        if ($written !== strlen($contents)) {
            @unlink($path);

            throw new RuntimeException('A private deployment credential file could not be written.');
        }
        if (PHP_OS_FAMILY !== 'Windows' && ! @chmod($path, 0600)) {
            @unlink($path);

            throw new RuntimeException('A private deployment credential file could not be secured.');
        }
    }
}
