<?php

namespace App\Http\Controllers\Admin;

use App\Services\GoogleSheetsService;
use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Models\FieldMapping;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;



class SettingsController extends Controller
{
    /**
     * Show the configuration page
     */
    public function showSettings()
    {
        $settings = SystemSetting::firstOrCreate(['id' => 1]);
        $mappings = FieldMapping::whereNot('google_header', 'Timestamp')
                    ->orderBy('id', 'asc')
                    ->get();
        $lastSync = $settings->last_updated_by;
        
        // Call the private method
        $systemDestinations = $this->getSystemDestinations();

        return view('admin.systemconfigurationpage.syncconfiguration', 
            compact('settings', 'mappings', 'lastSync', 'systemDestinations'));
    }
    /**
     * Update the Spreadsheet ID and School Year (Horizontal)
     */
    public function updateSettings(Request $request)
        {
            $validated = $request->validate([
                'active_spreadsheet_id' => 'required|string',
                'active_sheet_range'    => 'required|string',
                'active_school_year'    => 'required|string',
                'edit_form_url'         => 'required|url', 
                'public_form_url'       => 'required|url',
            ]);

            SystemSetting::updateOrCreate(
                ['id' => 1], 
                array_merge($validated, [
                    'last_updated_by' => Auth::id()
                ])
            );

            return back()->with('success', 'Source connection updated!');
        }
    
    /**
     * Connect to Google Sheets 
     */
    
    public function refreshHeaders()
    {
        $settings = SystemSetting::first();

        if (!$settings || !$settings->active_spreadsheet_id) {
            return redirect()->back()->with('error', 'Spreadsheet ID is missing.');
        }

        try {
            $sheets = new GoogleSheetsService();
            $service = $sheets->getService();
            $range = $settings->active_sheet_range ?: 'A1:Z1';

            $response = $service->spreadsheets_values->get($settings->active_spreadsheet_id, $range);
            $rows = $response->getValues();

            if (empty($rows) || empty($rows[0])) {
                return redirect()->back()->with('error', 'The Google Sheet is empty or range is invalid.');
            }

            $headers = $rows[0];
            $dictionary = $this->getMappingDictionary();
            $insertData = [];
            $unmappedCount = 0;

            DB::beginTransaction();
            
            // 1. Clear existing mappings safely
            FieldMapping::query()->delete(); 

            foreach ($headers as $index => $header) {
                // Normalize whitespace and trim
                $cleanHeader = trim(preg_replace('/\s+/', ' ', $header));
                if (empty($cleanHeader)) continue;

                $lowered = strtolower($cleanHeader);
                $detectedField = null;
                $detectedCategory = 'General'; // Default

                // 2. Enhanced Matching Logic
                foreach ($dictionary as $dbField => $keywords) {
                    foreach ($keywords as $keyword) {
                        // Using word boundaries (\b) to prevent partial matches (e.g., 'sex' matching 'extension')
                        $pattern = '/\b' . preg_quote(strtolower($keyword), '/') . '\b/';
                        
                        if (preg_match($pattern, $lowered)) {
                            $detectedField = $dbField;
                            $detectedCategory = $this->determineCategory($dbField);
                            break 2;
                        }
                    }
                }

                if (!$detectedField) {
                    $unmappedCount++;
                    Log::info("SerbIsko Auto-Map: Could not match header [$cleanHeader]");
                }

                // 3. Collect for Bulk Insert
                $insertData[] = [
                    'google_header'   => $cleanHeader,
                    'display_label'   => $cleanHeader,
                    'database_field'  => $detectedField,
                    'category'        => $detectedCategory,
                    'last_updated_by' => Auth::id(),
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ];
            }

            // 4. Perform Bulk Insert (Much faster for large sheets)
            FieldMapping::insert($insertData);
            
            DB::commit();

            $msg = "Success! " . count($insertData) . " headers imported.";
            if ($unmappedCount > 0) $msg .= " ($unmappedCount required manual mapping).";

            return redirect()->back()->with('success', $msg);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("SerbIsko Refresh Error: " . $e->getMessage());
            return redirect()->back()->with('error', 'System Error: ' . $e->getMessage());
        }
    }

    /**
     * Helper to determine category based on field prefix
     */
    private function determineCategory($dbField) 
    {
        if (str_contains($dbField, 'curr_') || str_contains($dbField, 'perm_')) return 'Address';
        if (preg_match('/(mother|father|guardian)_/', $dbField)) return 'Family';
        if (str_contains($dbField, 'users.')) return 'Identity';
        return 'Academic';
    }

    public function showMapping()
    {
        $mappings = FieldMapping::whereNot('google_header', 'Timestamp')
                    ->orderBy('id', 'asc')
                    ->get();
        
        // Call the same private method here
        $systemDestinations = $this->getSystemDestinations();

        return view('admin.systemconfigurationpage.syncconfiguration', 
            compact('mappings', 'systemDestinations'));
    }


    /**
     * Save the Alignment Matrix and trigger an immediate synchronization.
     */
    public function updateMapping(Request $request)
    {
        $mappingData = $request->input('mappings');
        if (!$mappingData) {
            return back()->with('error', 'No mapping data found to update.');
        }

        DB::beginTransaction();
        try {
            foreach ($mappingData as $id => $values) {
                $mapping = FieldMapping::find($id);
                if ($mapping) {
                    $mapping->update([
                        'display_label'   => $values['display_label'] ?? $mapping->google_header,
                        'database_field'  => $values['database_field'], // This is what changed
                        'category'        => $values['category'] ?? 'General',
                        'last_updated_by' => Auth::id(),
                    ]);
                }
            }
            
            // Force status to Pending so performSyncInternal picks up the changes
            DB::table('pre_enrollments')->update(['status' => 'Pending']);

            DB::commit();

            // Trigger the Sync immediately
            $syncResult = $this->performSyncInternal(); 

            return redirect()->back()->with('success', 
                "Matrix updated! Found {$syncResult['total']} rows, sync updated {$syncResult['updated']} records."
            );

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Mapping Update Failed: " . $e->getMessage());
            return redirect()->back()->with('error', 'Error: ' . $e->getMessage());
        }
    }


    /**
     * Show the configuration page
     */
    private function getSystemDestinations()
    {
        return [
            'Identity (Users Table)' => [
                'users.first_name' => 'First Name', 
                'users.last_name' => 'Last Name', 
                'users.middle_name' => 'Middle Name',
                'users.extension_name' => 'Extension Name',
                'users.birthday' => 'Date of Birth',
            ],
            'Academic (Students Table)' => [
                'students.lrn' => 'LRN',
                'students.sex' => 'Sex',
                'students.age' => 'Age',
                'students.place_of_birth' => 'Place of Birth',
                'students.mother_tongue' => 'Mother Tongue',
            ],
            'Address Details' => [
                'students.curr_house_number' => 'House No. (Current)',
                'students.curr_street' => 'Street (Current)',
                'students.curr_barangay' => 'Barangay (Current)',
                'students.curr_city' => 'City (Current)',
                'students.curr_province' => 'Province (Current)',
                'students.curr_zip_code' => 'Zip Code (Current)',
                'students.curr_country' => 'Country (Current)',
                'students.is_perm_same_as_curr' => 'Same as Current Address?',
                'students.perm_house_number' => 'House No. (Permanent)',
                'students.perm_street' => 'Street (Permanent)',
                'students.perm_barangay' => 'Barangay (Permanent)',
                'students.perm_city' => 'City (Permanent)',
                'students.perm_province' => 'Province (Permanent)',
                'students.perm_zip_code' => 'Zip Code (Permanent)',
                'students.perm_country' => 'Country (Permanent)',
            ],
            'Family Background' => [
                'students.mother_last_name' => "Mother's Last Name",
                'students.mother_first_name' => "Mother's First Name",
                'students.mother_middle_name' => "Mother's Middle Name",
                'students.mother_contact_number' => "Mother's Contact",
                'students.father_last_name' => "Father's Last Name",
                'students.father_first_name' => "Father's First Name",
                'students.father_middle_name' => "Father's Middle Name",
                'students.father_contact_number' => "Father's Contact",
                'students.guardian_last_name' => "Guardian's Last Name",
                'students.guardian_first_name' => "Guardian's First Name",
                'students.guardian_middle_name' => "Guardian's Middle Name",
                'students.guardian_contact_number' => "Guardian's Contact",
            ],
            'Metadata / Extra Fields' => [
                'json.responses' => 'Archive in JSON Responses',
            ]
        ];
    }

    /**
     * Returns the mapping between database fields and possible Google Form header keywords.
     */
    private function getMappingDictionary(): array
    {
        return [
            'users.first_name' => ['first name', 'given name', 'fname', 'pangalan'],
            'users.last_name' => ['last name', 'surname', 'family name', 'apelyido'],
            'users.middle_name' => ['middle name', 'mname', 'middle initial'],
            'users.extension_name' => ['extension name', 'ext', 'suffix', 'name suffix'],
            'users.birthday' => ['birthday', 'date of birth', 'dob', 'kapanganakan'],
            'students.lrn' => ['lrn', 'learner reference', 'student id', 'learner reference number'],
            'students.sex' => ['sex', 'gender', 'kasarian'],
            'students.mother_tongue' => ['mother tongue', 'native language', 'wika'],
            'students.age' => ['age', 'edad'],
            'students.place_of_birth' => ['place of birth', 'birth place', 'pinanganakan'],

            'students.curr_house_number' => ['house no current', 'current house number'],
            'students.curr_street' => ['street current', 'current street'],
            'students.curr_barangay' => ['barangay current', 'current barangay', 'brgy current'],
            'students.curr_city' => ['city current', 'current city', 'municipality current'],
            'students.curr_province' => ['province current', 'current province'],
            'students.curr_zip_code' => ['zip code current', 'postal code current'],

            'students.is_perm_same_as_curr' => ['same as current', 'permanent is same', 'same address'],

            'students.perm_house_number' => ['house no permanent', 'permanent house number'],
            'students.perm_street' => ['street permanent', 'permanent street'],
            'students.perm_barangay' => ['barangay permanent', 'permanent barangay'],
            'students.perm_city' => ['city permanent', 'permanent city'],
            'students.perm_province' => ['province permanent', 'permanent province'],
            'students.perm_zip_code' => ['zip code permanent', 'postal code permanent'],
            
            'students.mother_last_name' => ["mother last name", "mother surname"],
            'students.mother_first_name' => ["mother first name", "mother given name"],
            'students.mother_contact_number' => ["mother contact", "mother phone"],
            
            'students.father_last_name' => ["father last name", "father surname"],
            'students.father_first_name' => ["father first name", "father given name"],
            'students.father_contact_number' => ["father contact", "father phone"],

            'students.guardian_last_name' => ["guardian last name", "guardian surname"],
            'students.guardian_first_name' => ["guardian first name", "guardian given name"],
            'students.guardian_contact_number' => ["guardian contact", "guardian phone"],
        ];
    }

    /**
     * This is the "Engine" that actually talks to Google and updates your DB.
     */
    /**
     * This is the "Engine" that actually talks to Google and updates your DB.
     */
    private function performSyncInternal()
    {
        $settings = SystemSetting::first();
        $sheets = new GoogleSheetsService();
        
        try {
            $service = $sheets->getService();
            $response = $service->spreadsheets_values->get(
                $settings->active_spreadsheet_id, 
                $settings->active_sheet_range
            );
        } catch (\Exception $e) {
            Log::error("Google Sheets Sync Failed: " . $e->getMessage());
            return ['total' => 0, 'updated' => 0, 'error' => $e->getMessage()];
        }
        
        $rows = $response->getValues();
        if (empty($rows)) return ['total' => 0, 'updated' => 0];

        $headers = array_shift($rows); 
        if (empty($headers)) return ['total' => 0, 'updated' => 0];

        $mappings = FieldMapping::all();
        $updatedCount = 0;

        foreach ($rows as $rowData) {
            // Normalize data: trim strings and pad row to match header length
            $rowData = array_map(fn($v) => is_string($v) ? trim($v) : $v, $rowData);
            $row = array_combine($headers, array_pad($rowData, count($headers), null));

            // Skip empty rows
            if (!array_filter($row)) continue;

            $studentData = [];
            $userData = [];
            $jsonResponses = [];
            $lrn = null;

            foreach ($mappings as $map) {
                $header = $map->google_header;
                $val = $row[$header] ?? '';
                $dest = $map->database_field;

                if (empty($dest)) continue;
                if ($dest === 'students.lrn') { $lrn = $val; continue; }

                if ($dest === 'json.responses') {
                    $jsonResponses[$header] = $val;
                } elseif (str_starts_with($dest, 'users.')) {
                    $field = str_replace('users.', '', $dest);
                    $userData[$field] = $val;
                } elseif (str_starts_with($dest, 'students.')) {
                    $field = str_replace('students.', '', $dest);
                    // Boolean parsing for specific flags
                    $studentData[$field] = ($field === 'is_perm_same_as_curr') 
                        ? (filter_var($val, FILTER_VALIDATE_BOOLEAN) || strtolower($val) === 'same' ? 1 : 0)
                        : $val;
                }
            }

            if ($lrn) {
                $user = \App\Models\User::firstOrNew(['username' => $lrn]);
                $student = \App\Models\Student::firstOrNew(['lrn' => $lrn]);
                $preEnroll = \App\Models\PreEnrollment::firstOrNew(['lrn' => $lrn]);

                $user->fill($userData);
                $student->fill($studentData);

                // Order-insensitive JSON comparison
                $existingJson = json_decode($preEnroll->json_responses ?? '{}', true);
                $jsonChanged = $existingJson !== $jsonResponses;

                if ($student->isDirty() || $user->isDirty() || $jsonChanged) {
                    // Use a Transaction to ensure all or nothing is saved
                    DB::transaction(function () use ($user, $student, $preEnroll, $jsonResponses) {
                        $user->save();
                        $student->user_id = $user->id; 
                        $student->save();

                        $preEnroll->fill([
                            'json_responses' => json_encode($jsonResponses),
                            'status' => 'synced',
                            'synced_at' => now()
                        ])->save();
                    });

                    $updatedCount++;
                }
            }
        }

        return ['total' => count($rows), 'updated' => $updatedCount];
    }
}
