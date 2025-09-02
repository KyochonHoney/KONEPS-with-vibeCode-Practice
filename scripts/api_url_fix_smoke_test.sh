#!/bin/bash

# [BEGIN nara:api_url_smoke_test]
echo "=== 나라장터 API URL 수정 스모크 테스트 ==="
echo "실행 시간: $(date)"
echo ""

cd /home/tideflo/nara/public_html

# 1. NaraApiService 클래스에서 BASE_URL 수정 확인
echo "1. NaraApiService BASE_URL 수정 확인"
if grep -q "http://apis.data.go.kr/1230000/ad/BidPublicInfoService" app/Services/NaraApiService.php; then
    echo "✅ BASE_URL이 새로운 주소로 수정됨"
    grep -n "BASE_URL" app/Services/NaraApiService.php | head -1
else
    echo "❌ BASE_URL 수정이 적용되지 않음"
    exit 1
fi

echo ""

# 2. Laravel 설정 캐시 클리어 확인
echo "2. Laravel 설정 캐시 상태 확인"
if ! ls bootstrap/cache/config.php > /dev/null 2>&1; then
    echo "✅ 설정 캐시가 클리어됨"
else
    echo "⚠️ 설정 캐시가 존재함 (정상)"
fi

echo ""

# 3. API 연결 테스트 실행
echo "3. API 연결 테스트 실행"
API_TEST_OUTPUT=$(php artisan nara:test-filtering 2>&1)
if echo "$API_TEST_OUTPUT" | grep -q "API 연결: API 연결 성공"; then
    echo "✅ API 연결 테스트 성공"
else
    echo "❌ API 연결 테스트 실패"
    echo "출력: $API_TEST_OUTPUT" | head -5
fi

echo ""

# 4. 실제 API 호출 응답 체크
echo "4. 실제 API 응답 체크"
if php test_simple_api_call.php 2>&1 | grep -q "HTTP ROUTING ERROR"; then
    echo "⚠️ HTTP ROUTING ERROR 발생 - URL이 아직 올바르지 않음"
else
    echo "✅ HTTP ROUTING ERROR 없음"
fi

echo ""

# 5. 로그에서 새로운 URL 사용 확인
echo "5. 로그에서 새로운 URL 사용 확인"
if tail -20 storage/logs/laravel.log | grep -q "ad/BidPublicInfoService"; then
    echo "✅ 로그에서 새로운 URL 사용 확인됨"
else
    echo "❌ 로그에서 새로운 URL 사용 확인되지 않음"
fi

echo ""

# 6. 테스트 결과 요약
echo "6. 테스트 결과 요약"
echo "- URL 수정: 완료"
echo "- 설정 적용: 완료" 
echo "- API 테스트: HTTP ROUTING ERROR로 URL 미완성"
echo "- 추가 작업 필요: 올바른 API 엔드포인트 확인"

echo ""
echo "=== 스모크 테스트 완료 ==="
# [END nara:api_url_smoke_test]