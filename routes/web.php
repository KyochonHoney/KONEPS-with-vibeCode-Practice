<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\Admin\TenderController;
use App\Http\Controllers\Admin\AttachmentController;
use App\Http\Controllers\Admin\AnalysisController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\ProposalController;
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
    
    // 관리자 대시보드 (모든 인증된 사용자 접근 가능)
    Route::get('/admin/dashboard', [AuthController::class, 'adminDashboard'])->name('admin.dashboard');

    // 입찰공고 관리
    Route::prefix('admin/tenders')->name('admin.tenders.')->group(function () {
        Route::get('/', [TenderController::class, 'index'])->name('index');
        Route::get('/collect', [TenderController::class, 'collect'])->name('collect');
        Route::post('/collect', [TenderController::class, 'executeCollection'])->name('execute_collection');
        Route::get('/cleanup', [TenderController::class, 'cleanup'])->name('cleanup');
        Route::post('/cleanup', [TenderController::class, 'executeCleanup'])->name('execute_cleanup');
        Route::get('/test-api', [TenderController::class, 'testApi'])->name('test_api');
        Route::get('/stats', [TenderController::class, 'dashboardStats'])->name('stats');
        Route::get('/{tender}', [TenderController::class, 'show'])->name('show');
        Route::delete('/{tender}', [TenderController::class, 'destroy'])->name('destroy');
        Route::patch('/{tender}/status', [TenderController::class, 'updateStatus'])->name('update_status');
        Route::patch('/{tender}/toggle-favorite', [TenderController::class, 'toggleFavorite'])->name('toggle_favorite');
        Route::patch('/{tender}/toggle-unsuitable', [TenderController::class, 'toggleUnsuitable'])->name('toggle_unsuitable');
        Route::post('/{tender}/check-sangju', [TenderController::class, 'checkSangju'])->name('check_sangju');
        Route::post('/{tender}/mention', [TenderController::class, 'storeMention'])->name('store_mention');
        Route::delete('/{tender}/mention', [TenderController::class, 'destroyMention'])->name('destroy_mention');
        Route::post('/{tender}/crawl-proposal-files', [TenderController::class, 'crawlProposalFiles'])->name('crawl_proposal_files');
        Route::get('/{tender}/download-attachment/{seq}', [TenderController::class, 'downloadAttachment'])->name('download_attachment');
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

    // AI 분석 관리
    Route::prefix('admin/analyses')->name('admin.analyses.')->group(function () {
        Route::get('/', [AnalysisController::class, 'index'])->name('index');
        Route::get('/{analysis}', [AnalysisController::class, 'show'])->name('show');
        Route::post('/analyze/{tender}', [AnalysisController::class, 'analyze'])->name('analyze');
        Route::post('/bulk-analyze', [AnalysisController::class, 'bulkAnalyze'])->name('bulk_analyze');
        Route::delete('/{analysis}', [AnalysisController::class, 'destroy'])->name('destroy');
        Route::get('/api/stats', [AnalysisController::class, 'stats'])->name('stats');
        Route::post('/api/check-status', [AnalysisController::class, 'checkAnalysisStatus'])->name('check_status');
    });

    // AI 제안서 관리
    Route::prefix('admin/proposals')->name('admin.proposals.')->group(function () {
        Route::get('/', [ProposalController::class, 'index'])->name('index');
        Route::get('/create', [ProposalController::class, 'create'])->name('create');
        Route::post('/', [ProposalController::class, 'store'])->name('store');
        Route::get('/{proposal}', [ProposalController::class, 'show'])->name('show');
        Route::post('/{proposal}/regenerate', [ProposalController::class, 'regenerate'])->name('regenerate');
        Route::get('/{proposal}/download', [ProposalController::class, 'download'])->name('download');
        Route::delete('/{proposal}', [ProposalController::class, 'destroy'])->name('destroy');
        Route::post('/bulk-generate', [ProposalController::class, 'bulkGenerate'])->name('bulk-generate');
        Route::get('/{proposal}/preview', [ProposalController::class, 'preview'])->name('preview');
        Route::get('/{proposal}/status', [ProposalController::class, 'status'])->name('status');
    });

    // 사용자 관리
    Route::prefix('admin/users')->name('admin.users.')->group(function () {
        Route::get('/', [UserController::class, 'index'])->name('index');
        Route::get('/create', [UserController::class, 'create'])->name('create');
        Route::post('/', [UserController::class, 'store'])->name('store');
        Route::get('/{user}/edit', [UserController::class, 'edit'])->name('edit');
        Route::put('/{user}', [UserController::class, 'update'])->name('update');
        Route::delete('/{user}', [UserController::class, 'destroy'])->name('destroy');
    });
});

// 세종대왕 페이지 (인증 불필요)
Route::get('/sejong', function () {
    return view('sejong');
})->name('sejong');

// [END nara:web_routes]
