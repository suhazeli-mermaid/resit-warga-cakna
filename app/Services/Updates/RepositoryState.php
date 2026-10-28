<?php

namespace App\Services\Updates;

class RepositoryState
{
    /** @return array{branch: ?string, current_commit: ?string, origin_main_commit: ?string} */
    public function inspect(): array
    {
        $root = (string) config('update-manager.repository_path', base_path());
        $gitDirectory = $this->gitDirectory($root);
        if ($gitDirectory === null) {
            return ['branch' => null, 'current_commit' => null, 'origin_main_commit' => null];
        }

        $head = $this->readFile($gitDirectory.DIRECTORY_SEPARATOR.'HEAD');
        $branch = null;
        $current = null;

        if (is_string($head) && str_starts_with($head, 'ref: ')) {
            $reference = trim(substr($head, 5));
            if (preg_match('~\Arefs/heads/([a-zA-Z0-9._/-]+)\z~', $reference, $match) === 1) {
                $branch = $match[1];
                $current = $this->resolveReference($gitDirectory, $reference);
            }
        } elseif ($this->validCommit($head)) {
            $current = $head;
        }

        return [
            'branch' => $branch,
            'current_commit' => $current,
            'origin_main_commit' => $this->resolveReference($gitDirectory, 'refs/remotes/origin/main'),
        ];
    }

    private function gitDirectory(string $root): ?string
    {
        $candidate = rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'.git';
        if (is_dir($candidate)) {
            return realpath($candidate) ?: null;
        }
        if (! is_file($candidate)) {
            return null;
        }

        $contents = $this->readFile($candidate);
        if (! is_string($contents) || preg_match('/\Agitdir:\s*(.+)\z/', $contents, $match) !== 1) {
            return null;
        }

        $path = $match[1];
        if (! str_starts_with($path, DIRECTORY_SEPARATOR) && preg_match('/\A[A-Za-z]:[\\\\\/]/', $path) !== 1) {
            $path = dirname($candidate).DIRECTORY_SEPARATOR.$path;
        }

        return is_dir($path) ? (realpath($path) ?: null) : null;
    }

    private function resolveReference(string $gitDirectory, string $reference): ?string
    {
        if (preg_match('~\Arefs/[a-zA-Z0-9._/-]+\z~', $reference) !== 1 || str_contains($reference, '..')) {
            return null;
        }

        $loose = $this->readFile($gitDirectory.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $reference));
        if ($this->validCommit($loose)) {
            return $loose;
        }

        $packed = $this->readFile($gitDirectory.DIRECTORY_SEPARATOR.'packed-refs');
        if (! is_string($packed)) {
            return null;
        }

        foreach (preg_split('/\r\n|\r|\n/', $packed) ?: [] as $line) {
            if (str_starts_with($line, '#') || str_starts_with($line, '^')) {
                continue;
            }
            [$commit, $name] = array_pad(explode(' ', trim($line), 2), 2, null);
            if ($name === $reference && $this->validCommit($commit)) {
                return $commit;
            }
        }

        return null;
    }

    private function readFile(string $path): ?string
    {
        if (! is_file($path) || is_link($path) || filesize($path) > 1024 * 1024) {
            return null;
        }

        $contents = @file_get_contents($path);

        return is_string($contents) ? trim($contents) : null;
    }

    private function validCommit(?string $commit): bool
    {
        return is_string($commit) && preg_match('/\A[a-f0-9]{40}\z/', $commit) === 1;
    }
}
