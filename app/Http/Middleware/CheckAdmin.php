<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\DB;

class CheckAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $userId = Session::get('user_id');
        $userRole = Session::get('user_role');

        // 1. Updated session check to include super_admin
        if (!$userId || !in_array($userRole, ['admin', 'super_admin'])) {
            return redirect('/')->withErrors(['message' => 'Unauthorized access.']);
        }

        // 2. Real-time Database Check (The "Kill Switch")
        $user = DB::table('users')
                    ->where('id', $userId)
                    ->whereNull('deleted_at')
                    ->first();

        if (!$user) {
            Session::flush();
            return redirect('/')->withErrors(['message' => 'Your access has been revoked.']);
        }

        return $next($request);
    }
}