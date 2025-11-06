<?php

// [BEGIN nara:tender_collector]
namespace App\Services;

use App\Models\Tender;
use App\Models\TenderCategory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;

/**
 * 입찰공고 데이터 수집 및 처리 서비스
 * 
 * @package App\Services
 */
class TenderCollectorService
{
    private NaraApiService $naraApi;
    private AttachmentService $attachmentService;
    private SangjuCheckService $sangjuCheckService;

    public function __construct(
        NaraApiService $naraApi,
        AttachmentService $attachmentService,
        SangjuCheckService $sangjuCheckService
    ) {
        $this->naraApi = $naraApi;
        $this->attachmentService = $attachmentService;
        $this->sangjuCheckService = $sangjuCheckService;
    }
    
    /**
     * 기간별 입찰공고 데이터 수집 (고급 필터링 포함)
     * 
     * @param string $startDate 시작일 (YYYY-MM-DD)
     * @param string $endDate 종료일 (YYYY-MM-DD)
     * @param array $filters 추가 필터 조건
     * @return array 수집 결과 통계
     */
    public function collectTendersByDateRange(string $startDate, string $endDate, array $filters = []): array
    {
        $stats = [
            'total_fetched' => 0,
            'new_records' => 0,
            'updated_records' => 0,
            'errors' => 0,
            'start_time' => now(),
            'end_time' => null,
        ];
        
        Log::info('입찰공고 데이터 수집 시작', [
            'start_date' => $startDate,
            'end_date' => $endDate
        ]);
        
        try {
            // 날짜 포맷 변환 (API 형식: YYYYMMDD)
            $apiStartDate = Carbon::parse($startDate)->format('Ymd');
            $apiEndDate = Carbon::parse($endDate)->format('Ymd');
            
            $pageNo = 1;
            $totalPages = 1;
            
            do {
                $response = $this->naraApi->getTendersByDateRange(
                    $apiStartDate,
                    $apiEndDate,
                    $pageNo,
                    100,
                    $filters
                );
                
                if ($pageNo === 1) {
                    // API 응답 구조: response.body 접근
                    $totalCount = $response['response']['body']['totalCount'] ?? 0;
                    $totalPages = $totalCount > 0 ? ceil($totalCount / 100) : 1;
                    
                    Log::info('수집 대상 데이터 확인', [
                        'total_count' => $totalCount,
                        'total_pages' => $totalPages
                    ]);
                }
                
                // API 응답 구조: response.body.items 배열에 데이터 있음 (NaraApiService에서 이미 통합됨)
                $items = $response['response']['body']['items'] ?? [];
                
                if (!empty($items)) {
                    // 업종코드 필터 추출
                    $classificationFilter = $filters['classification_codes'] ?? [];
                    
                    // 디버그 로그 - 필터 확인
                    Log::info('필터 확인', [
                        'filters_received' => $filters,
                        'classification_filter' => $classificationFilter,
                        'filter_empty' => empty($classificationFilter),
                        'items_count' => count($items)
                    ]);
                    
                    // 전체 데이터 수집을 위해 모든 필드 포함
                    $result = $this->processTenderItems($items, true, $classificationFilter);
                    $stats['total_fetched'] += count($items);
                    $stats['new_records'] += $result['new_records'];
                    $stats['updated_records'] += $result['updated_records'];
                    $stats['errors'] += $result['errors'];
                    $stats['duplicate_skipped'] = ($stats['duplicate_skipped'] ?? 0) + $result['duplicate_skipped'];
                    $stats['classification_filtered'] = ($stats['classification_filtered'] ?? 0) + $result['classification_filtered'];
                }
                
                $pageNo++;
                
                // API 호출 간격 (Rate Limiting 방지)
                if ($pageNo <= $totalPages) {
                    sleep(1);
                }
                
            } while ($pageNo <= $totalPages);
            
        } catch (Exception $e) {
            Log::error('입찰공고 데이터 수집 오류', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $stats['errors']++;
        }
        
        $stats['end_time'] = now();
        $stats['duration'] = $stats['end_time']->diffInSeconds($stats['start_time']);
        
        Log::info('입찰공고 데이터 수집 완료', $stats);
        
        // 수집 완료 후 모든 공고의 상태 업데이트
        try {
            Log::info('수집 후 공고 상태 자동 업데이트 시작');
            $statusStats = $this->updateTenderStatuses();
            $stats['status_updates'] = $statusStats;
            Log::info('공고 상태 자동 업데이트 완료', $statusStats);
        } catch (Exception $e) {
            Log::error('공고 상태 자동 업데이트 오류', [
                'error' => $e->getMessage()
            ]);
        }
        
        return $stats;
    }
    
    /**
     * 오늘의 입찰공고 데이터 수집
     * 
     * @return array 수집 결과 통계
     */
    public function collectTodayTenders(): array
    {
        $today = date('Y-m-d');
        // 기본 고급 필터링 적용
        return $this->collectTendersWithAdvancedFilters($today, $today);
    }
    
    /**
     * 최근 7일간의 입찰공고 데이터 수집
     * 
     * @return array 수집 결과 통계
     */
    public function collectRecentTenders(): array
    {
        $endDate = date('Y-m-d');
        $startDate = date('Y-m-d', strtotime('-7 days'));
        // 기본 고급 필터링 적용
        return $this->collectTendersWithAdvancedFilters($startDate, $endDate);
    }
    
    /**
     * API에서 받은 입찰공고 데이터 처리 (전체 필드 및 중복 제거)
     * 
     * @param array $items API 응답의 items 배열
     * @param bool $includeAllFields 모든 필드 포함 여부
     * @param array $classificationFilter 분류코드 필터 (빈 배열이면 필터링 없음)
     * @return array 처리 결과 통계
     */
    private function processTenderItems(array $items, bool $includeAllFields = false, array $classificationFilter = []): array
    {
        $stats = [
            'new_records' => 0,
            'updated_records' => 0,
            'duplicate_skipped' => 0,
            'classification_filtered' => 0,
            'errors' => 0
        ];
        
        foreach ($items as $item) {
            try {
                // 정확한 8개 업종상세코드 매칭 (중복 제거)
                $targetCodes = [
                    '81112002', // 데이터처리서비스 (빅데이터분석서비스도 동일 코드)
                    '81112299', // 소프트웨어유지및지원서비스
                    '81111811', // 운영위탁서비스
                    '81111899', // 정보시스템유지관리서비스
                    '81112199', // 인터넷지원개발서비스
                    '81111598', // 패키지소프트웨어개발및도입서비스
                    '81111599', // 정보시스템개발서비스
                    '81151699'  // 공간정보DB구축서비스
                ];

                $itemClassification = $this->safeExtractString($item['pubPrcrmntClsfcNo'] ?? '');
                $bidNtceNo = $this->safeExtractString($item['bidNtceNo'] ?? '');

                // 서비스구분명 확인 (일반용역만 허용)
                $srvceDivNm = $this->safeExtractString($item['srvceDivNm'] ?? '');
                if (!empty($srvceDivNm) && $srvceDivNm !== '일반용역') {
                    $stats['classification_filtered']++;
                    Log::debug("필터링 제외: {$bidNtceNo} - 서비스구분 '{$srvceDivNm}' (일반용역 아님)");
                    continue;
                }

                // 정확한 코드 매칭으로 확인
                $isTargetCode = false;
                $matchedCode = '';
                $isEmptyClassification = false;

                // 빈 값이나 필드없음 확인
                if (empty($itemClassification) || trim($itemClassification) === '') {
                    $isEmptyClassification = true;
                    $matchedCode = 'EMPTY'; // 기타 카테고리용
                    Log::debug("빈 업종코드로 저장: {$bidNtceNo} - 분류코드 없음");
                } else {
                    // 정확한 코드 매칭 (정확히 일치하는 8개 코드만)
                    if (in_array($itemClassification, $targetCodes)) {
                        $isTargetCode = true;
                        $matchedCode = $itemClassification;
                    }
                }

                // 대상 코드도 아니고 빈 값도 아닌 경우만 제외
                if (!$isTargetCode && !$isEmptyClassification) {
                    $stats['classification_filtered']++;
                    Log::debug("필터링 제외: {$bidNtceNo} - 분류코드 '{$itemClassification}' (대상 코드 아님)");
                    continue;
                }

                Log::info("대상 업종코드 발견: {$bidNtceNo} - 분류코드 '{$itemClassification}' (매칭: {$matchedCode}), 서비스구분: {$srvceDivNm}");

                // 빈 분류코드 정보를 전달
                $extraData = ['is_empty_classification' => $isEmptyClassification];
                $tenderData = $this->mapApiDataToTender($item, $includeAllFields, $extraData);
                
                // 중복 확인
                if ($this->isDuplicate($tenderData)) {
                    $stats['duplicate_skipped']++;
                    continue;
                }
                
                $result = $this->saveTenderData($tenderData);
                if ($result === 'created') {
                    $stats['new_records']++;
                    // 첨부파일 다운로드 시도
                    $this->downloadTenderAttachments($item, $tenderData['tender_no']);

                    // ✨ 자동 제안요청정보 파일 수집 및 상주 검사
                    $tender = Tender::where('tender_no', $tenderData['tender_no'])->first();
                    if ($tender) {
                        // 제안요청정보 파일 수집
                        try {
                            $this->attachmentService->collectProposalFiles($tender);
                        } catch (Exception $e) {
                            Log::warning('자동 제안요청정보 파일 수집 실패', [
                                'tender_id' => $tender->id,
                                'error' => $e->getMessage()
                            ]);
                        }

                        // 상주 단어 검사 (파일 수집 후 실행)
                        try {
                            $this->sangjuCheckService->checkSangjuKeyword($tender);
                        } catch (Exception $e) {
                            Log::warning('자동 상주 검사 실패', [
                                'tender_id' => $tender->id,
                                'error' => $e->getMessage()
                            ]);
                        }
                    }
                } else {
                    $stats['updated_records']++;
                }
                
            } catch (Exception $e) {
                Log::error('입찰공고 데이터 처리 오류', [
                    'item' => $item,
                    'error' => $e->getMessage()
                ]);
                $stats['errors']++;
            }
        }
        
        return $stats;
    }
    
    /**
     * 기존 공고들의 상태 업데이트 (개찰일자 기준)
     * 
     * @return array 업데이트 결과 통계
     */
    public function updateTenderStatuses(): array
    {
        $stats = [
            'total_checked' => 0,
            'status_changed' => 0,
            'errors' => 0
        ];
        
        Log::info('공고 상태 업데이트 시작');
        
        // 현재 active 상태인 모든 공고 확인
        Tender::where('status', 'active')
            ->whereNotNull('openg_dt')
            ->where('openg_dt', '!=', '')
            ->chunk(100, function ($tenders) use (&$stats) {
                foreach ($tenders as $tender) {
                    $stats['total_checked']++;
                    
                    try {
                        $shouldBeClosed = false;
                        $today = Carbon::now()->startOfDay();
                        
                        // 개찰일자 확인 (날짜 기준)
                        if (!empty($tender->openg_dt)) {
                            $openingDate = Carbon::parse($tender->openg_dt)->startOfDay();
                            // 개찰일이 오늘보다 과거이거나 오늘이면 마감
                            if ($openingDate->isBefore($today) || $openingDate->isSameDay($today)) {
                                $shouldBeClosed = true;
                            }
                        }
                        
                        // 입찰 마감일 확인 (날짜 기준, 당일은 제외)
                        if (!$shouldBeClosed && !empty($tender->bid_clse_dt)) {
                            $bidCloseDate = Carbon::parse($tender->bid_clse_dt)->startOfDay();
                            // 마감일이 오늘보다 과거일 때만 마감 (당일은 D-Day로 유지)
                            if ($bidCloseDate->isBefore($today)) {
                                $shouldBeClosed = true;
                            }
                        }
                        
                        if ($shouldBeClosed) {
                            $tender->update(['status' => 'closed']);
                            $stats['status_changed']++;
                            
                            Log::debug('공고 상태 업데이트', [
                                'tender_no' => $tender->tender_no,
                                'opening_date' => $tender->openg_dt,
                                'old_status' => 'active',
                                'new_status' => 'closed'
                            ]);
                        }
                        
                    } catch (Exception $e) {
                        $stats['errors']++;
                        Log::error('공고 상태 업데이트 오류', [
                            'tender_no' => $tender->tender_no,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            });
        
        Log::info('공고 상태 업데이트 완료', $stats);
        
        return $stats;
    }
    
    /**
     * API 데이터를 Tender 모델 형식으로 변환 (전체 109개 필드 포함)
     * 
     * @param array $item API 응답 데이터 항목
     * @param bool $includeAllFields 모든 필드 포함 여부 (기본값: true)
     * @return array Tender 모델용 데이터
     */
    private function mapApiDataToTender(array $item, bool $includeAllFields = true, array $extraData = []): array
    {
        // 안전한 필드 추출 (배열일 수 있는 필드들 처리)
        $bidNtceNo = $this->safeExtractString($item['bidNtceNo'] ?? '');
        $bidNtceNm = $this->safeExtractString($item['bidNtceNm'] ?? '');
        $dminsttNm = $this->safeExtractString($item['dminsttNm'] ?? '');
        
        // 공고 분류 매핑 (세부업종코드 기반으로 정확한 분류)
        if ($extraData['is_empty_classification'] ?? false) {
            $categoryId = 4; // 기타 카테고리
        } else {
            // 세부업종코드로 분류 결정 (더 정확함)
            $detailCode = $this->safeExtractString($item['pubPrcrmntClsfcNo'] ?? '');
            $categoryId = $this->mapCategoryByDetailCode($detailCode);
            
            // 세부코드로 분류 실패시 기존 방식 사용
            if (!$categoryId) {
                $categoryId = $this->mapCategory($this->safeExtractString($item['inqryDiv'] ?? null));
            }
        }
        
        // 예산 금액 파싱
        $allocatedBudget = $this->parseBudget($item['presmptPrce'] ?? null);  // 추정가격 (부가세 제외)
        $vat = $this->parseBudget($item['VAT'] ?? null);                      // 부가세

        // 사업금액 = 추정가격 + 부가세 (입찰참가비 제외!)
        // 주의: asignBdgtAmt는 입찰참가비가 포함되어 있어서 사용하면 안됨!
        $totalBudget = null;
        if ($allocatedBudget && $vat) {
            $totalBudget = $allocatedBudget + $vat;
        } elseif ($allocatedBudget) {
            // VAT가 없으면 추정가격의 10%로 계산
            $vat = $allocatedBudget * 0.1;
            $totalBudget = $allocatedBudget + $vat;
        }

        // 날짜 파싱
        $startDate = $this->parseDate($item['bidNtceDt'] ?? null);
        $endDate = $this->parseDate($item['bidNtceEndDt'] ?? null);

        // 기본 데이터 배열 (기존 컬럼들)
        $tenderData = [
            'tender_no' => $bidNtceNo,
            'title' => $bidNtceNm,
            'content' => $this->extractContent($item),
            'agency' => $dminsttNm,
            'total_budget' => $totalBudget,        // 사업금액 (추정가격 + 부가세)
            'allocated_budget' => $allocatedBudget, // 추정가격 (부가세 제외)
            'vat' => $vat,                         // 부가세
            'currency' => 'KRW',
            'start_date' => $startDate,
            'end_date' => $endDate,
            'category_id' => $categoryId,
            'region' => $this->extractRegion($item),
            'status' => $this->mapStatus($item, $endDate),
            'source_url' => $this->safeExtractString($item['bidNtceUrl'] ?? '') ?: $this->generateNaraDetailUrl($bidNtceNo),
            'detail_url' => $this->safeExtractString($item['bidNtceDtlUrl'] ?? '') ?: $this->generateNaraDetailUrl($bidNtceNo),
            'collected_at' => now(),
            'metadata' => json_encode($item, JSON_UNESCAPED_UNICODE)
        ];
        
        // 전체 109개 API 필드를 데이터베이스 컬럼에 매핑
        if ($includeAllFields) {
            $additionalFields = $this->mapAllApiFields($item);
            $tenderData = array_merge($tenderData, $additionalFields);
        }
        
        return $tenderData;
    }
    
    /**
     * 입찰공고 데이터 저장 (중복 확인 후 생성/업데이트)
     * 
     * @param array $tenderData Tender 데이터
     * @return string 'created' 또는 'updated'
     */
    private function saveTenderData(array $tenderData): string
    {
        $tender = Tender::where('tender_no', $tenderData['tender_no'])->first();

        if ($tender) {
            // 기존 데이터 업데이트
            $tender->update($tenderData);
            $action = 'updated';
        } else {
            // 새 데이터 생성
            $tender = Tender::create($tenderData);
            $action = 'created';
        }

        // ✅ 자동 처리: 신규 생성 또는 업데이트된 공고에 대해 제안요청정보 파일 수집 및 상주 키워드 검사
        if ($action === 'created' || $action === 'updated') {
            try {
                // 1. 자동 제안요청정보 파일 수집
                Log::info('자동 제안요청정보 파일 수집 시작', [
                    'tender_id' => $tender->id,
                    'tender_no' => $tender->tender_no,
                    'action' => $action
                ]);

                $this->attachmentService->collectProposalFiles($tender);

                Log::info('자동 제안요청정보 파일 수집 완료', [
                    'tender_id' => $tender->id,
                    'tender_no' => $tender->tender_no
                ]);

            } catch (\Exception $e) {
                Log::warning('자동 제안요청정보 파일 수집 실패 (계속 진행)', [
                    'tender_id' => $tender->id,
                    'tender_no' => $tender->tender_no,
                    'error' => $e->getMessage()
                ]);
            }

            try {
                // 2. 자동 상주 키워드 검사
                Log::info('자동 상주 키워드 검사 시작', [
                    'tender_id' => $tender->id,
                    'tender_no' => $tender->tender_no
                ]);

                $result = $this->sangjuCheckService->checkSangjuKeyword($tender);

                if ($result['success']) {
                    Log::info('자동 상주 키워드 검사 완료', [
                        'tender_id' => $tender->id,
                        'tender_no' => $tender->tender_no,
                        'has_sangju' => $result['has_sangju'],
                        'total_files' => $result['total_files'],
                        'checked_files' => $result['checked_files'],
                        'total_occurrences' => $result['total_occurrences'] ?? 0,
                        'found_in_files_count' => count($result['found_in_files'])
                    ]);
                }

            } catch (\Exception $e) {
                Log::warning('자동 상주 키워드 검사 실패 (계속 진행)', [
                    'tender_id' => $tender->id,
                    'tender_no' => $tender->tender_no,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $action;
    }
    
    /**
     * 공고 분류 매핑
     * 
     * @param string|null $inqryDiv API의 조회구분
     * @return int|null 분류 ID
     */
    private function mapCategory(?string $inqryDiv): ?int
    {
        $categoryMap = [
            '11' => 1, // 용역
            '20' => 2, // 공사
            '30' => 3, // 물품
        ];
        
        return $categoryMap[$inqryDiv] ?? null;
    }

    /**
     * 세부업종코드로 분류 매핑 (더 정확한 방법)
     * 
     * @param string|null $detailCode 세부업종코드
     * @return int|null 분류 ID
     */
    private function mapCategoryByDetailCode(?string $detailCode): ?int
    {
        if (empty($detailCode)) {
            return null;
        }

        // 811로 시작하는 코드들은 모두 용역(IT서비스)
        if (str_starts_with($detailCode, '811')) {
            return 1; // 용역
        }

        // 추가 매핑 규칙 (필요시 확장)
        $prefixMap = [
            '80' => 2, // 공사 관련
            '90' => 3, // 물품 관련
        ];

        foreach ($prefixMap as $prefix => $categoryId) {
            if (str_starts_with($detailCode, $prefix)) {
                return $categoryId;
            }
        }

        return null; // 매핑되지 않는 경우
    }
    
    /**
     * 예산 금액 파싱 (배열 처리 포함)
     * 
     * @param mixed $priceData 가격 데이터 (문자열 또는 배열)
     * @return float|null 파싱된 금액
     */
    private function parseBudget($priceData): ?float
    {
        // 배열인 경우 (XML 파싱 시 빈 값이 배열이 될 수 있음)
        if (is_array($priceData)) {
            // 빈 배열이면 null 반환
            if (empty($priceData)) {
                return null;
            }
            // 배열의 첫 번째 값 사용
            $priceString = (string) reset($priceData);
        } else {
            $priceString = (string) $priceData;
        }
        
        if (empty($priceString)) {
            return null;
        }
        
        // 숫자가 아닌 문자 제거 후 변환
        $cleanPrice = preg_replace('/[^\d.]/', '', $priceString);
        return $cleanPrice ? (float) $cleanPrice : null;
    }
    
    /**
     * 날짜 파싱 (배열 처리 포함)
     * 
     * @param mixed $dateData 날짜 데이터 (문자열 또는 배열)
     * @return string|null 파싱된 날짜 (Y-m-d)
     */
    private function parseDate($dateData): ?string
    {
        // 배열인 경우 처리
        if (is_array($dateData)) {
            if (empty($dateData)) {
                return null;
            }
            $dateString = (string) reset($dateData);
        } else {
            $dateString = (string) $dateData;
        }
        
        if (empty($dateString)) {
            return null;
        }
        
        try {
            return Carbon::parse($dateString)->format('Y-m-d');
        } catch (Exception $e) {
            Log::warning('날짜 파싱 실패', [
                'input' => $dateString,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * 공고 상태 매핑
     * 
     * @param array $item API 데이터 항목
     * @param string|null $endDate 마감일
     * @return string 공고 상태
     */
    private function mapStatus(array $item, ?string $endDate): string
    {
        try {
            $today = Carbon::now()->startOfDay();
            
            // 1. 개찰일자 확인 (날짜 기준)
            $openingDate = $this->safeExtractString($item['opengDt'] ?? '');
            if (!empty($openingDate)) {
                $openingCarbon = Carbon::parse($openingDate)->startOfDay();
                // 개찰일이 오늘보다 과거이거나 오늘이면 마감
                if ($openingCarbon->isBefore($today) || $openingCarbon->isSameDay($today)) {
                    return 'closed';
                }
            }
            
            // 2. 입찰 마감일 확인 (날짜 기준, 당일은 D-Day)
            $bidCloseDate = $this->safeExtractString($item['bidClseDt'] ?? '');
            if (!empty($bidCloseDate)) {
                $bidCloseCarbon = Carbon::parse($bidCloseDate)->startOfDay();
                // 마감일이 오늘보다 과거일 때만 마감 (당일은 D-Day로 유지)
                if ($bidCloseCarbon->isBefore($today)) {
                    return 'closed';
                }
            }
            
            // 3. 기존 마감일 확인 (fallback, 날짜 기준)
            if ($endDate) {
                $endDateCarbon = Carbon::parse($endDate)->startOfDay();
                if ($endDateCarbon->isBefore($today)) {
                    return 'closed';
                }
            }
            
            // 4. 기본적으로 active
            return 'active';
            
        } catch (Exception $e) {
            // 날짜 파싱 오류 시 기본값 반환
            Log::warning('상태 매핑 중 날짜 파싱 오류', [
                'item_id' => $item['bidNtceNo'] ?? 'unknown',
                'error' => $e->getMessage()
            ]);
            return 'active';
        }
    }
    
    /**
     * 중복 데이터 확인
     * 
     * @param array $tenderData Tender 데이터
     * @return bool 중복 여부
     */
    private function isDuplicate(array $tenderData): bool
    {
        if (empty($tenderData['tender_no'])) {
            return false;
        }
        
        return Tender::where('tender_no', $tenderData['tender_no'])->exists();
    }
    
    /**
     * 첨부파일 다운로드 시도
     * 
     * @param array $item API 데이터 항목
     * @param string $bidNtceNo 공고번호
     * @return void
     */
    private function downloadTenderAttachments(array $item, string $bidNtceNo): void
    {
        // 첨부파일 정보 추출 (예시)
        $attachments = [];
        
        // API 응답에서 첨부파일 정보 추출
        if (isset($item['atchFileId']) && !empty($item['atchFileId'])) {
            // 나라장터 API의 첨부파일 URL 패턴
            $fileUrl = "https://www.g2b.go.kr/pt/menu/selectSubFrame.do?bidno={$bidNtceNo}&atchFileId={$item['atchFileId']}";
            $fileName = $item['atchFileName'] ?? 'attachment_' . $bidNtceNo . '.pdf';
            
            $localPath = $this->naraApi->downloadAttachment($bidNtceNo, $fileName, $fileUrl);
            
            if ($localPath) {
                Log::info('첨부파일 다운로드 성공', [
                    'bid_no' => $bidNtceNo,
                    'file_name' => $fileName,
                    'local_path' => $localPath
                ]);
            }
        }
    }
    
    /**
     * 내용 추출 (여러 필드 조합)
     * 
     * @param array $item API 데이터
     * @return string 조합된 내용
     */
    private function extractContent(array $item): string
    {
        $content = [];
        
        if (!empty($item['ntceInsttNm'])) {
            $content[] = '공고기관: ' . $item['ntceInsttNm'];
        }
        
        if (!empty($item['cntrctCnclsMthd'])) {
            $content[] = '계약방법: ' . $item['cntrctCnclsMthd'];
        }
        
        if (!empty($item['rbidOpengDt'])) {
            $content[] = '개찰일자: ' . $item['rbidOpengDt'];
        }
        
        return implode(' | ', $content);
    }
    
    /**
     * 지역 정보 추출
     * 
     * @param array $item API 데이터
     * @return string|null 지역 명
     */
    private function extractRegion(array $item): ?string
    {
        // 지역 정보 추출 로직
        if (!empty($item['ntceInsttNm'])) {
            // 공고기관명에서 지역 추출
            $region = $item['ntceInsttNm'];
            
            if (strpos($region, '서울') !== false) return '서울특별시';
            if (strpos($region, '경기') !== false) return '경기도';
            if (strpos($region, '부산') !== false) return '부산광역시';
            if (strpos($region, '대구') !== false) return '대구광역시';
            if (strpos($region, '인천') !== false) return '인천광역시';
        }
        
        return '전국';
    }
    
    /**
     * 필수 필드만 추출
     * 
     * @param array $item 전체 API 데이터
     * @return array 필수 필드만 포함한 데이터
     */
    private function getEssentialFields(array $item): array
    {
        return [
            'bidNtceNo' => $item['bidNtceNo'] ?? '',
            'bidNtceNm' => $item['bidNtceNm'] ?? '',
            'dminsttNm' => $item['dminsttNm'] ?? '',
            'ntceInsttNm' => $item['ntceInsttNm'] ?? '',
            'presmptPrce' => $item['presmptPrce'] ?? '',
            'bidNtceDt' => $item['bidNtceDt'] ?? '',
            'bidNtceEndDt' => $item['bidNtceEndDt'] ?? '',
            'inqryDiv' => $item['inqryDiv'] ?? ''
        ];
    }
    
    /**
     * 추가 필드 추출
     * 
     * @param array $item API 데이터
     * @return array 추가 필드 데이터
     */
    private function extractAdditionalFields(array $item): array
    {
        return [
            // 추가 메타데이터
            'industry_code' => $item['industryType'] ?? null,
            'product_code' => $item['productCode'] ?? null,
            'contract_method' => $item['cntrctCnclsMthd'] ?? null,
            'opening_date' => $item['rbidOpengDt'] ?? null,
            'qualification' => $item['qlfctnNm'] ?? null,
            'attachment_id' => $item['atchFileId'] ?? null,
            'attachment_name' => $item['atchFileName'] ?? null,
        ];
    }
    
    /**
     * IT서비스 분류코드 필터링으로 데이터 수집
     * 
     * @param string $startDate 시작일
     * @param string $endDate 종료일
     * @param array $classificationCodes IT서비스 분류코드 필터
     * @return array 수집 결과
     */
    public function collectTendersWithAdvancedFilters(
        string $startDate, 
        string $endDate, 
        array $classificationCodes = []
    ): array {
        // 정확한 8개 업종상세코드 매칭 (중복 제거)
        $targetCodes = [
            '81112002', // 데이터처리서비스 (빅데이터분석서비스도 동일 코드)
            '81112299', // 소프트웨어유지및지원서비스
            '81111811', // 운영위탁서비스
            '81111899', // 정보시스템유지관리서비스
            '81112199', // 인터넷지원개발서비스
            '81111598', // 패키지소프트웨어개발및도입서비스
            '81111599', // 정보시스템개발서비스
            '81151699'  // 공간정보DB구축서비스
        ];
        
        $filters = [
            'classification_codes' => [] // 빈 배열로 전달 (필터링은 processTenderItems에서)
        ];
        
        Log::info('업종코드 정확매칭 데이터 수집 시작', [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'target_codes' => $targetCodes,
            'code_count' => count($targetCodes)
        ]);
        
        return $this->collectTendersByDateRange($startDate, $endDate, $filters);
    }
    
    /**
     * 수집 통계 조회 (향상된 버전)
     * 
     * @param int $days 최근 N일간 통계
     * @return array 통계 데이터
     */
    public function getCollectionStats(int $days = 7): array
    {
        $startDate = Carbon::now()->subDays($days);
        
        return [
            'total_records' => Tender::count(),
            'recent_count' => Tender::where('collected_at', '>=', $startDate)->count(),
            'active_count' => Tender::where('status', 'active')->count(),
            'closed_count' => Tender::where('status', 'closed')->count(),
            'today_count' => Tender::whereDate('collected_at', today())->count(),
            'categories_breakdown' => Tender::selectRaw('category_id, COUNT(*) as count')
                ->groupBy('category_id')
                ->get()
                ->map(function ($item) {
                    $category = TenderCategory::find($item['category_id']);
                    return [
                        'category_name' => $category->name ?? '미분류',
                        'count' => $item['count']
                    ];
                })
                ->toArray(),
            'last_updated' => Tender::latest('collected_at')->value('collected_at')?->format('Y-m-d H:i:s'),
        ];
    }
    
    /**
     * 나라장터 공고 상세 페이지 URL 생성 (실제 접근 가능한 URL)
     * 
     * @param string $bidNtceNo 공고번호
     * @return string 나라장터 상세 페이지 URL
     */
    private function generateNaraDetailUrl(string $bidNtceNo): string
    {
        if (empty($bidNtceNo)) {
            return '#';
        }
        
        // 나라장터 공고 상세 페이지 직접 링크 (더 안정적)
        return "https://www.g2b.go.kr:8082/ep/invitation/publish/bidInfoDtl.do?bidno={$bidNtceNo}";
    }
    
    /**
     * 모든 109개 API 필드를 데이터베이스 컬럼으로 매핑
     * 
     * @param array $item API 데이터
     * @return array 모든 API 필드를 포함한 배열
     */
    private function mapAllApiFields(array $item): array
    {
        return [
            // 기본 공고 정보
            'bid_ntce_ord' => $this->safeExtractString($item['bidNtceOrd'] ?? ''),
            're_ntce_yn' => $this->safeExtractString($item['reNtceYn'] ?? ''),
            'rgst_ty_nm' => $this->safeExtractString($item['rgstTyNm'] ?? ''),
            'ntce_kind_nm' => $this->safeExtractString($item['ntceKindNm'] ?? ''),
            'intrbid_yn' => $this->safeExtractString($item['intrbidYn'] ?? ''),
            'ref_no' => $this->safeExtractString($item['refNo'] ?? ''),
            
            // 기관 정보
            'ntce_instt_cd' => $this->safeExtractString($item['ntceInsttCd'] ?? ''),
            'dminstt_cd' => $this->safeExtractString($item['dminsttCd'] ?? ''),
            
            // 입찰 방법 및 계약 정보
            'bid_methd_nm' => $this->safeExtractString($item['bidMethdNm'] ?? ''),
            'cntrct_cncls_mthd_nm' => $this->safeExtractString($item['cntrctCnclsMthdNm'] ?? ''),
            
            // 담당자 정보
            'ntce_instt_ofcl_nm' => $this->safeExtractString($item['ntceInsttOfclNm'] ?? ''),
            'ntce_instt_ofcl_tel_no' => $this->safeJsonEncode($item['ntceInsttOfclTelNo'] ?? []),
            'ntce_instt_ofcl_email_adrs' => $this->safeJsonEncode($item['ntceInsttOfclEmailAdrs'] ?? []),
            'exctv_nm' => $this->safeExtractString($item['exctvNm'] ?? ''),
            
            // 입찰 일정
            'bid_qlfct_rgst_dt' => $this->safeExtractString($item['bidQlfctRgstDt'] ?? ''),
            'bid_begin_dt' => $this->safeExtractString($item['bidBeginDt'] ?? ''),
            'bid_clse_dt' => $this->safeExtractString($item['bidClseDt'] ?? ''),
            'openg_dt' => $this->safeExtractString($item['opengDt'] ?? ''),
            'rbid_openg_dt' => $this->safeExtractString($item['rbidOpengDt'] ?? ''),
            
            // 공동수급 및 지역제한 정보
            'cmmn_spldmd_agrmnt_rcptdoc_methd' => $this->safeExtractString($item['cmmnSpldmdAgrmntRcptdocMethd'] ?? ''),
            'cmmn_spldmd_agrmnt_clse_dt' => $this->safeJsonEncode($item['cmmnSpldmdAgrmntClseDt'] ?? []),
            'cmmn_spldmd_corp_rgn_lmt_yn' => $this->safeJsonEncode($item['cmmnSpldmdCorpRgnLmtYn'] ?? []),
            
            // 입찰서류 URL (10개)
            'ntce_spec_doc_url1' => $this->safeExtractString($item['ntceSpecDocUrl1'] ?? ''),
            'ntce_spec_doc_url2' => $this->safeExtractString($item['ntceSpecDocUrl2'] ?? ''),
            'ntce_spec_doc_url3' => $this->safeJsonEncode($item['ntceSpecDocUrl3'] ?? []),
            'ntce_spec_doc_url4' => $this->safeJsonEncode($item['ntceSpecDocUrl4'] ?? []),
            'ntce_spec_doc_url5' => $this->safeJsonEncode($item['ntceSpecDocUrl5'] ?? []),
            'ntce_spec_doc_url6' => $this->safeJsonEncode($item['ntceSpecDocUrl6'] ?? []),
            'ntce_spec_doc_url7' => $this->safeJsonEncode($item['ntceSpecDocUrl7'] ?? []),
            'ntce_spec_doc_url8' => $this->safeJsonEncode($item['ntceSpecDocUrl8'] ?? []),
            'ntce_spec_doc_url9' => $this->safeJsonEncode($item['ntceSpecDocUrl9'] ?? []),
            'ntce_spec_doc_url10' => $this->safeJsonEncode($item['ntceSpecDocUrl10'] ?? []),
            
            // 입찰서류 파일명 (10개)
            'ntce_spec_file_nm1' => $this->safeExtractString($item['ntceSpecFileNm1'] ?? ''),
            'ntce_spec_file_nm2' => $this->safeExtractString($item['ntceSpecFileNm2'] ?? ''),
            'ntce_spec_file_nm3' => $this->safeJsonEncode($item['ntceSpecFileNm3'] ?? []),
            'ntce_spec_file_nm4' => $this->safeJsonEncode($item['ntceSpecFileNm4'] ?? []),
            'ntce_spec_file_nm5' => $this->safeJsonEncode($item['ntceSpecFileNm5'] ?? []),
            'ntce_spec_file_nm6' => $this->safeJsonEncode($item['ntceSpecFileNm6'] ?? []),
            'ntce_spec_file_nm7' => $this->safeJsonEncode($item['ntceSpecFileNm7'] ?? []),
            'ntce_spec_file_nm8' => $this->safeJsonEncode($item['ntceSpecFileNm8'] ?? []),
            'ntce_spec_file_nm9' => $this->safeJsonEncode($item['ntceSpecFileNm9'] ?? []),
            'ntce_spec_file_nm10' => $this->safeJsonEncode($item['ntceSpecFileNm10'] ?? []),
            
            // 재입찰 및 참가제한
            'rbid_permsn_yn' => $this->safeExtractString($item['rbidPermsnYn'] ?? ''),
            'bid_prtcpt_lmt_yn' => $this->safeExtractString($item['bidPrtcptLmtYn'] ?? ''),
            
            // 사전자격심사 관련
            'pq_appl_doc_rcpt_mthd_nm' => $this->safeJsonEncode($item['pqApplDocRcptMthdNm'] ?? []),
            'pq_appl_doc_rcpt_dt' => $this->safeJsonEncode($item['pqApplDocRcptDt'] ?? []),
            
            // 기술평가 관련
            'tp_eval_appl_mthd_nm' => $this->safeJsonEncode($item['tpEvalApplMthdNm'] ?? []),
            'tp_eval_appl_clse_dt' => $this->safeJsonEncode($item['tpEvalApplClseDt'] ?? []),
            'tp_eval_yn' => $this->safeExtractString($item['tpEvalYn'] ?? ''),
            
            // 공동계약 의무지역
            'jntcontrct_duty_rgn_nm1' => $this->safeJsonEncode($item['jntcontrctDutyRgnNm1'] ?? []),
            'jntcontrct_duty_rgn_nm2' => $this->safeJsonEncode($item['jntcontrctDutyRgnNm2'] ?? []),
            'jntcontrct_duty_rgn_nm3' => $this->safeJsonEncode($item['jntcontrctDutyRgnNm3'] ?? []),
            'rgn_duty_jntcontrct_rt' => $this->safeJsonEncode($item['rgnDutyJntcontrctRt'] ?? []),
            
            // 세부입찰 여부
            'dtls_bid_yn' => $this->safeJsonEncode($item['dtlsBidYn'] ?? []),
            
            // 예정가격 관련
            'prearng_prce_dcsn_mthd_nm' => $this->safeExtractString($item['prearngPrceDcsnMthdNm'] ?? ''),
            'tot_prdprc_num' => $this->safeExtractString($item['totPrdprcNum'] ?? ''),
            'drwt_prdprc_num' => $this->safeExtractString($item['drwtPrdprcNum'] ?? ''),
            'asign_bdgt_amt' => $this->safeExtractString($item['asignBdgtAmt'] ?? ''),
            'openg_plce' => $this->safeExtractString($item['opengPlce'] ?? ''),
            
            // 문서열람 관련
            'dcmtg_oprtn_dt' => $this->safeJsonEncode($item['dcmtgOprtnDt'] ?? []),
            'dcmtg_oprtn_plce' => $this->safeJsonEncode($item['dcmtgOprtnPlce'] ?? []),
            
            // 입찰공고 URL 정보
            'bid_ntce_dtl_url' => $this->safeExtractString($item['bidNtceDtlUrl'] ?? ''),
            'bid_ntce_url_original' => $this->safeJsonEncode($item['bidNtceUrl'] ?? []),
            
            // 참가수수료 및 보증금
            'bid_prtcpt_fee_paymnt_yn' => $this->safeJsonEncode($item['bidPrtcptFeePaymntYn'] ?? []),
            'bid_prtcpt_fee' => $this->safeExtractString($item['bidPrtcptFee'] ?? ''),
            'bid_grntymny_paymnt_yn' => $this->safeJsonEncode($item['bidGrntymnyPaymntYn'] ?? []),
            
            // 채권자 및 서비스 구분
            'crdtr_nm' => $this->safeExtractString($item['crdtrNm'] ?? ''),
            'ppsw_gnrl_srvce_yn' => $this->safeExtractString($item['ppswGnrlSrvceYn'] ?? ''),
            'srvce_div_nm' => $this->safeExtractString($item['srvceDivNm'] ?? ''),
            
            // 물품분류제한 및 제조업체
            'prdct_clsfc_lmt_yn' => $this->safeJsonEncode($item['prdctClsfcLmtYn'] ?? []),
            'mnfct_yn' => $this->safeJsonEncode($item['mnfctYn'] ?? []),
            'purchs_obj_prdct_list' => $this->safeJsonEncode($item['purchsObjPrdctList'] ?? []),
            
            // 통합공고번호 및 공동수급방법
            'unty_ntce_no' => $this->safeExtractString($item['untyNtceNo'] ?? ''),
            'cmmn_spldmd_methd_cd' => $this->safeJsonEncode($item['cmmnSpldmdMethdCd'] ?? []),
            'cmmn_spldmd_methd_nm' => $this->safeExtractString($item['cmmnSpldmdMethdNm'] ?? ''),
            
            // 표준공고문서 URL
            'std_ntce_doc_url' => $this->safeExtractString($item['stdNtceDocUrl'] ?? ''),
            
            // 기타 입찰 관련 설정
            'brffc_bidprc_permsn_yn' => $this->safeJsonEncode($item['brffcBidprcPermsnYn'] ?? []),
            'dsgnt_cmpt_yn' => $this->safeExtractString($item['dsgntCmptYn'] ?? ''),
            'arslt_cmpt_yn' => $this->safeExtractString($item['arsltCmptYn'] ?? ''),
            'pq_eval_yn' => $this->safeJsonEncode($item['pqEvalYn'] ?? []),
            'ntce_dscrpt_yn' => $this->safeJsonEncode($item['ntceDscrptYn'] ?? []),
            
            // 예비가격 재생성 방법
            'rsrvtn_prce_re_mkng_mthd_nm' => $this->safeExtractString($item['rsrvtnPrceReMkngMthdNm'] ?? ''),
            
            // 실적신청서류 접수
            'arslt_appl_doc_rcpt_mthd_nm' => $this->safeJsonEncode($item['arsltApplDocRcptMthdNm'] ?? []),
            'arslt_reqstdoc_rcpt_dt' => $this->safeJsonEncode($item['arsltReqstdocRcptDt'] ?? []),
            
            // 계획번호
            'order_plan_unty_no' => $this->safeJsonEncode($item['orderPlanUntyNo'] ?? []),
            
            // 낙찰 관련
            'sucsfbid_lwlt_rate' => $this->safeExtractString($item['sucsfbidLwltRate'] ?? ''),
            'sucsfbid_mthd_cd' => $this->safeExtractString($item['sucsfbidMthdCd'] ?? ''),
            'sucsfbid_mthd_nm' => $this->safeExtractString($item['sucsfbidMthdNm'] ?? ''),
            
            // 등록 및 변경일자
            'rgst_dt' => $this->safeExtractString($item['rgstDt'] ?? ''),
            'chg_dt' => $this->safeJsonEncode($item['chgDt'] ?? []),
            'chg_ntce_rsn' => $this->safeJsonEncode($item['chgNtceRsn'] ?? []),
            
            // 사전규격등록번호
            'bf_spec_rgst_no' => $this->safeJsonEncode($item['bfSpecRgstNo'] ?? []),
            
            // 정보화사업여부
            'info_biz_yn' => $this->safeJsonEncode($item['infoBizYn'] ?? []),
            
            // 수요기관담당자 이메일
            'dminstt_ofcl_email_adrs' => $this->safeJsonEncode($item['dminsttOfclEmailAdrs'] ?? []),
            
            // 업종제한여부
            'indstryty_lmt_yn' => $this->safeExtractString($item['indstrytyLmtYn'] ?? ''),
            
            // 부가세 관련
            'vat_amount' => $this->safeExtractString($item['VAT'] ?? ''),
            'induty_vat' => $this->safeJsonEncode($item['indutyVAT'] ?? []),
            
            // 지역제한 입찰지역판정기준
            'rgn_lmt_bid_locplc_jdgm_bss_cd' => $this->safeJsonEncode($item['rgnLmtBidLocplcJdgmBssCd'] ?? []),
            'rgn_lmt_bid_locplc_jdgm_bss_nm' => $this->safeJsonEncode($item['rgnLmtBidLocplcJdgmBssNm'] ?? []),
            
            // 조달분류 정보
            'pub_prcrmnt_lrg_clsfc_nm' => $this->safeExtractString($item['pubPrcrmntLrgClsfcNm'] ?? ''),
            'pub_prcrmnt_mid_clsfc_nm' => $this->safeExtractString($item['pubPrcrmntMidClsfcNm'] ?? ''),
            'pub_prcrmnt_clsfc_no' => $this->safeExtractString($item['pubPrcrmntClsfcNo'] ?? ''),
            'pub_prcrmnt_clsfc_nm' => $this->safeExtractString($item['pubPrcrmntClsfcNm'] ?? ''),
        ];
    }
    
    /**
     * 배열 데이터를 안전하게 JSON으로 변환
     * 
     * @param mixed $data 변환할 데이터
     * @return string JSON 문자열 또는 빈 문자열
     */
    private function safeJsonEncode($data): string
    {
        if (is_array($data)) {
            if (empty($data)) {
                return '';
            }
            return json_encode($data, JSON_UNESCAPED_UNICODE);
        }
        
        if (empty($data)) {
            return '';
        }
        
        return json_encode([$data], JSON_UNESCAPED_UNICODE);
    }
    
    /**
     * 배열일 수 있는 데이터에서 안전하게 문자열 추출
     * 
     * @param mixed $data 추출할 데이터
     * @return string 추출된 문자열
     */
    private function safeExtractString($data): string
    {
        if (is_array($data)) {
            if (empty($data)) {
                return '';
            }
            return (string) reset($data);
        }
        
        if (is_null($data)) {
            return '';
        }
        
        return (string) $data;
    }
    
    /**
     * 텍스트에 IT 관련 키워드가 포함되어 있는지 확인
     * 
     * @param string $text 검사할 텍스트
     * @return bool IT 키워드 포함 여부
     */
    private function containsItKeywords(string $text): bool
    {
        $itKeywords = [
            // 소프트웨어 관련
            '소프트웨어', 'SW', 'software',
            '시스템', 'system', '솔루션',
            '프로그램', 'program',
            
            // 개발 관련
            '개발', '구축', '개선',
            '웹', 'web', '앱', 'app',
            '데이터베이스', 'DB', 'database',
            
            // IT 서비스
            '정보시스템', '전산', '컴퓨터',
            '네트워크', 'network',
            '서버', 'server',
            '클라우드', 'cloud',
            '디지털서비스', 'digital',
            
            // 데이터 관련
            '데이터', 'data',
            '빅데이터', 'big data',
            'AI', '인공지능',
            
            // 보안 관련
            '보안', 'security',
            '방화벽', 'firewall',
            
            // 기타 IT 용어
            '플랫폼', 'platform',
            '인터페이스', 'interface',
            '유지관리', '운영위탁',
            '커스터마이징', 'customizing'
        ];
        
        $text = strtolower($text);
        
        foreach ($itKeywords as $keyword) {
            if (strpos($text, strtolower($keyword)) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * 업종코드 확인이 필요한지 판단
     * 
     * @param array $tenderData 공고 데이터
     * @return bool 확인 필요 여부
     */
    private function shouldVerifyIndustryCode(array $tenderData): bool
    {
        // IT 관련 분류코드이면서 detail_url이 있는 경우만 확인
        $classification = $tenderData['pub_prcrmnt_clsfc_no'] ?? '';
        $detailUrl = $tenderData['detail_url'] ?? '';
        
        return !empty($classification) && 
               strpos($classification, '811') === 0 && 
               !empty($detailUrl);
    }
    
    /**
     * 웹 크롤링으로 업종코드 1468 확인
     * 
     * @param string $detailUrl 상세 페이지 URL
     * @param string $bidNtceNo 공고번호
     * @return bool 업종코드 1468 여부
     */
    private function verifyIndustryCodeByCrawling(string $detailUrl, string $bidNtceNo): bool
    {
        try {
            // cURL을 사용하여 상세페이지 HTML 가져오기
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $detailUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false
            ]);
            
            $html = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode !== 200 || empty($html)) {
                Log::warning("크롤링 실패: {$bidNtceNo} - HTTP {$httpCode}");
                return false; // 크롤링 실패 시 제외
            }
            
            // HTML에서 업종코드 1468 찾기
            // 나라장터 페이지 구조에 따라 패턴 조정 필요
            $patterns = [
                '/업종.*?1468/i',
                '/1468.*?소프트웨어/i',
                '/소프트웨어사업자.*?1468/i',
                '/컴퓨터관련서비스.*?1468/i',
                '/1468.*?컴퓨터관련서비스/i'
            ];
            
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $html)) {
                    Log::info("업종코드 1468 발견: {$bidNtceNo} - 패턴: {$pattern}");
                    return true;
                }
            }
            
            // 추가 패턴: 테이블이나 dd/dt 구조에서 찾기
            if (preg_match('/(?:업종|산업).*?(?:코드|번호).*?1468/i', $html) ||
                preg_match('/1468.*?(?:업종|산업)/i', $html)) {
                Log::info("업종코드 1468 발견 (추가패턴): {$bidNtceNo}");
                return true;
            }
            
            Log::debug("업종코드 1468 없음: {$bidNtceNo}");
            return false;
            
        } catch (Exception $e) {
            Log::error("크롤링 오류: {$bidNtceNo} - {$e->getMessage()}");
            return false; // 오류 시 제외
        }
    }
    
    /**
     * 마감된 공고 자동 삭제 (크롤링 기능의 일부)
     * 
     * @param int $expiredDays 마감 후 N일 지난 공고 삭제 (기본: 7일)
     * @param bool $dryRun 실제 삭제하지 않고 확인만 (기본: false)
     * @return array 삭제 결과 통계
     */
    public function cleanupExpiredTenders(int $expiredDays = 7, bool $dryRun = false): array
    {
        $stats = [
            'total_expired' => 0,
            'deleted_count' => 0,
            'errors' => 0,
            'start_time' => now(),
            'end_time' => null,
            'deleted_tender_nos' => []
        ];
        
        Log::info('마감된 공고 정리 시작', [
            'expired_days' => $expiredDays,
            'dry_run' => $dryRun
        ]);
        
        try {
            // 마감 기준일 계산 (마감일 + N일)
            $expiredDate = Carbon::now()->subDays($expiredDays)->format('Y-m-d');
            
            // 마감된 공고 조회
            $expiredTenders = Tender::where(function($query) use ($expiredDate) {
                // end_date가 있고 만료된 경우
                $query->where('end_date', '<=', $expiredDate)
                      ->whereNotNull('end_date');
            })
            ->orWhere(function($query) use ($expiredDate) {
                // bid_clse_dt (입찰마감일시)가 있고 만료된 경우
                $query->whereRaw("DATE(bid_clse_dt) <= ?", [$expiredDate])
                      ->whereNotNull('bid_clse_dt');
            })
            ->orWhere(function($query) use ($expiredDate) {
                // openg_dt (개찰일시)가 있고 만료된 경우  
                $query->whereRaw("DATE(openg_dt) <= ?", [$expiredDate])
                      ->whereNotNull('openg_dt');
            })
            ->get();
            
            $stats['total_expired'] = $expiredTenders->count();
            
            if ($stats['total_expired'] === 0) {
                Log::info('삭제할 마감된 공고가 없습니다.');
                $stats['end_time'] = now();
                return $stats;
            }
            
            Log::info("발견된 마감 공고: {$stats['total_expired']}개");
            
            foreach ($expiredTenders as $tender) {
                try {
                    // 마감 상태 재확인
                    if ($this->isActuallyExpired($tender, $expiredDays)) {
                        $tenderNo = $tender->tender_no;
                        $stats['deleted_tender_nos'][] = $tenderNo;
                        
                        if (!$dryRun) {
                            // 관련 데이터도 함께 삭제
                            $this->deleteTenderWithRelatedData($tender);
                            Log::info("마감 공고 삭제 완료: {$tenderNo}");
                        } else {
                            Log::info("마감 공고 삭제 대상 (dry run): {$tenderNo}");
                        }
                        
                        $stats['deleted_count']++;
                    }
                } catch (Exception $e) {
                    Log::error('공고 삭제 오류', [
                        'tender_no' => $tender->tender_no,
                        'error' => $e->getMessage()
                    ]);
                    $stats['errors']++;
                }
            }
            
            $action = $dryRun ? '검사' : '삭제';
            Log::info("마감된 공고 {$action} 완료", [
                'total_expired' => $stats['total_expired'],
                'deleted_count' => $stats['deleted_count'],
                'errors' => $stats['errors']
            ]);
            
        } catch (Exception $e) {
            Log::error('마감 공고 정리 중 오류 발생', [
                'error' => $e->getMessage()
            ]);
            $stats['errors']++;
        }
        
        $stats['end_time'] = now();
        return $stats;
    }
    
    /**
     * 공고가 실제로 마감되었는지 확인
     * 
     * @param Tender $tender 확인할 공고
     * @param int $expiredDays 마감 후 기간
     * @return bool 실제 마감 여부
     */
    private function isActuallyExpired(Tender $tender, int $expiredDays): bool
    {
        $now = Carbon::now();
        $expiredThreshold = $now->copy()->subDays($expiredDays);
        
        // 1. end_date 확인 (가장 기본적인 마감일)
        if ($tender->end_date) {
            $endDate = Carbon::parse($tender->end_date);
            if ($endDate->lte($expiredThreshold)) {
                Log::debug("마감 확인 (end_date): {$tender->tender_no} - {$tender->end_date}");
                return true;
            }
        }
        
        // 2. 입찰마감일시 확인 (bid_clse_dt)
        if ($tender->bid_clse_dt) {
            $closeDate = Carbon::parse($tender->bid_clse_dt);
            if ($closeDate->lte($expiredThreshold)) {
                Log::debug("마감 확인 (bid_clse_dt): {$tender->tender_no} - {$tender->bid_clse_dt}");
                return true;
            }
        }
        
        // 3. 개찰일시 확인 (opng_dt) - 일반적으로 최종 마감
        if ($tender->opng_dt) {
            $openingDate = Carbon::parse($tender->opng_dt);
            if ($openingDate->lte($expiredThreshold)) {
                Log::debug("마감 확인 (opng_dt): {$tender->tender_no} - {$tender->opng_dt}");
                return true;
            }
        }
        
        // 4. 상태가 명시적으로 마감인 경우
        if (in_array($tender->status, ['closed', 'expired', 'cancelled'])) {
            Log::debug("마감 확인 (status): {$tender->tender_no} - {$tender->status}");
            return true;
        }
        
        return false;
    }
    
    /**
     * 공고와 관련된 모든 데이터 삭제
     * 
     * @param Tender $tender 삭제할 공고
     * @return void
     */
    private function deleteTenderWithRelatedData(Tender $tender): void
    {
        DB::transaction(function () use ($tender) {
            // 1. 분석 결과 삭제
            $tender->analyses()->delete();
            
            // 2. 제안서 삭제  
            $tender->proposals()->delete();
            
            // 3. 첨부파일 삭제
            $attachments = $tender->attachments()->get();
            foreach ($attachments as $attachment) {
                // 실제 파일 삭제
                if ($attachment->file_path && file_exists(storage_path('app/' . $attachment->file_path))) {
                    unlink(storage_path('app/' . $attachment->file_path));
                }
                $attachment->delete();
            }
            
            // 4. 공고 자체 삭제
            $tender->delete();
        });
    }
    
    /**
     * 크롤링과 함께 자동 정리 실행
     * 
     * @param string $startDate 수집 시작일
     * @param string $endDate 수집 종료일  
     * @param array $filters 추가 필터
     * @param int $cleanupDays 정리할 마감 기간
     * @return array 수집 및 정리 결과
     */
    public function collectAndCleanup(string $startDate, string $endDate, array $filters = [], int $cleanupDays = 7): array
    {
        $results = [
            'collection' => [],
            'cleanup' => [],
            'total_time' => 0
        ];
        
        $startTime = microtime(true);
        
        // 1. 데이터 수집
        Log::info('크롤링 + 정리 모드 시작');
        $results['collection'] = $this->collectTendersByDateRange($startDate, $endDate, $filters);
        
        // 2. 마감된 공고 정리
        $results['cleanup'] = $this->cleanupExpiredTenders($cleanupDays, false);
        
        $results['total_time'] = round(microtime(true) - $startTime, 2);
        
        Log::info('크롤링 + 정리 모드 완료', [
            'collection_new' => $results['collection']['new_records'] ?? 0,
            'cleanup_deleted' => $results['cleanup']['deleted_count'] ?? 0,
            'total_time' => $results['total_time']
        ]);
        
        return $results;
    }
}
// [END nara:tender_collector]