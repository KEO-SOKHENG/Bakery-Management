<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\PermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * Display a paginated listing of users with search, filtering, and summary statistics.
     */
    public function index(Request $request)
    {
        $query = User::query();

        // Search by name, username, email, or phone
        if ($request->filled('search')) {
            $search = trim($request->search);
            $like = \Illuminate\Support\Facades\DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where(function ($q) use ($search, $like) {
                $q->where('name', $like, "%{$search}%")
                  ->orWhere('username', $like, "%{$search}%")
                  ->orWhere('email', $like, "%{$search}%")
                  ->orWhere('phone', $like, "%{$search}%");
            });
        }

        // Filter by Role
        if ($request->filled('role') && in_array($request->role, ['admin', 'manager', 'cashier', 'baker'])) {
            $query->where('role', $request->role);
        }

        // Filter by Status
        if ($request->filled('status') && in_array($request->status, ['active', 'inactive', 'suspended'])) {
            $query->where('status', $request->status);
        }

        $users = $query->orderBy('id', 'desc')->get();

        // Calculate KPI Metrics
        $totalUsers = User::count();
        $activeUsers = User::where('status', 'active')->count();
        $inactiveUsers = User::where('status', 'inactive')->count();
        $suspendedUsers = User::where('status', 'suspended')->count();

        $modules = PermissionService::getAllModules();
        $roles = ['admin' => 'Admin', 'manager' => 'Manager', 'cashier' => 'Cashier', 'baker' => 'Baker'];
        $statuses = ['active' => 'Active', 'inactive' => 'Inactive', 'suspended' => 'Suspended'];

        return view('admin.users.index', compact(
            'users',
            'totalUsers',
            'activeUsers',
            'inactiveUsers',
            'suspendedUsers',
            'modules',
            'roles',
            'statuses'
        ));
    }

    /**
     * Display details and audit history for a specific user (supports JSON for modal views).
     */
    public function show(User $user, Request $request)
    {
        $user->loadCount(['orders', 'sales', 'productions', 'payments', 'assignedProductions', 'purchaseOrders', 'stockMovements']);
        
        $effectivePermissions = PermissionService::getEffectivePermissions($user);
        $roleDefaults = PermissionService::getRoleDefaults($user->role);
        $customOverrides = $user->custom_permissions ?? [];
        $modules = PermissionService::getAllModules();

        $recentAudits = AuditLog::where(function ($q) use ($user) {
            $q->where('user_id', $user->id)
              ->orWhere(function ($sub) use ($user) {
                  $sub->where('target_type', 'User')->where('target_id', $user->id);
              });
        })->orderBy('created_at', 'desc')->limit(20)->get();

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'user' => $user,
                'effective_permissions' => $effectivePermissions,
                'role_defaults' => $roleDefaults,
                'custom_overrides' => $customOverrides,
                'recent_audits' => $recentAudits,
            ]);
        }

        return view('admin.users.show', compact('user', 'effectivePermissions', 'roleDefaults', 'customOverrides', 'recentAudits', 'modules'));
    }

    /**
     * Store a newly created user account.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:100|unique:users,username',
            'email' => 'required|string|email|max:255|unique:users,email',
            'phone' => 'nullable|string|max:50',
            'password' => 'required|string|min:6',
            'role' => ['required', Rule::in(['admin', 'manager', 'cashier', 'baker'])],
            'status' => ['required', Rule::in(['active', 'inactive', 'suspended'])],
            'must_change_password' => 'nullable|boolean',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'username' => $validated['username'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'password' => Hash::make($validated['password']),
            'role' => strtolower($validated['role']),
            'status' => $validated['status'],
            'must_change_password' => $request->boolean('must_change_password'),
        ]);

        AuditLogService::log(
            'user_created',
            "Admin created new {$user->role} account '{$user->username}'",
            $user,
            [
                'name' => $user->name,
                'role' => $user->role,
                'status' => $user->status,
                'must_change_password' => $user->must_change_password,
            ]
        );

        return redirect()->route('admin.users.index')->with('success', "Staff account '{$user->username}' created successfully!");
    }

    /**
     * Update an existing user account.
     */
    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'username' => ['required', 'string', 'max:100', Rule::unique('users')->ignore($user->id)],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'phone' => 'nullable|string|max:50',
            'role' => ['required', Rule::in(['admin', 'manager', 'cashier', 'baker'])],
            'status' => ['required', Rule::in(['active', 'inactive', 'suspended'])],
            'password' => 'nullable|string|min:6',
            'must_change_password' => 'nullable|boolean',
        ]);

        $newRole = strtolower($validated['role']);
        $newStatus = $validated['status'];

        // Self-protection: Admin cannot deactivate or suspend their own account
        if ($user->id === Auth::id() && $newStatus !== 'active') {
            return redirect()->back()->with('error', 'You cannot deactivate or suspend your own administrator account.');
        }

        // Last active Admin protection: cannot demote last active admin
        if ($user->role === 'admin' && $newRole !== 'admin') {
            $otherActiveAdmins = User::where('role', 'admin')
                ->where('status', 'active')
                ->where('id', '!=', $user->id)
                ->count();

            if ($otherActiveAdmins === 0) {
                return redirect()->back()->with('error', 'Action prohibited: Cannot remove the Admin role from the last active Administrator.');
            }
        }

        // Last active Admin protection: cannot deactivate/suspend last active admin
        if ($user->role === 'admin' && $newStatus !== 'active') {
            $otherActiveAdmins = User::where('role', 'admin')
                ->where('status', 'active')
                ->where('id', '!=', $user->id)
                ->count();

            if ($otherActiveAdmins === 0) {
                return redirect()->back()->with('error', 'Action prohibited: Cannot deactivate or suspend the last active Administrator.');
            }
        }

        $oldValues = [
            'name' => $user->name,
            'role' => $user->role,
            'status' => $user->status,
            'email' => $user->email,
        ];

        $user->name = $validated['name'];
        $user->username = $validated['username'];
        $user->email = $validated['email'];
        $user->phone = $validated['phone'] ?? null;
        $user->role = $newRole;
        $user->status = $newStatus;

        $user->must_change_password = $request->boolean('must_change_password');

        if (!empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();

        AuditLogService::log(
            'user_updated',
            "Admin updated user account '{$user->username}'",
            $user,
            [
                'old' => $oldValues,
                'new' => [
                    'name' => $user->name,
                    'role' => $user->role,
                    'status' => $user->status,
                    'email' => $user->email,
                ]
            ]
        );

        return redirect()->route('admin.users.index')->with('success', "User '{$user->username}' updated successfully!");
    }

    /**
     * Safely update user account status (active, inactive, suspended).
     */
    public function updateStatus(Request $request, User $user)
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['active', 'inactive', 'suspended'])],
        ]);

        $targetStatus = $validated['status'];

        // Self-protection: Admin cannot deactivate or suspend own account
        if ($user->id === Auth::id() && $targetStatus !== 'active') {
            return redirect()->back()->with('error', 'You cannot deactivate or suspend your own account.');
        }

        // Last active Admin protection
        if ($user->role === 'admin' && $targetStatus !== 'active') {
            $otherActiveAdmins = User::where('role', 'admin')
                ->where('status', 'active')
                ->where('id', '!=', $user->id)
                ->count();

            if ($otherActiveAdmins === 0) {
                return redirect()->back()->with('error', 'Action prohibited: Cannot deactivate or suspend the last active Administrator.');
            }
        }

        $oldStatus = $user->status;
        $user->status = $targetStatus;
        $user->save();

        AuditLogService::log(
            'user_status_changed',
            "Status for '{$user->username}' changed from {$oldStatus} to {$targetStatus}",
            $user,
            ['old_status' => $oldStatus, 'new_status' => $targetStatus]
        );

        return redirect()->route('admin.users.index')->with('success', "Account status for '{$user->username}' updated to " . ucfirst($targetStatus) . ".");
    }

    /**
     * Legacy toggle status between active and inactive.
     */
    public function toggleStatus(User $user)
    {
        $newStatus = $user->status === 'active' ? 'inactive' : 'active';
        $request = new Request(['status' => $newStatus]);
        return $this->updateStatus($request, $user);
    }

    /**
     * Admin password reset with optional temporary password and force change flag.
     */
    public function resetPassword(Request $request, User $user)
    {
        $validated = $request->validate([
            'password' => 'required|string|min:6',
            'must_change_password' => 'nullable|boolean',
        ]);

        $mustChange = $request->boolean('must_change_password', false);

        $user->password = Hash::make($validated['password']);
        $user->must_change_password = $mustChange;
        $user->save();

        AuditLogService::log(
            'password_reset',
            "Admin reset password for user '{$user->username}'" . ($mustChange ? ' (force_change=yes)' : ''),
            $user,
            ['must_change_password' => $mustChange]
        );

        $notice = $mustChange
            ? "Password reset successfully for '{$user->username}'. User will be forced to change password on next login."
            : "Password reset successfully for '{$user->username}'.";

        return redirect()->route('admin.users.index')->with('success', $notice);
    }

    /**
     * Retrieve user permissions data for modal inspection.
     */
    public function permissions(User $user)
    {
        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'role' => $user->role,
            ],
            'modules' => PermissionService::getAllModules(),
            'role_defaults' => PermissionService::getRoleDefaults($user->role),
            'custom_overrides' => $user->custom_permissions ?? (object)[],
            'effective_permissions' => PermissionService::getEffectivePermissions($user),
        ]);
    }

    /**
     * Update custom permission overrides for a user.
     */
    public function updatePermissions(Request $request, User $user)
    {
        if ($user->isAdmin()) {
            return redirect()->back()->with('info', 'Administrators natively hold all system permissions and do not require custom overrides.');
        }

        $submittedOverrides = $request->input('permissions', []);
        $validKeys = PermissionService::getAllPermissionKeys();
        $roleDefaults = PermissionService::getRoleDefaults($user->role);
        $cleanOverrides = [];

        // Check if caller explicitly passed a key-value boolean map (e.g. from API/test)
        $hasExplicitFalse = false;
        foreach ($submittedOverrides as $v) {
            if ($v === false || $v === 0 || $v === '0' || $v === 'false') {
                $hasExplicitFalse = true;
                break;
            }
        }

        if ($hasExplicitFalse) {
            foreach ($validKeys as $key) {
                if (isset($submittedOverrides[$key])) {
                    $cleanOverrides[$key] = filter_var($submittedOverrides[$key], FILTER_VALIDATE_BOOLEAN);
                }
            }
        } else {
            // Form submission from permissions modal (unchecked checkboxes are omitted)
            foreach ($validKeys as $key) {
                $isChecked = isset($submittedOverrides[$key]) && filter_var($submittedOverrides[$key], FILTER_VALIDATE_BOOLEAN);
                $isDefault = in_array($key, $roleDefaults);

                if ($isChecked && !$isDefault) {
                    // Explicit grant override
                    $cleanOverrides[$key] = true;
                } elseif (!$isChecked && $isDefault) {
                    // Explicit revocation override
                    $cleanOverrides[$key] = false;
                }
            }
        }

        $user->custom_permissions = !empty($cleanOverrides) ? $cleanOverrides : null;
        $user->save();

        AuditLogService::log(
            'permissions_updated',
            "Custom permissions updated for '{$user->username}'",
            $user,
            ['custom_permissions' => $user->custom_permissions]
        );

        return redirect()->route('admin.users.index')->with('success', "Permissions updated successfully for '{$user->username}'!");
    }

    /**
     * Delete a user account (strictly protected against self-deletion, last-admin deletion, and historical integrity).
     */
    public function destroy(User $user)
    {
        // Self-protection
        if ($user->id === Auth::id()) {
            return redirect()->back()->with('error', 'You cannot delete your own account.');
        }

        // Last active Admin protection
        if ($user->role === 'admin') {
            $otherActiveAdmins = User::where('role', 'admin')
                ->where('status', 'active')
                ->where('id', '!=', $user->id)
                ->count();

            if ($otherActiveAdmins === 0) {
                return redirect()->back()->with('error', 'Action prohibited: Cannot delete the last active Administrator account.');
            }
        }

        // Disassociate user from historical business transactions so sales/orders/productions remain intact
        \App\Models\Order::where('user_id', $user->id)->update(['user_id' => null]);
        \App\Models\Sale::where('user_id', $user->id)->update(['user_id' => null]);
        \App\Models\Production::where('user_id', $user->id)->update(['user_id' => null]);
        \App\Models\Production::where('baker_id', $user->id)->update(['baker_id' => null]);
        \App\Models\Payment::where('user_id', $user->id)->update(['user_id' => null]);
        \App\Models\PurchaseOrder::where('user_id', $user->id)->update(['user_id' => null]);
        \App\Models\StockMovement::where('user_id', $user->id)->update(['user_id' => null]);

        // Clean up staff personal HR records
        \App\Models\Attendance::where('user_id', $user->id)->delete();
        \App\Models\WorkSchedule::where('user_id', $user->id)->delete();
        \App\Models\Salary::where('user_id', $user->id)->delete();
        \App\Models\Notification::where('user_id', $user->id)->delete();

        $username = $user->username;
        $userId = $user->id;
        $user->delete();

        AuditLogService::log(
            'user_deleted',
            "Deleted user account '{$username}' (ID: {$userId})",
            null,
            ['deleted_user_id' => $userId, 'username' => $username]
        );

        return redirect()->route('admin.users.index')->with('success', "Staff account '{$username}' deleted successfully. All associated business records have been preserved.");
    }
}
