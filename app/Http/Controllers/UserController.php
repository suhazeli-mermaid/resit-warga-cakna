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
        $users = User::orderBy('created_at', 'desc')->paginate(15);
        return view('users.index', compact('users'));
    }

    /**
     * Mengemaskini Peranan (Role) AJK / Admin.
     */
    public function updateRole(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $request->validate([
            'role' => 'required|in:admin,ajk,user',
        ]);

        $user->update([
            'role' => $request->role,
        ]);

        return redirect()->back()->with('success', "Peranan pengguna {$user->name} berjaya dikemaskini kepada [" . strtoupper($request->role) . "].");
    }
}