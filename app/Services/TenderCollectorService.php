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
    
    public function __construct(NaraApiService $naraApi)
    {
        $this->naraApi = $naraApi;
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
                    $totalCount = $response['response']['body']['totalCount'] ?? 0;
                    $totalPages = $totalCount > 0 ? ceil($totalCount / 100) : 1;
                    
                    Log::info('수집 대상 데이터 확인', [
                        'total_count' => $totalCount,
                        'total_pages' => $totalPages
                    ]);
                }
                
                $items = $response['response']['body']['items'] ?? [];
                
                if (!empty($items)) {
                    // 전체 데이터 수집을 위해 모든 필드 포함
                    $result = $this->processTenderItems($items, true);
                    $stats['total_fetched'] += count($items);
                    $stats['new_records'] += $result['new_records'];
                    $stats['updated_records'] += $result['updated_records'];
                    $stats['errors'] += $result['errors'];
                    $stats['duplicate_skipped'] = ($stats['duplicate_skipped'] ?? 0) + $result['duplicate_skipped'];
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
     * @return array 처리 결과 통계
     */
    private function processTenderItems(array $items, bool $includeAllFields = false): array
    {
        $stats = [
            'new_records' => 0,
            'updated_records' => 0,
            'duplicate_skipped' => 0,
            'errors' => 0
        ];
        
        foreach ($items as $item) {
            try {
                $tenderData = $this->mapApiDataToTender($item, $includeAllFields);
                
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
     * API 데이터를 Tender 모델 형식으로 변환 (전체 필드 포함)
     * 
     * @param array $item API 응답 데이터 항목
     * @param bool $includeAllFields 모든 필드 포함 여부
     * @return array Tender 모델용 데이터
     */
    private function mapApiDataToTender(array $item, bool $includeAllFields = false): array
    {
        // 공고 분류 매핑
        $categoryId = $this->mapCategory($item['inqryDiv'] ?? null);
        
        // 예산 금액 파싱
        $budget = $this->parseBudget($item['presmptPrce'] ?? null);
        
        // 날짜 파싱
        $startDate = $this->parseDate($item['bidNtceDt'] ?? null);
        $endDate = $this->parseDate($item['bidNtceEndDt'] ?? null);
        
        // 기본 데이터 배열
        $tenderData = [
            'tender_no' => $item['bidNtceNo'] ?? '',
            'title' => $item['bidNtceNm'] ?? '',
            'content' => $this->extractContent($item),
            'agency' => $item['dminsttNm'] ?? '',
            'budget' => $budget,
            'currency' => 'KRW',
            'start_date' => $startDate,
            'end_date' => $endDate,
            'category_id' => $categoryId,
            'region' => $this->extractRegion($item),
            'status' => $this->mapStatus($item, $endDate),
            'source_url' => $item['bidNtceUrl'] ?? $this->generateNaraDetailUrl($item['bidNtceNo'] ?? ''),
            'detail_url' => $item['bidNtceUrl'] ?? $this->generateNaraDetailUrl($item['bidNtceNo'] ?? ''),
            'collected_at' => now(),
            'metadata' => json_encode($includeAllFields ? $item : $this->getEssentialFields($item), JSON_UNESCAPED_UNICODE)
        ];
        
        // 전체 필드 포함 시 추가 데이터
        if ($includeAllFields) {
            $tenderData = array_merge($tenderData, $this->extractAdditionalFields($item));
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
            return 'updated';
        } else {
            // 새 데이터 생성
            Tender::create($tenderData);
            return 'created';
        }
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
     * 예산 금액 파싱
     * 
     * @param string|null $priceString 가격 문자열
     * @return float|null 파싱된 금액
     */
    private function parseBudget(?string $priceString): ?float
    {
        if (empty($priceString)) {
            return null;
        }
        
        // 숫자가 아닌 문자 제거 후 변환
        $cleanPrice = preg_replace('/[^\d.]/', '', $priceString);
        return $cleanPrice ? (float) $cleanPrice : null;
    }
    
    /**
     * 날짜 파싱
     * 
     * @param string|null $dateString 날짜 문자열 (YYYY-MM-DD HH:mm)
     * @return string|null 파싱된 날짜 (Y-m-d)
     */
    private function parseDate(?string $dateString): ?string
    {
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
        // 마감일이 지났으면 closed
        if ($endDate && Carbon::parse($endDate)->isPast()) {
            return 'closed';
        }
        
        // 기본적으로 active
        return 'active';
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
     * 고급 필터링으로 데이터 수집
     * 
     * @param string $startDate 시작일
     * @param string $endDate 종료일
     * @param array $regions 지역 필터
     * @param array $industryCodes 업종 코드 필터
     * @param array $productCodes 직접생산확인증명서 코드 필터
     * @return array 수집 결과
     */
    public function collectTendersWithAdvancedFilters(
        string $startDate, 
        string $endDate, 
        array $regions = ['전체', '서울', '경기'], 
        array $industryCodes = ['1426', '1468', '6528'],
        array $productCodes = [
            '8111200201', '8111200202', '8111229901', '8111181101', 
            '8111189901', '8111219901', '8111159801', '8111159901', '8115169901'
        ]
    ): array {
        $filters = [
            'regions' => $regions,
            'industry_codes' => $industryCodes,
            'product_codes' => $productCodes
        ];
        
        Log::info('고급 필터링 데이터 수집 시작', [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'filters' => $filters
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
}
// [END nara:tender_collector]