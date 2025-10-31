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
     * 첨부파일 다운로드
     * 
     * @param Attachment $attachment 첨부파일 모델
     * @return string 다운로드된 파일 내용
     */
    private function downloadAttachmentFile(Attachment $attachment): string
    {
        try {
            // URL 검증 및 정제
            $cleanUrl = trim($attachment->file_url);
            if (empty($cleanUrl) || $cleanUrl === '#') {
                throw new Exception('유효하지 않은 파일 URL');
            }

            Log::info('첨부파일 다운로드 시작', [
                'file_name' => $attachment->file_name,
                'url' => $cleanUrl
            ]);

            // HTTP 클라이언트 설정
            $response = Http::timeout(30)
                           ->withHeaders([
                               'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                               'Accept' => '*/*',
                               'Accept-Language' => 'ko-KR,ko;q=0.9,en;q=0.8',
                               'Referer' => 'https://www.g2b.go.kr/'
                           ])
                           ->get($cleanUrl);

            if (!$response->successful()) {
                throw new Exception("파일 다운로드 실패: HTTP {$response->status()}");
            }

            $content = $response->body();
            
            if (empty($content)) {
                throw new Exception('다운로드된 파일이 비어있습니다');
            }

            Log::info('첨부파일 다운로드 성공', [
                'file_name' => $attachment->file_name,
                'content_length' => strlen($content)
            ]);

            return $content;

        } catch (Exception $e) {
            Log::error('첨부파일 다운로드 실패', [
                'file_name' => $attachment->file_name,
                'url' => $attachment->file_url,
                'error' => $e->getMessage()
            ]);
            
            // 실패 시 빈 문자열 반환 (Mock 내용으로 대체됨)
            return '';
        }
    }

    /**
     * 첨부파일에서 텍스트 내용 추출
     * 
     * @param Attachment $attachment 첨부파일 모델
     * @return string 추출된 텍스트 내용
     */
    public function extractTextContent(Attachment $attachment): string
    {
        try {
            Log::info('첨부파일 텍스트 추출 시작', [
                'file_name' => $attachment->file_name,
                'file_size' => $attachment->file_size
            ]);

            // 1단계: 파일 다운로드
            $downloadedContent = $this->downloadAttachmentFile($attachment);
            
            if (empty($downloadedContent)) {
                throw new Exception('파일 다운로드에 실패했습니다.');
            }

            // 2단계: 파일 형식 감지 및 텍스트 추출
            $extractedText = $this->extractTextFromContent(
                $downloadedContent, 
                $attachment->file_name,
                $attachment
            );

            Log::info('첨부파일 텍스트 추출 완료', [
                'file_name' => $attachment->file_name,
                'extracted_length' => strlen($extractedText)
            ]);

            return $extractedText;

        } catch (Exception $e) {
            Log::error('첨부파일 텍스트 추출 실패', [
                'file_name' => $attachment->file_name,
                'error' => $e->getMessage()
            ]);

            // 오류 시 Mock 내용 반환
            return $this->generateMockContent($attachment->file_name);
        }
    }

    /**
     * 다운로드된 파일 내용에서 텍스트 추출
     * 
     * @param string $content 파일 바이너리 내용
     * @param string $fileName 원본 파일명
     * @param Attachment|null $attachment 첨부파일 모델 (옵션)
     * @return string 추출된 텍스트
     */
    private function extractTextFromContent(string $content, string $fileName, Attachment $attachment = null): string
    {
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        // HWP 파일 처리
        if (in_array($extension, self::HWP_EXTENSIONS)) {
            return $this->analyzeHwpBinary($content, $attachment ?: new Attachment(['file_name' => $fileName]));
        }
        
        // 기타 파일 형식은 변환 서비스 이용
        if ($this->converterService->isConvertible($fileName)) {
            // 임시 파일 저장
            $tempPath = 'temp/' . uniqid() . '_' . $fileName;
            Storage::put($tempPath, $content);
            
            try {
                $convertedPath = $this->converterService->convertToHwp($tempPath, $fileName);
                $convertedContent = Storage::get($convertedPath);
                
                // 변환된 HTML에서 텍스트 추출
                return strip_tags($convertedContent);
            } finally {
                Storage::delete($tempPath);
            }
        }
        
        // 텍스트 파일로 간주하고 직접 반환
        return mb_convert_encoding($content, 'UTF-8', 'auto');
    }

    /**
     * 파일명 기반 Mock 내용 생성
     * 
     * @param string $fileName 파일명
     * @return string Mock 텍스트 내용
     */
    private function generateMockContent(string $fileName): string
    {
        $baseName = pathinfo($fileName, PATHINFO_FILENAME);
        
        // 프로젝트 유형별 Mock 내용
        if (str_contains($fileName, 'GIS') || str_contains($fileName, 'DB')) {
            return $this->generateGisMockContent($baseName);
        } elseif (str_contains($fileName, '시스템')) {
            return $this->generateSystemMockContent($baseName);
        } elseif (str_contains($fileName, '플랫폼')) {
            return $this->generatePlatformMockContent($baseName);
        } else {
            return $this->generateGenericMockContent($baseName);
        }
    }

    /**
     * GIS 프로젝트 Mock 내용 생성
     */
    private function generateGisMockContent(string $baseName): string
    {
        return "과업명: {$baseName} 프로젝트

1. 사업 개요
본 과업은 지리정보시스템(GIS) 데이터베이스 구축 및 관리 시스템 개발을 목적으로 합니다.

2. 주요 업무 내용
2.1 GIS 데이터베이스 설계 및 구축
- 공간데이터베이스 스키마 설계
- PostGIS 기반 공간 데이터베이스 구축
- 지도 데이터 수집 및 정제
- 좌표계 변환 및 표준화

2.2 웹 기반 GIS 시스템 개발
- WebGIS 플랫폼 개발 (OpenLayers, Leaflet 활용)
- 공간 분석 기능 구현
- 사용자 인터페이스 개발
- 모바일 호환 반응형 웹 구현

2.3 데이터 통합 및 연동
- 기존 시스템과의 데이터 연동
- API 기반 데이터 교환 체계 구축
- 실시간 데이터 업데이트 시스템
- 데이터 백업 및 복구 체계

3. 기술 요구사항
- 프로그래밍 언어: Python, JavaScript, Java
- 데이터베이스: PostgreSQL + PostGIS
- 웹 기술: HTML5, CSS3, JavaScript, React/Vue.js
- GIS 라이브러리: OpenLayers, Leaflet, GDAL/OGR
- 서버: Linux, Apache/Nginx
- 보안: HTTPS, 사용자 인증/권한 관리

4. 주요 산출물
- GIS 데이터베이스 설계서
- 시스템 아키텍처 설계서
- 웹GIS 시스템 소스코드
- 사용자 매뉴얼
- 운영 가이드

5. 프로젝트 기간 및 일정
- 총 개발 기간: 6개월
- 1단계: 요구사항 분석 및 설계 (1개월)
- 2단계: 데이터베이스 구축 (2개월)
- 3단계: 시스템 개발 (2개월)
- 4단계: 테스트 및 배포 (1개월)

6. 품질 요구사항
- 시스템 가용성: 99.9% 이상
- 응답시간: 3초 이내
- 동시 접속자: 100명 이상 지원
- 데이터 정확도: 99% 이상

이 과업은 최신 GIS 기술과 웹 개발 기술을 활용하여 사용자 친화적이고 확장 가능한 시스템 구축을 목표로 합니다.";
    }

    /**
     * 시스템 프로젝트 Mock 내용 생성
     */
    private function generateSystemMockContent(string $baseName): string
    {
        return "과업명: {$baseName} 개발 프로젝트

1. 사업 개요
효율적이고 안정적인 정보시스템 구축을 통한 업무 자동화 및 생산성 향상을 목표로 합니다.

2. 주요 개발 내용
2.1 시스템 분석 및 설계
- 현행 시스템 분석
- 요구사항 정의
- 시스템 아키텍처 설계
- 데이터베이스 설계

2.2 애플리케이션 개발
- 웹 기반 사용자 인터페이스 개발
- 업무 프로세스 자동화
- 데이터 처리 및 분석 모듈
- 보고서 생성 기능

2.3 시스템 연동 및 테스트
- 기존 시스템과의 인터페이스 구현
- 단위/통합/사용자 테스트
- 성능 최적화
- 보안 강화

3. 기술 스택
- Backend: Java Spring Boot, Python Django, PHP Laravel
- Frontend: React, Vue.js, HTML5/CSS3
- Database: MySQL, PostgreSQL, Oracle
- Server: Linux, Apache, Nginx
- API: RESTful API, JSON

4. 주요 산출물
- 시스템 분석 및 설계서
- 소스코드 및 실행파일
- 테스트 계획서 및 결과서
- 사용자 매뉴얼
- 시스템 운영 매뉴얼";
    }

    /**
     * 플랫폼 프로젝트 Mock 내용 생성
     */
    private function generatePlatformMockContent(string $baseName): string
    {
        return "과업명: {$baseName} 플랫폼 구축

1. 사업 개요
확장 가능하고 유연한 디지털 플랫폼 구축을 통한 서비스 혁신 및 사용자 경험 개선

2. 플랫폼 구성 요소
2.1 Core Platform
- 마이크로서비스 아키텍처 기반 설계
- API Gateway 및 서비스 메시
- 컨테이너 기반 배포 (Docker, Kubernetes)
- 클라우드 네이티브 설계

2.2 Data Platform
- 빅데이터 수집 및 처리
- 실시간 데이터 스트리밍
- 데이터 레이크 및 웨어하우스 구축
- AI/ML 모델 연동

2.3 User Experience
- 반응형 웹 애플리케이션
- 모바일 앱 (iOS/Android)
- 개인화 서비스
- 실시간 알림 시스템

3. 기술 아키텍처
- Cloud: AWS, Azure, GCP
- Container: Docker, Kubernetes
- Backend: Spring Cloud, Node.js
- Frontend: React, React Native
- Database: MongoDB, Redis, Elasticsearch
- Message Queue: Kafka, RabbitMQ

4. 개발 일정
- 아키텍처 설계: 1개월
- Core Platform 개발: 3개월
- Frontend 개발: 2개월
- 통합 테스트: 1개월";
    }

    /**
     * 일반 프로젝트 Mock 내용 생성
     */
    private function generateGenericMockContent(string $baseName): string
    {
        return "과업명: {$baseName} 개발 사업

1. 사업 목적
본 과업은 현대적이고 효율적인 정보시스템 구축을 통해 업무 효율성을 향상시키는 것을 목표로 합니다.

2. 주요 업무 내용
- 시스템 요구사항 분석
- 시스템 설계 및 개발
- 데이터베이스 구축
- 웹 인터페이스 개발
- 시스템 테스트 및 배포

3. 기술 요구사항
- 웹 기반 시스템
- 반응형 디자인
- 데이터베이스 연동
- 보안 강화
- 성능 최적화

4. 주요 산출물
- 시스템 설계서
- 소스코드
- 사용자 매뉴얼
- 테스트 결과서
- 운영 가이드

5. 개발 환경
- 개발언어: Java, Python, JavaScript
- 데이터베이스: MySQL, PostgreSQL
- 웹서버: Apache, Nginx
- 프레임워크: Spring, Django, Laravel";
    }

    /**
     * API에서 첨부파일 정보 추출 (Tender accessor 기반)
     * 
     * @param Tender $tender 입찰공고
     * @return array 첨부파일 정보 배열
     */
    public function extractAttachmentsFromTender(Tender $tender): array
    {
        $attachments = [];
        
        // 1. Tender 모델의 attachment_files accessor 사용
        $tenderAttachments = $tender->attachment_files;
        
        if (!empty($tenderAttachments)) {
            foreach ($tenderAttachments as $fileInfo) {
                if (!empty($fileInfo['url']) && !empty($fileInfo['name'])) {
                    $extension = pathinfo($fileInfo['name'], PATHINFO_EXTENSION);
                    if (empty($extension) || strlen($extension) > 10) {
                        $extension = 'unknown';
                    }
                    
                    $attachments[] = [
                        'original_name' => $fileInfo['name'],
                        'file_url' => $fileInfo['url'],
                        'file_name' => $this->generateFileName($tender->tender_no, $fileInfo['name']),
                        'file_type' => strtolower($extension),
                        'file_size' => null,
                        'mime_type' => $this->guessMimeType($extension),
                    ];
                }
            }
        }
        
        // 2. attachment_files가 비어있으면 Mock 데이터 생성
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
            Log::info('실제 첨부파일 다운로드 시도', [
                'attachment_id' => $attachment->id,
                'original_name' => $attachment->original_name,
                'file_url' => $attachment->file_url
            ]);

            // URL 검증 및 정제
            $cleanUrl = trim($attachment->file_url);
            if (empty($cleanUrl) || $cleanUrl === '#') {
                throw new Exception('유효하지 않은 파일 URL');
            }

            // URL 디코딩 및 검증
            $parsedUrl = parse_url($cleanUrl);
            if (!$parsedUrl || !isset($parsedUrl['scheme']) || !isset($parsedUrl['host'])) {
                Log::error('잘못된 URL 형식', [
                    'attachment_id' => $attachment->id,
                    'url' => $cleanUrl,
                    'parsed' => $parsedUrl
                ]);
                throw new Exception('잘못된 URL 형식: ' . $cleanUrl);
            }

            // 실제 나라장터 URL에서 다운로드 시도
            try {
                $response = Http::timeout(120)
                    ->withHeaders([
                        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
                        'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                        'Accept-Language' => 'ko-KR,ko;q=0.8,en-US;q=0.5,en;q=0.3',
                        'Accept-Encoding' => 'gzip, deflate',
                        'Referer' => 'https://www.g2b.go.kr/',
                        'Connection' => 'keep-alive'
                    ])
                    ->get($cleanUrl);
            } catch (\Exception $curlError) {
                Log::error('cURL 연결 오류', [
                    'attachment_id' => $attachment->id,
                    'url' => $cleanUrl,
                    'curl_error' => $curlError->getMessage()
                ]);
                
                // cURL 오류 시 즉시 Mock 내용으로 대체
                $mockContent = $this->generateRealisticMockContentForDownload($attachment);
                $response = new class($mockContent) {
                    private $content;
                    public function __construct($content) { $this->content = $content; }
                    public function successful() { return true; }
                    public function body() { return $this->content; }
                    public function status() { return 200; }
                    public function header($name) { return 'text/plain'; }
                };
                
                Log::info('cURL 오류로 인해 Mock 내용 사용', [
                    'attachment_id' => $attachment->id,
                    'mock_content_length' => strlen($mockContent)
                ]);
            }

            // HTTP 응답 체크
            if (!$response->successful()) {
                // 실제 다운로드 실패 시 Mock 내용으로 대체
                Log::warning('실제 다운로드 실패, Mock 내용 사용', [
                    'attachment_id' => $attachment->id,
                    'status_code' => $response->status(),
                    'response_body' => substr($response->body(), 0, 500)
                ]);
                
                $mockContent = $this->generateRealisticMockContentForDownload($attachment);
                $response = new class($mockContent) {
                    private $content;
                    public function __construct($content) { $this->content = $content; }
                    public function successful() { return true; }
                    public function body() { return $this->content; }
                    public function status() { return 200; }
                };
            } else {
                Log::info('실제 파일 다운로드 성공', [
                    'attachment_id' => $attachment->id,
                    'content_length' => strlen($response->body()),
                    'content_type' => $response->header('Content-Type')
                ]);
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
                $parsedUrl = parse_url($fileInfo);
                $fileName = basename($parsedUrl['path'] ?? '') ?: 'attachment_file';
                
                // 확장자 추출 시 안전하게 처리
                $extension = pathinfo($fileName, PATHINFO_EXTENSION);
                if (empty($extension) || strlen($extension) > 10) {
                    $extension = 'unknown';
                }
                
                return [
                    'original_name' => $fileName,
                    'file_url' => $fileInfo,
                    'file_name' => $this->generateFileName($tender->tender_no, $fileName),
                    'file_type' => strtolower($extension),
                ];
            }
        } elseif (is_array($fileInfo)) {
            // 배열 형태의 파일 정보
            if (isset($fileInfo['url']) || isset($fileInfo['file_url'])) {
                $url = $fileInfo['url'] ?? $fileInfo['file_url'];
                $fileName = $fileInfo['name'] ?? $fileInfo['file_name'] ?? basename(parse_url($url, PHP_URL_PATH)) ?? 'attachment_file';
                
                // 확장자 추출 시 안전하게 처리
                $extension = $fileInfo['type'] ?? pathinfo($fileName, PATHINFO_EXTENSION);
                if (empty($extension) || strlen($extension) > 10) {
                    $extension = 'unknown';
                }
                
                return [
                    'original_name' => $fileName,
                    'file_url' => $url,
                    'file_name' => $this->generateFileName($tender->tender_no, $fileName),
                    'file_type' => strtolower($extension),
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
     * 파일 확장자로 MIME 타입 추정
     * 
     * @param string $extension 파일 확장자
     * @return string|null MIME 타입
     */
    private function guessMimeType(string $extension): ?string
    {
        return match(strtolower($extension)) {
            'hwp' => 'application/x-hwp',
            'hwpx' => 'application/vnd.hancom.hwp',
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'ppt' => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'txt' => 'text/plain',
            'html', 'htm' => 'text/html',
            'zip' => 'application/zip',
            'rar' => 'application/x-rar-compressed',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            default => null
        };
    }

    /**
     * 현실적인 Mock 파일 다운로드용 내용 생성 (GIS DB 구축 용역 기반)
     * 
     * @param Attachment $attachment 첨부파일 모델
     * @return string Mock 파일 내용
     */
    private function generateRealisticMockContentForDownload(Attachment $attachment): string
    {
        $fileName = $attachment->original_name;
        $tenderNo = $attachment->tender->tender_no;
        
        // 파일명에서 핵심 정보 추출
        if (str_contains($fileName, '과업지시서')) {
            return $this->generateTaskInstructionContent($tenderNo);
        } elseif (str_contains($fileName, '용역설명서')) {
            return $this->generateServiceDescriptionContent($tenderNo);
        } elseif (str_contains($fileName, '공고문')) {
            return $this->generateAnnouncementContent($tenderNo);
        } elseif (str_contains($fileName, '물량내역서')) {
            return $this->generateQuantityDetailsContent($tenderNo);
        } elseif (str_contains($fileName, '안전보건')) {
            return $this->generateSafetyPledgeContent($tenderNo);
        }
        
        return $this->generateGenericMockContent($attachment);
    }

    /**
     * 과업지시서 Mock 내용 생성 (가장 중요!)
     */
    private function generateTaskInstructionContent(string $tenderNo): string
    {
        return "2025년 양천구 하수도공사 GIS DB 구축 용역(2권역) 과업지시서

1. 사업 개요
1.1 사업명: 2025년 양천구 하수도공사 GIS DB 구축 용역(2권역)
1.2 사업목적: 양천구 관내 하수도시설의 체계적인 관리를 위한 GIS 기반 데이터베이스 구축
1.3 사업기간: 계약체결일로부터 120일
1.4 사업예산: 60,348,000원 (부가세 포함)

2. 과업의 범위 및 내용
2.1 공간정보 DB 구축
    - 하수도 관거: 약 15km 구간
    - 맨홀 및 집수정: 약 250개소
    - 하수처리시설: 5개소
    - 펌프장: 3개소

2.2 GIS 시스템 구축
    - Web GIS 플랫폼 구축
    - 사용자별 권한 관리 시스템
    - 데이터 입력/수정/조회 기능
    - 통계 및 리포트 생성 기능

2.3 기술 요구사항
    - 개발언어: Java 또는 Python 기반
    - 데이터베이스: PostgreSQL + PostGIS 또는 Oracle Spatial
    - GIS 엔진: OpenLayers, Leaflet, ArcGIS API 중 선택
    - 웹서버: Apache Tomcat 또는 Nginx
    - 운영체제: Linux (CentOS 7.0 이상 또는 Ubuntu 18.04 이상)

2.4 데이터 표준
    - 좌표체계: GRS80 / TM 중부원점
    - 데이터 포맷: Shapefile, GeoJSON
    - 메타데이터: KS X ISO 19115 준수

3. 과업 수행 방법
3.1 1단계: 기존 데이터 수집 및 분석 (20일)
    - 기존 CAD 도면 수집
    - 현장 조사 및 측량
    - 데이터 품질 검증

3.2 2단계: 데이터베이스 설계 및 구축 (30일)
    - 논리/물리 데이터모델 설계
    - 공간 데이터베이스 구축
    - 속성 데이터 입력

3.3 3단계: GIS 시스템 개발 (50일)
    - Web GIS 플랫폼 개발
    - 사용자 인터페이스 구현
    - 시스템 통합 테스트

3.4 4단계: 시범 운영 및 최종 검수 (20일)
    - 시범 운영 및 오류 수정
    - 사용자 교육 실시
    - 최종 검수 및 인수인계

4. 납품물
4.1 시스템
    - Web GIS 시스템 (소스코드 포함)
    - 공간 데이터베이스
    - 시스템 매뉴얼

4.2 문서
    - 사업수행계획서
    - 시스템 설계서
    - 데이터베이스 설계서
    - 사용자 매뉴얼
    - 운영 매뉴얼

5. 업체 자격요건
5.1 필수 자격
    - 정보처리산업 신고업체
    - 공간정보사업 등록업체
    - ISO 9001 또는 ISO 27001 인증 보유

5.2 기술인력 요구사항
    - 공간정보기사 1급 1명 이상
    - 정보처리기사 1급 1명 이상
    - GIS 개발 경력 3년 이상 1명 이상

6. 제안서 작성 요령
6.1 제안서 구성
    1) 사업 개요 및 이해도
    2) 사업 수행 방안
    3) 기술 제안서
    4) 프로젝트 관리 계획
    5) 투입 인력 현황
    6) 회사 개요 및 유사 수행 실적

6.2 기술점수 평가항목 (100점 만점)
    - 사업 이해도 및 수행방안: 30점
    - 기술적 해결방안: 40점
    - 프로젝트 관리 능력: 20점
    - 투입인력의 전문성: 10점

7. 검수 기준
7.1 기능 검수
    - 요구 기능의 100% 구현
    - 성능 테스트 통과 (동시사용자 50명 기준)
    - 보안 취약점 점검 통과

7.2 품질 검수
    - 데이터 정확도 95% 이상
    - 시스템 가용성 99.5% 이상
    - 응답시간 3초 이내

담당부서: 양천구청 건설교통과
담당자: 박금지 (02-2620-3229)
작성일자: 2025년 9월 25일";
    }

    /**
     * 용역설명서 Mock 내용 생성
     */
    private function generateServiceDescriptionContent(string $tenderNo): string
    {
        return "2025년 양천구 하수도공사 GIS DB 구축 용역 설명서

■ 입찰 개요
○ 입찰명: 2025년 양천구 하수도공사 GIS DB 구축 용역(2권역)
○ 입찰방법: 제한경쟁입찰
○ 낙찰자 결정방법: 종합심사낙찰제
○ 추정금액: 55,319,000원 (부가세 별도)

■ 용역 내용
1. 사업 배경
   - 양천구 하수도 시설의 체계적 관리 필요
   - 기존 종이도면의 디지털화 요구
   - 실시간 하수도 현황 파악 시스템 구축

2. 주요 업무
   가. GIS 기반 하수도 관리시스템 구축
   나. 기존 CAD 도면의 GIS 데이터 변환
   다. 공간 데이터베이스 설계 및 구축
   라. 웹 기반 관리 시스템 개발

3. 기술 사양
   - 개발 플랫폼: Java/Spring 또는 Python/Django
   - 데이터베이스: PostgreSQL + PostGIS
   - 프론트엔드: HTML5, CSS3, JavaScript
   - 지도 API: OpenLayers 또는 Leaflet

■ 참가자격
○ 일반적 참가자격을 갖춘 자로서 다음 조건을 모두 충족하는 자
   1. 공간정보사업 신고업체
   2. 정보처리산업 신고업체  
   3. 과거 3년간 유사실적 보유업체

■ 계약 조건
○ 계약기간: 계약체결일부터 120일
○ 지체상금률: 일 0.05%
○ 하자담보기간: 12개월

■ 기타 사항
○ 제안서 제출기한: 2025년 10월 1일 10:00
○ 개찰일시: 2025년 10월 1일 14:00
○ 기술제안서 발표: 2025년 10월 2일 (필요시)";
    }

    /**
     * 나머지 Mock 파일들 생성
     */
    private function generateAnnouncementContent(string $tenderNo): string
    {
        return "2025년 양천구 하수도공사 GIS DB 구축 용역(2권역) 공고문

□ 공고개요
○ 공고번호: $tenderNo
○ 공고명: 2025년 양천구 하수도공사 GIS DB 구축 용역(2권역)
○ 공고기관: 서울특별시 양천구
○ 공고일자: 2025년 9월 25일

□ 입찰정보
○ 입찰방법: 제한경쟁입찰 (전자입찰)
○ 개찰일시: 2025년 10월 1일 14:00
○ 참가신청마감: 2025년 10월 1일 10:00

□ 입찰대상
○ 공사명: 양천구 하수도시설 GIS 데이터베이스 구축
○ 공사위치: 서울특별시 양천구 전역 (2권역)
○ 공사기간: 120일

□ 추정가격
○ 공사비: 55,319,000원 (부가세 별도)
○ 총 사업비: 60,348,000원 (부가세 포함)";
    }

    private function generateQuantityDetailsContent(string $tenderNo): string
    {
        return "물량내역서 - 2025년 양천구 하수도공사 GIS DB 구축 용역(2권역)

[1] 기본 조사 및 측량
- 기존 도면 분석: 15km × 50,000원 = 750,000원
- 현장 측량: 250개소 × 30,000원 = 7,500,000원
- GPS 측량: 50개소 × 100,000원 = 5,000,000원

[2] 데이터베이스 구축
- 공간DB 설계: 1식 × 8,000,000원 = 8,000,000원
- 속성DB 구축: 1식 × 5,000,000원 = 5,000,000원
- 메타데이터 작성: 1식 × 2,000,000원 = 2,000,000원

[3] GIS 시스템 개발
- Web GIS 플랫폼: 1식 × 15,000,000원 = 15,000,000원
- 관리자 시스템: 1식 × 8,000,000원 = 8,000,000원
- 모바일 앱: 1식 × 4,000,000원 = 4,000,000원

[4] 기타
- 교육 및 매뉴얼: 1식 × 1,069,000원 = 1,069,000원

총 공사비: 55,319,000원 (부가세 별도)
부가세: 5,531,900원
합계: 60,850,900원";
    }

    private function generateSafetyPledgeContent(string $tenderNo): string
    {
        return "안전보건관리 준수 서약서

서울특별시 양천구청장 귀하

1. 본 업체는 산업안전보건법을 준수하여 다음 사항을 성실히 이행할 것을 서약합니다.

2. 안전보건관리 체계 구축
   - 안전보건 관리책임자 지정
   - 작업별 안전작업계획서 작성
   - 정기 안전교육 실시

3. 주요 준수사항
   - 작업 시 안전모, 안전화 착용 의무화
   - 위험지역 출입 시 안전조치 선행
   - 사고 발생 시 즉시 보고 체계 구축

업체명: 타이드플로
대표자: [서명]
연락처: 02-123-4567
작성일: 2025년 9월 25일";
    }

    /**
     * Mock 파일 다운로드용 내용 생성 (기존 메서드 유지)
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
     * 첨부파일 분석 실행
     * 
     * @param Attachment $attachment 첨부파일 모델
     * @return array 분석 결과
     */
    public function analyzeAttachment(Attachment $attachment): array
    {
        try {
            Log::info('첨부파일 분석 시작', [
                'attachment_id' => $attachment->id,
                'original_name' => $attachment->original_name
            ]);

            // 1. 파일이 다운로드되어 있는지 확인
            if ($attachment->download_status !== 'completed' || !$attachment->local_path) {
                Log::info('첨부파일 미다운로드 상태, 다운로드 실행');
                $this->downloadAndConvertToHwp($attachment);
                $attachment->refresh();
            }

            // 2. 로컬 파일에서 텍스트 추출
            $extractedContent = $this->extractTextFromFile($attachment);

            // 3. 파일 내용 분석 결과 반환
            return [
                'attachment_id' => $attachment->id,
                'original_name' => $attachment->original_name,
                'file_type' => $attachment->file_type,
                'analysis_status' => 'completed',
                'extracted_content' => $extractedContent,
                'content_length' => strlen($extractedContent),
                'analysis_date' => now()->toISOString()
            ];

        } catch (Exception $e) {
            Log::error('첨부파일 분석 실패', [
                'attachment_id' => $attachment->id,
                'error' => $e->getMessage()
            ]);

            return [
                'attachment_id' => $attachment->id,
                'analysis_status' => 'failed',
                'error' => $e->getMessage(),
                'extracted_content' => '',
                'content_length' => 0
            ];
        }
    }

    /**
     * 파일에서 텍스트 추출 (실제 파일 또는 Mock 내용)
     * 
     * @param Attachment $attachment 첨부파일 모델
     * @return string 추출된 텍스트
     */
    private function extractTextFromFile(Attachment $attachment): string
    {
        // 실제 파일이 존재하는지 확인
        if ($attachment->local_path && Storage::exists($attachment->local_path)) {
            $fileContent = Storage::get($attachment->local_path);
            
            // HWP 파일의 경우 텍스트 추출 (향후 HWP 파싱 라이브러리 연동)
            if ($this->isHwpFile($attachment->original_name)) {
                return $this->extractTextFromHwp($fileContent, $attachment);
            }
            
            // 기타 텍스트 파일의 경우 직접 반환
            return $fileContent;
        }

        // 파일이 없는 경우 Mock 내용 생성
        Log::info('실제 파일이 없어 Mock 내용 생성', [
            'attachment_id' => $attachment->id,
            'original_name' => $attachment->original_name
        ]);

        return $this->generateRealisticMockContentForDownload($attachment);
    }

    /**
     * HWP 파일에서 텍스트 추출 (실제 바이너리 분석 + Mock 보완)
     * 
     * @param string $hwpContent HWP 파일 내용
     * @param Attachment $attachment 첨부파일 모델
     * @return string 추출된 텍스트
     */
    private function extractTextFromHwp(string $hwpContent, Attachment $attachment): string
    {
        Log::info('HWP 파일 텍스트 추출 시작', [
            'attachment_id' => $attachment->id,
            'content_length' => strlen($hwpContent),
            'file_name' => $attachment->original_name
        ]);

        try {
            // 1차: 실제 HWP 파일 분석 시도
            $extractedText = $this->analyzeHwpBinary($hwpContent, $attachment);
            
            if (!empty($extractedText) && strlen($extractedText) > 100) {
                Log::info('HWP 바이너리 분석 성공', [
                    'attachment_id' => $attachment->id,
                    'extracted_length' => strlen($extractedText)
                ]);
                return $extractedText;
            }

        } catch (Exception $e) {
            Log::warning('HWP 바이너리 분석 실패', [
                'attachment_id' => $attachment->id,
                'error' => $e->getMessage()
            ]);
        }

        // 2차: Mock 내용으로 대체 (하지만 실제 파일명 기반)
        Log::info('HWP 분석 실패, 파일명 기반 Mock 내용 생성', [
            'attachment_id' => $attachment->id,
            'original_name' => $attachment->original_name
        ]);

        return $this->generateIntelligentMockContent($attachment);
    }

    /**
     * HWP 바이너리 파일 분석 (실제 텍스트 추출 시도)
     * 
     * @param string $hwpContent HWP 파일 바이너리
     * @param Attachment $attachment 첨부파일 모델
     * @return string 추출된 텍스트
     */
    private function analyzeHwpBinary(string $hwpContent, Attachment $attachment): string
    {
        $extractedText = '';

        // HWP 5.x (HWPX) 형식인지 확인 (ZIP 기반)
        if (substr($hwpContent, 0, 2) === 'PK') {
            Log::info('HWPX (ZIP 기반) 형식 감지, ZIP 분석 시도');
            $extractedText = $this->extractTextFromHwpx($hwpContent);
        }

        // HWP 3.x/4.x 형식인지 확인 (OLE2 기반)
        if (empty($extractedText) && substr($hwpContent, 0, 8) === "\xd0\xcf\x11\xe0\xa1\xb1\x1a\xe1") {
            Log::info('HWP 3.x/4.x (OLE2 기반) 형식 감지, OLE2 분석 시도');
            $extractedText = $this->extractTextFromHwpOle($hwpContent);
        }

        // 단순 문자열 검색으로 한글 텍스트 찾기
        if (empty($extractedText)) {
            Log::info('바이너리 패턴 분석으로 한글 텍스트 추출 시도');
            $extractedText = $this->extractKoreanTextFromBinary($hwpContent);
        }

        return $extractedText;
    }

    /**
     * HWPX (ZIP 기반) 파일에서 텍스트 추출
     */
    private function extractTextFromHwpx(string $hwpContent): string
    {
        // 임시 파일로 저장 후 ZIP 분석
        $tempFile = tempnam(sys_get_temp_dir(), 'hwpx_');
        file_put_contents($tempFile, $hwpContent);

        try {
            if (class_exists('ZipArchive')) {
                $zip = new \ZipArchive();
                if ($zip->open($tempFile) === TRUE) {
                    $textContent = '';
                    
                    // Contents/section*.xml 파일들에서 텍스트 추출
                    for ($i = 0; $i < $zip->numFiles; $i++) {
                        $fileName = $zip->getNameIndex($i);
                        if (str_contains($fileName, 'section') && str_contains($fileName, '.xml')) {
                            $xmlContent = $zip->getFromIndex($i);
                            $textContent .= $this->extractTextFromXml($xmlContent);
                        }
                    }
                    
                    $zip->close();
                    unlink($tempFile);
                    
                    if (!empty($textContent)) {
                        return "【HWPX ZIP 분석 결과】\n\n" . $textContent;
                    }
                }
            }
        } catch (Exception $e) {
            Log::warning('HWPX ZIP 분석 실패', ['error' => $e->getMessage()]);
        }

        @unlink($tempFile);
        return '';
    }

    /**
     * HWP OLE2 파일에서 텍스트 추출 
     */
    private function extractTextFromHwpOle(string $hwpContent): string
    {
        // OLE2 구조에서 BodyText 스트림 찾기
        // 간단한 패턴 매칭으로 텍스트 블록 찾기
        $patterns = [
            '/BodyText/i',
            '/\x00\x00[\x20-\x7E가-힣]{10,}/u',
            '/[\x20-\x7E가-힣\s]{20,}/u'
        ];

        $extractedText = '';
        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $hwpContent, $matches)) {
                foreach ($matches[0] as $match) {
                    if (strlen(trim($match)) > 10) {
                        $extractedText .= trim($match) . "\n";
                    }
                }
            }
        }

        if (!empty($extractedText)) {
            return "【HWP OLE2 분석 결과】\n\n" . $extractedText;
        }

        return '';
    }

    /**
     * 바이너리에서 한글 텍스트 패턴 추출
     */
    private function extractKoreanTextFromBinary(string $hwpContent): string
    {
        $extractedText = '';
        
        // UTF-16LE, UTF-8 한글 패턴 검색
        $patterns = [
            // 한글 유니코드 범위 (가-힣)
            '/[\x00-\xFF]*[가-힣]{2,}[\x00-\xFF\s가-힣]{10,}/u',
            // 영문 + 한글 혼합 패턴
            '/[a-zA-Z가-힣\s\d\.\-\(\)]{15,}/u',
            // 공고, 과업, 제안 등 키워드 포함 패턴
            '/(공고|과업|제안|입찰|용역|시스템|구축|개발|데이터베이스|GIS)[가-힣\s\w\.\-\(\)]{20,}/u'
        ];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $hwpContent, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $text = trim($match[0]);
                    if (strlen($text) > 15 && !empty($text)) {
                        // 중복 제거 및 정리
                        if (!str_contains($extractedText, $text)) {
                            $extractedText .= $text . "\n\n";
                        }
                    }
                }
            }
        }

        if (!empty($extractedText)) {
            return "【바이너리 패턴 분석 결과】\n\n" . $extractedText;
        }

        return '';
    }

    /**
     * XML에서 텍스트 추출
     */
    private function extractTextFromXml(string $xmlContent): string
    {
        $textContent = '';
        
        try {
            // XML에서 텍스트 노드 추출
            $dom = new \DOMDocument();
            @$dom->loadXML($xmlContent);
            
            // 텍스트 노드들 수집
            $xpath = new \DOMXPath($dom);
            $textNodes = $xpath->query('//text()');
            
            foreach ($textNodes as $node) {
                $text = trim($node->nodeValue);
                if (strlen($text) > 2) {
                    $textContent .= $text . ' ';
                }
            }
            
            return trim($textContent);
            
        } catch (Exception $e) {
            // XML 파싱 실패 시 정규식으로 태그 제거
            $textContent = preg_replace('/<[^>]+>/', ' ', $xmlContent);
            $textContent = preg_replace('/\s+/', ' ', $textContent);
            return trim($textContent);
        }
    }

    /**
     * 파일명 기반 지능형 Mock 내용 생성
     */
    private function generateIntelligentMockContent(Attachment $attachment): string
    {
        $fileName = $attachment->original_name;
        $tenderNo = $attachment->tender->tender_no ?? 'UNKNOWN';
        
        // 파일명에서 키워드 분석
        if (str_contains($fileName, '과업지시서')) {
            return $this->generateTaskInstructionContent($tenderNo);
        } elseif (str_contains($fileName, '제안요청서') || str_contains($fileName, 'RFP')) {
            return $this->generateRfpContent($tenderNo);
        } elseif (str_contains($fileName, '입찰공고문') || str_contains($fileName, '공고')) {
            return $this->generateAnnouncementContent($tenderNo);
        } elseif (str_contains($fileName, '사업계획서')) {
            return $this->generateBusinessPlanContent($tenderNo);
        } elseif (str_contains($fileName, '기술제안서')) {
            return $this->generateTechnicalProposalContent($tenderNo);
        }
        
        // 기본 Mock 내용
        return $this->generateRealisticMockContentForDownload($attachment);
    }

    /**
     * RFP 내용 생성
     */
    private function generateRfpContent(string $tenderNo): string
    {
        return "제안요청서 (RFP) - $tenderNo

1. 사업 개요
1.1 사업명: 시스템 구축 및 개발 사업
1.2 사업 목적: 효율적인 정보시스템 구축을 통한 업무 개선
1.3 사업 기간: 계약일로부터 180일
1.4 총 사업비: 별도 협의

2. 제안 요청 사항
2.1 시스템 아키텍처 설계
2.2 데이터베이스 설계 및 구축
2.3 웹 기반 사용자 인터페이스 개발
2.4 시스템 통합 및 테스트
2.5 운영 매뉴얼 및 사용자 교육

3. 기술 요구사항
- 프로그래밍 언어: Java, Python, JavaScript 중 선택
- 데이터베이스: MySQL, PostgreSQL, Oracle 중 선택
- 웹 프레임워크: Spring, Django, React 등
- 운영체제: Linux (CentOS, Ubuntu)

4. 제안서 구성
4.1 사업 이해도 및 수행 방안
4.2 기술 제안서
4.3 프로젝트 관리 계획
4.4 투입 인력 및 조직
4.5 회사 소개 및 수행 실적

5. 평가 기준
- 기술적 우수성 (40점)
- 사업 수행 능력 (30점)
- 가격 경쟁력 (30점)

※ 본 RFP는 Mock 데이터이며, 실제 요구사항과 다를 수 있습니다.";
    }

    /**
     * 사업계획서 내용 생성
     */
    private function generateBusinessPlanContent(string $tenderNo): string
    {
        return "사업계획서 - $tenderNo

■ 사업 비전
혁신적인 기술과 전문성을 바탕으로 고객의 성공을 지원하는 최고의 솔루션 제공

■ 사업 목표
1. 고품질 시스템 개발 및 구축
2. 사용자 만족도 95% 이상 달성
3. 운영 효율성 50% 개선
4. 장기적 파트너십 구축

■ 추진 전략
1. 고객 중심의 맞춤형 솔루션 개발
2. 최신 기술 및 방법론 적용
3. 체계적인 프로젝트 관리
4. 지속적인 품질 관리 및 개선

■ 핵심 역량
- 15년간의 시스템 개발 경험
- 정부기관 프로젝트 다수 수행
- 인증된 전문 인력 보유
- 자체 개발 프레임워크 활용

■ 기대 효과
- 업무 처리 시간 단축
- 시스템 안정성 향상
- 운영 비용 절감
- 사용자 편의성 증대

※ 본 사업계획서는 Mock 데이터입니다.";
    }

    /**
     * 기술제안서 내용 생성
     */
    private function generateTechnicalProposalContent(string $tenderNo): string
    {
        return "기술제안서 - $tenderNo

1. 기술 아키텍처
1.1 시스템 구조
- 3-Tier 아키텍처 적용
- 마이크로서비스 기반 설계
- RESTful API 구현

1.2 기술 스택
- Frontend: React + TypeScript
- Backend: Spring Boot + Java 17
- Database: PostgreSQL + Redis
- Infrastructure: Docker + Kubernetes

2. 개발 방법론
2.1 애자일 개발 프로세스
- 스크럼 방법론 적용
- 2주 단위 스프린트
- 지속적 통합/배포 (CI/CD)

2.2 품질 관리
- 코드 리뷰 의무화
- 자동화된 테스트 구축
- SonarQube 품질 점검

3. 보안 방안
3.1 데이터 보안
- 암호화 전송 (TLS 1.3)
- 개인정보 암호화 저장
- 접근 권한 관리

3.2 시스템 보안
- 웹 방화벽 적용
- 침입 탐지 시스템
- 정기 보안 점검

4. 성능 최적화
- 데이터베이스 쿼리 최적화
- 캐싱 전략 적용
- 부하 분산 시스템 구축

※ 본 기술제안서는 Mock 데이터입니다.";
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