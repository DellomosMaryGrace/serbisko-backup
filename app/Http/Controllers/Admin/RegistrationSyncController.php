<?php

namespace App\Http\Controllers\Admin;

use App\Services\GoogleSheetsService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use App\Models\SystemSetting;
use App\Models\FieldMapping;
use Carbon\Carbon;

class RegistrationSyncController extends Controller
{
    /**
     * Show the Sync Dashboard
     */
    public function systemsync()
    {
        $settings = SystemSetting::firstOrCreate(['id' => 1]);
        $syncHistory = DB::table('sync_histories')->orderBy('created_at', 'desc')->get();
        $lastSync = DB::table('sync_histories')->where('status', 'Success')->latest()->first();

        $isConnected = false;
        if ($settings && $settings->active_spreadsheet_id) {
            try {
                $sheets = new GoogleSheetsService();
                $service = $sheets->getService();
                $service->spreadsheets->get($settings->active_spreadsheet_id);
                $isConnected = true;
            } catch (\Exception $e) {
                Log::error("Google Sheets Connection Check Failed: " . $e->getMessage());
                $isConnected = false;
            }
        }

        $formUrl = $settings->public_form_url; 
        return view('admin.systemsync', compact('syncHistory', 'lastSync', 'formUrl', 'isConnected', 'settings'));
    }

    /**
     * The Main Sync Logic (Enhanced with Intelligent Fuzzy Matching)
     */
    public function performSync(Request $request) 
    {
        set_time_limit(600); 
        $settings = SystemSetting::first();
        if (!$settings || !$settings->active_spreadsheet_id) {
            return back()->with('error', 'Spreadsheet ID not configured.');
        }

        $targetSY = $settings->active_school_year; 
        $spreadsheetId = $settings->active_spreadsheet_id;
        $range = $settings->active_sheet_range ?? 'Form_Responses1!A1:ZZ'; 
        $mappings = FieldMapping::all();

        try {
            $sheets = new GoogleSheetsService();
            $service = $sheets->getService();
            $response = $service->spreadsheets_values->get($spreadsheetId, $range);
            $values = $response->getValues();

            if (empty($values)) return back()->with('info', 'Sheet is empty.');

            $headers = $values[0];
            $dataRows = array_slice($values, 1);
            
            $newCount = 0; $updatedCount = 0; $skippedRows = 0; $skippedLocked = 0; $conflictCount = 0;

            foreach ($dataRows as $row) { 
                $row = array_pad($row, count($headers), null);
                if (empty(array_filter($row))) continue; 

                $userData = []; $studentData = []; $jsonResponses = []; 

                // 1. MAP DATA
                foreach ($headers as $index => $header) {
                    $cleanHeader = trim($header);
                    $val = trim($row[$index] ?? '');
                    $map = $mappings->where('google_header', $cleanHeader)->first();

                    if ($map && $map->database_field) {
                        if ($map->database_field === 'json.responses') {
                            $jsonResponses[$cleanHeader] = $val;
                            continue;
                        }
                        $columnName = str_replace(['users.', 'students.'], '', $map->database_field);
                        if (str_contains($map->database_field, 'users.')) {
                            $userData[$columnName] = $val;
                        } elseif (str_contains($map->database_field, 'students.')) {
                            $studentData[$columnName] = $val;
                        }
                    } else {
                        $jsonResponses[$cleanHeader] = $val;
                    }
                }

                // 2. REQUIRED FIELDS & DATE PARSING
                $lrn = $studentData['lrn'] ?? null;
                $firstName = $userData['first_name'] ?? null;
                $lastName = $userData['last_name'] ?? null;
                $dobRaw = $userData['birthday'] ?? null;

                if (empty($lrn) || empty($firstName) || empty($lastName) || empty($dobRaw)) {
                    $skippedRows++; continue; 
                }

                try {
                    $formattedDob = Carbon::parse($dobRaw)->format('Y-m-d');
                    $userData['birthday'] = $formattedDob;
                } catch (\Exception $e) {
                    $skippedRows++; continue; 
                }

                // 3. IDENTITY LOGIC (FUZZY MATCHING & TOKEN SORTING)
                $student = \App\Models\Student::where('lrn', $lrn)
                    ->where('school_year', $targetSY)
                    ->first();

                if ($student) {

                    if ($student->is_manually_edited) {
                        $skippedLocked++; 
                        continue;
                    }

                    $user = $student->user; 

                    // --- INTELLIGENT SIMILARITY CHECK ---
                    $normalize = function($str) {
                        return strtolower(preg_replace('/[^A-Za-z0-9]/', '', trim($str ?? '')));
                    };

                    $fSheet = $normalize($userData['first_name']);
                    $lSheet = $normalize($userData['last_name']);
                    $fDb = $normalize($user->first_name);
                    $lDb = $normalize($user->last_name);

                    // Token Sorting: Handles Name Swaps ("John Doe" vs "Doe John")
                    $getTokens = function($f, $l) {
                        $parts = [$f, $l];
                        sort($parts);
                        return implode('', $parts);
                    };

                    $dbTokens = $getTokens($fDb, $lDb);
                    $sheetTokens = $getTokens($fSheet, $lSheet);

                    // Calculate Name Score
                    $nameScore = 100;
                    if ($dbTokens !== $sheetTokens) {
                        $lev = levenshtein($dbTokens, $sheetTokens);
                        $maxLen = max(strlen($dbTokens), strlen($sheetTokens));
                        $nameScore = $maxLen > 0 ? (1 - ($lev / $maxLen)) * 100 : 0;
                    }

                    // Birthdate Difference (Handles 1-day margin for timezone/entry errors)
                    $dbDobDate = ($user->birthday instanceof \Carbon\Carbon) ? $user->birthday : \Carbon\Carbon::parse($user->birthday);
                    $sheetDobDate = Carbon::parse($formattedDob);
                    $daysDiff = abs($dbDobDate->diffInDays($sheetDobDate));

                    // DECISION: 90% Similarity & max 1 day DOB difference = Auto-Approve
                    if ($nameScore < 90 || $daysDiff > 1) {
                        \App\Models\SyncConflict::updateOrCreate(
                            ['lrn' => $lrn, 'school_year' => $targetSY, 'status' => 'pending'],
                            [
                                'existing_user_id' => $user->id,
                                'existing_data_json' => array_merge($user->toArray(), $student->toArray()),
                                'incoming_data_json' => array_merge($userData, $studentData, ['responses' => $jsonResponses]),
                                'raw_sheet_row' => $row,
                                'conflict_type' => 'identity_mismatch'
                            ]
                        );
                        $conflictCount++;
                        continue; 
                    }
                    } else {
                    // 1. IDENTITY CHECK: Does a User exist with this Name + Birthday?
                    $possibleUser = \App\Models\User::where('birthday', $formattedDob)
                        ->where('first_name', $userData['first_name'])
                        ->where('last_name', $userData['last_name'])
                        ->first();

                    if ($possibleUser) {
                        if ($possibleUser->student && $possibleUser->student->is_manually_edited) {
                            $skippedLocked++;
                            continue; 
                        }
                        
                        // 2. RE-ENROLLMENT CHECK: Is this user already enrolled for the TARGET Year?
                        $existingEnrollment = \App\Models\Student::where('user_id', $possibleUser->id)
                            ->where('school_year', $targetSY)
                            ->first();

                        if ($existingEnrollment) {
                            // CASE: DUPLICATE - This LRN is new, but the Person/Year already exists.
                            \App\Models\SyncConflict::updateOrCreate(
                                ['lrn' => $lrn, 'school_year' => $targetSY, 'status' => 'pending'],
                                [
                                    'existing_user_id' => $possibleUser->id,
                                    'existing_data_json' => $possibleUser->load('student')->toArray(),
                                    'incoming_data_json' => array_merge($userData, $studentData, ['responses' => $jsonResponses]),
                                    'raw_sheet_row' => $row,
                                    'conflict_type' => 'potential_duplicate'
                                ]
                            );
                            $conflictCount++;
                            continue;
                        } else {
                            // CASE: RE-ENROLLMENT (The Elena Scenario)
                            // We link to the existing user but prepare a new student record for the new year.
                            $user = $possibleUser;
                            $student = new \App\Models\Student();
                        }
                    } else {
                        // CASE: BRAND NEW STUDENT
                        $user = new \App\Models\User();
                        $student = new \App\Models\Student();
                    }
                }

                // 4. PROTECTION & DATA PREP
                if ($student->exists && $student->is_manually_edited) {
                    $skippedLocked++; continue;
                }

                if (isset($studentData['is_perm_same_as_curr'])) {
                    $val = trim(strtolower($studentData['is_perm_same_as_curr']));
                    $studentData['is_perm_same_as_curr'] = ($val === 'yes') ? 1 : 0;
                }

                $userData['role'] = 'student';
                if (!$user->exists) $user->password = Hash::make($lrn);
                
                $userData = array_map(fn($v) => $v === '' ? null : $v, $userData);
                $studentData = array_map(fn($v) => $v === '' ? null : $v, $studentData);

                $user->fill($userData);
                $student->fill($studentData);
                $student->school_year = $targetSY;

                // 5. ATOMIC SAVE & VERSIONING
                $newResponsesJson = json_encode($jsonResponses);
                $latestEnrollment = DB::table('pre_enrollments')
                    ->where('student_id', $student->id)
                    ->orderBy('submission_version', 'desc')->first();

                $isJsonChanged = (!$latestEnrollment || $latestEnrollment->responses !== $newResponsesJson);

                if ($user->isDirty() || $student->isDirty() || $isJsonChanged || !$student->exists) {
                    DB::beginTransaction();
                    try {
                        $user->save();
                        $student->user_id = $user->id;
                        $student->save();

                        if ($isJsonChanged) {
                            $vCount = DB::table('pre_enrollments')->where('student_id', $student->id)->count();
                            DB::table('pre_enrollments')->insert([
                                'student_id' => $student->id,
                                'submission_version' => $vCount + 1,
                                'responses' => $newResponsesJson,
                                'status' => 'Synced',
                                'created_at' => now(),
                            ]);
                        }
                        DB::commit();
                        $student->wasRecentlyCreated ? $newCount++ : $updatedCount++;
                    } catch (\Exception $e) {
                        DB::rollBack();
                        Log::error("Row Error LRN $lrn: " . $e->getMessage());
                    }
                }
            }

            // 6. RECORD AUDIT LOG
            DB::table('sync_histories')->insert([
                'school_year'     => $targetSY,
                'new_records'     => $newCount,
                'updated_records' => $updatedCount,
                'records_synced'  => $newCount + $updatedCount,
                'status'          => 'Success',
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);

            return back()->with('success', "Sync complete! New: $newCount, Updated: $updatedCount, Conflicts: $conflictCount, Skipped: " . ($skippedRows + $skippedLocked));

        } catch (\Exception $e) {
            Log::error("Sync Error: " . $e->getMessage());
            return back()->with('error', 'Sync Failed: ' . $e->getMessage());
        }
    }
}
