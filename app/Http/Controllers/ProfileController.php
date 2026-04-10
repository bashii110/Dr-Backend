<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    // ── PUT /api/profile ──────────────────────────────────────────────────
    // Auth: update own name/email

    public function update(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'name'  => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $user->id,
        ]);

        $user->fill($request->only('name', 'email'))->save();

        return response()->json([
            'status'  => 200,
            'message' => 'Profile updated.',
            'user'    => $user,
        ]);
    }

    // ── POST /api/profile/change-password ─────────────────────────────────

    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'new_password'     => 'required|min:6',
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'status'  => 400,
                'message' => 'Current password is incorrect.',
            ]);
        }

        $user->update(['password' => Hash::make($request->new_password)]);

        // Revoke all tokens
        $user->tokens()->delete();

        return response()->json([
            'status'  => 200,
            'message' => 'Password changed. Please log in again.',
        ]);
    }
}