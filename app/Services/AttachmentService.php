<?php

namespace App\Services;

use App\Models\Tender;
use App\Models\Attachment;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Exception;

/**
 * 첨부파일 수집 및 관리 서비스
 *
 * @package App\Services
 */
class AttachmentService
{
    /**
     * 특정 공고의 제안요청정보 파일 자동 수집
     *
     * @param Tender $tender 입찰공고 모델
     * @return array 수집 결과
     */
    public function collectProposalFiles(Tender $tender): array
    {
        try {
            Log::info('제안요청정보 파일 자동 수집 시작', [
                'tender_id' => $tender->id,
                'tender_no' => $tender->tender_no
            ]);

            // Playwright를 통한 파일 수집
            $url = "https://www.g2b.go.kr/link/PNPE027_01/single/?bidPbancNo={$tender->tender_no}&bidPbancOrd=000";

            $command = sprintf(
                'timeout 120 node %s crawl-proposal-files %s 2>&1',
                base_path('playwright-scripts/tender-file-crawler.js'),
                escapeshellarg($url)
            );

            $output = shell_exec($command);
            $result = json_decode($output, true);

            if (!$result || !isset($result['success'])) {
                Log::warning('제안요청정보 파일 수집 실패', [
                    'tender_id' => $tender->id,
                    'output' => $output
                ]);

                return [
                    'success' => false,
                    'message' => '파일 정보를 가져올 수 없습니다.',
                    'files_found' => 0,
                    'files_downloaded' => 0
                ];
            }

            $files = $result['files'] ?? [];
            $downloadedCount = 0;
            $errors = [];

            foreach ($files as $file) {
                try {
                    // 이미 존재하는 파일인지 확인
                    $existingAttachment = Attachment::where('tender_id', $tender->id)
                        ->where('original_name', $file['file_name'])
                        ->first();

                    if ($existingAttachment) {
                        Log::info('이미 존재하는 파일 스킵', [
                            'tender_id' => $tender->id,
                            'file_name' => $file['file_name']
                        ]);
                        $downloadedCount++;
                        continue;
                    }

                    // 첨부파일 메타데이터 저장
                    $attachment = Attachment::create([
                        'tender_id' => $tender->id,
                        'file_name' => $file['file_name'],
                        'original_name' => $file['file_name'],
                        'doc_name' => $file['doc_name'] ?? null,
                        'download_url' => $file['download_url'] ?? null,
                        'post_data' => $file['post_data'] ?? null,
                        'download_status' => 'pending',
                        'file_size' => 0,
                        'local_path' => null,
                    ]);

                    Log::info('제안요청정보 파일 메타데이터 저장', [
                        'tender_id' => $tender->id,
                        'filename' => $file['file_name'],
                        'doc_name' => $file['doc_name'] ?? null,
                        'download_url' => !empty($file['download_url']) ? '있음' : '없음',
                        'post_data' => !empty($file['post_data']) ? '있음' : '없음',
                        'url' => $file['download_url'] ?? null,
                    ]);

                    $downloadedCount++;
                } catch (Exception $e) {
                    $errors[] = $file['file_name'] . ': ' . $e->getMessage();
                    Log::error('제안요청정보 파일 저장 오류', [
                        'tender_id' => $tender->id,
                        'file' => $file,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            Log::info('제안요청정보 파일 크롤링 완료', [
                'success' => true,
                'message' => count($files) . '개 파일 저장 완료',
                'files_found' => count($files),
                'files_downloaded' => $downloadedCount,
                'errors' => $errors
            ]);

            return [
                'success' => true,
                'message' => count($files) . '개 파일 저장 완료',
                'files_found' => count($files),
                'files_downloaded' => $downloadedCount,
                'errors' => $errors
            ];

        } catch (Exception $e) {
            Log::error('제안요청정보 파일 자동 수집 오류', [
                'tender_id' => $tender->id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => '파일 수집 중 오류 발생: ' . $e->getMessage(),
                'files_found' => 0,
                'files_downloaded' => 0,
                'errors' => [$e->getMessage()]
            ];
        }
    }

    /**
     * 개별 첨부파일 다운로드 (재다운로드용)
     *
     * @param Attachment $attachment 첨부파일 모델
     * @return void
     * @throws Exception
     */
    public function downloadAttachment(Attachment $attachment): void
    {
        Log::info('첨부파일 재다운로드 시작', [
            'attachment_id' => $attachment->id,
            'file_name' => $attachment->file_name
        ]);

        try {
            // 1단계: ProposalFileCrawlerService로 다운로드 메타데이터 갱신
            $crawler = app(\App\Services\ProposalFileCrawlerService::class);
            $metaResult = $crawler->downloadSingleFile($attachment);

            if (!$metaResult['success']) {
                throw new Exception($metaResult['message'] ?? '파일 메타데이터 갱신에 실패했습니다.');
            }

            Log::info('파일 메타데이터 갱신 완료', [
                'attachment_id' => $attachment->id,
                'download_url' => $attachment->download_url ? '있음' : '없음'
            ]);

            // 2단계: ProposalFileDownloaderService로 실제 파일 다운로드
            $downloader = app(\App\Services\ProposalFileDownloaderService::class);
            $downloadResult = $downloader->downloadFile($attachment->fresh());

            if (!$downloadResult['success']) {
                throw new Exception($downloadResult['message'] ?? '파일 다운로드에 실패했습니다.');
            }

            Log::info('첨부파일 재다운로드 완료', [
                'attachment_id' => $attachment->id,
                'local_path' => $downloadResult['local_path'],
                'file_size' => $downloadResult['file_size']
            ]);

        } catch (Exception $e) {
            Log::error('첨부파일 재다운로드 오류', [
                'attachment_id' => $attachment->id,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }
}
