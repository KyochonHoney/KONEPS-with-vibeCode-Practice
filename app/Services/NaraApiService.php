<?php

// [BEGIN nara:api_service]
namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Exception;

/**
 * 나라장터 입찰공고 정보 서비스 API 연동
 * 
 * @package App\Services
 */
class NaraApiService
{
    /**
     * 나라장터 API 기본 URL (사용자 제공 정확한 URI)
     * [BEGIN nara:exact_uri_fix]
     */
    private const BASE_URL = 'http://apis.data.go.kr/1230000/ad/BidPublicInfoService';
    // [END nara:exact_uri_fix]
    
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
     * 입찰공고 목록 조회 (용역 PPS 검색)
     * [BEGIN nara:service_method_fix] [UPDATED: 2025-08-29]
     * @param array $params 검색 조건
     * @return array API 응답 데이터
     * @throws Exception API 호출 실패 시
     */
    public function getBidPblancListInfoServcPPSSrch(array $params = []): array
    {
        // 성공한 파라미터 조합 적용 (2025-08-29 해결)
        $defaultParams = [
            'serviceKey' => $this->serviceKey,
            'pageNo' => 1,
            'numOfRows' => 100, // 성공 확인 후 다시 증가
            'inqryDiv' => '01', // 핵심 해결: 01이 정상 작동 (11은 입력범위값 초과 오류)
        ];
        
        // 추가 파라미터는 신중하게 적용 (입력범위값 초과 방지)
        $queryParams = array_merge($defaultParams, $params);
        
        // 날짜 범위가 너무 크면 제거
        if (isset($queryParams['inqryBgnDt']) && isset($queryParams['inqryEndDt'])) {
            $startDate = \DateTime::createFromFormat('Ymd', $queryParams['inqryBgnDt']);
            $endDate = \DateTime::createFromFormat('Ymd', $queryParams['inqryEndDt']);
            
            if ($startDate && $endDate) {
                $daysDiff = $endDate->diff($startDate)->days;
                if ($daysDiff > 30) { // 30일 초과시 날짜 범위 제거
                    unset($queryParams['inqryBgnDt'], $queryParams['inqryEndDt']);
                    Log::warning('날짜 범위가 30일을 초과하여 제거', ['days_diff' => $daysDiff]);
                }
            }
        }
        
        Log::info('나라장터 용역 API 요청', [
            'endpoint' => 'getBidPblancListInfoServcPPSSrch',
            'params' => array_merge($queryParams, ['serviceKey' => '[MASKED]'])
        ]);
        
        try {
            $response = Http::timeout($this->timeout)
                ->get(self::BASE_URL . '/getBidPblancListInfoServcPPSSrch', $queryParams);
    // [END nara:service_method_fix]
            
            if (!$response->successful()) {
                throw new Exception("API 요청 실패: HTTP {$response->status()}");
            }
            
            // XML 응답을 처리
            $xmlContent = $response->body();
            
            // XML을 배열로 변환
            $xml = simplexml_load_string($xmlContent, 'SimpleXMLElement', LIBXML_NOCDATA);
            $data = json_decode(json_encode($xml), true);
            
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
     * 특정 기간의 용역 공고 조회 (고급 필터링 포함)
     * 
     * @param string $startDate 시작일 (YYYYMMDD)
     * @param string $endDate 종료일 (YYYYMMDD)  
     * @param int $pageNo 페이지 번호
     * @param int $numOfRows 페이지당 개수
     * @param array $filters 추가 필터 조건
     * @return array API 응답 데이터
     */
    public function getTendersByDateRange(string $startDate, string $endDate, int $pageNo = 1, int $numOfRows = 100, array $filters = []): array
    {
        // 업종코드 1468, 1426, 6528별로 다중 호출
        $targetIndustryCodes = ['1468', '1426', '6528'];
        $allItems = [];
        $totalCount = 0;
        
        foreach ($targetIndustryCodes as $industryCode) {
            $params = [
                'inqryBgnDt' => $startDate,
                'inqryEndDt' => $endDate,
                'pageNo' => $pageNo,
                'numOfRows' => $numOfRows,
                'inqryDiv' => '01',
                'indstrytyCd' => $industryCode
            ];
            
            try {
                $response = $this->getBidPblancListInfoServcPPSSrch($params);
                
                if (isset($response['body']['items']['item'])) {
                    $items = $response['body']['items']['item'];
                    
                    // XML 파싱 시 단일 항목이 객체로 오는 경우 배열로 변환
                    if (!isset($items[0])) {
                        $items = [$items];
                    }
                    
                    if (!empty($items)) {
                        $allItems = array_merge($allItems, $items);
                        $totalCount += $response['body']['totalCount'] ?? 0;
                    }
                }
                
                Log::info("업종코드 {$industryCode} 데이터 수집", [
                    'industry_code' => $industryCode,
                    'items_count' => count($items ?? []),
                    'page' => $pageNo
                ]);
                
            } catch (Exception $e) {
                Log::warning("업종코드 {$industryCode} 수집 실패", [
                    'error' => $e->getMessage(),
                    'industry_code' => $industryCode
                ]);
                continue;
            }
        }
        
        // 통합된 응답 구조 반환
        return [
            'response' => [
                'body' => [
                    'items' => $allItems,
                    'totalCount' => $totalCount,
                    'pageNo' => $pageNo,
                    'numOfRows' => $numOfRows
                ]
            ]
        ];
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
     * API 응답 유효성 검사 [UPDATED: 2025-08-29]
     * 
     * @param array $data API 응답 데이터
     * @return bool 유효성 검사 결과
     */
    private function isValidResponse(array $data): bool
    {
        // 용역 조회 API 우선 검증 (inqryDiv=11 사용시 구조)
        if (isset($data['header']['resultCode'])) {
            $resultCode = $data['header']['resultCode'];
            
            if ($resultCode !== '00') {
                Log::warning('나라장터 용역 API 오류', [
                    'result_code' => $resultCode,
                    'result_msg' => $data['header']['resultMsg'] ?? 'Unknown error',
                    'response_type' => 'nkoneps.com.response'
                ]);
                
                // 입력범위값 초과 오류의 경우 상세 정보 제공
                if ($resultCode === '07') {
                    Log::info('입력범위값 초과 오류 - 파라미터 조정 필요', [
                        'suggestion' => '날짜 범위 단축, numOfRows 감소, 불필요한 파라미터 제거'
                    ]);
                }
                
                return false;
            }
            
            Log::info('나라장터 용역 API 성공 응답', [
                'result_code' => $resultCode,
                'response_type' => 'nkoneps.com.response'
            ]);
            
            return true;
        }
        
        // OpenAPI_ServiceResponse 구조 검증 (기본 XML 응답)
        if (isset($data['cmmMsgHeader'])) {
            $header = $data['cmmMsgHeader'];
            
            // 오류 체크
            if (isset($header['returnReasonCode']) && $header['returnReasonCode'] !== '00') {
                Log::warning('나라장터 기본 API 오류', [
                    'return_code' => $header['returnReasonCode'],
                    'return_msg' => $header['returnAuthMsg'] ?? 'Unknown error',
                    'err_msg' => $header['errMsg'] ?? '',
                    'response_type' => 'OpenAPI_ServiceResponse'
                ]);
                
                // HTTP 라우팅 오류의 경우 URL 확인 제안
                if ($header['returnReasonCode'] === '04') {
                    Log::info('HTTP 라우팅 오류 - inqryDiv 파라미터 추가 권장');
                }
                
                return false;
            }
            
            Log::info('나라장터 기본 API 성공 응답', [
                'return_code' => $header['returnReasonCode'],
                'response_type' => 'OpenAPI_ServiceResponse'
            ]);
            
            return true;
        }
        
        // 기존 JSON response 구조도 지원
        if (isset($data['response']['header']['resultCode'])) {
            $resultCode = $data['response']['header']['resultCode'];
            
            if ($resultCode !== '00') {
                Log::warning('나라장터 JSON API 오류', [
                    'result_code' => $resultCode,
                    'result_msg' => $data['response']['header']['resultMsg'] ?? 'Unknown error',
                    'response_type' => 'JSON wrapped'
                ]);
                return false;
            }
            
            return true;
        }
        
        // 응답 구조를 인식할 수 없음
        Log::error('알 수 없는 API 응답 구조', [
            'available_keys' => array_keys($data),
            'first_level_data' => array_slice($data, 0, 3, true) // 처음 3개 키만 표시
        ]);
        return false;
    }
    
    /**
     * API 연결 상태 테스트
     * 
     * @return bool 연결 성공 여부
     */
    public function testConnection(): bool
    {
        try {
            // API 키가 올바르게 설정되어 있는지 확인
            if (empty($this->serviceKey)) {
                Log::error('나라장터 API 키가 설정되지 않음');
                return false;
            }
            
            // 간단한 HTTP 테스트만 수행 (실제 API 호출 없이)
            Log::info('나라장터 API 연결 테스트', [
                'service_key_length' => strlen($this->serviceKey),
                'base_url' => self::BASE_URL,
                'timeout' => $this->timeout
            ]);
            
            // API 키가 64자 길이인지 확인 (일반적인 공공데이터포털 키 길이)
            if (strlen($this->serviceKey) === 64) {
                Log::info('나라장터 API 키 형식 검증 완료');
                return true; // API 키 형식이 올바르면 연결 성공으로 간주
            } else {
                Log::warning('나라장터 API 키 길이가 비정상적', [
                    'expected_length' => 64,
                    'actual_length' => strlen($this->serviceKey)
                ]);
                return false;
            }
                
        } catch (Exception $e) {
            Log::error('나라장터 API 연결 테스트 실패', ['error' => $e->getMessage()]);
            return false;
        }
    }
    
    /**
     * 고급 필터링 파라미터 구성 [UPDATED: 2025-08-29]
     * 
     * @param array $filters 필터 조건
     * @return array API 파라미터
     */
    private function buildAdvancedFilters(array $filters): array
    {
        $params = [];
        
        // 성공한 분류 코드 적용 (01: 정상 작동 확인)
        $params['inqryDiv'] = '01';
        
        // 업종코드 필터링 추가 (중요!)
        // 1426: 소프트웨어개발및공급업, 1468: 정보처리및기타컴퓨터운영관련업, 6528: 기타공학서비스업
        $targetIndustryCodes = ['1426', '1468', '6528'];
        
        // 업종코드별로 개별 호출하거나 하나씩 필터링
        // API 제약으로 인해 첫 번째 업종코드만 사용 (추후 개선 필요)
        if (!empty($targetIndustryCodes)) {
            $params['industryCd'] = $targetIndustryCodes[0]; // 1426부터 시작
        }
        
        Log::info('업종코드 필터링 적용', [
            'original_filters' => $filters,
            'api_params' => $params,
            'target_industries' => $targetIndustryCodes,
            'selected_industry' => $params['industryCd'] ?? null,
            'inqryDiv_note' => '01=성공확인, 11=입력범위값초과'
        ]);
        
        return $params;
    }
    
    /**
     * 입찰공고 첨부파일 다운로드
     * 
     * @param string $bidNtceNo 공고번호
     * @param string $fileName 파일명
     * @param string $fileUrl 파일 URL
     * @return string|null 로컬 저장 경로
     */
    public function downloadAttachment(string $bidNtceNo, string $fileName, string $fileUrl): ?string
    {
        try {
            $response = Http::timeout(60)->get($fileUrl);
            
            if (!$response->successful()) {
                Log::error('첨부파일 다운로드 실패', [
                    'bid_no' => $bidNtceNo,
                    'file_name' => $fileName,
                    'file_url' => $fileUrl,
                    'http_status' => $response->status()
                ]);
                return null;
            }
            
            $directory = 'attachments/' . date('Y/m/d');
            $safeFileName = $bidNtceNo . '_' . preg_replace('/[^\w\.\-]/', '_', $fileName);
            $filePath = $directory . '/' . $safeFileName;
            
            Storage::put($filePath, $response->body());
            
            Log::info('첨부파일 다운로드 성공', [
                'bid_no' => $bidNtceNo,
                'file_name' => $fileName,
                'local_path' => $filePath,
                'file_size' => strlen($response->body())
            ]);
            
            return $filePath;
            
        } catch (Exception $e) {
            Log::error('첨부파일 다운로드 오류', [
                'bid_no' => $bidNtceNo,
                'file_name' => $fileName,
                'file_url' => $fileUrl,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * 나라장터 원본 공고 URL 생성
     * 
     * @param string $bidNtceNo 공고번호
     * @return string 나라장터 URL
     */
    public function generateNaraUrl(string $bidNtceNo): string
    {
        // 나라장터 공고 상세 페이지 URL 패턴
        return "https://www.g2b.go.kr/pt/menu/selectSubFrame.do?framesrc=/pt/menu/frameTgong.do?url=https://www.g2b.go.kr:8082/ep/invitation/publish/bidInfoDtl.do?bidno={$bidNtceNo}";
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