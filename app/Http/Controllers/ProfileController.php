<?php

namespace App\Http\Controllers;

use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    /**
     * Show the authenticated user's self-service profile.
     */
    public function index()
    {
        $user = Auth::user();
        return view('profile.index', compact('user'));
    }

    /**
     * Update the authenticated user's personal details and/or password.
     */
    public function update(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $rules = [
            'name'  => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id), new \App\Rules\ReservedOwnerEmail($user->id)],
            'phone' => ['nullable', 'string', 'max:30'],
        ];

        if ($request->filled('password') || $request->filled('current_password')) {
            $rules['current_password'] = ['required', 'current_password'];
            $rules['password'] = ['required', 'string', 'min:6', 'confirmed', 'different:current_password'];
        }

        $messages = [
            'current_password.current_password' => 'The provided current password does not match our records.',
            'password.different' => 'The new password must be different from your current password.',
        ];

        $validated = $request->validate($rules, $messages);

        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->phone = $validated['phone'] ?? null;

        if (!empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
            $user->must_change_password = false;
        }

        $user->save();

        AuditLogService::log(
            'profile_updated',
            "User '{$user->username}' updated their profile" . (!empty($validated['password']) ? ' and password' : ''),
            $user,
            ['ip' => $request->ip()]
        );

        return redirect()->route('profile')->with('success', 'Profile updated successfully!');
    }
}

