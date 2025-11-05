<?php

// [BEGIN nara:attachment_controller]
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Models\Tender;
use App\Services\AttachmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use ZipArchive;

/**
 * 첨부파일 관리 컨트롤러
 * 
 * @package App\Http\Controllers\Admin
 */
class AttachmentController extends Controller
{
    private AttachmentService $attachmentService;

    public function __construct(AttachmentService $attachmentService)
    {
        $this->attachmentService = $attachmentService;
    }

    /**
     * 첨부파일 목록 조회
     */
    public function index(Request $request)
    {
        $query = Attachment::with('tender')
                          ->latest('created_at');

        // 필터링 옵션
        if ($request->filled('status')) {
            $query->where('download_status', $request->status);
        }

        if ($request->filled('type') && $request->type === 'hwp') {
            $query->hwpFiles();
        }

        if ($request->filled('tender_id')) {
            $query->where('tender_id', $request->tender_id);
        }

        $attachments = $query->paginate(50)->withQueryString();
        
        // AJAX 요청인 경우 JSON으로 응답
        if ($request->filled('ajax')) {
            return response()->json([
                'attachments' => $attachments
            ]);
        }
        
        $stats = $this->attachmentService->getDownloadStats();
        return view('admin.attachments.index', compact('attachments', 'stats'));
    }

    /**
     * 특정 공고의 첨부파일 수집
     */
    public function collect(Tender $tender): JsonResponse
    {
        try {
            $count = $this->attachmentService->collectAttachmentsForTender($tender);
            
            return response()->json([
                'success' => true,
                'message' => "{$count}개의 첨부파일 정보를 수집했습니다.",
                'count' => $count
            ]);
        } catch (\Exception $e) {
            Log::error('첨부파일 수집 실패', [
                'tender_id' => $tender->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => '첨부파일 수집에 실패했습니다: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 특정 공고의 모든 파일을 한글(HWP) 형식으로 다운로드
     */
    public function downloadAllFilesAsHwp(Tender $tender): JsonResponse
    {
        try {
            $results = $this->attachmentService->downloadAllFilesAsHwp($tender);
            
            $message = "모든 파일을 한글 형식으로 다운로드 완료: ";
            $message .= "{$results['downloaded']}개 성공, {$results['failed']}개 실패";
            if ($results['converted'] > 0) {
                $message .= " ({$results['converted']}개 파일이 HWP로 변환됨)";
            }
            
            return response()->json([
                'success' => true,
                'message' => $message,
                'results' => $results
            ]);
        } catch (\Exception $e) {
            Log::error('모든 파일 한글 변환 다운로드 실패', [
                'tender_id' => $tender->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => '모든 파일을 한글 형식으로 다운로드하는데 실패했습니다: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 특정 공고의 한글파일만 다운로드 (기존 기능 유지)
     */
    public function downloadHwpFiles(Tender $tender): JsonResponse
    {
        try {
            $results = $this->attachmentService->downloadHwpFilesForTender($tender);
            
            return response()->json([
                'success' => true,
                'message' => "한글파일 다운로드 완료: {$results['downloaded']}개 성공, {$results['failed']}개 실패",
                'results' => $results
            ]);
        } catch (\Exception $e) {
            Log::error('한글파일 다운로드 실패', [
                'tender_id' => $tender->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => '한글파일 다운로드에 실패했습니다: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 개별 첨부파일 다운로드
     */
    public function download(Attachment $attachment): BinaryFileResponse
    {
        if (!$attachment->is_downloaded) {
            abort(404, '파일을 찾을 수 없습니다.');
        }

        $filePath = $attachment->local_path;

        // Check both possible storage paths
        $fullPath = storage_path('app/' . $filePath);
        if (!file_exists($fullPath)) {
            $fullPath = storage_path('app/private/' . $filePath);
        }

        if (!file_exists($fullPath)) {
            abort(404, '파일이 존재하지 않습니다: ' . basename($filePath));
        }

        return response()->download($fullPath, $attachment->file_name ?: $attachment->original_name);
    }

    /**
     * 첨부파일 강제 다운로드 (재시도)
     */
    public function forceDownload(Attachment $attachment): JsonResponse
    {
        try {
            $this->attachmentService->downloadAttachment($attachment);
            
            return response()->json([
                'success' => true,
                'message' => '파일 다운로드가 완료되었습니다.',
                'attachment' => $attachment->fresh()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '파일 다운로드에 실패했습니다: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 첨부파일 삭제
     */
    public function destroy(Attachment $attachment): JsonResponse
    {
        try {
            // 로컬 파일도 삭제
            if ($attachment->local_path && Storage::exists($attachment->local_path)) {
                Storage::delete($attachment->local_path);
            }

            $attachment->delete();

            return response()->json([
                'success' => true,
                'message' => '첨부파일이 삭제되었습니다.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '첨부파일 삭제에 실패했습니다: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 대량 다운로드 작업
     */
    public function bulkDownload(Request $request): JsonResponse
    {
        $request->validate([
            'attachment_ids' => 'required|array',
            'attachment_ids.*' => 'integer|exists:attachments,id'
        ]);

        $attachments = Attachment::whereIn('id', $request->attachment_ids)
                                ->where('download_status', 'pending')
                                ->get();

        $results = [
            'total' => $attachments->count(),
            'downloaded' => 0,
            'failed' => 0,
            'errors' => []
        ];

        foreach ($attachments as $attachment) {
            try {
                $this->attachmentService->downloadAttachment($attachment);
                $results['downloaded']++;
            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][] = [
                    'id' => $attachment->id,
                    'name' => $attachment->original_name,
                    'error' => $e->getMessage()
                ];
            }
        }

        return response()->json([
            'success' => true,
            'message' => "대량 다운로드 완료: {$results['downloaded']}개 성공, {$results['failed']}개 실패",
            'results' => $results
        ]);
    }

    /**
     * 공고의 모든 HWP 변환 파일을 ZIP으로 다운로드
     */
    public function downloadAllHwpAsZip(Tender $tender): BinaryFileResponse
    {
        // 해당 공고의 완료된 첨부파일 조회
        $attachments = Attachment::where('tender_id', $tender->id)
                                ->where('download_status', 'completed')
                                ->get();

        if ($attachments->isEmpty()) {
            abort(404, '다운로드할 파일이 없습니다. 먼저 파일을 변환해주세요.');
        }

        // 임시 ZIP 파일 경로
        $zipFileName = 'hwp_files_' . $tender->tender_no . '_' . date('YmdHis') . '.zip';
        $zipPath = storage_path('app/temp/' . $zipFileName);
        
        // temp 디렉토리 생성
        if (!file_exists(dirname($zipPath))) {
            mkdir(dirname($zipPath), 0755, true);
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE) !== TRUE) {
            abort(500, 'ZIP 파일을 생성할 수 없습니다.');
        }

        $addedFiles = 0;
        foreach ($attachments as $attachment) {
            if (Storage::exists($attachment->local_path)) {
                $fileContent = Storage::get($attachment->local_path);
                $fileName = $attachment->file_name ?: $attachment->original_name;
                
                // ZIP에 파일 추가
                $zip->addFromString($fileName, $fileContent);
                $addedFiles++;
            }
        }

        $zip->close();

        if ($addedFiles === 0) {
            if (file_exists($zipPath)) {
                unlink($zipPath);
            }
            abort(404, '유효한 파일을 찾을 수 없습니다.');
        }

        Log::info('HWP 파일 ZIP 다운로드', [
            'tender_id' => $tender->id,
            'tender_no' => $tender->tender_no,
            'file_count' => $addedFiles,
            'zip_size' => filesize($zipPath)
        ]);

        // ZIP 파일 다운로드 후 삭제
        return response()->download($zipPath, $zipFileName)->deleteFileAfterSend();
    }

    /**
     * 개별 HWP 변환 파일 다운로드
     */
    public function downloadHwpFile(Attachment $attachment): BinaryFileResponse
    {
        // HWP로 변환된 파일만 다운로드 허용
        if ($attachment->file_type !== 'hwp' || $attachment->download_status !== 'completed') {
            abort(404, 'HWP 파일을 찾을 수 없습니다.');
        }

        if (!Storage::exists($attachment->local_path)) {
            abort(404, '파일이 존재하지 않습니다.');
        }

        Log::info('개별 HWP 파일 다운로드', [
            'attachment_id' => $attachment->id,
            'original_name' => $attachment->original_name,
            'hwp_name' => $attachment->file_name
        ]);

        return Storage::download(
            $attachment->local_path, 
            $attachment->file_name ?: $attachment->original_name
        );
    }

    /**
     * 첨부파일 통계 조회 (AJAX)
     */
    public function stats(): JsonResponse
    {
        $stats = $this->attachmentService->getDownloadStats();
        return response()->json($stats);
    }
}
// [END nara:attachment_controller]
