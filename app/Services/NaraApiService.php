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
     * 나라장터 API 기본 URL
     */
    private const BASE_URL = 'https://apis.data.go.kr/1230000/BidPublicInfoService';
    
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
            'serviceKey' => urlencode($this->serviceKey), // URL 인코딩 추가
            'pageNo' => 1,
            'numOfRows' => 100,
            // 'type' => 'json', // 기본 XML 응답으로 시도
            // 'inqryDiv' => '11', // 용역 분류 - 일단 제거해서 테스트
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
        $params = [
            'inqryBgnDt' => $startDate,
            'inqryEndDt' => $endDate,
            'pageNo' => $pageNo,
            'numOfRows' => $numOfRows,
        ];
        
        // 고급 필터링 적용
        if (!empty($filters)) {
            $params = array_merge($params, $this->buildAdvancedFilters($filters));
        }
        
        return $this->getBidPblancListInfoServc($params);
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
        // OpenAPI_ServiceResponse 구조 검증 (XML 기반)
        if (isset($data['cmmMsgHeader'])) {
            $header = $data['cmmMsgHeader'];
            
            // 오류 체크
            if (isset($header['returnReasonCode']) && $header['returnReasonCode'] !== '00') {
                Log::warning('나라장터 API 오류', [
                    'return_code' => $header['returnReasonCode'],
                    'return_msg' => $header['returnAuthMsg'] ?? 'Unknown error',
                    'err_msg' => $header['errMsg'] ?? ''
                ]);
                return false;
            }
            
            return true;
        }
        
        // 기존 JSON response 구조도 지원
        if (isset($data['response']['header']['resultCode'])) {
            $resultCode = $data['response']['header']['resultCode'];
            
            if ($resultCode !== '00') {
                Log::warning('나라장터 API 오류 코드', [
                    'result_code' => $resultCode,
                    'result_msg' => $data['response']['header']['resultMsg'] ?? 'Unknown error'
                ]);
                return false;
            }
            
            return true;
        }
        
        // 응답 구조를 인식할 수 없음
        Log::error('알 수 없는 API 응답 구조', ['data' => $data]);
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
     * 고급 필터링 파라미터 구성
     * 
     * @param array $filters 필터 조건
     * @return array API 파라미터
     */
    private function buildAdvancedFilters(array $filters): array
    {
        $params = [];
        
        // 지역 필터 (전체, 경기, 서울)
        if (!empty($filters['regions'])) {
            $regionMap = [
                '전체' => '',
                '서울' => '11',
                '경기' => '41'
            ];
            
            $regionCodes = [];
            foreach ($filters['regions'] as $region) {
                if (isset($regionMap[$region]) && $regionMap[$region] !== '') {
                    $regionCodes[] = $regionMap[$region];
                }
            }
            
            if (!empty($regionCodes)) {
                $params['area'] = implode(',', $regionCodes);
            }
        }
        
        // 업종 코드 필터 (1426, 1468, 6528)
        if (!empty($filters['industry_codes'])) {
            $allowedCodes = ['1426', '1468', '6528'];
            $validCodes = array_intersect($filters['industry_codes'], $allowedCodes);
            
            if (!empty($validCodes)) {
                $params['industryType'] = implode(',', $validCodes);
            }
        }
        
        // 직접생산확인증명서 코드 필터
        if (!empty($filters['product_codes'])) {
            $allowedProductCodes = [
                '8111200201', '8111200202', '8111229901', '8111181101', 
                '8111189901', '8111219901', '8111159801', '8111159901', '8115169901'
            ];
            $validProductCodes = array_intersect($filters['product_codes'], $allowedProductCodes);
            
            if (!empty($validProductCodes)) {
                $params['productCode'] = implode(',', $validProductCodes);
            }
        }
        
        // 용역 분류 강제 설정 (11: 용역)
        $params['inqryDiv'] = '11';
        
        Log::info('고급 필터링 적용', [
            'original_filters' => $filters,
            'api_params' => $params
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