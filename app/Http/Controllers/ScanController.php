<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ScanController extends Controller
{
    // 1. RECEIVE IMAGE FROM FRONTEND
    public function processDocument(Request $request)
    {
        // Validate
        $request->validate([
            'image_data' => 'required', // Base64 string from camera
            'document_type' => 'required|string',
        ]);

        $user = session('user_name', 'Student');
        $docType = $this->normalizeDocType($request->document_type);

        // Decode Base64 Image
        $image = $request->input('image_data');
        $image = str_replace('data:image/jpeg;base64,', '', $image);
        $image = str_replace(' ', '+', $image);
        $imageName = 'scans/' . time() . '.jpg';
        
        // Save to Storage
        Storage::disk('public')->put($imageName, base64_decode($image));

        // CALL PYTHON OCR (Port 5000)
        try {
            $response = Http::attach(
                'image', Storage::disk('public')->get($imageName), 'doc.jpg'
            )->post('http://localhost:5000/ocr', [
                'doc_type' => $docType,
                'student_name' => $user
            ]);

            $result = $response->json();

            // Handle OCR Failure
            if ($response->failed() || !($result['success'] ?? false)) {
                return back()->with('error', 'Document Error: ' . ($result['error'] ?? 'Unknown error'));
            }

            // Save Success to Database
            $scanId = DB::table('scans')->insertGetId([
                'user_id' => session('user_id'),
                'doc_type' => $docType,
                'image_path' => $imageName,
                'lrn' => $result['lrn'] ?? null,
                'status' => 'verified_ocr',
                'remarks' => $result['message'] ?? 'OCR Verified',
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // IF REPORT CARD -> TRIGGER LIS VERIFIER (Port 5001)
            if ($docType === 'report_card' && isset($result['lrn'])) {
                $this->triggerLisVerification($scanId, $result['lrn']);
            }

            return redirect('/student/verifying');

        } catch (\Exception $e) {
            return back()->with('error', 'System Error: OCR Service unreachable.');
        }
    }

    // Helper: Normalize names
    private function normalizeDocType($type) {
        if (str_contains(strtolower($type), 'report')) return 'report_card';
        if (str_contains(strtolower($type), 'birth')) return 'birth_cert';
        return 'generic_doc';
    }

    // 2. TRIGGER LIS VERIFICATION (Background)
    private function triggerLisVerification($scanId, $lrn)
    {
        try {
            // Fire and Forget (don't wait for response)
            Http::post('http://localhost:5001/verify', [
                'lrn' => $lrn,
                'scan_id' => $scanId,
                'webhook_url' => route('api.lis_callback') // Laravel URL for callback
            ]);
        } catch (\Exception $e) {
            // Log error but don't stop flow
        }
    }

    // 3. WEBHOOK: CALLED BY PYTHON LIS WHEN DONE
    public function lisCallback(Request $request)
    {
        $scanId = $request->input('scan_id');
        $result = $request->input('result'); // GRADE_10, GRADE_11, NEITHER

        $status = ($result == 'GRADE_10' || $result == 'GRADE_11') ? 'verified_lis' : 'failed_lis';
        
        DB::table('scans')->where('id', $scanId)->update([
            'status' => $status,
            'remarks' => "LIS Result: $result",
            'updated_at' => now()
        ]);

        return response()->json(['success' => true]);
    }

    // 4. TRIGGER ARDUINO (Frontend calls this)
    public function triggerSorting()
    {
        // Get Student's Strand
        $strand = session('track') == 'academic' ? 'STEM' : 'TVL'; // Simplified logic

        try {
            $response = Http::post("http://localhost:8080/api/strand/" . $strand);
            return response()->json($response->json());
        } catch (\Exception $e) {
            return response()->json(['error' => 'Arduino Offline'], 500);
        }
    }
}