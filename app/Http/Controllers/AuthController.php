<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;

class AuthController extends Controller
{
    // Handle the Login Submission
    public function login(Request $request)
    {
        // 1. Get inputs
        $lastName = $request->input('last_name');
        $givenName = $request->input('given_name');
        $middleName = $request->input('middle_name');
        $dob = $request->input('dob'); // YYYY-MM-DD
        $password = $request->input('password');

        // 2. Find user in Database
        $query = DB::table('users')
            ->where('last_name', $lastName)
            ->where('first_name', $givenName)
            ->where('birthday', $dob)
            ->whereNull('deleted_at');

        // SAFELY check middle name: If they typed one, search for it. 
        // If they left it blank, look for NULL or empty in the database.
        if (!empty($middleName)) {
            $query->where('middle_name', $middleName);
        } else {
            $query->where(function($q) {
                $q->whereNull('middle_name')->orWhere('middle_name', '');
            });
        }

        $user = $query->first();

        // 3. Check if user exists and password is correct
        if ($user && Hash::check($password, $user->password)) {
            
            // Force role to lowercase just in case the database says 'Admin' instead of 'admin'
            $role = strtolower($user->role); 

            // Save user info to session
            Session::put('user_id', $user->id);
            Session::put('user_role', $role); 
            Session::put('user_name', $user->first_name);

            // 4. Redirect based on Role
            if (in_array($role, ['admin', 'super_admin', 'facilitator'])) {
                return redirect('/dashboard'); 
            } else {
                return redirect('/student/grade-selection');
            }
        }

        // 5. Login Failed
        return back()->withErrors(['message' => 'Invalid credentials. Please check your details.']);
    }

    public function logout() {
        Session::flush();
        return redirect('/');
    }
}