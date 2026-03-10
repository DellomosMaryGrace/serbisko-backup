<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\ScanController;
use App\Http\Middleware\CheckAdmin;
use App\Http\Controllers\EnrollmentController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return view('login');
});

Route::post('/login', [AuthController::class, 'login']);
Route::get('/logout', [AuthController::class, 'logout']);

// ==========================================
// ADMIN ROUTES (Protected via CheckAdmin)
// ==========================================
Route::middleware([CheckAdmin::class])->group(function () {
    Route::get('/dashboard', [AdminController::class, 'index'])->name('admin.dashboard');

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('/dashboard', [AdminController::class, 'index'])->name('dashboard');
        Route::get('/students', [AdminController::class, 'students'])->name('students');
        Route::get('/students/profile/{lrn}', [AdminController::class, 'profilepage'])->name('studentpage.profilepage'); // Merged from backup
        Route::get('/systemsync', [AdminController::class, 'systemsync'])->name('systemsync');
        Route::get('/verification', [AdminController::class, 'verification'])->name('verification');
        Route::get('/requirementhub', [AdminController::class, 'requirementhub'])->name('requirementhub');
        Route::get('/accountsettings', [AdminController::class, 'accountsettings'])->name('accountsettings');
        Route::post('/systemsync/perform', [AdminController::class, 'performSync'])->name('sync.perform');
        Route::post('/verification/action', [AdminController::class, 'handleVerificationAction'])->name('verification.action');
        Route::get('/accessmanagement', [AdminController::class, 'accessManagement'])->name('accessmanagement');
        Route::post('/accessmanagement/store', [AdminController::class, 'storeUser'])->name('accessmanagement.store');
        Route::delete('/users/{id}', [AdminController::class, 'destroy'])->name('destroyUser'); // Resulting name: admin.destroyUser
    });
});

// ==========================================
// STUDENT ENROLLMENT FLOW (Protected)
// ==========================================
Route::get('/student/grade-selection', function () {
    if (!session()->has('user_id')) return redirect('/');
    return view('student.selection');
});

Route::post('/student/save-grade', [EnrollmentController::class, 'saveGrade']);

Route::get('/student/status-selection', function () {
    if (!session()->has('user_id')) return redirect('/');
    return view('student.status');
});

Route::post('/student/save-status', [EnrollmentController::class, 'saveStatus']);

Route::get('/student/track-selection', function () {
    if (!session()->has('user_id')) return redirect('/');
    return view('student.track');
});

Route::post('/student/save-track', [EnrollmentController::class, 'saveTrack']);

Route::get('/student/cluster-selection', function () {
    if (!session()->has('user_id')) return redirect('/');
    return view('student.cluster');
});

// JUST THIS ONE LINE - The Controller handles the Arduino logic now!
Route::post('/student/save-cluster', [EnrollmentController::class, 'saveCluster']);

Route::get('/student/cluster-loading', function () {
    if (!session()->has('user_id')) return redirect('/');
    return view('student.cluster_loading');
});

Route::get('/student/checklist', function () {
    if (!session()->has('user_id')) return redirect('/');
    return view('student.checklist');
});

// Replace the old checklist closure
Route::post('/student/save-checklist', [EnrollmentController::class, 'saveChecklist']);

// Replace the old capture closure
Route::get('/student/capture', [EnrollmentController::class, 'showCapture']);

Route::post('/student/save-image', [ScanController::class, 'processDocument']);

Route::get('/student/verifying', function () {
    if (!session()->has('user_id')) return redirect('/');
    return view('student.verifying');
});

Route::get('/student/check-scan-status', [ScanController::class, 'checkScanStatus']);

Route::get('/api/check-completion', function () {
    $record = DB::table('scans')
                ->where('user_id', session('user_id', 1)) 
                ->latest()
                ->first();
    
    if (!$record) {
        return response()->json(['status' => 'not_found']);
    }
    return response()->json(['status' => $record->status]);
});

Route::get('/student/mismatch', function () {
    if (!session()->has('user_id')) return redirect('/');
    return view('student.mismatch'); 
});

Route::get('/student/dashboard', function () {
    if (!session()->has('user_id')) return redirect('/');
    return "<h1>Enrollment Data Saved! Welcome to your Dashboard.</h1>";
});

Route::get('/student/thankyou', function () {
    if (!session()->has('user_id')) return redirect('/');
    return view('student.thankyou');
});

// ==========================================
// PYTHON WEBHOOKS (CSRF Exempt)
// ==========================================
Route::post('/api/lis-callback', [ScanController::class, 'lisCallback'])
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);
