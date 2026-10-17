<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    public function updateRole(Request $request, $id)
    {
        // Hanya Admin Utama / wargacaknatrg@gmail.com dibenarkan menukar peranan
        if (!Auth::user()->isAdmin()) {
            return redirect()->back()->with('error', 'Anda tidak mempunyai kebenaran untuk menukar peranan pengguna!');
        }

        $user = User::findOrFail($id);

        $validated = $request->validate([
            'role' => 'required|in:penderma,ajk,admin',
        ]);

        $user->update(['role' => $validated['role']]);

        return redirect()->back()->with('success', "Peranan bagi {$user->name} berjaya dikemaskini kepada " . strtoupper($validated['role']) . '!');
    }
}