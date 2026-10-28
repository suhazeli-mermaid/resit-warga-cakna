<?php

namespace App\Services\Updates;

use JsonException;

class ShellDeploymentHistory
{
    /** @return array<int, array<string, mixed>> */
    public function latest(int $limit = 25): array
    {
        $directory = (string) config('update-manager.shell_history_path');
        $root = realpath($directory);
        if ($root === false || ! is_dir($root)) {
            return [];
        }

        $paths = glob($root.DIRECTORY_SEPARATOR.'*.json') ?: [];
        rsort($paths, SORT_STRING);
        $history = [];

        foreach (array_slice($paths, 0, max(1, min($limit, 100))) as $path) {
            $record = $this->readRecord($root, $path);
            if ($record !== null) {
                $history[] = $record;
            }
        }

        return $history;
    }

    /** @return array<string, mixed>|null */
    private function readRecord(string $root, string $path): ?array
    {
        $resolved = realpath($path);
        if ($resolved === false || is_link($path) || ! str_starts_with($resolved, $root.DIRECTORY_SEPARATOR)) {
            return null;
        }
        $size = filesize($resolved);
        if (! is_int($size) || $size < 2 || $size > 64 * 1024) {
            return null;
        }

        try {
            $record = json_decode((string) file_get_contents($resolved), true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }
        if (! is_array($record) || ! $this->validRecord($record)) {
            return null;
        }

        $allowed = array_flip([
            'schema', 'deployment_id', 'mode', 'status', 'started_at', 'finished_at',
            'current_commit', 'target_commit', 'installed_commit', 'code_backup_path',
            'code_backup_checksum', 'database_backup_path', 'database_backup_checksum',
            'maintenance_entered', 'failure_code',
        ]);

        return array_merge([
            'schema' => 1,
            'deployment_id' => '',
            'mode' => '',
            'status' => '',
            'started_at' => '',
            'finished_at' => '',
            'current_commit' => '',
            'target_commit' => '',
            'installed_commit' => '',
            'code_backup_path' => '',
            'code_backup_checksum' => '',
            'database_backup_path' => '',
            'database_backup_checksum' => '',
            'maintenance_entered' => false,
            'failure_code' => '',
        ], array_intersect_key($record, $allowed));
    }

    /** @param array<string, mixed> $record */
    private function validRecord(array $record): bool
    {
        if (($record['schema'] ?? null) !== 1) {
            return false;
        }
        if (preg_match('/\A[0-9]{8}T[0-9]{6}Z-[0-9]+\z/', (string) ($record['deployment_id'] ?? '')) !== 1) {
            return false;
        }
        if (! in_array($record['mode'] ?? null, ['deploy', 'dry-run', 'self-check'], true)) {
            return false;
        }
        if (! in_array($record['status'] ?? null, ['successful', 'failed', 'no_update', 'dry_run_passed', 'self_check_passed'], true)) {
            return false;
        }

        foreach (['current_commit', 'target_commit', 'installed_commit'] as $key) {
            $value = $record[$key] ?? '';
            if ($value !== '' && preg_match('/\A[a-f0-9]{40}\z/', (string) $value) !== 1) {
                return false;
            }
        }
        foreach (['code_backup_checksum', 'database_backup_checksum'] as $key) {
            $value = $record[$key] ?? '';
            if ($value !== '' && preg_match('/\A[a-f0-9]{64}\z/', (string) $value) !== 1) {
                return false;
            }
        }

        return true;
    }
}
