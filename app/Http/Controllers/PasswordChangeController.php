<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class PasswordChangeController extends Controller
{
    /**
     * Redirect to appropriate dashboard (mandatory change flow removed).
     */
    public function show()
    {
        /** @var User|null $user */
        $user = Auth::user();
        if ($user) {
            if ($user->must_change_password) {
                $user->must_change_password = false;
                $user->save();
            }

            return match (strtolower($user->role)) {
                'admin' => redirect()->route('admin.dashboard'),
                'manager' => redirect()->route('manager.dashboard'),
                'cashier' => redirect()->route('cashier.dashboard'),
                'baker' => redirect()->route('admin.production'),
                default => redirect()->route('login'),
            };
        }

        return redirect()->route('login');
    }

    /**
     * Update the user password and remove the mandatory change flag.
     */
    public function update(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();

        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:6', 'confirmed', 'different:current_password'],
        ], [
            'password.different' => 'Your new password cannot be the same as your temporary password.',
            'current_password.current_password' => 'The provided current/temporary password does not match our records.',
        ]);

        $user->password = Hash::make($request->password);
        $user->must_change_password = false;
        $user->save();

        AuditLogService::log(
            'password_changed',
            "User '{$user->username}' successfully updated their temporary password",
            $user,
            ['ip' => $request->ip()]
        );

        $targetRoute = match (strtolower($user->role)) {
            'admin' => 'admin.dashboard',
            'manager' => 'manager.dashboard',
            'cashier' => 'cashier.dashboard',
            'baker' => 'admin.production',
            default => 'login',
        };

        return redirect()->route($targetRoute)->with('success', 'Your password has been changed successfully!');
    }
}
