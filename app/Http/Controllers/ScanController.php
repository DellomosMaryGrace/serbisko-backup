<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ScanController extends Controller
{
    private function getPrefix($docType) {
        $lowerDoc = strtolower($docType);
        if (str_contains($lowerDoc, 'report') || str_contains($lowerDoc, 'sf9')) return 'sf9';
        if (str_contains($lowerDoc, 'birth') || str_contains($lowerDoc, 'psa')) return 'psa';
        if (str_contains($lowerDoc, 'enrollment') || str_contains($lowerDoc, 'form')) return 'enroll_form';
        if (str_contains($lowerDoc, 'als') || str_contains($lowerDoc, 'alternative')) return 'als_cert';
        if (str_contains($lowerDoc, 'affidavit') || str_contains($lowerDoc, 'sworn')) return 'affidavit';
        if (str_contains($lowerDoc, 'moral')) return 'good_moral';
        if (str_contains($lowerDoc, '137') || str_contains($lowerDoc, 'sf10')) return 'sf10';
        return 'sf9'; // Fallback
    }

    private function triggerArduinoSuccess() {
        try {
            // 1. Close Slot (F)
            Http::timeout(3)->post('http://127.0.0.1:51234/api/door', ['action' => 'close']);
            
            // 2. Trigger Conveyor Belt (w)
            // Note: arduino_server.py might need a /api/conveyor/w endpoint 
            // but for now we'll use the existing send_command logic if possible 
            // or just hit a generic endpoint that we'll add.
            Http::timeout(3)->post('http://127.0.0.1:51234/api/conveyor/w');
            
            Log::info("Arduino Success commands (F + w) sent.");
        } catch (\Exception $e) {
            Log::error("Arduino Success Trigger failed: " . $e->getMessage());
        }
    }

    public function triggerSorting(Request $request) {
        $cluster = $request->input('cluster');
        if (!$cluster) return response()->json(['error' => 'No cluster provided'], 400);

        try {
            Http::timeout(3)->post('http://127.0.0.1:51234/api/strand/' . $cluster);
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function processDocument(Request $request)
    {
        try {
            set_time_limit(0); 
            
            $imageData = $request->input('image_data');
            $docType = $request->input('document_type', 'Report Card (SF9)');
            $userId = session('user_id', 1);

            Log::info("--- START processDocument ---", ['userId' => $userId, 'docType' => $docType]);

            if (!$imageData || strpos($imageData, ';base64,') === false) {
                return response()->json(['status' => 'error', 'message' => 'Image data is invalid.']);
            }

            // Decode and Save Image
            $imageParts = explode(";base64,", $imageData);
            $imageTypeAux = explode("image/", $imageParts[0]);
            $imageType = $imageTypeAux[1] ?? 'jpeg';
            $imageBase64 = base64_decode($imageParts[1]);
            
            $fileName = 'scan_' . $userId . '_' . time() . '.' . $imageType;
            $filePath = 'scans/' . $fileName;

            Storage::disk('public')->put($filePath, $imageBase64);
            $imageFullPath = storage_path('app/public/' . $filePath);
            Log::info("Image saved locally", ['path' => $imageFullPath]);

            $prefix = $this->getPrefix($docType);
            Log::info("Using prefix", ['prefix' => $prefix]);

            // Update Kiosk Enrollment with the new file path and initial status
            DB::table('kiosk_enrollments')->updateOrInsert(
                ['id' => $userId],
                [
                    "{$prefix}_path" => $filePath,
                    "{$prefix}_status" => 'pending',
                    "{$prefix}_remarks" => 'Processing...',
                    'latest_scan_type' => $docType,
                    'latest_scan_status' => 'pending',
                    'latest_scan_remarks' => 'Processing...',
                    'updated_at' => now()
                ]
            );
            Log::info("Database updated with 'pending' status");

            // --- HELPER: Handles failures and checks for 3rd strike ---
            $handleFailure = function($remarks) use ($userId, $docType, $prefix) {
                $enrollment = DB::table('kiosk_enrollments')->where('id', $userId)->first();
                $attemptsCol = "{$prefix}_attempts";
                $newAttempts = ($enrollment->$attemptsCol ?? 0) + 1;

                $status = ($newAttempts >= 3) ? 'manual_verification' : 'failed';
                $finalRemarks = ($newAttempts >= 3) ? 'Sent to Admin for Manual Verification.' : $remarks;

                DB::table('kiosk_enrollments')->where('id', $userId)->update([
                    "{$prefix}_status" => $status,
                    "{$prefix}_remarks" => $finalRemarks,
                    "{$prefix}_attempts" => $newAttempts,
                    'latest_scan_status' => $status,
                    'latest_scan_remarks' => $finalRemarks
                ]);

                Log::warning("Failure handled", ['userId' => $userId, 'attempts' => $newAttempts, 'status' => $status]);
                return ['is_strike_3' => ($newAttempts >= 3), 'count' => $newAttempts];
            };

            // --- 1. Dynamic Document Classification ---
            $lowerDoc = strtolower($docType);
            if (str_contains($lowerDoc, 'report') || str_contains($lowerDoc, 'sf9')) $pythonDocType = 'report_card';
            elseif (str_contains($lowerDoc, 'birth') || str_contains($lowerDoc, 'psa')) $pythonDocType = 'birth_certificate';
            elseif (str_contains($lowerDoc, 'enrollment') || str_contains($lowerDoc, 'form')) $pythonDocType = 'enroll_form';
            elseif (str_contains($lowerDoc, 'als') || str_contains($lowerDoc, 'alternative')) $pythonDocType = 'als_certificate';
            elseif (str_contains($lowerDoc, 'affidavit') || str_contains($lowerDoc, 'sworn')) $pythonDocType = 'affidavit';
            elseif (str_contains($lowerDoc, 'moral')) $pythonDocType = 'good_moral';
            elseif (str_contains($lowerDoc, '137') || str_contains($lowerDoc, 'sf10')) $pythonDocType = 'form_137';
            else $pythonDocType = 'generic_name_check'; 

            $user = DB::table('users')->where('id', $userId)->first();
            $expectedFirstName = $user->first_name ?? 'Unknown';
            $expectedLastName = $user->last_name ?? 'Unknown';

            Log::info("Sending to OCR Server", ['url' => 'http://127.0.0.1:9001/ocr']);

            try {
                $ocrResponse = Http::timeout(300)
                    ->attach('image', file_get_contents($imageFullPath), $fileName)
                    ->post('http://127.0.0.1:9001/ocr', [
                        'doc_type'   => $pythonDocType,
                        'scan_id'    => $userId,
                        'first_name' => $expectedFirstName,
                        'last_name'  => $expectedLastName
                    ]);

                if ($ocrResponse->failed()) {
                    Log::error("OCR HTTP Request failed");
                    $handleFailure('OCR Server Error');
                    return response()->json(['status' => 'success', 'redirect' => '/student/verifying']);
                }

                $ocrResult = $ocrResponse->json();
                Log::info("OCR Response received", ['result' => $ocrResult]);
                
                if (isset($ocrResult['success']) && $ocrResult['success'] === false) {
                    $handleFailure($ocrResult['error'] ?? 'Document Rejected.');
                    return response()->json(['status' => 'success', 'redirect' => '/student/verifying']);
                }

                if (isset($ocrResult['success']) && $ocrResult['success'] === true) {
                    $lrn = $ocrResult['lrn'] ?? null;
                    $isReportCard = (str_contains(strtolower($docType), 'report') || str_contains(strtolower($docType), 'sf9'));
                    
                    if ($lrn && $isReportCard) {
                        Log::info("LRN Found & Doc is Report Card. Preparing LIS call.");
                        
                        DB::table('kiosk_enrollments')->where('id', $userId)->update([
                            'sf9_lrn' => $lrn, 
                            'sf9_remarks' => 'Sending to LIS...',
                            'student_lrn' => $lrn,
                            'latest_scan_remarks' => 'Sending to LIS...',
                            'updated_at' => now()
                        ]);
                        
                        $enrollingGrade = session('grade_level');
                        if (!$enrollingGrade) {
                            $enrollingGrade = DB::table('kiosk_enrollments')->where('id', $userId)->value('grade_level') ?? '11'; 
                        }
                        $expectedGrade = ($enrollingGrade == '12') ? 'Grade 11' : 'Grade 10';

                        // Dynamically determine the callback URL based on the current request
                        $callbackUrl = $request->getSchemeAndHttpHost() . '/api/lis-callback'; 
                        
                        Log::info("Triggering LIS Verifier", [
                            'lrn' => $lrn,
                            'expectedGrade' => $expectedGrade,
                            'callback' => $callbackUrl
                        ]);

                        try {
                            $lisResponse = Http::timeout(10)->post('http://127.0.0.1:5001/verify', [
                                'lrn' => $lrn,
                                'expected_grade' => $expectedGrade,
                                'webhook_url' => $callbackUrl, 
                                'scan_id' => $userId
                            ]);
                            Log::info("LIS Server hit successfully", ['status' => $lisResponse->status()]);
                        } catch (\Exception $e) {
                            Log::error("LIS Trigger Error", ['error' => $e->getMessage()]);
                            $handleFailure('LIS Verifier is offline.');
                        }
                    } else {
                        Log::info("Document verified without LIS (Non-Report Card or missing LRN)");
                        DB::table('kiosk_enrollments')->where('id', $userId)->update([
                            "{$prefix}_status" => 'verified',
                            "{$prefix}_remarks" => 'Verified',
                            'latest_scan_status' => 'verified',
                            'latest_scan_remarks' => 'Verified',
                            'updated_at' => now()
                        ]);

                        // Trigger Arduino SUCCESS (Close + w)
                        $this->triggerArduinoSuccess();
                    }
                }

            } catch (\Exception $e) {
                Log::error("OCR Exception", ['error' => $e->getMessage()]);
                $handleFailure('AI Engine Offline');
            }

            return response()->json(['status' => 'success', 'redirect' => '/student/verifying']);

        } catch (\Exception $e) {
            Log::error("FATAL ERROR in processDocument", [
                'msg' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return response()->json(['status' => 'error', 'message' => 'System Error.']);
        }
    }

    public function lisCallback(Request $request)
    {
        $userId = $request->input('scan_id');
        $status = $request->input('result'); 
        
        Log::info("LIS Callback received", ['userId' => $userId, 'status' => $status]);

        if ($userId && $status) {
            $finalStatus = ($status === 'verified_lis') ? 'verified' : 'failed';
            
            if ($finalStatus === 'failed') {
                $enrollment = DB::table('kiosk_enrollments')->where('id', $userId)->first();
                $newAttempts = ($enrollment->sf9_attempts ?? 0) + 1;
                
                $dbStatus = ($newAttempts >= 3) ? 'manual_verification' : 'failed';
                $remarks = ($newAttempts >= 3) ? 'Sent to Admin for Manual Verification.' : 'LIS Verification Failed.';

                DB::table('kiosk_enrollments')->where('id', $userId)->update([
                    'sf9_status' => $dbStatus,
                    'sf9_remarks' => $remarks,
                    'sf9_attempts' => $newAttempts,
                    'latest_scan_status' => $dbStatus,
                    'latest_scan_remarks' => $remarks,
                    'updated_at' => now()
                ]);
            } else {
                DB::table('kiosk_enrollments')->where('id', $userId)->update([
                    'sf9_status' => 'verified',
                    'sf9_remarks' => 'Verified',
                    'latest_scan_status' => 'verified',
                    'latest_scan_remarks' => 'Verified',
                    'updated_at' => now()
                ]);

                // Trigger Arduino SUCCESS (Close + w)
                $this->triggerArduinoSuccess();
            }
            return response()->json(['success' => true]);
        }
        return response()->json(['success' => false], 400);
    }
    
    public function checkScanStatus()
    {
        $userId = session('user_id');
        if (!$userId) return response()->json(['status' => 'error', 'message' => 'Session expired.']);

        $enrollment = DB::table('kiosk_enrollments')->where('id', $userId)->first();

        if (!$enrollment) return response()->json(['status' => 'pending']);

        $docType = session('current_doc', 'Report Card (SF9)');
        $prefix = $this->getPrefix($docType);
        $attemptsCol = "{$prefix}_attempts";
        $attempts = $enrollment->$attemptsCol ?? 0;

        return response()->json([
            'status' => $enrollment->latest_scan_status ?? 'pending',
            'remarks' => $enrollment->latest_scan_remarks,
            'next_url' => $this->getNextUrl($userId),
            'current_doc' => $docType,
            'attempts' => $attempts
        ]);
    }

    public function checkRejection(Request $request) {
        $userId = session('user_id');
        if (!$userId) return response()->json(['rejected' => false]);

        try {
            $response = Http::timeout(2)->get('http://127.0.0.1:51234/api/sensor/check-rejection');
            $data = $response->json();

            if (isset($data['rejected']) && $data['rejected'] === true) {
                $docType = session('current_doc', 'Unknown Document');
                Log::warning("PAPER_REJECTED detected for User {$userId}, Doc: {$docType}");

                // Update kiosk_enrollments with rejection info
                $enrollment = DB::table('kiosk_enrollments')->where('id', $userId)->first();
                $rejectedPapers = json_decode($enrollment->rejected_papers ?? '[]', true);
                
                // --- DUPLICATE CHECK ---
                // Only add if not already recorded within the last 15 seconds for this doc type
                $alreadyExists = false;
                foreach ($rejectedPapers as $rej) {
                    if ($rej['document_type'] === $docType) {
                        $diff = abs(now()->diffInSeconds(\Carbon\Carbon::parse($rej['rejected_at'])));
                        if ($diff < 15) {
                            $alreadyExists = true;
                            break;
                        }
                    }
                }

                if (!$alreadyExists) {
                    $rejectedPapers[] = [
                        'document_type' => $docType,
                        'rejected_at' => now()->toDateTimeString(),
                        'prefix' => $this->getPrefix($docType)
                    ];

                    DB::table('kiosk_enrollments')->where('id', $userId)->update([
                        'rejected_papers' => json_encode($rejectedPapers)
                    ]);
                }

                return response()->json(['rejected' => true]);
            }
        } catch (\Exception $e) {
            Log::error("Error checking rejection from Arduino: " . $e->getMessage());
        }

        return response()->json(['rejected' => false]);
    }

    private function getNextUrl($userId) {
        $selectedDocs = session('docs_to_scan', []);
        $currentDoc = session('current_doc');

        if (!empty($selectedDocs)) {
            $currentIndex = array_search($currentDoc, $selectedDocs);
            if ($currentIndex !== false && isset($selectedDocs[$currentIndex + 1])) {
                $nextDoc = $selectedDocs[$currentIndex + 1];
                // Do NOT update session(['current_doc']) here! 
                // Let the capture controller update it when they land on the page.
                return '/student/capture?doc=' . urlencode($nextDoc);
            }
        }

        // Check if EVERYTHING required for their status is verified
        $enrollment = DB::table('kiosk_enrollments')->where('id', $userId)->first();
        if ($enrollment) {
            $enrollController = new \App\Http\Controllers\EnrollmentController();
            $requiredDocs = $enrollController->getRequiredDocs($enrollment->academic_status);
            
            $allVerified = true;
            foreach ($requiredDocs as $label => $prefix) {
                $statusCol = $prefix . '_status';
                $status = $enrollment->$statusCol ?? 'pending';
                if ($status !== 'verified' && $status !== 'manual_verification') {
                    $allVerified = false;
                    break;
                }
            }

            if ($allVerified) {
                return '/student/thankyou';
            }
        }

        return '/student/checklist';
    }
}