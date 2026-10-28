<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\UpdateOperation;
use Illuminate\Http\Request;

class SmartUpdateController extends Controller
{
    public function index(Request $request)
    {
        $this->admin($request);

        return view('admin.updates.index', [
            'operations' => UpdateOperation::query()->with('initiator')->latest()->paginate(15),
        ]);
    }

    public function show(Request $request, UpdateOperation $operation)
    {
        $this->admin($request);

        return view('admin.updates.show', compact('operation'));
    }

    private function admin(Request $request): void
    {
        abort_unless($request->user()?->isAdmin(), 403);
    }
}
