<?php

// [BEGIN nara:attachment_service]
namespace App\Services;

use App\Models\Attachment;
use App\Models\Tender;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Exception;

/**
 * 첨부파일 처리 서비스
 * 
 * @package App\Services
 */
class AttachmentService
{
    /**
     * 지원하는 한글 파일 확장자
     */
    private const HWP_EXTENSIONS = ['hwp', 'hwpx'];
    
    /**
     * 한글 파일 MIME 타입
     */
    private const HWP_MIME_TYPES = [
        'application/x-hwp',
        'application/vnd.hancom.hwp',
        'application/haansofthwp'
    ];

    /**
     * 파일 변환 서비스
     */
    private FileConverterService $converterService;

    public function __construct(FileConverterService $converterService = null)
    {
        $this->converterService = $converterService ?: new FileConverterService();
    }

    /**
     * API에서 첨부파일 정보 추출 (Mock 데이터 기반)
     * 
     * @param Tender $tender 입찰공고
     * @return array 첨부파일 정보 배열
     */
    public function extractAttachmentsFromTender(Tender $tender): array
    {
        $attachments = [];
        $metadata = $tender->metadata;
        
        // metadata가 문자열인 경우 배열로 변환
        if (is_string($metadata)) {
            $metadata = json_decode($metadata, true);
        }
        
        if (!is_array($metadata)) {
            // 메타데이터가 없으면 바로 Mock 데이터 생성
            return $this->generateMockAttachments($tender);
        }

        // 메타데이터에서 첨부파일 관련 정보 검색
        $fileFields = $this->findFileFields($metadata);
        
        foreach ($fileFields as $field => $value) {
            if (is_array($value)) {
                // 배열 형태의 첨부파일 정보
                foreach ($value as $fileInfo) {
                    $attachment = $this->parseAttachmentInfo($fileInfo, $tender);
                    if ($attachment) {
                        $attachments[] = $attachment;
                    }
                }
            } else {
                // 단일 파일 정보
                $attachment = $this->parseAttachmentInfo($value, $tender);
                if ($attachment) {
                    $attachments[] = $attachment;
                }
            }
        }

        // 공고번호 기반 Mock 첨부파일 생성 (실제 API 연동 전까지)
        if (empty($attachments)) {
            $attachments = $this->generateMockAttachments($tender);
        }

        return $attachments;
    }

    /**
     * 입찰공고의 첨부파일 정보를 데이터베이스에 저장
     * 
     * @param Tender $tender 입찰공고
     * @return int 저장된 첨부파일 수
     */
    public function collectAttachmentsForTender(Tender $tender): int
    {
        $attachmentData = $this->extractAttachmentsFromTender($tender);
        $savedCount = 0;

        foreach ($attachmentData as $data) {
            // 중복 방지: 같은 파일명과 URL이 이미 있는지 확인
            $existing = Attachment::where('tender_id', $tender->id)
                                  ->where('original_name', $data['original_name'])
                                  ->where('file_url', $data['file_url'])
                                  ->first();

            if (!$existing) {
                Attachment::create(array_merge($data, [
                    'tender_id' => $tender->id,
                    'download_status' => 'pending'
                ]));
                $savedCount++;
            }
        }

        Log::info('첨부파일 수집 완료', [
            'tender_id' => $tender->id,
            'tender_no' => $tender->tender_no,
            'saved_count' => $savedCount,
            'total_found' => count($attachmentData)
        ]);

        return $savedCount;
    }

    /**
     * 모든 첨부파일을 한글(HWP) 형식으로 변환하여 다운로드
     * 
     * @param Tender $tender 입찰공고
     * @return array 다운로드 결과
     */
    public function downloadAllFilesAsHwp(Tender $tender): array
    {
        $allAttachments = Attachment::where('tender_id', $tender->id)
                                   ->where('download_status', 'pending')
                                   ->get();

        $results = [
            'total' => $allAttachments->count(),
            'downloaded' => 0,
            'converted' => 0,
            'failed' => 0,
            'errors' => []
        ];

        foreach ($allAttachments as $attachment) {
            try {
                $this->downloadAndConvertToHwp($attachment);
                $results['downloaded']++;
                if (!$this->isHwpFile($attachment->original_name)) {
                    $results['converted']++;
                }
            } catch (Exception $e) {
                $results['failed']++;
                $results['errors'][] = [
                    'file' => $attachment->original_name,
                    'error' => $e->getMessage()
                ];
            }
        }

        return $results;
    }

    /**
     * 한글파일만 필터링하여 다운로드 (기존 기능 유지)
     * 
     * @param Tender $tender 입찰공고
     * @return array 다운로드 결과
     */
    public function downloadHwpFilesForTender(Tender $tender): array
    {
        $hwpAttachments = Attachment::where('tender_id', $tender->id)
                                   ->hwpFiles()
                                   ->where('download_status', 'pending')
                                   ->get();

        $results = [
            'total' => $hwpAttachments->count(),
            'downloaded' => 0,
            'failed' => 0,
            'errors' => []
        ];

        foreach ($hwpAttachments as $attachment) {
            try {
                $this->downloadAttachment($attachment);
                $results['downloaded']++;
            } catch (Exception $e) {
                $results['failed']++;
                $results['errors'][] = [
                    'file' => $attachment->original_name,
                    'error' => $e->getMessage()
                ];
            }
        }

        return $results;
    }

    /**
     * 첨부파일을 다운로드하고 HWP 형식으로 변환
     * 
     * @param Attachment $attachment 첨부파일 모델
     * @throws Exception 다운로드 또는 변환 실패 시
     */
    public function downloadAndConvertToHwp(Attachment $attachment): void
    {
        if (!$attachment->file_url || $attachment->file_url === '#') {
            throw new Exception('유효하지 않은 파일 URL');
        }

        // 다운로드 상태 업데이트
        $attachment->update(['download_status' => 'downloading']);

        try {
            // Mock 데이터인 경우 실제 HTTP 요청 대신 Mock 내용 생성
            if (str_contains($attachment->file_url, 'g2b.go.kr/ep/preparation/fileDownload.do')) {
                $mockContent = $this->generateMockFileContentForDownload($attachment);
                $response = new class($mockContent) {
                    private $content;
                    public function __construct($content) { $this->content = $content; }
                    public function successful() { return true; }
                    public function body() { return $this->content; }
                    public function status() { return 200; }
                };
            } else {
                $response = Http::timeout(120)->get($attachment->file_url);
                if (!$response->successful()) {
                    throw new Exception("HTTP {$response->status()}: 파일 다운로드 실패");
                }
            }

            $directory = 'attachments/' . date('Y/m/d') . '/' . $attachment->tender->tender_no;
            $originalFileName = $this->generateSafeFileName($attachment);
            $originalFilePath = $directory . '/' . $originalFileName;

            // 원본 파일 저장
            Storage::put($originalFilePath, $response->body());

            // HWP로 변환
            $convertedFilePath = $this->converterService->convertToHwp($originalFilePath, $attachment->original_name);

            // HWP 변환된 파일 이름 생성
            $baseName = pathinfo($attachment->original_name, PATHINFO_FILENAME);
            $hwpFileName = $baseName . '.hwp';

            // 다운로드 완료 정보 업데이트 (HWP 파일 경로로)
            $attachment->update([
                'local_path' => $convertedFilePath,
                'file_name' => $hwpFileName,
                'file_type' => 'hwp',
                'mime_type' => 'application/x-hwp',
                'file_size' => Storage::size($convertedFilePath),
                'download_status' => 'completed',
                'download_error' => null,
                'downloaded_at' => now()
            ]);

            Log::info('첨부파일 다운로드 및 HWP 변환 성공', [
                'attachment_id' => $attachment->id,
                'original_name' => $attachment->original_name,
                'original_path' => $originalFilePath,
                'converted_path' => $convertedFilePath,
                'final_name' => $hwpFileName
            ]);

        } catch (Exception $e) {
            $attachment->update([
                'download_status' => 'failed',
                'download_error' => $e->getMessage()
            ]);

            Log::error('첨부파일 다운로드 및 HWP 변환 실패', [
                'attachment_id' => $attachment->id,
                'original_name' => $attachment->original_name,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * 첨부파일 다운로드 실행 (원본 형식 유지)
     * 
     * @param Attachment $attachment 첨부파일 모델
     * @throws Exception 다운로드 실패 시
     */
    public function downloadAttachment(Attachment $attachment): void
    {
        if (!$attachment->file_url || $attachment->file_url === '#') {
            throw new Exception('유효하지 않은 파일 URL');
        }

        // 다운로드 상태 업데이트
        $attachment->update(['download_status' => 'downloading']);

        try {
            $response = Http::timeout(120)->get($attachment->file_url);

            if (!$response->successful()) {
                throw new Exception("HTTP {$response->status()}: 파일 다운로드 실패");
            }

            $directory = 'attachments/' . date('Y/m/d') . '/' . $attachment->tender->tender_no;
            $safeFileName = $this->generateSafeFileName($attachment);
            $filePath = $directory . '/' . $safeFileName;

            // 파일 저장
            Storage::put($filePath, $response->body());

            // 다운로드 완료 정보 업데이트
            $attachment->update([
                'local_path' => $filePath,
                'file_size' => strlen($response->body()),
                'download_status' => 'completed',
                'download_error' => null,
                'downloaded_at' => now()
            ]);

            Log::info('첨부파일 다운로드 성공', [
                'attachment_id' => $attachment->id,
                'original_name' => $attachment->original_name,
                'local_path' => $filePath,
                'file_size' => $attachment->file_size
            ]);

        } catch (Exception $e) {
            $attachment->update([
                'download_status' => 'failed',
                'download_error' => $e->getMessage()
            ]);

            Log::error('첨부파일 다운로드 실패', [
                'attachment_id' => $attachment->id,
                'original_name' => $attachment->original_name,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * 메타데이터에서 파일 관련 필드 검색
     * 
     * @param array $metadata 메타데이터
     * @return array 파일 관련 필드들
     */
    private function findFileFields(array $metadata): array
    {
        $fileFields = [];
        
        foreach ($metadata as $key => $value) {
            $lowerKey = strtolower($key);
            if (str_contains($lowerKey, 'file') ||
                str_contains($lowerKey, 'attach') ||
                str_contains($lowerKey, 'doc') ||
                str_contains($lowerKey, 'atch') ||
                str_contains($lowerKey, 'download')) {
                $fileFields[$key] = $value;
            }
        }
        
        return $fileFields;
    }

    /**
     * 첨부파일 정보 파싱
     * 
     * @param mixed $fileInfo 파일 정보
     * @param Tender $tender 입찰공고
     * @return array|null 파싱된 첨부파일 정보
     */
    private function parseAttachmentInfo($fileInfo, Tender $tender): ?array
    {
        if (is_string($fileInfo)) {
            // URL 형태의 문자열인 경우
            if (filter_var($fileInfo, FILTER_VALIDATE_URL)) {
                return [
                    'original_name' => basename(parse_url($fileInfo, PHP_URL_PATH)) ?: 'unknown_file',
                    'file_url' => $fileInfo,
                    'file_name' => $this->generateFileName($tender->tender_no, basename($fileInfo)),
                    'file_type' => pathinfo($fileInfo, PATHINFO_EXTENSION),
                ];
            }
        } elseif (is_array($fileInfo)) {
            // 배열 형태의 파일 정보
            if (isset($fileInfo['url']) || isset($fileInfo['file_url'])) {
                $url = $fileInfo['url'] ?? $fileInfo['file_url'];
                return [
                    'original_name' => $fileInfo['name'] ?? $fileInfo['file_name'] ?? basename($url),
                    'file_url' => $url,
                    'file_name' => $this->generateFileName($tender->tender_no, $fileInfo['name'] ?? basename($url)),
                    'file_type' => $fileInfo['type'] ?? pathinfo($url, PATHINFO_EXTENSION),
                    'file_size' => $fileInfo['size'] ?? null,
                    'mime_type' => $fileInfo['mime_type'] ?? null,
                ];
            }
        }

        return null;
    }

    /**
     * Mock 첨부파일 생성 (실제 API 연동 전까지)
     * 
     * @param Tender $tender 입찰공고
     * @return array Mock 첨부파일 정보
     */
    private function generateMockAttachments(Tender $tender): array
    {
        $mockFiles = [
            // 한글 파일들
            ['name' => '입찰공고서.hwp', 'type' => 'hwp', 'mime' => 'application/x-hwp'],
            ['name' => '과업지시서.hwp', 'type' => 'hwp', 'mime' => 'application/x-hwp'],
            ['name' => '제안서양식.hwp', 'type' => 'hwp', 'mime' => 'application/x-hwp'],
            
            // 다양한 형식의 파일들 (변환 테스트용)
            ['name' => '계약조건.pdf', 'type' => 'pdf', 'mime' => 'application/pdf'],
            ['name' => '입찰참가자격.pdf', 'type' => 'pdf', 'mime' => 'application/pdf'],
            ['name' => '사업계획서.docx', 'type' => 'docx', 'mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            ['name' => '예산서.xlsx', 'type' => 'xlsx', 'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            ['name' => '제안발표.pptx', 'type' => 'pptx', 'mime' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation'],
            ['name' => '요구사항.txt', 'type' => 'txt', 'mime' => 'text/plain'],
            ['name' => '회사소개.html', 'type' => 'html', 'mime' => 'text/html'],
        ];

        $attachments = [];
        foreach ($mockFiles as $file) {
            $attachments[] = [
                'original_name' => $file['name'],
                'file_url' => "https://www.g2b.go.kr/ep/preparation/fileDownload.do?fileId={$tender->tender_no}_{$file['name']}",
                'file_name' => $this->generateFileName($tender->tender_no, $file['name']),
                'file_type' => $file['type'],
                'mime_type' => $file['mime'],
            ];
        }

        return $attachments;
    }

    /**
     * 안전한 파일명 생성
     * 
     * @param string $tenderNo 공고번호
     * @param string $originalName 원본 파일명
     * @return string 안전한 파일명
     */
    private function generateFileName(string $tenderNo, string $originalName): string
    {
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $baseName = pathinfo($originalName, PATHINFO_FILENAME);
        $safeName = preg_replace('/[^a-zA-Z0-9가-힣_.-]/', '_', $baseName);
        
        return $tenderNo . '_' . $safeName . '.' . $extension;
    }

    /**
     * 다운로드용 안전한 파일명 생성
     * 
     * @param Attachment $attachment 첨부파일 모델
     * @return string 안전한 파일명
     */
    private function generateSafeFileName(Attachment $attachment): string
    {
        return $attachment->file_name ?: $this->generateFileName(
            $attachment->tender->tender_no,
            $attachment->original_name
        );
    }

    /**
     * 한글파일 여부 확인
     * 
     * @param string $fileName 파일명
     * @param string|null $mimeType MIME 타입
     * @return bool 한글파일 여부
     */
    public function isHwpFile(string $fileName, ?string $mimeType = null): bool
    {
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        return in_array($extension, self::HWP_EXTENSIONS) ||
               ($mimeType && in_array($mimeType, self::HWP_MIME_TYPES));
    }

    /**
     * Mock 파일 다운로드용 내용 생성
     * 
     * @param Attachment $attachment 첨부파일 모델
     * @return string Mock 파일 내용
     */
    private function generateMockFileContentForDownload(Attachment $attachment): string
    {
        $extension = strtolower(pathinfo($attachment->original_name, PATHINFO_EXTENSION));
        
        $baseContent = "Mock 파일 내용: " . $attachment->original_name . "\n";
        $baseContent .= "파일 형식: " . $extension . "\n";
        $baseContent .= "공고번호: " . $attachment->tender->tender_no . "\n";
        $baseContent .= "생성 시간: " . date('Y-m-d H:i:s') . "\n\n";
        
        return match($extension) {
            'pdf' => $baseContent . "이것은 PDF 문서의 Mock 내용입니다.\n페이지 1: 첫 번째 페이지 내용\n페이지 2: 두 번째 페이지 내용\n페이지 3: 세 번째 페이지 내용\n\nPDF 텍스트 추출 완료.",
            'docx' => $baseContent . "Microsoft Word 문서의 Mock 내용입니다.\n\n제목: " . pathinfo($attachment->original_name, PATHINFO_FILENAME) . "\n\n본문:\n이 문서는 한글 파일로 변환될 예정입니다.\n문서의 서식과 내용이 보존됩니다.\n\n작성자: 시스템\n작성일: " . date('Y-m-d'),
            'xlsx' => $baseContent . "Excel 스프레드시트의 Mock 내용입니다.\n\n시트1 데이터:\nA1: 항목\tB1: 값\tC1: 비고\nA2: 예산\tB2: 1,000만원\tC2: 승인됨\nA3: 기간\tB3: 6개월\tC3: 연장가능\nA4: 담당자\tB4: 홍길동\tC4: 연락처: 010-1234-5678",
            'pptx' => $baseContent . "PowerPoint 프레젠테이션의 Mock 내용입니다.\n\n슬라이드 1: 제목 - " . pathinfo($attachment->original_name, PATHINFO_FILENAME) . "\n슬라이드 2: 목차\n- 개요\n- 주요 내용\n- 결론\n슬라이드 3: 개요\n프레젠테이션의 개요 내용\n슬라이드 4: 주요 내용\n상세한 설명과 데이터\n슬라이드 5: 결론\n요약 및 향후 계획",
            'txt' => $baseContent . "텍스트 파일의 내용입니다.\n\n요구사항 목록:\n1. 기능 A 구현 - 우선순위 높음\n2. 기능 B 테스트 - 우선순위 중간\n3. 기능 C 배포 - 우선순위 낮음\n\n추가 사항:\n- 모든 기능은 한글을 지원해야 함\n- 사용자 인터페이스는 직관적이어야 함\n- 성능 최적화 필요",
            'html' => $baseContent . "<!DOCTYPE html>\n<html lang=\"ko\">\n<head>\n    <meta charset=\"UTF-8\">\n    <title>" . pathinfo($attachment->original_name, PATHINFO_FILENAME) . "</title>\n</head>\n<body>\n    <h1>회사 소개</h1>\n    <p>저희 회사는 혁신적인 기술로 고객의 성공을 돕습니다.</p>\n    <h2>주요 서비스</h2>\n    <ul>\n        <li>웹 개발</li>\n        <li>모바일 앱 개발</li>\n        <li>AI/ML 솔루션</li>\n    </ul>\n</body>\n</html>",
            'hwp' => $baseContent . "한글 문서의 Mock 내용입니다.\n\n이 문서는 이미 HWP 형식이므로 변환이 필요하지 않습니다.\n\n제목: " . pathinfo($attachment->original_name, PATHINFO_FILENAME) . "\n\n본문 내용:\n한글과컴퓨터 오피스 프로그램으로 작성된 문서입니다.\n문서의 레이아웃과 서식이 그대로 유지됩니다.",
            default => $baseContent . "일반 파일의 Mock 내용입니다.\n\n파일 정보:\n- 크기: 알 수 없음\n- 형식: " . $extension . "\n- 상태: 변환 대상\n\n이 파일은 한글 형식으로 변환될 예정입니다."
        };
    }

    /**
     * 다운로드 통계 조회
     * 
     * @return array 다운로드 통계
     */
    public function getDownloadStats(): array
    {
        return Attachment::getDownloadStats();
    }
}
// [END nara:attachment_service]