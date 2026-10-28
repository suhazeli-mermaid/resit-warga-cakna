<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\UpdateOperation;
use App\Services\Updates\RepositoryState;
use App\Services\Updates\ShellDeploymentHistory;
use App\Services\Updates\UpdateRuntimeMode;
use Illuminate\Http\Request;

class SmartUpdateController extends Controller
{
    public function __construct(
        private readonly UpdateRuntimeMode $runtimeMode,
        private readonly RepositoryState $repositoryState,
        private readonly ShellDeploymentHistory $shellHistory,
    ) {}

    public function index(Request $request)
    {
        $this->admin($request);

        return view('admin.updates.index', [
            'enabled' => (bool) config('update-manager.enabled'),
            'operations' => UpdateOperation::query()->with('initiator')->latest()->paginate(15),
            'runtime' => $this->runtimeMode->status(),
            'repository' => $this->repositoryState->inspect(),
            'shellDeployments' => $this->shellHistory->latest(),
            'terminalDirectory' => base_path(),
            'terminalCommand' => (string) config('update-manager.shell_command', 'bash deploy.sh'),
        ]);
    }

    public function show(Request $request, UpdateOperation $operation)
    {
        $this->admin($request);

        return view('admin.updates.show', [
            'operation' => $operation,
            'runtime' => $this->runtimeMode->status(),
            'terminalDirectory' => base_path(),
            'terminalCommand' => (string) config('update-manager.shell_command', 'bash deploy.sh'),
        ]);
    }

    private function admin(Request $request): void
    {
        abort_unless($request->user()?->isAdmin(), 403);
    }
}
