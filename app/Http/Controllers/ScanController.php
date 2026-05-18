<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Models\Student;

class ScanController extends Controller
{
    private function getStudentId($userId) {
        $student = Student::where('user_id', $userId)->first();
        return $student ? $student->id : null;
    }

    private function getPrefix($docType) {
        $lowerDoc = strtolower($docType);
        if (str_contains($lowerDoc, 'sf9 back') || str_contains($lowerDoc, 'report card back')) return 'sf9_back';
        if (str_contains($lowerDoc, 'report') || str_contains($lowerDoc, 'sf9')) return 'sf9';
        if (str_contains($lowerDoc, 'birth') || str_contains($lowerDoc, 'psa')) return 'psa';
        if (str_contains($lowerDoc, 'enrollment') || str_contains($lowerDoc, 'form')) return 'enroll_form';
        if (str_contains($lowerDoc, 'als') || str_contains($lowerDoc, 'alternative')) return 'als_cert';
        if (str_contains($lowerDoc, 'affidavit') || str_contains($lowerDoc, 'sworn') || str_contains($lowerDoc, 'undertaking')) return 'affidavit';
        return 'sf9';
    }

    private function triggerArduinoSuccess() {
        try {
            Http::timeout(3)->post('http://' . env('SERVICE_HOST', '127.0.0.1') . ':51234/api/door', ['action' => 'close']);
            Http::timeout(3)->post('http://' . env('SERVICE_HOST', '127.0.0.1') . ':51234/api/conveyor/c0');
            Log::info("Arduino Success commands (F + c0) sent.");
        } catch (\Exception $e) {
            Log::error("Arduino Success Trigger failed: " . $e->getMessage());
        }
    }

    public function triggerSorting(Request $request) {
        $cluster = $request->input('cluster');
        if (!$cluster) return response()->json(['error' => 'No cluster provided'], 400);
        try {
            Http::timeout(3)->post('http://' . env('SERVICE_HOST', '127.0.0.1') . ':51234/api/strand/' . $cluster);
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function stopConveyor() {
        try {
            Http::timeout(3)->post('http://' . env('SERVICE_HOST', '127.0.0.1') . ':51234/api/conveyor/stop');
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * FUZZY MATCHMAKER (STRICTLY AGAINST DATABASE)
     * Finds the closest LRN in the students table from OCR candidates.
     * PRIORITIZES the preferred LRN if provided.
     */
    private function findBestLrnMatch($candidates, $preferredLrn = null) {
        if (empty($candidates)) return null;

        // Clean and normalize candidates
        $cleanCandidates = array_map(fn($c) => preg_replace('/[^0-9]/', '', $c), $candidates);
        $cleanCandidates = array_filter($cleanCandidates, fn($c) => strlen($c) >= 10);
        $cleanCandidates = array_unique($cleanCandidates);

        // 1. High-Priority Check: Does any candidate match the preferred LRN closely?
        if ($preferredLrn) {
            $cleanPreferred = preg_replace('/[^0-9]/', '', $preferredLrn);
            foreach ($cleanCandidates as $cand) {
                similar_text($cand, $cleanPreferred, $percent);
                // Require 67% match (approx 8/12 digits) to consider it the same student
                if ($percent >= 67) {
                    Log::info("Preferred LRN Match Found (High Priority)", ['lrn' => $preferredLrn, 'percent' => $percent]);
                    return $preferredLrn;
                }
            }
        }

        // 2. General Database Match (Fallback)
        $allLrns = DB::table('students')->pluck('lrn')->toArray();
        $bestMatch = null;
        $bestScore = 999; 
        $bestDist = 999;

        foreach ($cleanCandidates as $cand) {
            foreach ($allLrns as $dbLrn) {
                $cleanDb = preg_replace('/[^0-9]/', '', $dbLrn);
                $dist = levenshtein($cand, $cleanDb);
                
                // Weighting: Prefix match is a strong signal for LRNs
                $prefixLen = 0;
                $maxCheck = min(strlen($cand), strlen($cleanDb), 6);
                for($i=0; $i<$maxCheck; $i++) {
                    if($cand[$i] === $cleanDb[$i]) $prefixLen++;
                    else break;
                }

                // Calculate a score where prefix matches reduce the "perceived" distance
                $score = $dist - ($prefixLen * 0.7);

                // Acceptance criteria: Strict matching for database fallback
                if ($dist <= 2 || ($dist <= 3 && $prefixLen >= 4)) {
                    if ($score < $bestScore) {
                        $bestScore = $score;
                        $bestMatch = $dbLrn;
                        $bestDist = $dist;
                    }
                }
            }
        }

        if ($bestMatch) {
            Log::info("LRN Closest Match Found in Database", ['matched' => $bestMatch, 'score' => $bestScore]);
        }

        return $bestMatch;
    }

    public function processDocument(Request $request)
    {
        try {
            set_time_limit(0); 
            $imageData = $request->input('image_data');
            $docType = $request->input('document_type', 'Report Card (SF9)');
            $userId = session('user_id', 1);
            $studentId = $this->getStudentId($userId);

            Log::info("--- START processDocument ---", ['userId' => $userId, 'studentId' => $studentId, 'docType' => $docType]);
            session(['current_doc' => $docType]);

            if (!$imageData || strpos($imageData, ';base64,') === false) {
                return response()->json(['status' => 'error', 'message' => 'Image data is invalid.']);
            }

            $imageParts = explode(";base64,", $imageData);
            $imageTypeAux = explode("image/", $imageParts[0]);
            $imageType = $imageTypeAux[1] ?? 'jpeg';
            $imageBase64 = base64_decode($imageParts[1]);
            
            $fileName = 'scan_' . $userId . '_' . time() . '.' . $imageType;
            $filePath = 'scans/' . $fileName;
            Storage::disk('public')->put($filePath, $imageBase64);
            $imageFullPath = storage_path('app/public/' . $filePath);

            $scanId = DB::table('scans')->insertGetId([
                'user_id' => $userId, 'document_type' => $docType, 'file_path' => $filePath,
                'status' => 'pending', 'remarks' => 'Processing...', 'created_at' => now(), 'updated_at' => now()
            ]);

            $prefix = $this->getPrefix($docType);

            if ($prefix === 'sf9_back') {
                DB::table('kiosk_enrollments')->updateOrInsert(
                    ['student_id' => $studentId],
                    [
                        "{$prefix}_path" => $filePath, "{$prefix}_status" => 'verified', "{$prefix}_remarks" => 'Stored (No verification required)',
                        'latest_scan_type' => $docType, 'latest_scan_status' => 'verified', 'latest_scan_remarks' => 'Stored', 'updated_at' => now()
                    ]
                );
                DB::table('scans')->where('id', $scanId)->update(['status' => 'verified', 'remarks' => 'Stored', 'updated_at' => now()]);
                return response()->json(['status' => 'success', 'redirect' => url('/student/verifying')]);
            }

            $student = Student::find($studentId);
            $expectedLrn = $student->lrn ?? null;

            DB::table('kiosk_enrollments')->updateOrInsert(
                ['student_id' => $studentId],
                [
                    "{$prefix}_path" => $filePath, "{$prefix}_status" => 'pending', "{$prefix}_remarks" => 'Processing...',
                    'latest_scan_type' => $docType, 'latest_scan_status' => 'pending', 'latest_scan_remarks' => 'Processing...', 'updated_at' => now()
                ]
            );

            $handleFailure = function($remarks) use ($studentId, $docType, $prefix, $scanId) {
                $enrollment = DB::table('kiosk_enrollments')->where('student_id', $studentId)->first();
                $attemptsCol = "{$prefix}_attempts";
                $newAttempts = ($enrollment->$attemptsCol ?? 0) + 1;
                $status = ($newAttempts >= 3) ? 'manual_verification' : 'failed';
                $finalRemarks = ($newAttempts >= 3) ? 'Sent to Admin for Manual Verification.' : $remarks;
                DB::table('kiosk_enrollments')->where('student_id', $studentId)->update([
                    "{$prefix}_status" => $status, "{$prefix}_remarks" => $finalRemarks, "{$prefix}_attempts" => $newAttempts,
                    'latest_scan_status' => $status, 'latest_scan_remarks' => $finalRemarks
                ]);
                DB::table('scans')->where('id', $scanId)->update(['status' => $status, 'remarks' => $finalRemarks, 'updated_at' => now()]);
                return ['is_strike_3' => ($newAttempts >= 3), 'count' => $newAttempts];
            };

            $lowerDoc = strtolower($docType);
            if (str_contains($lowerDoc, 'report') || str_contains($lowerDoc, 'sf9')) $pythonDocType = 'report_card';
            elseif (str_contains($lowerDoc, 'birth') || str_contains($lowerDoc, 'psa')) $pythonDocType = 'birth_certificate';
            elseif (str_contains($lowerDoc, 'enrollment') || str_contains($lowerDoc, 'form')) $pythonDocType = 'enroll_form';
            elseif (str_contains($lowerDoc, 'als') || str_contains($lowerDoc, 'alternative')) $pythonDocType = 'als_certificate';
            elseif (str_contains($lowerDoc, 'affidavit') || str_contains($lowerDoc, 'sworn') || str_contains($lowerDoc, 'undertaking')) $pythonDocType = 'affidavit';
            else $pythonDocType = 'generic_name_check'; 

            $user = DB::table('users')->where('id', $userId)->first();
            $expectedFirstName = $user->first_name ?? 'Unknown';
            $expectedLastName = $user->last_name ?? 'Unknown';

            try {
                $ocrResponse = Http::timeout(300)
                    ->attach('image', file_get_contents($imageFullPath), $fileName)
                    ->post('http://'.env('SERVICE_HOST', '127.0.0.1').':9001/ocr', [
                        'doc_type' => $pythonDocType, 
                        'scan_id' => $userId,
                        'first_name' => $expectedFirstName, 
                        'last_name' => $expectedLastName,
                        'expected_lrn' => $expectedLrn
                    ]);

                if ($ocrResponse->failed()) {
                    $handleFailure('OCR Server Error');
                    return response()->json(['status' => 'success', 'redirect' => url('/student/verifying')]);
                }

                $ocrResult = $ocrResponse->json();
                if (isset($ocrResult['success']) && $ocrResult['success'] === false) {
                    $handleFailure($ocrResult['error'] ?? 'Document Rejected.');
                    return response()->json(['status' => 'success', 'redirect' => url('/student/verifying')]);
                }

                if (isset($ocrResult['success']) && $ocrResult['success'] === true) {
                    // --- SMART MATCHMAKING ---
                    $candidates = $ocrResult['candidates'] ?? [$ocrResult['lrn'] ?? null];
                    $candidates = array_filter($candidates);
                    
                    // PRIORITIZE: If the user is logged in, and OCR verified the name/doc, use the system LRN.
                    $lrn = $expectedLrn ?: $this->findBestLrnMatch($candidates);
                    
                    if ($lrn) DB::table('scans')->where('id', $scanId)->update(['lrn' => $lrn]);

                    $isReportCard = (str_contains(strtolower($docType), 'report') || str_contains(strtolower($docType), 'sf9'));
                    if ($isReportCard) {
                        if (!$lrn) {
                            $handleFailure("Could not find your record in the database based on the scanned LRN.");
                            return response()->json(['status' => 'success', 'redirect' => url('/student/verifying')]);
                        }

                        DB::table('kiosk_enrollments')->where('student_id', $studentId)->update([
                            'sf9_remarks' => 'Sending to LIS...', 'student_lrn' => $lrn,
                            'latest_scan_remarks' => 'Sending to LIS...', 'updated_at' => now()
                        ]);
                        
                        $enrollingGrade = session('grade_level') ?: DB::table('kiosk_enrollments')->where('student_id', $studentId)->value('grade_level') ?: '11'; 
                        $expectedGrade = ($enrollingGrade == '12') ? 'Grade 11' : 'Grade 10';
                        $callbackUrl = $request->getSchemeAndHttpHost() . '/api/lis-callback'; 

                        try {
                            Http::timeout(10)->post('http://'.env('SERVICE_HOST', '127.0.0.1').':5001/verify', [
                                'lrn' => $lrn, 'expected_grade' => $expectedGrade, 'webhook_url' => $callbackUrl, 'scan_id' => $scanId
                            ]);
                        } catch (\Exception $e) {
                            $handleFailure('LIS Verifier is offline.');
                        }
                    } else {
                        DB::table('kiosk_enrollments')->where('student_id', $studentId)->update([
                            "{$prefix}_status" => 'verified', "{$prefix}_remarks" => 'Verified',
                            'latest_scan_status' => 'verified', 'latest_scan_remarks' => 'Verified', 'updated_at' => now()
                        ]);
                        DB::table('scans')->where('id', $scanId)->update(['status' => 'verified', 'remarks' => 'Verified', 'updated_at' => now()]);
                    }
                }
            } catch (\Exception $e) {
                Log::error("OCR Exception", ['error' => $e->getMessage()]);
                $handleFailure('AI Engine Offline');
            }
            return response()->json(['status' => 'success', 'redirect' => url('/student/verifying')]);
        } catch (\Exception $e) {
            Log::error("FATAL ERROR in processDocument", ['msg' => $e->getMessage()]);
            return response()->json(['status' => 'error', 'message' => 'System Error.']);
        }
    }

    public function lisCallback(Request $request)
    {
        $scanId = $request->input('scan_id');
        $status = $request->input('result'); 
        $extractedLrn = $request->input('lrn');
        Log::info("LIS Callback received", ['scanId' => $scanId, 'status' => $status, 'lrn' => $extractedLrn]);
        if ($scanId && $status) {
            $scan = DB::table('scans')->where('id', $scanId)->first();
            if (!$scan) return response()->json(['success' => false], 404);
            $studentId = $this->getStudentId($scan->user_id);
            $finalStatus = ($status === 'verified_lis') ? 'verified' : 'failed';
            if ($finalStatus === 'failed') {
                $enrollment = DB::table('kiosk_enrollments')->where('student_id', $studentId)->first();
                $newAttempts = ($enrollment->sf9_attempts ?? 0) + 1;
                $dbStatus = ($newAttempts >= 3) ? 'manual_verification' : 'failed';
                $remarks = ($newAttempts >= 3) ? 'Sent to Admin for Manual Verification.' : 'LIS Verification Failed.';
                DB::table('kiosk_enrollments')->where('student_id', $studentId)->update([
                    'sf9_status' => $dbStatus, 'sf9_remarks' => $remarks, 'sf9_attempts' => $newAttempts,
                    'latest_scan_status' => $dbStatus, 'latest_scan_remarks' => $remarks, 'updated_at' => now()
                ]);
                DB::table('scans')->where('id', $scanId)->update(['status' => $dbStatus, 'remarks' => $remarks, 'updated_at' => now()]);
            } else {
                if ($extractedLrn) {
                    $student = Student::find($studentId);
                    if ($student && $student->lrn !== $extractedLrn) {
                        $student->update(['lrn' => $extractedLrn]);
                        DB::table('kiosk_enrollments')->where('student_id', $studentId)->update(['student_lrn' => $extractedLrn]);
                    }
                }
                DB::table('kiosk_enrollments')->where('student_id', $studentId)->update([
                    'sf9_status' => 'verified', 'sf9_remarks' => 'Verified via LIS',
                    'latest_scan_status' => 'verified', 'latest_scan_remarks' => 'Verified via LIS', 'updated_at' => now()
                ]);
                DB::table('scans')->where('id', $scanId)->update(['status' => 'verified', 'remarks' => 'Verified via LIS', 'updated_at' => now()]);
            }
            return response()->json(['success' => true]);
        }
        return response()->json(['success' => false], 400);
    }
    
    public function checkScanStatus()
    {
        $userId = session('user_id');
        if (!$userId) return response()->json(['status' => 'error']);
        $studentId = $this->getStudentId($userId);
        $enrollment = DB::table('kiosk_enrollments')->where('student_id', $studentId)->first();
        if (!$enrollment) return response()->json(['status' => 'pending']);
        $docType = session('current_doc', 'Report Card (SF9)');
        $prefix = $this->getPrefix($docType);
        $attempts = $enrollment->{$prefix . '_attempts'} ?? 0;
        $currentStatus = $enrollment->{$prefix . '_status'} ?? 'pending';
        $currentRemarks = $enrollment->{$prefix . '_remarks'} ?? 'Processing...';
        $shouldTriggerHardware = (str_contains(strtolower($docType), 'sf9') && !str_contains(strtolower($docType), 'back')) ? false : true;
        return response()->json([
            'status' => $currentStatus, 'remarks' => $currentRemarks, 'next_url' => $this->getNextUrl($userId),
            'current_doc' => $docType, 'attempts' => $attempts, 'should_trigger_hardware' => $shouldTriggerHardware
        ]);
    }

    public function checkRejection(Request $request) {
        $userId = session('user_id');
        if (!$userId) return response()->json(['rejected' => false]);
        try {
            $response = Http::timeout(2)->get('http://' . env('SERVICE_HOST', '127.0.0.1') . ':51234/api/sensor/check-rejection');
            $data = $response->json();
            if (isset($data['rejected']) && $data['rejected'] === true) {
                $docType = session('current_doc', 'Unknown Document');
                $studentId = $this->getStudentId($userId);
                $enrollment = DB::table('kiosk_enrollments')->where('student_id', $studentId)->first();
                $rejectedPapers = json_decode($enrollment->rejected_papers ?? '[]', true);
                $alreadyExists = false;
                foreach ($rejectedPapers as $rej) {
                    if ($rej['document_type'] === $docType) {
                        if (abs(now()->diffInSeconds(\Carbon\Carbon::parse($rej['rejected_at']))) < 15) { $alreadyExists = true; break; }
                    }
                }
                if (!$alreadyExists) {
                    $rejectedPapers[] = ['document_type' => $docType, 'rejected_at' => now()->toDateTimeString(), 'prefix' => $this->getPrefix($docType)];
                    DB::table('kiosk_enrollments')->where('student_id', $studentId)->update(['rejected_papers' => json_encode($rejectedPapers)]);
                }
                return response()->json(['rejected' => true]);
            }
        } catch (\Exception $e) {}
        return response()->json(['rejected' => false]);
    }

    private function getNextUrl($userId) {
        $selectedDocs = session('docs_to_scan', []);
        $currentDoc = session('current_doc');
        $studentId = $this->getStudentId($userId);
        $enrollment = DB::table('kiosk_enrollments')->where('student_id', $studentId)->first();
        if ($currentDoc && (str_contains(strtolower($currentDoc), 'sf9') && !str_contains(strtolower($currentDoc), 'back'))) {
             if ($enrollment && $enrollment->sf9_status === 'verified' && ($enrollment->sf9_back_status !== 'verified' && $enrollment->sf9_back_status !== 'manual_verification')) {
                 return url('/student/capture?doc=' . urlencode('Report Card (SF9 Back)'));
             }
        }
        if (!empty($selectedDocs)) {
            $currentIndex = array_search($currentDoc, $selectedDocs);
            if ($currentIndex === false && str_contains(strtolower($currentDoc), 'sf9 back')) {
                foreach ($selectedDocs as $idx => $doc) {
                    if (str_contains(strtolower($doc), 'sf9') && !str_contains(strtolower($doc), 'back')) { $currentIndex = $idx; break; }
                }
            }
            if ($currentIndex !== false && isset($selectedDocs[$currentIndex + 1])) return url('/student/capture?doc=' . urlencode($selectedDocs[$currentIndex + 1]));
        }
        if ($enrollment) {
            $enrollController = new \App\Http\Controllers\EnrollmentController();
            $requiredDocs = $enrollController->getRequiredDocs($enrollment->academic_status);
            $allVerified = true;
            foreach ($requiredDocs as $label => $prefix) {
                $status = $enrollment->{$prefix . '_status'} ?? 'pending';
                if ($prefix === 'sf9') {
                    if (($status !== 'verified' && $status !== 'manual_verification') || ($enrollment->sf9_back_status !== 'verified' && $enrollment->sf9_back_status !== 'manual_verification')) { $allVerified = false; break; }
                } elseif ($status !== 'verified' && $status !== 'manual_verification') { $allVerified = false; break; }
            }
            if ($allVerified) return url('/student/thankyou');
        }
        return url('/student/checklist');
    }
}
