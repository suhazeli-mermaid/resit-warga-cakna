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
     * Mengemaskini peranan, jawatan dan kebenaran kewangan pengguna.
     */
    public function updateRole(Request $request, $id)
    {
        abort_unless($request->user()?->isAdmin(), 403);
        $user = User::findOrFail($id);

        $request->validate([
            'role' => 'required|in:admin,setiausaha,bendahari,ajk,penderma,user',
            'organization_position' => 'nullable|string|max:120',
            'can_create_receipt' => 'nullable|boolean',
            'can_create_voucher' => 'nullable|boolean',
            'can_approve_voucher' => 'nullable|boolean',
            'can_confirm_payment' => 'nullable|boolean',
            'can_manage_favourites' => 'nullable|boolean',
        ]);

        $user->update([
            'role' => $request->role,
            'organization_position' => filled($request->organization_position)
                ? trim($request->organization_position)
                : null,
            'can_create_receipt' => $request->boolean('can_create_receipt'),
            'can_create_voucher' => $request->boolean('can_create_voucher'),
            'can_approve_voucher' => $request->boolean('can_approve_voucher'),
            'can_confirm_payment' => $request->boolean('can_confirm_payment'),
            'can_manage_favourites' => $request->boolean('can_manage_favourites'),
        ]);

        return redirect()->back()->with('success', "Akaun dan kebenaran kewangan bagi {$user->name} berjaya dikemas kini.");
    }
}
