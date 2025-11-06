<?php

namespace App\Services;

use App\Models\Tender;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Exception;

/**
 * 상주 단어 검사 서비스
 *
 * @package App\Services
 */
class SangjuCheckService
{
    /**
     * 특정 공고의 모든 첨부파일에서 "상주" 단어 자동 검사
     *
     * @param Tender $tender 입찰공고 모델
     * @return array 검사 결과
     */
    public function checkSangjuKeyword(Tender $tender): array
    {
        $hasSangju = false;
        $foundInFiles = [];
        $totalFiles = 0;
        $checkedFiles = 0;

        try {
            // 1. 제안요청정보 파일 검사 (데이터베이스 attachments)
            $proposalAttachments = $tender->attachments()->where('download_status', 'completed')->get();

            foreach ($proposalAttachments as $attachment) {
                $totalFiles++;

                $filePath = $attachment->local_path;

                // 파일 경로 확인
                $fullPath = storage_path('app/' . $filePath);
                if (!file_exists($fullPath)) {
                    $fullPath = storage_path('app/private/' . $filePath);
                }

                if (!file_exists($fullPath)) {
                    continue;
                }

                // 파일 확장자 확인
                $extension = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));

                // 확장자가 없는 경우 file_name에서 확장자 가져오기
                if (empty($extension) || $extension === pathinfo($fullPath, PATHINFO_BASENAME)) {
                    $extension = strtolower(pathinfo($attachment->file_name, PATHINFO_EXTENSION));
                }

                // 텍스트 추출 가능한 파일만 처리 (hwp, hwpx, pdf, doc, docx, txt)
                if (!in_array($extension, ['hwp', 'hwpx', 'pdf', 'doc', 'docx', 'txt'])) {
                    continue;
                }

                $checkedFiles++;

                // 파일 형식별 텍스트 추출
                $extractedText = null;

                if ($extension === 'hwp' || $extension === 'hwpx') {
                    if ($extension === 'hwp') {
                        // HWP 파일 - hwp5txt 기반 스크립트 사용
                        $scriptPath = base_path('scripts/extract_hwp_text_hwp5.py');
                        $command = "python3 " . escapeshellarg($scriptPath) . " " . escapeshellarg($fullPath) . " 2>&1";
                        $extractedText = shell_exec($command);
                    } else {
                        // HWPX 파일 - ZIP/XML 파싱 스크립트 사용
                        $scriptPath = base_path('scripts/extract_hwpx_text.py');
                        $command = "python3 " . escapeshellarg($scriptPath) . " " . escapeshellarg($fullPath) . " 2>&1";
                        $extractedText = shell_exec($command);
                    }
                } elseif ($extension === 'pdf') {
                    // PDF 파일 - pdftotext 사용
                    $command = "pdftotext " . escapeshellarg($fullPath) . " - 2>&1";
                    $extractedText = shell_exec($command);
                } elseif (in_array($extension, ['doc', 'docx'])) {
                    // DOC/DOCX 파일
                    if ($extension === 'doc') {
                        $command = "antiword " . escapeshellarg($fullPath) . " 2>&1";
                    } else {
                        $command = "docx2txt " . escapeshellarg($fullPath) . " - 2>&1";
                    }
                    $extractedText = shell_exec($command);
                } elseif ($extension === 'txt') {
                    // 텍스트 파일 - 직접 읽기
                    $extractedText = file_get_contents($fullPath);
                }

                if ($extractedText && mb_stripos($extractedText, '상주') !== false) {
                    $hasSangju = true;
                    $foundInFiles[] = [
                        'file_name' => ($attachment->file_name ?: $attachment->original_name),
                        'file_type' => '제안요청정보',
                        'extension' => $extension,
                        'occurrences' => substr_count(mb_strtolower($extractedText), '상주'),
                        'file_size' => file_exists($fullPath) ? filesize($fullPath) : 0,
                        'file_path' => $attachment->local_path
                    ];
                }
            }

            // 2. 나라장터 첨부파일 다운로드 및 검사
            $attachmentFiles = $tender->attachment_files;

            if (is_array($attachmentFiles) && !empty($attachmentFiles)) {
                foreach ($attachmentFiles as $fileInfo) {
                    $totalFiles++;

                    // 파일명과 다운로드 URL 확인
                    $fileName = $fileInfo['name'] ?? '첨부파일';
                    $downloadUrl = $fileInfo['url'] ?? null;

                    if (!$downloadUrl) {
                        continue;
                    }

                    // 파일 확장자 확인
                    $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

                    // 텍스트 추출 가능한 파일만 처리 (hwp, hwpx, pdf, doc, docx, txt)
                    if (!in_array($extension, ['hwp', 'hwpx', 'pdf', 'doc', 'docx', 'txt'])) {
                        continue;
                    }

                    try {
                        // 임시 파일 경로
                        $tempDir = storage_path('app/temp_sangju_check/' . $tender->id);
                        if (!file_exists($tempDir)) {
                            mkdir($tempDir, 0755, true);
                        }

                        $tempFilePath = $tempDir . '/' . $fileName;

                        // 파일 다운로드 (G2B 서버는 브라우저 헤더 필요)
                        $response = Http::withHeaders([
                            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                            'Referer' => 'https://www.g2b.go.kr/',
                            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
                            'Accept-Language' => 'ko-KR,ko;q=0.9,en-US;q=0.8,en;q=0.7',
                        ])
                        ->timeout(30)
                        ->get($downloadUrl);

                        if (!$response->successful()) {
                            Log::warning('G2B 첨부파일 다운로드 실패 (자동 상주 검사)', [
                                'tender_id' => $tender->id,
                                'file_name' => $fileName,
                                'status' => $response->status(),
                                'url' => $downloadUrl
                            ]);
                            continue; // 다운로드 실패 시 건너뛰기
                        }

                        file_put_contents($tempFilePath, $response->body());
                        $checkedFiles++;

                        // 파일 형식별 텍스트 추출
                        $extractedText = null;

                        if ($extension === 'hwp' || $extension === 'hwpx') {
                            if ($extension === 'hwp') {
                                // HWP 파일 - hwp5txt 기반 스크립트 사용
                                $scriptPath = base_path('scripts/extract_hwp_text_hwp5.py');
                                $command = "python3 " . escapeshellarg($scriptPath) . " " . escapeshellarg($tempFilePath) . " 2>&1";
                                $extractedText = shell_exec($command);
                            } else {
                                // HWPX 파일 - ZIP/XML 파싱 스크립트 사용
                                $scriptPath = base_path('scripts/extract_hwpx_text.py');
                                $command = "python3 " . escapeshellarg($scriptPath) . " " . escapeshellarg($tempFilePath) . " 2>&1";
                                $extractedText = shell_exec($command);
                            }
                        } elseif ($extension === 'pdf') {
                            // PDF 파일 - pdftotext 명령어 사용
                            $command = "pdftotext " . escapeshellarg($tempFilePath) . " - 2>&1";
                            $extractedText = shell_exec($command);
                        } elseif (in_array($extension, ['doc', 'docx'])) {
                            // DOC/DOCX 파일 처리
                            if ($extension === 'doc') {
                                $command = "antiword " . escapeshellarg($tempFilePath) . " 2>&1";
                            } else {
                                $command = "docx2txt " . escapeshellarg($tempFilePath) . " - 2>&1";
                            }
                            $extractedText = shell_exec($command);
                        } elseif ($extension === 'txt') {
                            // TXT 파일 - 직접 읽기
                            $extractedText = file_get_contents($tempFilePath);
                        }

                        // "상주" 단어 검색 (대소문자 구분 없이)
                        if ($extractedText && mb_stripos($extractedText, '상주') !== false) {
                            $hasSangju = true;
                            $foundInFiles[] = [
                                'file_name' => $fileName,
                                'file_type' => '첨부파일',
                                'extension' => $extension,
                                'occurrences' => substr_count(mb_strtolower($extractedText), '상주'),
                                'file_size' => file_exists($tempFilePath) ? filesize($tempFilePath) : 0,
                                'file_path' => 'temp/' . $fileName
                            ];
                        }

                        // 임시 파일 삭제
                        @unlink($tempFilePath);

                    } catch (Exception $e) {
                        Log::warning('G2B 첨부파일 상주 검사 오류 (자동)', [
                            'tender_id' => $tender->id,
                            'file_name' => $fileName,
                            'error' => $e->getMessage()
                        ]);
                        continue;
                    }
                }

                // 임시 디렉토리 삭제
                $tempDir = storage_path('app/temp_sangju_check/' . $tender->id);
                if (file_exists($tempDir)) {
                    @rmdir($tempDir);
                }
            }

            // 결과 로깅
            Log::info('상주 단어 자동 검사 완료', [
                'tender_id' => $tender->id,
                'tender_no' => $tender->tender_no,
                'has_sangju' => $hasSangju,
                'total_files' => $totalFiles,
                'checked_files' => $checkedFiles,
                'found_in' => $foundInFiles
            ]);

            // 상주가 발견되면 자동으로 is_unsuitable = true 설정
            if ($hasSangju && !$tender->is_unsuitable) {
                $tender->update(['is_unsuitable' => true]);
                Log::info('상주 발견으로 비적합 자동 설정', [
                    'tender_id' => $tender->id,
                    'tender_no' => $tender->tender_no
                ]);
            }

            // 총 발견 횟수 계산
            $totalOccurrences = 0;
            foreach ($foundInFiles as $fileInfo) {
                $totalOccurrences += $fileInfo['occurrences'];
            }

            return [
                'success' => true,
                'has_sangju' => $hasSangju,
                'total_files' => $totalFiles,
                'checked_files' => $checkedFiles,
                'found_in_files' => $foundInFiles,
                'total_occurrences' => $totalOccurrences,
                'auto_marked_unsuitable' => $hasSangju
            ];

        } catch (Exception $e) {
            Log::error('상주 단어 자동 검사 오류', [
                'tender_id' => $tender->id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'has_sangju' => false,
                'total_files' => $totalFiles,
                'checked_files' => $checkedFiles,
                'found_in_files' => [],
                'error' => $e->getMessage()
            ];
        }
    }
}
