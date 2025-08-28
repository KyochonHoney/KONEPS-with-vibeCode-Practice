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
     * 기간별 입찰공고 데이터 수집
     * 
     * @param string $startDate 시작일 (YYYY-MM-DD)
     * @param string $endDate 종료일 (YYYY-MM-DD)
     * @return array 수집 결과 통계
     */
    public function collectTendersByDateRange(string $startDate, string $endDate): array
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
                    100
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
                    $result = $this->processTenderItems($items);
                    $stats['total_fetched'] += count($items);
                    $stats['new_records'] += $result['new_records'];
                    $stats['updated_records'] += $result['updated_records'];
                    $stats['errors'] += $result['errors'];
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
        return $this->collectTendersByDateRange($today, $today);
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
        return $this->collectTendersByDateRange($startDate, $endDate);
    }
    
    /**
     * API에서 받은 입찰공고 데이터 처리
     * 
     * @param array $items API 응답의 items 배열
     * @return array 처리 결과 통계
     */
    private function processTenderItems(array $items): array
    {
        $stats = [
            'new_records' => 0,
            'updated_records' => 0,
            'errors' => 0
        ];
        
        foreach ($items as $item) {
            try {
                $tenderData = $this->mapApiDataToTender($item);
                
                if ($this->saveTenderData($tenderData)) {
                    $stats['new_records']++;
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
     * API 데이터를 Tender 모델 형식으로 변환
     * 
     * @param array $item API 응답 데이터 항목
     * @return array Tender 모델용 데이터
     */
    private function mapApiDataToTender(array $item): array
    {
        // 공고 분류 매핑
        $categoryId = $this->mapCategory($item['inqryDiv'] ?? null);
        
        // 예산 금액 파싱
        $budget = $this->parseBudget($item['presmptPrce'] ?? null);
        
        // 날짜 파싱
        $startDate = $this->parseDate($item['bidNtceDt'] ?? null);
        $endDate = $this->parseDate($item['bidNtceEndDt'] ?? null);
        
        return [
            'tender_no' => $item['bidNtceNo'] ?? '',
            'title' => $item['bidNtceNm'] ?? '',
            'content' => $item['ntceInsttNm'] ?? '',
            'agency' => $item['dminsttNm'] ?? '',
            'budget' => $budget,
            'currency' => 'KRW',
            'start_date' => $startDate,
            'end_date' => $endDate,
            'category_id' => $categoryId,
            'region' => $item['rgstDt'] ?? null,
            'status' => $this->mapStatus($item, $endDate),
            'source_url' => $this->buildSourceUrl($item['bidNtceNo'] ?? ''),
            'collected_at' => now(),
            'metadata' => json_encode($item, JSON_UNESCAPED_UNICODE)
        ];
    }
    
    /**
     * 입찰공고 데이터 저장 (중복 확인 후 생성/업데이트)
     * 
     * @param array $tenderData Tender 데이터
     * @return bool true: 새로 생성, false: 기존 데이터 업데이트
     */
    private function saveTenderData(array $tenderData): bool
    {
        $tender = Tender::where('tender_no', $tenderData['tender_no'])->first();
        
        if ($tender) {
            // 기존 데이터 업데이트
            $tender->update($tenderData);
            return false;
        } else {
            // 새 데이터 생성
            Tender::create($tenderData);
            return true;
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
     * 나라장터 상세 페이지 URL 생성
     * 
     * @param string $bidNtceNo 공고번호
     * @return string 상세 페이지 URL
     */
    private function buildSourceUrl(string $bidNtceNo): string
    {
        if (empty($bidNtceNo)) {
            return '';
        }
        
        return "https://www.g2b.go.kr/pt/menu/selectSubFrame.do?framesrc=/pt/menu/frameTgong.do?url=https://www.g2b.go.kr:8082/ep/invitation/publish/bidInfoDtl.do?bidno={$bidNtceNo}";
    }
    
    /**
     * 수집 통계 조회
     * 
     * @param int $days 최근 N일간 통계
     * @return array 통계 데이터
     */
    public function getCollectionStats(int $days = 7): array
    {
        $startDate = Carbon::now()->subDays($days);
        
        return [
            'total_tenders' => Tender::count(),
            'recent_tenders' => Tender::where('collected_at', '>=', $startDate)->count(),
            'active_tenders' => Tender::where('status', 'active')->count(),
            'closed_tenders' => Tender::where('status', 'closed')->count(),
            'categories_breakdown' => Tender::selectRaw('category_id, COUNT(*) as count')
                ->groupBy('category_id')
                ->get()
                ->toArray(),
            'last_collection' => Tender::latest('collected_at')->value('collected_at'),
        ];
    }
}
// [END nara:tender_collector]