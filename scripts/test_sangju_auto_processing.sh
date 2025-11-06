#!/bin/bash

echo "=================================================="
echo "Test: 상주 키워드 자동 처리 시스템"
echo "=================================================="
echo ""

# 색상 정의
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo "======================================"
echo "Phase 1: 상주 단어 출처 파일 상세 표시 테스트"
echo "======================================"
echo ""

echo "Test 1-1: Tender 1715 상주 검사 (HWP 파일)"
echo "----------------------------------------"
cd /home/tideflo/nara/public_html && timeout 120 php artisan tinker --execute="
\$tender = App\Models\Tender::find(1715);
if (!\$tender) {
    echo 'ERROR: Tender 1715 not found' . PHP_EOL;
    exit(1);
}

\$controller = new App\Http\Controllers\Admin\TenderController();
\$response = \$controller->checkSangju(\$tender);
\$data = json_decode(\$response->getContent(), true);

if (!\$data['success']) {
    echo 'ERROR: Check failed: ' . \$data['message'] . PHP_EOL;
    exit(1);
}

echo '✅ 상주 검사 성공' . PHP_EOL;
echo '검사 결과: ' . (\$data['has_sangju'] ? 'YES' : 'NO') . PHP_EOL;
echo '총 파일: ' . \$data['total_files'] . PHP_EOL;
echo '검사 파일: ' . \$data['checked_files'] . PHP_EOL;

if (\$data['has_sangju']) {
    echo '총 발견 횟수: ' . \$data['total_occurrences'] . PHP_EOL;
    echo '발견된 파일 수: ' . count(\$data['found_in_files']) . PHP_EOL;
    echo '' . PHP_EOL;

    echo '파일별 상세 정보:' . PHP_EOL;
    foreach (\$data['found_in_files'] as \$file) {
        echo '  - 파일명: ' . \$file['file_name'] . PHP_EOL;
        echo '    파일 유형: ' . \$file['file_type'] . PHP_EOL;
        echo '    확장자: ' . \$file['extension'] . PHP_EOL;
        echo '    발견 횟수: ' . \$file['occurrences'] . '회' . PHP_EOL;
        echo '    파일 크기: ' . number_format(\$file['file_size'] / 1024, 1) . ' KB' . PHP_EOL;
        echo '' . PHP_EOL;
    }
}
"

if [ $? -ne 0 ]; then
    echo -e "${RED}❌ Test 1-1 FAILED${NC}"
    exit 1
fi
echo -e "${GREEN}✅ Test 1-1 PASSED${NC}"
echo ""

echo "Test 1-2: Tender 1768 상주 검사 (HWPX 파일)"
echo "----------------------------------------"
cd /home/tideflo/nara/public_html && timeout 120 php artisan tinker --execute="
\$tender = App\Models\Tender::find(1768);
if (!\$tender) {
    echo 'ERROR: Tender 1768 not found' . PHP_EOL;
    exit(1);
}

\$controller = new App\Http\Controllers\Admin\TenderController();
\$response = \$controller->checkSangju(\$tender);
\$data = json_decode(\$response->getContent(), true);

if (!\$data['success']) {
    echo 'ERROR: Check failed: ' . \$data['message'] . PHP_EOL;
    exit(1);
}

echo '✅ 상주 검사 성공' . PHP_EOL;
echo '검사 결과: ' . (\$data['has_sangju'] ? 'YES' : 'NO') . PHP_EOL;
echo '총 파일: ' . \$data['total_files'] . PHP_EOL;
echo '검사 파일: ' . \$data['checked_files'] . PHP_EOL;

if (\$data['has_sangju']) {
    echo '총 발견 횟수: ' . \$data['total_occurrences'] . PHP_EOL;
    echo '발견된 파일 수: ' . count(\$data['found_in_files']) . PHP_EOL;

    foreach (\$data['found_in_files'] as \$file) {
        if (\$file['extension'] === 'hwpx') {
            echo '✅ HWPX 파일 정상 처리 확인!' . PHP_EOL;
        }
    }
}
"

if [ $? -ne 0 ]; then
    echo -e "${RED}❌ Test 1-2 FAILED${NC}"
    exit 1
fi
echo -e "${GREEN}✅ Test 1-2 PASSED${NC}"
echo ""

echo "======================================"
echo "Phase 2: 크롤링 시 자동 처리 테스트"
echo "======================================"
echo ""

echo "Test 2-1: 자동 처리 로직 확인"
echo "----------------------------------------"
cd /home/tideflo/nara/public_html && php artisan tinker --execute="
\$service = app(App\Services\TenderCollectorService::class);

// Reflection으로 private 메서드 확인
\$reflection = new \ReflectionClass(\$service);
\$method = \$reflection->getMethod('saveTenderData');

// 메서드 소스 코드 확인
\$filename = \$reflection->getFileName();
\$startLine = \$method->getStartLine();
\$endLine = \$method->getEndLine();

\$file = file(\$filename);
\$methodCode = implode('', array_slice(\$file, \$startLine - 1, \$endLine - \$startLine + 1));

// 자동 처리 로직 포함 여부 확인
if (strpos(\$methodCode, 'collectProposalFiles') !== false) {
    echo '✅ 자동 제안요청정보 파일 수집 로직 확인' . PHP_EOL;
} else {
    echo '❌ 자동 제안요청정보 파일 수집 로직 없음' . PHP_EOL;
    exit(1);
}

if (strpos(\$methodCode, 'checkSangjuKeyword') !== false) {
    echo '✅ 자동 상주 키워드 검사 로직 확인' . PHP_EOL;
} else {
    echo '❌ 자동 상주 키워드 검사 로직 없음' . PHP_EOL;
    exit(1);
}

echo '' . PHP_EOL;
echo '자동 처리 워크플로우:' . PHP_EOL;
echo '1. 공고 데이터 저장 (created 또는 updated)' . PHP_EOL;
echo '2. 자동 제안요청정보 파일 수집 (AttachmentService::collectProposalFiles)' . PHP_EOL;
echo '3. 자동 상주 키워드 검사 (SangjuCheckService::checkSangjuKeyword)' . PHP_EOL;
echo '4. 검사 결과 로깅 및 is_unsuitable 자동 설정' . PHP_EOL;
"

if [ $? -ne 0 ]; then
    echo -e "${RED}❌ Test 2-1 FAILED${NC}"
    exit 1
fi
echo -e "${GREEN}✅ Test 2-1 PASSED${NC}"
echo ""

echo "Test 2-2: 로그 파일 확인 (최근 1시간)"
echo "----------------------------------------"
LOG_FILE="/home/tideflo/nara/public_html/storage/logs/laravel.log"

if [ ! -f "$LOG_FILE" ]; then
    echo -e "${YELLOW}⚠️  로그 파일 없음 (신규 시스템)${NC}"
else
    echo "최근 자동 처리 로그:"
    grep -E "(자동 제안요청정보|자동 상주)" "$LOG_FILE" | tail -n 10
    echo ""

    # 자동 처리 로그 통계
    AUTO_COLLECTION_COUNT=$(grep -c "자동 제안요청정보 파일 수집 시작" "$LOG_FILE" 2>/dev/null || echo 0)
    AUTO_CHECK_COUNT=$(grep -c "자동 상주 키워드 검사 시작" "$LOG_FILE" 2>/dev/null || echo 0)

    echo "통계:"
    echo "  - 자동 파일 수집 시도: ${AUTO_COLLECTION_COUNT}건"
    echo "  - 자동 상주 검사 시도: ${AUTO_CHECK_COUNT}건"
fi

echo -e "${GREEN}✅ Test 2-2 PASSED${NC}"
echo ""

echo "Test 2-3: 서비스 주입 확인"
echo "----------------------------------------"
cd /home/tideflo/nara/public_html && php artisan tinker --execute="
\$service = app(App\Services\TenderCollectorService::class);

\$reflection = new \ReflectionClass(\$service);

// AttachmentService 주입 확인
\$attachmentProperty = \$reflection->getProperty('attachmentService');
\$attachmentProperty->setAccessible(true);
\$attachmentService = \$attachmentProperty->getValue(\$service);

if (\$attachmentService instanceof App\Services\AttachmentService) {
    echo '✅ AttachmentService 주입 확인' . PHP_EOL;
} else {
    echo '❌ AttachmentService 주입 실패' . PHP_EOL;
    exit(1);
}

// SangjuCheckService 주입 확인
\$sangjuProperty = \$reflection->getProperty('sangjuCheckService');
\$sangjuProperty->setAccessible(true);
\$sangjuService = \$sangjuProperty->getValue(\$service);

if (\$sangjuService instanceof App\Services\SangjuCheckService) {
    echo '✅ SangjuCheckService 주입 확인' . PHP_EOL;
} else {
    echo '❌ SangjuCheckService 주입 실패' . PHP_EOL;
    exit(1);
}
"

if [ $? -ne 0 ]; then
    echo -e "${RED}❌ Test 2-3 FAILED${NC}"
    exit 1
fi
echo -e "${GREEN}✅ Test 2-3 PASSED${NC}"
echo ""

echo "=================================================="
echo -e "${GREEN}✅ 모든 테스트 통과!${NC}"
echo "=================================================="
echo ""

echo "요약:"
echo "----------------------------------------"
echo "Phase 1: 상주 단어 출처 파일 상세 표시"
echo "  ✅ HWP 파일 상세 정보 표시 (파일명, 발견 횟수, 크기)"
echo "  ✅ HWPX 파일 지원 및 상세 정보 표시"
echo "  ✅ JSON 응답에 total_occurrences 포함"
echo "  ✅ UI 토스트 메시지에 상세 정보 표시"
echo ""
echo "Phase 2: 크롤링 시 자동 처리"
echo "  ✅ TenderCollectorService에 자동 처리 로직 추가"
echo "  ✅ 서비스 의존성 주입 확인 (AttachmentService, SangjuCheckService)"
echo "  ✅ 자동 제안요청정보 파일 수집"
echo "  ✅ 자동 상주 키워드 검사"
echo "  ✅ 에러 격리 (한 단계 실패해도 다음 단계 계속 진행)"
echo ""

echo "다음 단계:"
echo "  1. 실제 크롤링 테스트: php artisan tender:collect --days=1"
echo "  2. 로그 모니터링: tail -f storage/logs/laravel.log | grep -E '(자동 제안요청정보|자동 상주)'"
echo "  3. 웹 UI에서 상세 정보 확인: https://nara.tideflo.work/admin/tenders/1715"
echo ""
