<?php

namespace App\Services\Updates;

class UpdateRuntimeMode
{
    private const PROCESS_FUNCTIONS = [
        'proc_open',
        'exec',
        'shell_exec',
        'system',
        'passthru',
        'popen',
    ];

    /**
     * @param  array<string, bool>|null  $availability
     * @return array{mode: string, safe_mode: bool, reason: string, unavailable_functions: array<int, string>, php_process_execution: bool}
     */
    public function status(?array $availability = null, ?string $disabledFunctions = null): array
    {
        $disabled = collect(explode(',', $disabledFunctions ?? (string) ini_get('disable_functions')))
            ->map(fn (string $function) => trim($function))
            ->filter()
            ->values()
            ->all();

        $unavailable = collect(self::PROCESS_FUNCTIONS)
            ->filter(function (string $function) use ($availability, $disabled): bool {
                $exists = $availability[$function] ?? function_exists($function);

                return ! $exists || in_array($function, $disabled, true);
            })
            ->values()
            ->all();

        return [
            'mode' => 'terminal',
            'safe_mode' => true,
            'reason' => $unavailable === []
                ? 'terminal_only_architecture'
                : 'php_process_functions_unavailable',
            'unavailable_functions' => $unavailable,
            'php_process_execution' => false,
        ];
    }
}
