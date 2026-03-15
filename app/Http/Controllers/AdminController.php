<?php

namespace App\Http\Controllers;

use Google\Client; 
use App\Models\User;
use Google\Service\Sheets; 
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class AdminController extends Controller
{
    public function showLoginForm()
    {
        return view('login');
    }

    public function checkUserStatus($id)
    {
        $isOnline = \Illuminate\Support\Facades\Cache::has('user-is-online-' . $id);
        
        return response()->json([
            'online' => $isOnline
        ]);
    }

    // 1. DASHBOARD LOGIC
    public function index(Request $request) 
    {
        $grade = $request->grade_level;

        /**
         * Reusable filter for general counts.
         * Note: This still handles the fallback logic for general registration counts.
         */
        $applyFilter = function($query) use ($grade) {
            if (!empty($grade)) {
                $query->where(function($q) use ($grade) {
                    $q->where('kiosk_enrollments.grade_level', '=', $grade)
                    ->orWhere(function($sq) use ($grade) {
                        $sq->whereNull('kiosk_enrollments.grade_level')
                            ->where('pre_enrollments.responses', 'like', '%"Grade Level to Enroll":"' . $grade . '"%');
                    });
                });
            }
            return $query;
        };

        // --- Core Enrollment Stats ---

        $totalRegistrations = $applyFilter(DB::table('students')
            ->join('users', 'students.user_id', '=', 'users.id') 
            ->whereNull('users.deleted_at')
            ->leftJoin('kiosk_enrollments', 'users.id', '=', 'kiosk_enrollments.id')
            ->leftJoin('pre_enrollments', 'students.lrn', '=', 'pre_enrollments.student_lrn'))
            ->count();

        $totalSubmissions = $applyFilter(DB::table('students')
            ->join('users', 'students.user_id', '=', 'users.id')
            ->whereNull('users.deleted_at')
            ->join('kiosk_enrollments', 'users.id', '=', 'kiosk_enrollments.id')
            ->leftJoin('pre_enrollments', 'students.lrn', '=', 'pre_enrollments.student_lrn'))
            ->count();

        $totalEnrolled = $applyFilter(DB::table('students')
            ->join('users', 'students.user_id', '=', 'users.id')
            ->whereNull('users.deleted_at')
            ->join('kiosk_enrollments', 'users.id', '=', 'kiosk_enrollments.id')
            ->leftJoin('pre_enrollments', 'students.lrn', '=', 'pre_enrollments.student_lrn')
            ->where('kiosk_enrollments.academic_status', '=', 'Officially Enrolled')) 
            ->count();

        $max = $totalRegistrations > 0 ? $totalRegistrations : 1;
        $percVerified = ($totalSubmissions / $max) * 100;
        $percEnrolled = ($totalEnrolled / $max) * 100;

        // --- Refactored Elective Counting (Exclusive to kiosk_enrollments.cluster) ---

        $rawCounts = DB::table('kiosk_enrollments')
            ->join('users', 'kiosk_enrollments.id', '=', 'users.id')
            ->whereNull('users.deleted_at')
            ->when(!empty($grade), function($query) use ($grade) {
                return $query->where('kiosk_enrollments.grade_level', $grade);
            })
            ->whereIn('cluster', ['STEM', 'ASSH', 'BE', 'TechPro'])
            ->select('cluster', DB::raw('count(*) as count'))
            ->groupBy('cluster')
            ->pluck('count', 'cluster')
            ->toArray();

        // Mapping to ensure all keys exist for the frontend even if count is 0
        $electiveCounts = [
            'STEM'    => $rawCounts['STEM'] ?? 0,
            'ASSH'    => $rawCounts['ASSH'] ?? 0,
            'BE'      => $rawCounts['BE'] ?? 0,
            'TechPro' => $rawCounts['TechPro'] ?? 0
        ];

        // --- Recent Submissions Logic ---

        $recentKioskQuery = DB::table('kiosk_enrollments')
            ->join('users', 'kiosk_enrollments.id', '=', 'users.id')
            ->whereNull('users.deleted_at')
            ->select(
                'users.first_name', 'users.middle_name', 'users.last_name',
                'users.extension_name', 'kiosk_enrollments.grade_level',
                'kiosk_enrollments.track', 'kiosk_enrollments.cluster',
                'kiosk_enrollments.completed_at', 'kiosk_enrollments.academic_status as status'
            );

        if (!empty($grade)) {
            $recentKioskQuery->where('kiosk_enrollments.grade_level', $grade);
        }

        $recentKioskSubmissions = $recentKioskQuery->orderBy('kiosk_enrollments.completed_at', 'desc')->limit(5)->get();

        // --- UI Helpers & Formatting ---

        $lastSync = DB::table('sync_histories')->where('status', 'Success')->latest()->first();
        $lastSyncTime = $lastSync ? \Carbon\Carbon::parse($lastSync->created_at)->diffForHumans() : 'Never';

        // Calculation for Donut Chart Gradient
        $totalElectives = array_sum($electiveCounts) ?: 1;
        $pSTEM = ($electiveCounts['STEM'] / $totalElectives) * 100;
        $pASSH = ($electiveCounts['ASSH'] / $totalElectives) * 100;
        $pBE   = ($electiveCounts['BE'] / $totalElectives) * 100;
        $pTech = ($electiveCounts['TechPro'] / $totalElectives) * 100;

        $stop1 = $pSTEM;
        $stop2 = $stop1 + $pASSH;
        $stop3 = $stop2 + $pBE;

        $donutGradient = "conic-gradient(
            #00568d 0% {$stop1}%, 
            #00897b {$stop1}% {$stop2}%, 
            #1a8a44 {$stop2}% {$stop3}%, 
            #facc15 {$stop3}% 100%
        )";

        $data = compact(
                    'totalRegistrations', 'totalSubmissions', 'totalEnrolled',
                    'percVerified', 'percEnrolled', 'electiveCounts',
                    'donutGradient', 'lastSyncTime', 'recentKioskSubmissions'
                );

                // Check if the request is an AJAX call (from your new buttons)
                if ($request->ajax()) {
                    return view('admin.dashboardpage.partials._dashboard_wrapper', $data)->render();
                }

                // Standard load
                return view('admin.dashboardpage.dashboard', $data);
    }

    // 2. STUDENT LIST LOGIC
    public function students(Request $request)
    {
        $query = DB::table('users')
            ->leftJoin('students', 'users.id', '=', 'students.user_id')
            ->whereNull('users.deleted_at')
            ->leftJoin('pre_enrollments', 'students.lrn', '=', 'pre_enrollments.student_lrn')
            ->leftJoin('kiosk_enrollments', 'users.id', '=', 'kiosk_enrollments.id')
            ->where('users.role', 'student')
            ->select(
                'users.first_name', 'users.last_name', 'users.middle_name',
                'users.created_at', 'users.id as user_primary_id',
                'students.lrn', 'users.extension_name',
                'pre_enrollments.responses',
                'kiosk_enrollments.grade_level as kiosk_grade',
                'kiosk_enrollments.track as kiosk_track',
                'kiosk_enrollments.cluster as kiosk_cluster',
                'kiosk_enrollments.academic_status as kiosk_status'
            );

        if ($request->filled('search')) {
            $searchTerm = trim($request->search);
            $query->where(function($q) use ($searchTerm) {
                $q->where('users.first_name', 'like', "%{$searchTerm}%")
                ->orWhere('users.last_name', 'like', "%{$searchTerm}%")
                ->orWhere('users.middle_name', 'like', "%{$searchTerm}%")
                ->orWhere('students.lrn', 'like', "%{$searchTerm}%");
            });
        }

        $filters = [
            'student_type' => ['kiosk' => 'kiosk_enrollments.academic_status', 'json' => 'Academic Status'],
            'grade_level'  => ['kiosk' => 'kiosk_enrollments.grade_level',     'json' => 'Grade Level to Enroll'],
            'track'        => ['kiosk' => 'kiosk_enrollments.track',           'json' => 'Track'],
            'cluster'      => ['kiosk' => 'kiosk_enrollments.cluster',         'json' => 'Cluster of Electives']
        ];

        $fullClusterNames = [
            'STEM'    => 'Science, Technology, Engineering, and Mathematics (STEM)',
            'BE'      => 'Business and Entrepreneurship (BE)',
            'ASSH'    => 'Arts, Social Sciences, and Humanities (ASSH)',
            'TechPro' => 'Technical-Vocational-Livelihood (TVL)'
        ];

        foreach ($filters as $requestKey => $keys) {
            if ($request->filled($requestKey)) {
                $val = $request->$requestKey;
                $query->where(function($q) use ($keys, $val, $requestKey, $fullClusterNames) {
                    $q->where($keys['kiosk'], '=', $val);
                    $q->orWhere(function($sq) use ($keys, $val, $requestKey, $fullClusterNames) {
                        $searchString = ($requestKey === 'cluster' && isset($fullClusterNames[$val])) 
                            ? $fullClusterNames[$val] 
                            : $val;

                        $sq->whereNull($keys['kiosk'])
                        ->where('pre_enrollments.responses', 'like', '%"' . $keys['json'] . '":"' . $searchString . '"%');
                    });
                });
            }
        }

        if ($request->filled('status')) {
            $status = $request->status;
            if ($status === 'Registered') {
                $query->whereNull('kiosk_enrollments.grade_level');
            } elseif ($status === 'Document Verified') {
                $query->whereNotNull('kiosk_enrollments.grade_level');
            }
        }

        switch ($request->get('sort')) {
            case 'za': $query->orderBy('users.last_name', 'desc'); break;
            case 'newest': $query->orderBy('users.created_at', 'desc'); break;
            case 'oldest': $query->orderBy('users.created_at', 'asc'); break;
            default: $query->orderBy('users.last_name', 'asc'); break;
        }

        $students = $query->get()->map(function($student) {
            $raw = json_decode($student->responses, true) ?? [];
            $details = [];
            foreach ($raw as $key => $value) $details[trim($key)] = $value;

            $acronyms = [
                'Science, Technology, Engineering, and Mathematics (STEM)' => 'STEM',
                'Business and Entrepreneurship (BE)' => 'BE',
                'Arts, Social Sciences, and Humanities (ASSH)' => 'ASSH',
                'Technical-Vocational-Livelihood (TVL)' => 'TechPro'
            ];

            $jsonCluster = $details['Cluster of Electives'] ?? '—';

            $student->display_grade   = $student->kiosk_grade   ?? ($details['Grade Level to Enroll'] ?? '—');
            $student->display_track   = $student->kiosk_track   ?? ($details['Track'] ?? '—');
            $student->display_status  = $student->kiosk_status  ?? ($details['Academic Status'] ?? '—');
            $student->display_cluster = $student->kiosk_cluster ?? ($acronyms[$jsonCluster] ?? $jsonCluster);

            if (!empty($student->kiosk_grade)) {
                $student->enrollment_category = 'Document Verified';
                $student->status_style = 'bg-[#00923F] text-white border-green-200';
            } else {
                $student->enrollment_category = 'Registered';
                $student->status_style = 'bg-[#048F81] text-white border-[#048F81]';
            }
            return $student;
        });

        if ($request->ajax()) return view('admin.studentpage.partials.student-table', compact('students'))->render();
        return view('admin.studentpage.students', compact('students'));
    }

    // 3. STUDENT PROFILE LOGIC
    public function profilepage($lrn)
    {
        $student = DB::table('students')
            ->join('users', 'students.user_id', '=', 'users.id')
            ->whereNull('users.deleted_at')
            ->leftJoin('pre_enrollments', 'students.lrn', '=', 'pre_enrollments.student_lrn')
            ->leftJoin('kiosk_enrollments', 'users.id', '=', 'kiosk_enrollments.id') 
            ->select(
                'users.first_name', 'users.last_name','users.extension_name', 'users.middle_name', 'users.birthday', 
                'students.*', 'pre_enrollments.responses',
                'kiosk_enrollments.grade_level as kiosk_grade', 'kiosk_enrollments.track as kiosk_track',
                'kiosk_enrollments.cluster as kiosk_cluster', 'kiosk_enrollments.academic_status as kiosk_status'
            )
            ->where('students.lrn', $lrn)
            ->first();

        if (!$student) abort(404);

        $rawDetails = json_decode($student->responses, true) ?? [];
        $details = [];
        foreach ($rawDetails as $key => $value) $details[trim($key)] = $value;

        $finalGrade   = $student->kiosk_grade   ?? ($details['Grade Level to Enroll'] ?? '—');
        $finalTrack   = $student->kiosk_track   ?? ($details['Track'] ?? '—');
        $finalCluster = $student->kiosk_cluster ?? ($details['Cluster of Electives'] ?? '—');
        $finalStatus  = $student->kiosk_status  ?? ($details['Academic Status'] ?? '—');

        $fixedKeys = [
            'School Year', 'Grade Level to Enroll', 'Track', 'Cluster of Electives', 
            'Academic Status', 'Last School Year Completed', 'Last Grade Level Completed', 
            'Last School Attended', 'School ID'
        ];
        $dynamicDetails = array_diff_key($details, array_flip($fixedKeys));

        return view('admin.studentpage.profilepage', compact(
            'student', 'details', 'dynamicDetails', 
            'finalGrade', 'finalTrack', 'finalCluster', 'finalStatus'
        ));
    }

    // 4. SYNC AND UTILITY LOGIC
    public function systemsync()
    {
        $syncHistory = DB::table('sync_histories')->orderBy('created_at', 'desc')->get();
        $lastSync = DB::table('sync_histories')->where('status', 'Success')->latest()->first();

        $isConnected = false;
        try {
            $client = new \Google\Client();
            $client->setAuthConfig(storage_path('app/google-credentials.json'));
            $client->addScope(\Google\Service\Sheets::SPREADSHEETS_READONLY);
            
            $service = new \Google\Service\Sheets($client);
            $spreadsheetId = '1pUdqUbAMQEZ4Kg2V6A05orHY9xnDCJLp2QWLQaXXmSk';
            $service->spreadsheets->get($spreadsheetId);
            $isConnected = true;
        } catch (\Exception $e) {
            $isConnected = false;
        }

        $formUrl = "https://forms.gle/7wrtrGWf2nDCWcz9A";

        return view('admin.systemsync', compact('syncHistory', 'lastSync', 'formUrl', 'isConnected'));
    }

    // 5. ADMIN MANUAL VERIFICATION LOGIC
    public function verification()
    { 
        $enrollments = DB::table('kiosk_enrollments')
            ->join('users', 'kiosk_enrollments.id', '=', 'users.id')
            ->whereNull('users.deleted_at')
            ->leftJoin('students', 'users.id', '=', 'students.user_id')
            ->leftJoin('pre_enrollments as pe', 'students.lrn', '=', 'pe.student_lrn')
            ->select(
                'kiosk_enrollments.*', 
                'users.first_name', 
                'users.last_name',
                'pe.responses'
            )
            ->where(function($q) {
                $q->where('sf9_status', 'manual_verification')
                  ->orWhere('psa_status', 'manual_verification')
                  ->orWhere('enroll_form_status', 'manual_verification')
                  ->orWhere('als_cert_status', 'manual_verification')
                  ->orWhere('affidavit_status', 'manual_verification');
            })
            ->get();

        $pendingScans = collect();

        foreach ($enrollments as $row) {
            $docTypes = [
                'sf9' => 'Report Card (SF9)',
                'psa' => 'Birth Certificate',
                'enroll_form' => 'Enrollment Form',
                'als_cert' => 'ALS Certificate',
                'affidavit' => 'Affidavit'
            ];

            foreach ($docTypes as $prefix => $docName) {
                $statusCol = "{$prefix}_status";
                if ($row->$statusCol === 'manual_verification') {
                    $pathCol = "{$prefix}_path";
                    
                    $details = json_decode($row->responses, true) ?? [];
                    $displayGrade = $row->grade_level ?? ($details['Grade Level to Enroll'] ?? '—');

                    $pendingScans->push((object)[
                        'id' => $row->id . ':' . $prefix, 
                        'first_name' => $row->first_name,
                        'last_name' => $row->last_name,
                        'display_grade' => $displayGrade,
                        'document_type' => $docName,
                        'file_path' => $row->$pathCol,
                        'created_at' => $row->updated_at 
                    ]);
                }
            }
        }

        if (request()->ajax()) {
            return view('admin.partials.verification-table', compact('pendingScans'))->render();
        }

        return view('admin.verification', compact('pendingScans')); 
    }

    public function handleVerificationAction(Request $request) 
    {
        $idAndPrefix = $request->input('scan_id'); // Format: "userId:prefix"
        $action = $request->input('action'); 
        
        $parts = explode(':', $idAndPrefix);
        if (count($parts) !== 2) return back()->with('error', 'Invalid verification action.');
        
        $userId = $parts[0];
        $prefix = $parts[1];

        $finalStatus = ($action === 'approve') ? 'verified' : 'failed';

        DB::table('kiosk_enrollments')->where('id', $userId)->update([
            "{$prefix}_status" => $finalStatus,
            "{$prefix}_remarks" => 'Manually ' . $action . 'd by Admin',
            'latest_scan_status' => $finalStatus,
            'latest_scan_remarks' => 'Manually ' . $action . 'd by Admin'
        ]);

        return back()->with('success', 'Document has been ' . $action . 'd.');
    }

    public function requirementhub(){ return view('admin.requirementhub');}
    public function accountsettings(){ return view('admin.account_settings.accountsettings'); }

    // 6. ACCESS MANAGEMENT LOGIC
    public function accessManagement(Request $request)
    {
        $activeTab = $request->get('role', 'All');

        if ($activeTab === 'Archived') {
            $staff = User::onlyTrashed()
                ->whereIn('role', ['super_admin', 'admin', 'facilitator'])
                ->get();
        } else {
            $query = User::query()->whereIn('role', ['super_admin', 'admin', 'facilitator']);

            if ($activeTab !== 'All') {
                $roleMap = [
                    'Administrator' => 'admin',
                    'Facilitator'   => 'facilitator'
                ];
                $targetRole = $roleMap[$activeTab] ?? strtolower($activeTab);
                $query->where('role', $targetRole);
            }
            $staff = $query->get();
        }

        return view('admin.accessmanagement_page.accessmanagement', compact('staff'));
    }

    public function storeUser(Request $request)
    {
        $validated = $request->validate([
            'first_name'     => 'required|string|max:255', 
            'last_name'      => 'required|string|max:255',
            'middle_name'    => 'nullable|string|max:255',
            'extension_name' => 'nullable|string|max:10',
            'birthday'       => 'required|date',
            'role'           => 'required|string|in:admin,administrator,facilitator,super_admin',
            'password'       => 'sometimes|nullable|string|min:8',
        ]);

        $roleMap = [
            'admin'         => 'admin',
            'administrator' => 'admin',
            'facilitator'   => 'facilitator',
            'super_admin'   => 'super_admin'
        ];

        $finalRole = $roleMap[strtolower($validated['role'])] ?? 'facilitator';

        $user = User::withTrashed()
            ->where('first_name', $validated['first_name'])
            ->where('last_name', $validated['last_name'])
            ->where('birthday', $validated['birthday'])
            ->first();

        if ($user) {
            if ($user->trashed()) {
                $user->restore();
                $statusMessage = 'User found in archives and access has been restored!';
            } else {
                return back()->with('info', 'This user is already active in the system.');
            }

            $updateData = [
                'middle_name'    => $validated['middle_name'],
                'extension_name' => $validated['extension_name'],
                'role'           => $finalRole,
            ];

            if ($request->filled('password')) {
                $updateData['password'] = Hash::make($request->password);
            }

            $user->update($updateData);

        } else {
            if (!$request->filled('password')) {
                return back()->withErrors(['password' => 'A password is required for new users.']);
            }

            User::create([
                'first_name'     => $validated['first_name'],
                'last_name'      => $validated['last_name'],
                'middle_name'    => $validated['middle_name'],
                'extension_name' => $validated['extension_name'],
                'birthday'       => $validated['birthday'],
                'role'           => $finalRole,
                'password'       => Hash::make($request->password),
            ]);
            $statusMessage = 'New staff member added successfully!';
        }

        return redirect()->route('admin.accessmanagement')->with('success', $statusMessage);
    }

    public function restoreUser($id)
    {
        User::onlyTrashed()->findOrFail($id)->restore();
        return back()->with('success', 'User access restored!');
    }

    public function destroy($id)
    {
        $user = User::findOrFail($id);

        if (Auth::id() == $user->id) {
            return back()->with('error', 'You cannot revoke your own access.');
        }

        $user->delete(); 

        return back()->with('success', 'User access has been revoked successfully.');
    }

    public function updateRole(Request $request, $id)
    {
        $user = User::findOrFail($id);

        if (Auth::id() == $user->id) {
            return back()->with('error', 'You cannot change your own role.');
        }

        $validated = $request->validate([
            'role' => 'required|in:admin,facilitator,super_admin',
        ]);

        $user->update(['role' => $validated['role']]);

        return back()->with('success', "Role updated successfully.");
    }

    // 7. SYNC LOGIC
    public function performSync() {
        set_time_limit(300); 
        
        $spreadsheetId = '1pUdqUbAMQEZ4Kg2V6A05orHY9xnDCJLp2QWLQaXXmSk'; 
        $range = 'Form_Responses2!A1:ZZ'; 

        try {
            $client = new \Google\Client(); 
            $client->setAuthConfig(storage_path('app/google-credentials.json'));
            $client->addScope(\Google\Service\Sheets::SPREADSHEETS_READONLY);

            $service = new \Google\Service\Sheets($client);
            $spreadsheetId = '1pUdqUbAMQEZ4Kg2V6A05orHY9xnDCJLp2QWLQaXXmSk'; 
            $response = $service->spreadsheets_values->get($spreadsheetId, $range);
            $values = $response->getValues();

            if (empty($values)) {
                return back()->with('info', 'The Google Sheet is currently empty.');
            }

            $headers = $values[0];
            $dataRows = array_slice($values, 1);
            $newCount = 0;
            $updatedCount = 0;

            foreach ($dataRows as $row) { 
                $row = array_pad($row, count($headers), null);
                if (empty(array_filter($row))) continue; 
                if (empty(trim($row[1] ?? '')) || empty(trim($row[2] ?? '')) || empty(trim($row[3] ?? '')) || empty(trim($row[6] ?? ''))) continue; 

                try {
                    $formattedDob = Carbon::parse($row[6])->format('Y-m-d');
                } catch (\Exception $e) { continue; }

                $lrn = trim($row[1]);

                DB::transaction(function () use ($row, $headers, $formattedDob, $lrn, &$newCount, &$updatedCount) {
                    $existingStudent = DB::table('students')
                        ->join('users', 'students.user_id', '=', 'users.id')
                        ->where('students.lrn', $lrn)
                        ->whereNull('users.deleted_at')
                        ->select('students.*', 'users.id as active_user_id')
                        ->first();

                    $userId = $existingStudent ? $existingStudent->active_user_id : null;

                    $dynamicResponses = [];
                    for ($i = 41; $i < count($headers); $i++) {
                        $question = $headers[$i] ?? "Field " . $i;
                        $dynamicResponses[$question] = $row[$i] ?? null;
                    }
                    $newJson = json_encode($dynamicResponses);

                    $incomingStudentData = [
                        'sex'                   => $row[9] ?? null,
                        'age'                   => is_numeric($row[8]) ? (int)$row[8] : null,
                        'place_of_birth'        => $row[7] ?? null,
                        'mother_tongue'         => $row[10] ?? null,
                        'curr_house_number'     => $row[11] ?? null,
                        'curr_street'           => $row[12] ?? null,
                        'curr_barangay'         => $row[13] ?? null,
                        'curr_city'             => $row[14] ?? null,
                        'curr_province'         => $row[15] ?? null,
                        'curr_zip_code'         => $row[17] ?? null,
                        'is_perm_same_as_curr'  => (isset($row[19]) && $row[19] == 'Yes') ? 1 : 0,
                        'perm_house_number'     => $row[20] ?? null,
                        'perm_street'           => $row[21] ?? null,
                        'perm_barangay'         => $row[22] ?? null,
                        'perm_city'             => $row[23] ?? null,
                        'perm_province'         => $row[24] ?? null,
                        'perm_zip_code'         => $row[26] ?? null,
                        'father_last_name'      => $row[29] ?? null,
                        'father_first_name'     => $row[30] ?? null,
                        'father_middle_name'    => $row[31] ?? null,
                        'father_contact_number' => $row[32] ?? null,
                        'mother_last_name'      => $row[33] ?? null,
                        'mother_first_name'     => $row[34] ?? null,
                        'mother_middle_name'    => $row[35] ?? null,
                        'mother_contact_number' => $row[36] ?? null,
                        'guardian_last_name'    => $row[37] ?? null,
                        'guardian_first_name'   => $row[38] ?? null,
                        'guardian_middle_name'  => $row[39] ?? null,
                        'guardian_contact_number' => $row[40] ?? null,
                    ];
                    
                    $hasChanged = false;
                    if ($existingStudent) {
                        $existingUser = DB::table('users')->where('id', $userId)->first();
                        $existingEnrollment = DB::table('pre_enrollments')->where('student_lrn', $lrn)->first();

                        $identityChanged = ($existingUser && $existingUser->last_name !== trim($row[2])) ||
                                        ($existingUser && $existingUser->extension_name !== ($row[5] ?? null));
                        $jsonChanged     = ($existingEnrollment && $existingEnrollment->responses !== $newJson);
                        
                        $detailsChanged = false;
                        foreach ($incomingStudentData as $key => $value) {
                            if ($existingStudent->$key != $value) { 
                                $detailsChanged = true;
                                break;
                            }
                        }

                        if ($identityChanged || $jsonChanged || $detailsChanged) {
                            $hasChanged = true; 
                        }
                    }

                    if ($userId) {
                        DB::table('users')->where('id', $userId)->update([
                            'last_name'      => trim($row[2]),
                            'first_name'     => trim($row[3]),
                            'middle_name'    => $row[4] ?? null,
                            'extension_name' => $row[5] ?? null,
                            'birthday'       => $formattedDob,
                            'updated_at'     => $hasChanged ? now() : DB::raw('updated_at'),
                        ]);
                    } else {
                        $userId = DB::table('users')->insertGetId([
                            'last_name'      => trim($row[2]),
                            'first_name'     => trim($row[3]),
                            'middle_name'    => $row[4] ?? null,
                            'extension_name' => $row[5] ?? null,
                            'birthday'       => $formattedDob,
                            'role'           => 'student',
                            'password'       => Hash::make($lrn),
                            'created_at'     => now(),
                            'updated_at'     => now(),
                        ]);
                    }

                    DB::table('students')->updateOrInsert(
                        ['lrn' => $lrn],
                        array_merge($incomingStudentData, [
                            'user_id'    => $userId,
                            'updated_at' => ($hasChanged || !$existingStudent) ? now() : DB::raw('updated_at'),
                            'created_at' => $existingStudent ? DB::raw('created_at') : now(), 
                        ])
                    );

                    DB::table('pre_enrollments')->updateOrInsert(
                        ['student_lrn' => $lrn],
                        [
                            'responses'  => $newJson,
                            'updated_at' => ($hasChanged || !$existingStudent) ? now() : DB::raw('updated_at'),
                        ]
                    );

                    if (!$existingStudent) $newCount++;
                    elseif ($hasChanged) $updatedCount++;
                });
            }

            $now = Carbon::now('Asia/Manila');

            DB::table('sync_histories')->insert([
                'records_synced'  => ($newCount + $updatedCount),
                'new_records'     => $newCount,
                'updated_records' => $updatedCount,
                'status'          => 'Success',
                'created_at'      => $now,
                'updated_at'      => $now,
            ]);

            return back()->with('success', "Sync Complete! $newCount new and $updatedCount updated.");

        } catch (\Exception $e) {
            Log::error("Sync Error: " . $e->getMessage());
            return back()->with('error', 'Sync Failed: ' . $e->getMessage());
        }
    }
}