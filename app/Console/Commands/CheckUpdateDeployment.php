<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Throwable;

class CheckUpdateDeployment extends Command
{
    protected $signature = 'updates:post-deploy-check';

    protected $description = 'Run critical application checks without spawning operating-system processes';

    public function handle(Migrator $migrator): int
    {
        try {
            DB::select('select 1');
        } catch (Throwable) {
            $this->components->error('Database connectivity check failed.');

            return self::FAILURE;
        }

        if (! $migrator->repositoryExists()) {
            $this->components->error('Migration repository is unavailable.');

            return self::FAILURE;
        }

        $files = $migrator->getMigrationFiles(database_path('migrations'));
        $pending = array_diff(array_keys($files), $migrator->getRepository()->getRan());
        if ($pending !== []) {
            $this->components->error('Pending migrations remain after deployment.');

            return self::FAILURE;
        }

        foreach ((array) config('update-manager.critical_routes', []) as $route) {
            if (! is_string($route) || ! Route::has($route)) {
                $this->components->error('A required application route is unavailable.');

                return self::FAILURE;
            }
        }

        foreach ([storage_path(), storage_path('framework'), storage_path('logs'), base_path('bootstrap/cache')] as $path) {
            if (! is_dir($path) || ! is_writable($path)) {
                $this->components->error('A required Laravel runtime path is not writable.');

                return self::FAILURE;
            }
        }

        $this->components->info('Post-deployment checks passed.');

        return self::SUCCESS;
    }
}
