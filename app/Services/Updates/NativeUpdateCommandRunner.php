<?php

namespace App\Services\Updates;

use Symfony\Component\Process\Process;

class NativeUpdateCommandRunner implements UpdateCommandRunner
{
    public function run(array $command, int $timeout = 120): array
    {
        $process = new Process($command, base_path());
        $process->setTimeout($timeout);
        $process->run();

        return [
            'successful' => $process->isSuccessful(),
            'exit_code' => $process->getExitCode() ?? 1,
            'output' => trim($process->getOutput()."\n".$process->getErrorOutput()),
        ];
    }
}
