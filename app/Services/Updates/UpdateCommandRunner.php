<?php

namespace App\Services\Updates;

interface UpdateCommandRunner
{
    /** @return array{successful: bool, exit_code: int, output: string} */
    public function run(array $command, int $timeout = 120): array;
}
