<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class CheckAdmin
{
    public function handle(Request $request, Closure $next)
    {
        // 1. Skip checks for public routes
        if ($request->is('/') || $request->is('login') || $request->is('logout')) {
            return $next($request);
        }

        // 2. Auth Check
        if (!Auth::check()) {
            return redirect('/login')->withErrors(['message' => 'Please login first.']);
        }

        $user = Auth::user();
        $userRole = strtolower($user->role);

        // Check if account is revoked (Soft Deleted)
        if ($user->trashed()) {
            Auth::logout();
            Session::flush();
            return redirect('/login')->withErrors(['message' => 'Your access has been revoked.']);
        }

        // 3. Path-Based Permission Check
        if ($request->is('student/*') || $request->is('api/*')) {
            // Students, Admins, and Facilitators can all access the kiosk
            if (!in_array($userRole, ['student', 'admin', 'super_admin', 'facilitator'])) {
                return redirect('/login')->withErrors(['message' => 'Unauthorized student access.']);
            }
        } else {
            // STRICT: Only Staff can access non-student routes (Dashboard, etc.)
            if (!in_array($userRole, ['admin', 'super_admin', 'facilitator'])) {
                // If a student tries to go to /dashboard, send them back to the kiosk
                return redirect(url('/student/grade-selection'))->withErrors(['message' => 'Access Denied.']);
            }

            // Role-based Restrictions
            if (in_array($userRole, ['admin', 'facilitator'])) {
                // Admins and Facilitators can access everything except Access Management
                if ($request->is('admin/accessmanagement*') || $request->is('admin/users/*')) {
                    $roleLabel = ($userRole === 'facilitator') ? 'Facilitators' : 'Admins';
                    return redirect('/dashboard')->withErrors(['message' => "$roleLabel do not have access to Access Management."]);
                }
            }
            // super_admin has no restrictions
        }

        return $next($request);
    }   
}