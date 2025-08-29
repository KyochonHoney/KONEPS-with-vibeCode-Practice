<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\Admin\TenderController;
use App\Http\Controllers\Admin\AttachmentController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// [BEGIN nara:web_routes]

// 홈페이지 - 인증 상태에 따른 리다이렉션
Route::get('/', function () {
    if (Auth::check()) {
        $user = Auth::user();
        if ($user->isAdmin()) {
            return redirect('/admin/dashboard');
        }
        return redirect('/dashboard');
    }
    return view('home');
})->name('home');

// 인증 관련 라우트 (게스트만 접근 가능)
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'showRegisterForm'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
});

// 인증된 사용자만 접근 가능한 라우트
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    
    // 일반 사용자 대시보드
    Route::get('/dashboard', [AuthController::class, 'dashboard'])->name('dashboard');
    
    // 관리자 대시보드 (관리자 이상만 접근)
    Route::middleware(['role:admin,super_admin'])->group(function () {
        Route::get('/admin/dashboard', [AuthController::class, 'adminDashboard'])->name('admin.dashboard');
        
        // 입찰공고 관리
        Route::prefix('admin/tenders')->name('admin.tenders.')->group(function () {
            Route::get('/', [TenderController::class, 'index'])->name('index');
            Route::get('/collect', [TenderController::class, 'collect'])->name('collect');
            Route::post('/collect', [TenderController::class, 'executeCollection'])->name('execute_collection');
            Route::get('/test-api', [TenderController::class, 'testApi'])->name('test_api');
            Route::get('/stats', [TenderController::class, 'dashboardStats'])->name('stats');
            Route::get('/{tender}', [TenderController::class, 'show'])->name('show');
            Route::delete('/{tender}', [TenderController::class, 'destroy'])->name('destroy');
            Route::patch('/{tender}/status', [TenderController::class, 'updateStatus'])->name('update_status');
            Route::patch('/bulk/status', [TenderController::class, 'bulkUpdateStatus'])->name('bulk_update_status');
        });
        
        // 첨부파일 관리
        Route::prefix('admin/attachments')->name('admin.attachments.')->group(function () {
            Route::get('/', [AttachmentController::class, 'index'])->name('index');
            Route::post('/collect/{tender}', [AttachmentController::class, 'collect'])->name('collect');
            Route::post('/download-all-as-hwp/{tender}', [AttachmentController::class, 'downloadAllFilesAsHwp'])->name('download_all_as_hwp');
            Route::post('/download-hwp/{tender}', [AttachmentController::class, 'downloadHwpFiles'])->name('download_hwp');
            Route::get('/download/{attachment}', [AttachmentController::class, 'download'])->name('download');
            Route::get('/download-hwp-zip/{tender}', [AttachmentController::class, 'downloadAllHwpAsZip'])->name('download_hwp_zip');
            Route::get('/download-hwp/{attachment}', [AttachmentController::class, 'downloadHwpFile'])->name('download_hwp_file');
            Route::post('/force-download/{attachment}', [AttachmentController::class, 'forceDownload'])->name('force_download');
            Route::delete('/{attachment}', [AttachmentController::class, 'destroy'])->name('destroy');
            Route::post('/bulk-download', [AttachmentController::class, 'bulkDownload'])->name('bulk_download');
            Route::get('/stats', [AttachmentController::class, 'stats'])->name('stats');
        });
    });
});

// [END nara:web_routes]
