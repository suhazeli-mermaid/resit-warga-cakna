<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\UpdateOperation;
use App\Services\Updates\SmartUpdateManager;
use Illuminate\Http\Request;
use Throwable;

class SmartUpdateController extends Controller
{
    public function __construct(private readonly SmartUpdateManager $manager) {}

    public function index(Request $request)
    {
        $this->admin($request);

        return view('admin.updates.index', [
            'enabled' => (bool) config('update-manager.enabled'),
            'operations' => UpdateOperation::query()->with('initiator')->latest()->paginate(15),
        ]);
    }

    public function show(Request $request, UpdateOperation $operation)
    {
        $this->admin($request);

        return view('admin.updates.show', compact('operation'));
    }

    public function check(Request $request)
    {
        $this->admin($request);

        return $this->attempt(fn () => redirect()->route('admin.updates.show', $this->manager->check($request->user())));
    }

    public function preflight(Request $request, UpdateOperation $operation)
    {
        $this->admin($request);

        return $this->attempt(function () use ($operation) {
            $operation = $this->manager->preflight($operation);

            return back()->with($operation->status === 'preflight_passed' ? 'success' : 'error', $operation->status === 'preflight_passed' ? 'Preflight lulus.' : 'Preflight gagal. Pemasangan disekat.');
        });
    }

    public function backup(Request $request, UpdateOperation $operation)
    {
        $this->admin($request);

        return $this->attempt(function () use ($operation) {
            $this->manager->backup($operation);

            return back()->with('success', 'Backup kod dan backup pangkalan data yang diperlukan telah disahkan.');
        });
    }

    public function install(Request $request, UpdateOperation $operation)
    {
        $this->admin($request);
        $request->validate([
            'password' => ['required', 'current_password'],
            'confirmation' => ['required', 'in:PASANG '.substr((string) $operation->target_commit, 0, 8)],
        ]);
        $operation = $this->manager->install($operation, $request->user());

        return back()->with(
            $operation->status === 'successful' ? 'success' : 'error',
            $operation->status === 'successful' ? 'UPDATE SUCCESSFUL' : 'UPDATE FAILED — pemulihan selamat diperlukan.',
        );
    }

    private function admin(Request $request): void
    {
        abort_unless($request->user()?->isAdmin(), 403);
    }

    private function attempt(callable $callback)
    {
        try {
            return $callback();
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', $e->getMessage());
        }
    }
}
