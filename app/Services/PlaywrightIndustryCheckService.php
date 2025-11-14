<?php

namespace App\Services;

use App\Models\Tender;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Exception;

/**
 * Playwright 기반 업종제한사항 검사 서비스
 *
 * 나라장터 공고 상세 페이지에서 업종제한사항 HTML을 파싱하여
 * 타이드플로 대상 업종코드(1468, 1426, 6528) 외의 코드가 있으면
 * is_unsuitable = true 설정
 *
 * @package App\Services
 */
class PlaywrightIndustryCheckService
{
    /**
     * 타이드플로 대상 업종코드 (정보처리, 소프트웨어개발, 공학서비스)
     */
    private const TARGET_INDUSTRY_CODES = ['1468', '1426', '6528'];

    /**
     * Playwright로 공고 상세 페이지에서 업종제한사항 검사
     *
     * @param Tender $tender 입찰공고 모델
     * @return array 검사 결과
     */
    public function checkIndustryRestrictionFromWeb(Tender $tender): array
    {
        try {
            // 1. 공고 상세 URL 생성
            $detailUrl = $tender->detail_url;
            if (!$detailUrl) {
                return [
                    'success' => false,
                    'error' => '공고 상세 URL 없음'
                ];
            }

            // 2. Playwright 스크립트 실행
            $scriptPath = base_path('scripts/extract_industry_restriction.cjs');
            $result = $this->runPlaywrightScript($scriptPath, $detailUrl);

            if (!$result['success']) {
                return $result;
            }

            $industryText = $result['industry_text'];
            $foundCodes = $result['found_codes'];

            // 3. 업종코드 분석
            $otherCodes = [];
            foreach ($foundCodes as $code) {
                if (!in_array($code, self::TARGET_INDUSTRY_CODES)) {
                    $otherCodes[] = $code;
                }
            }

            $isSuitable = empty($otherCodes);

            // 4. 비적합 자동 설정
            if (!$isSuitable) {
                // 비적합 이유 생성
                $reason = '업종코드 ' . implode(', ', $otherCodes) . ' 포함 (대상: ' . implode(', ', self::TARGET_INDUSTRY_CODES) . ')';

                $tender->update([
                    'is_unsuitable' => true,
                    'unsuitable_reason' => $reason
                ]);

                Log::warning('업종제한으로 비적합 자동 설정 (Playwright)', [
                    'tender_id' => $tender->id,
                    'tender_no' => $tender->tender_no,
                    'target_codes' => self::TARGET_INDUSTRY_CODES,
                    'found_codes' => $foundCodes,
                    'other_codes' => $otherCodes,
                    'reason' => $reason,
                    'industry_text' => mb_substr($industryText, 0, 200)
                ]);
            } else {
                // 적합한 경우 비적합 마크 해제
                $tender->update([
                    'is_unsuitable' => false,
                    'unsuitable_reason' => null
                ]);
            }

            return [
                'success' => true,
                'has_restriction' => !empty($foundCodes),
                'found_codes' => $foundCodes,
                'other_codes' => $otherCodes,
                'is_suitable' => $isSuitable,
                'industry_text' => $industryText,
                'auto_marked_unsuitable' => !$isSuitable
            ];

        } catch (Exception $e) {
            Log::error('Playwright 업종제한사항 검사 오류', [
                'tender_id' => $tender->id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Playwright 스크립트 실행
     *
     * @param string $scriptPath 스크립트 경로
     * @param string $url 공고 상세 URL
     * @return array 실행 결과
     */
    private function runPlaywrightScript(string $scriptPath, string $url): array
    {
        try {
            $command = sprintf(
                'node %s %s 2>&1',
                escapeshellarg($scriptPath),
                escapeshellarg($url)
            );

            $output = shell_exec($command);

            if (!$output) {
                return [
                    'success' => false,
                    'error' => 'Playwright 스크립트 실행 실패'
                ];
            }

            // JSON 결과 파싱
            $result = json_decode($output, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return [
                    'success' => false,
                    'error' => 'JSON 파싱 실패: ' . $output
                ];
            }

            return $result;

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
