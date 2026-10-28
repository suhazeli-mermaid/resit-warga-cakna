<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * Memaparkan Admin Hub / Senarai Pengguna & AJK.
     */
    public function index()
    {
        abort_unless(request()->user()?->isAdmin(), 403);
        $users = User::orderBy('created_at', 'desc')->paginate(15);

        return view('users.index', compact('users'));
    }

    /**
     * Mengemaskini Peranan (Role) AJK / Admin.
     */
    public function updateRole(Request $request, $id)
    {
        abort_unless($request->user()?->isAdmin(), 403);
        $user = User::findOrFail($id);

        $request->validate([
            'role' => 'required|in:admin,setiausaha,bendahari,ajk,penderma,user',
            'organization_position' => 'nullable|string|max:120',
        ]);

        $user->update([
            'role' => $request->role,
            'organization_position' => filled($request->organization_position)
                ? trim($request->organization_position)
                : null,
        ]);

        return redirect()->back()->with('success', "Peranan dan jawatan Warga Cakna bagi {$user->name} berjaya dikemas kini.");
    }
}
