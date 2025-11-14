<?php

namespace App\Services;

use App\Models\Tender;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * 업종제한사항 검사 서비스
 *
 * 제안요청정보 파일에서 업종제한사항을 분석하여
 * 타이드플로 대상 업종코드(1468, 1426, 6528) 외의 코드가 있으면
 * is_unsuitable = true 설정
 *
 * @package App\Services
 */
class IndustryRestrictionCheckService
{
    /**
     * 타이드플로 대상 업종코드 (정보처리, 소프트웨어개발, 공학서비스)
     */
    private const TARGET_INDUSTRY_CODES = ['1468', '1426', '6528'];

    /**
     * 특정 공고의 업종제한사항 자동 검사
     *
     * @param Tender $tender 입찰공고 모델
     * @return array 검사 결과
     */
    public function checkIndustryRestriction(Tender $tender): array
    {
        $hasRestriction = false;
        $foundCodes = [];
        $restrictionText = '';
        $totalFiles = 0;
        $checkedFiles = 0;

        try {
            // 1. 제안요청정보 파일 검사 (데이터베이스 attachments)
            $proposalAttachments = $tender->attachments()
                ->where('type', 'proposal')
                ->where('download_status', 'completed')
                ->get();

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

                if ($extractedText) {
                    // "업종제한" 또는 "업종 제한" 키워드 주변 텍스트 추출
                    $result = $this->parseIndustryRestriction($extractedText);

                    if (!empty($result['codes'])) {
                        $hasRestriction = true;
                        $foundCodes = array_merge($foundCodes, $result['codes']);
                        $restrictionText = $result['context'];

                        Log::info('업종제한사항 발견', [
                            'tender_id' => $tender->id,
                            'tender_no' => $tender->tender_no,
                            'file_name' => $attachment->file_name,
                            'found_codes' => $result['codes'],
                            'context' => mb_substr($result['context'], 0, 200)
                        ]);

                        // 첫 번째 발견 시 중단
                        break;
                    }
                }
            }

            // 업종코드가 발견된 경우 적합성 판단
            $isSuitable = true;
            $otherCodes = [];

            if ($hasRestriction && !empty($foundCodes)) {
                // 타이드플로 대상 외 업종코드 확인
                foreach ($foundCodes as $code) {
                    if (!in_array($code, self::TARGET_INDUSTRY_CODES)) {
                        $otherCodes[] = $code;
                    }
                }

                // 1468, 1426, 6528 외의 코드가 있으면 비적합
                if (!empty($otherCodes)) {
                    $isSuitable = false;

                    // 비적합 자동 설정
                    $tender->update(['is_unsuitable' => true]);

                    Log::warning('업종제한으로 비적합 자동 설정', [
                        'tender_id' => $tender->id,
                        'tender_no' => $tender->tender_no,
                        'target_codes' => self::TARGET_INDUSTRY_CODES,
                        'found_codes' => $foundCodes,
                        'other_codes' => $otherCodes
                    ]);
                }
            }

            return [
                'success' => true,
                'has_restriction' => $hasRestriction,
                'found_codes' => array_unique($foundCodes),
                'other_codes' => $otherCodes,
                'is_suitable' => $isSuitable,
                'restriction_text' => $restrictionText,
                'total_files' => $totalFiles,
                'checked_files' => $checkedFiles,
                'auto_marked_unsuitable' => !$isSuitable
            ];

        } catch (Exception $e) {
            Log::error('업종제한사항 검사 오류', [
                'tender_id' => $tender->id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'has_restriction' => false,
                'found_codes' => [],
                'other_codes' => [],
                'is_suitable' => true,
                'total_files' => $totalFiles,
                'checked_files' => $checkedFiles,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 텍스트에서 업종제한사항 파싱
     *
     * @param string $text 추출된 텍스트
     * @return array ['codes' => [...], 'context' => '...']
     */
    private function parseIndustryRestriction(string $text): array
    {
        $codes = [];
        $context = '';

        // "업종제한", "업종 제한", "업종한정" 등 키워드 찾기
        $patterns = [
            '/업종\s*제한[^a-zA-Z0-9가-힣]{0,500}/u',
            '/업종\s*한정[^a-zA-Z0-9가-힣]{0,500}/u',
            '/입찰\s*참가\s*자격[^a-zA-Z0-9가-힣]{0,800}/u'
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $context = $matches[0];

                // 4자리 숫자 모두 추출 (업종코드)
                preg_match_all('/\b(\d{4})\b/', $context, $numberMatches);

                if (!empty($numberMatches[1])) {
                    $codes = array_merge($codes, $numberMatches[1]);
                }

                // 첫 번째 매칭으로 충분
                if (!empty($codes)) {
                    break;
                }
            }
        }

        return [
            'codes' => array_unique($codes),
            'context' => $context
        ];
    }
}
