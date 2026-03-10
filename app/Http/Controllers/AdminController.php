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
    // 1. DASHBOARD LOGIC
    public function index(Request $request) 
    {
        $grade = $request->grade_level;

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

        $totalRegistrations = $applyFilter(DB::table('students')
            ->join('users', 'students.user_id', '=', 'users.id') 
            ->whereNull('users.deleted_at') // Add this condition to exclude soft-deleted users
            ->leftJoin('kiosk_enrollments', 'students.lrn', '=', 'kiosk_enrollments.student_lrn')
            ->leftJoin('pre_enrollments', 'students.lrn', '=', 'pre_enrollments.student_lrn'))
            ->count();

        $totalSubmissions = $applyFilter(DB::table('students')
            ->join('users', 'students.user_id', '=', 'users.id')
            ->whereNull('users.deleted_at')
            ->join('kiosk_enrollments', 'students.lrn', '=', 'kiosk_enrollments.student_lrn')
            ->leftJoin('pre_enrollments', 'students.lrn', '=', 'pre_enrollments.student_lrn'))
            ->count();

        $totalEnrolled = $applyFilter(DB::table('students')
            ->join('users', 'students.user_id', '=', 'users.id')
            ->whereNull('users.deleted_at')
            ->join('kiosk_enrollments', 'students.lrn', '=', 'kiosk_enrollments.student_lrn')
            ->leftJoin('pre_enrollments', 'students.lrn', '=', 'pre_enrollments.student_lrn')
            ->where('kiosk_enrollments.academic_status', '=', 'Officially Enrolled')) 
            ->count();

        $max = $totalRegistrations > 0 ? $totalRegistrations : 1;
        $percVerified = ($totalSubmissions / $max) * 100;
        $percEnrolled = ($totalEnrolled / $max) * 100;

        $electives = ['STEM', 'ASSH', 'BE', 'TechPro'];
        $electiveCounts = [];

        foreach ($electives as $elective) {
            $fullMap = [
                'STEM'    => 'Science, Technology, Engineering, and Mathematics (STEM)',
                'BE'      => 'Business and Entrepreneurship (BE)',
                'ASSH'    => 'Arts, Social Sciences, and Humanities (ASSH)',
                'TechPro' => 'Technical-Vocational-Livelihood (TVL)'
            ];
            $fullString = $fullMap[$elective] ?? $elective;

            $electiveCounts[$elective] = $applyFilter(DB::table('students')
                ->join('users', 'students.user_id', '=', 'users.id')
                ->whereNull('users.deleted_at')
                ->join('pre_enrollments', 'students.lrn', '=', 'pre_enrollments.student_lrn')
                ->leftJoin('kiosk_enrollments', 'students.lrn', '=', 'kiosk_enrollments.student_lrn')
                ->where(function($q) use ($elective, $fullString) {
                    $q->where('kiosk_enrollments.cluster', $elective)
                    ->orWhere(function($sub) use ($fullString) {
                        $sub->whereNull('kiosk_enrollments.cluster')
                            ->where('pre_enrollments.responses', 'like', '%"Cluster of Electives":"' . $fullString . '"%');
                    });
                }))
                ->count();
        }

        $recentKioskQuery = DB::table('kiosk_enrollments')
            ->join('students', 'kiosk_enrollments.student_lrn', '=', 'students.lrn')
            ->join('users', 'students.user_id', '=', 'users.id')
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

        $lastSync = DB::table('sync_histories')->where('status', 'Success')->latest()->first();
        $lastSyncTime = $lastSync ? \Carbon\Carbon::parse($lastSync->created_at)->diffForHumans() : 'Never';

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

        return view('admin.dashboardpage.dashboard', compact(
            'totalRegistrations', 'totalSubmissions', 'totalEnrolled',
            'percVerified', 'percEnrolled', 'electiveCounts',
            'donutGradient', 'lastSyncTime', 'recentKioskSubmissions'
        ));
    }

    // 2. STUDENT LIST LOGIC
    public function students(Request $request)
    {
        $query = DB::table('users')
            ->join('students', 'users.id', '=', 'students.user_id')
            ->whereNull('users.deleted_at')
            ->leftJoin('pre_enrollments', 'students.lrn', '=', 'pre_enrollments.student_lrn')
            ->leftJoin('kiosk_enrollments', 'students.lrn', '=', 'kiosk_enrollments.student_lrn')
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
            ->leftJoin('kiosk_enrollments', 'students.lrn', '=', 'kiosk_enrollments.student_lrn') 
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
        $pendingScans = DB::table('scans')
            ->join('users', 'scans.user_id', '=', 'users.id')
            ->whereNull('users.deleted_at')
            ->leftJoin('students', 'users.id', '=', 'students.user_id')
            ->leftJoin('kiosk_enrollments as ke', 'students.lrn', '=', 'ke.student_lrn')
            ->leftJoin('pre_enrollments as pe', 'students.lrn', '=', 'pe.student_lrn')
            ->select(
                'scans.*', 
                'users.first_name', 
                'users.last_name',
                'ke.grade_level as kiosk_grade',
                'pe.responses'
            )
            ->where('scans.status', 'manual_verification')
            ->orderBy('scans.created_at', 'asc')
            ->get()
            ->map(function($scan) {
                if ($scan->kiosk_grade) {
                    $scan->display_grade = $scan->kiosk_grade;
                } else {
                    $details = json_decode($scan->responses, true) ?? [];
                    $scan->display_grade = $details['Grade Level to Enroll'] ?? '—';
                }
                return $scan;
            });

        if (request()->ajax()) {
            return view('admin.partials.verification-table', compact('pendingScans'))->render();
        }

        return view('admin.verification', compact('pendingScans')); 
    }

    public function handleVerificationAction(Request $request) 
    {
        $scanId = $request->input('scan_id');
        $action = $request->input('action'); 

        $status = ($action === 'approve') ? 'manual_approved' : 'manual_declined';

        DB::table('scans')->where('id', $scanId)->update([
            'status' => $status,
            'remarks' => 'Manually ' . $action . 'd by Admin'
        ]);

        return back()->with('success', 'Document has been ' . $action . 'd.');
    }

    public function requirementhub(){ return view('admin.requirementhub');}
    public function accountsettings(){ return view('admin.accountsettings'); }

    public function accessManagement(Request $request)
    {
        $query = User::query();

        $query->whereIn('role', ['super_admin', 'admin', 'facilitator']);

        if ($request->filled('role') && $request->role !== 'All') {
            $roleMap = [
                'Administrator' => 'admin',
                'Facilitator'   => 'facilitator'
            ];

            $targetRole = $roleMap[$request->role] ?? strtolower($request->role);
            $query->where('role', $targetRole);
        }

        $staff = $query->get();

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
            'password'       => 'required|string|min:8',
        ]);

        // Map the form value to the exact string stored in the database
        $roleMap = [
            'admin' => 'admin',
            'facilitator'   => 'facilitator',
            'super_admin'   => 'super_admin'
        ];

        User::create([
            'first_name'     => $validated['first_name'],
            'last_name'      => $validated['last_name'],
            'middle_name'    => $validated['middle_name'],
            'extension_name' => $validated['extension_name'],
            'birthday'       => $validated['birthday'],
            'role'           => $roleMap[strtolower($validated['role'])] ?? 'facilitator',
            'password' => Hash::make($request->password),
        ]);

        return redirect()->route('admin.accessmanagement')->with('success', 'New staff member added!');
    }

    public function destroy($id)
    {
        // 1. Fetch the user FIRST to check if it's the currently authenticated admin
        $user = User::findOrFail($id);

        // 2. Safely check the ID against the authenticated user
        if (Auth::id() == $user->id) {
            return back()->with('error', 'You cannot revoke your own access. Please contact another administrator.');
        }

        $user = User::findOrFail($id);
        
        // 3. Apply Soft Delete
        $user->delete(); 

        return back()->with('success', 'User access has been revoked successfully.');
    }

    public function performSync() {
        set_time_limit(300); 
        
        $spreadsheetId = '1pUdqUbAMQEZ4Kg2V6A05orHY9xnDCJLp2QWLQaXXmSk'; 
        $range = 'Form_Responses2!A1:ZZ'; 

        try {
            $client = new \Google\Client(); 
            $client->setAuthConfig(storage_path('app/google-credentials.json'));
            $client->addScope(\Google\Service\Sheets::SPREADSHEETS_READONLY);

            $service = new \Google\Service\Sheets($client);
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
                    // 1. Check for an existing ACTIVE student only
                    $existingStudent = DB::table('students')
                        ->join('users', 'students.user_id', '=', 'users.id')
                        ->where('students.lrn', $lrn)
                        ->whereNull('users.deleted_at') // Crucial: Ignore soft-deleted users
                        ->select('students.*', 'users.id as active_user_id')
                        ->first();

                    $userId = $existingStudent ? $existingStudent->active_user_id : null;

                    // Dynamic JSON Responses (Field 41 onwards)
                    $dynamicResponses = [];
                    for ($i = 41; $i < count($headers); $i++) {
                        $question = $headers[$i] ?? "Field " . $i;
                        $dynamicResponses[$question] = $row[$i] ?? null;
                    }
                    $newJson = json_encode($dynamicResponses);

                    // Prepare incoming data for comparison
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

                    // 2. Update/Insert User (Only if they aren't soft-deleted elsewhere)
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
                        // This will create a fresh user if the LRN is new OR if the old LRN user was soft-deleted
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

                    // 3. Update/Insert Student 
                    DB::table('students')->updateOrInsert(
                        ['lrn' => $lrn],
                        array_merge($incomingStudentData, [
                            'user_id'    => $userId,
                            'updated_at' => ($hasChanged || !$existingStudent) ? now() : DB::raw('updated_at'),
                            'created_at' => $existingStudent ? DB::raw('created_at') : now(), 
                        ])
                    );

                    // 4. Update Pre-Enrollment
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