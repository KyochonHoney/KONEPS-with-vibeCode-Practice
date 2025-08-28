<?php

// [BEGIN nara:api_service]
namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * 나라장터 입찰공고 정보 서비스 API 연동
 * 
 * @package App\Services
 */
class NaraApiService
{
    /**
     * 나라장터 API 기본 URL
     */
    private const BASE_URL = 'https://apis.data.go.kr/1230000/BidPublicInfoService04';
    
    /**
     * API 서비스 키
     */
    private string $serviceKey;
    
    /**
     * HTTP 클라이언트 타임아웃 (초)
     */
    private int $timeout;
    
    public function __construct()
    {
        $this->serviceKey = config('services.nara.api_key');
        $this->timeout = config('services.nara.timeout', 30);
    }
    
    /**
     * 입찰공고 목록 조회
     * 
     * @param array $params 검색 조건
     * @return array API 응답 데이터
     * @throws Exception API 호출 실패 시
     */
    public function getBidPblancListInfoServc(array $params = []): array
    {
        // 기본 파라미터 설정
        $defaultParams = [
            'serviceKey' => $this->serviceKey,
            'pageNo' => 1,
            'numOfRows' => 100,
            'type' => 'json',
            'inqryDiv' => '11', // 용역 분류
        ];
        
        $queryParams = array_merge($defaultParams, $params);
        
        Log::info('나라장터 API 요청', [
            'endpoint' => 'getBidPblancListInfoServc',
            'params' => array_merge($queryParams, ['serviceKey' => '[MASKED]'])
        ]);
        
        try {
            $response = Http::timeout($this->timeout)
                ->get(self::BASE_URL . '/getBidPblancListInfoServc', $queryParams);
            
            if (!$response->successful()) {
                throw new Exception("API 요청 실패: HTTP {$response->status()}");
            }
            
            $data = $response->json();
            
            if (!$this->isValidResponse($data)) {
                throw new Exception('API 응답 형식 오류: ' . json_encode($data, JSON_UNESCAPED_UNICODE));
            }
            
            Log::info('나라장터 API 응답 성공', [
                'total_count' => $data['response']['body']['totalCount'] ?? 0,
                'page_no' => $queryParams['pageNo'],
                'num_of_rows' => $queryParams['numOfRows']
            ]);
            
            return $data;
            
        } catch (Exception $e) {
            Log::error('나라장터 API 호출 오류', [
                'error' => $e->getMessage(),
                'params' => array_merge($queryParams, ['serviceKey' => '[MASKED]'])
            ]);
            
            throw $e;
        }
    }
    
    /**
     * 특정 기간의 용역 공고 조회
     * 
     * @param string $startDate 시작일 (YYYYMMDD)
     * @param string $endDate 종료일 (YYYYMMDD)  
     * @param int $pageNo 페이지 번호
     * @param int $numOfRows 페이지당 개수
     * @return array API 응답 데이터
     */
    public function getTendersByDateRange(string $startDate, string $endDate, int $pageNo = 1, int $numOfRows = 100): array
    {
        return $this->getBidPblancListInfoServc([
            'inqryBgnDt' => $startDate,
            'inqryEndDt' => $endDate,
            'pageNo' => $pageNo,
            'numOfRows' => $numOfRows,
        ]);
    }
    
    /**
     * 오늘의 용역 공고 조회
     * 
     * @param int $pageNo 페이지 번호
     * @param int $numOfRows 페이지당 개수
     * @return array API 응답 데이터
     */
    public function getTodayTenders(int $pageNo = 1, int $numOfRows = 100): array
    {
        $today = date('Ymd');
        return $this->getTendersByDateRange($today, $today, $pageNo, $numOfRows);
    }
    
    /**
     * 최근 7일간의 용역 공고 조회
     * 
     * @param int $pageNo 페이지 번호
     * @param int $numOfRows 페이지당 개수
     * @return array API 응답 데이터
     */
    public function getRecentTenders(int $pageNo = 1, int $numOfRows = 100): array
    {
        $endDate = date('Ymd');
        $startDate = date('Ymd', strtotime('-7 days'));
        return $this->getTendersByDateRange($startDate, $endDate, $pageNo, $numOfRows);
    }
    
    /**
     * API 응답 유효성 검사
     * 
     * @param array $data API 응답 데이터
     * @return bool 유효성 검사 결과
     */
    private function isValidResponse(array $data): bool
    {
        // 기본 응답 구조 검증
        if (!isset($data['response']['header']['resultCode'])) {
            return false;
        }
        
        $resultCode = $data['response']['header']['resultCode'];
        
        // 성공 코드가 아닌 경우
        if ($resultCode !== '00') {
            Log::warning('나라장터 API 오류 코드', [
                'result_code' => $resultCode,
                'result_msg' => $data['response']['header']['resultMsg'] ?? 'Unknown error'
            ]);
            return false;
        }
        
        // body 구조 검증 (데이터가 없는 경우도 정상)
        if (!isset($data['response']['body'])) {
            return false;
        }
        
        return true;
    }
    
    /**
     * API 연결 상태 테스트
     * 
     * @return bool 연결 성공 여부
     */
    public function testConnection(): bool
    {
        try {
            $response = $this->getBidPblancListInfoServc([
                'pageNo' => 1,
                'numOfRows' => 1
            ]);
            
            return isset($response['response']['header']['resultCode']) 
                && $response['response']['header']['resultCode'] === '00';
                
        } catch (Exception $e) {
            Log::error('나라장터 API 연결 테스트 실패', ['error' => $e->getMessage()]);
            return false;
        }
    }
    
    /**
     * 남은 API 호출 가능 횟수 확인 (실제 API에서 제공하는 경우)
     * 
     * @return int|null 남은 호출 횟수 (제공되지 않으면 null)
     */
    public function getRemainingQuota(): ?int
    {
        // 실제 API 스펙에 따라 구현
        // 현재는 기본값으로 null 반환
        return null;
    }
}
// [END nara:api_service]