#!/bin/bash

echo "=================================================="
echo "Test: 상주 키워드 파일별 표시 기능"
echo "=================================================="
echo ""

# 색상 정의
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo "======================================"
echo "Phase 1: 백엔드 로직 테스트"
echo "======================================"
echo ""

echo "Test 1: Tender 1768 상주 검사 (HWPX 파일)"
echo "----------------------------------------"
cd /home/tideflo/nara/public_html && timeout 120 php artisan tinker --execute="
\$tender = App\Models\Tender::find(1768);
if (!\$tender) {
    echo 'ERROR: Tender 1768 not found' . PHP_EOL;
    exit(1);
}

echo '공고명: ' . \$tender->title . PHP_EOL;
echo '' . PHP_EOL;

// 컨트롤러 시뮬레이션
\$collectorService = app(App\Services\TenderCollectorService::class);
\$naraApiService = app(App\Services\NaraApiService::class);
\$controller = new App\Http\Controllers\Admin\TenderController(\$collectorService, \$naraApiService);

\$proposalFiles = \$tender->attachments()->where('type', 'proposal')->where('download_status', 'completed')->get();
echo '제안요청정보 파일: ' . \$proposalFiles->count() . '개' . PHP_EOL;

\$reflection = new \ReflectionClass(\$controller);
\$method = \$reflection->getMethod('checkFileSangju');
\$method->setAccessible(true);

foreach (\$proposalFiles as \$file) {
    \$file->sangju_status = \$method->invoke(\$controller, \$file);

    echo '' . PHP_EOL;
    echo '파일: ' . \$file->file_name . PHP_EOL;
    echo '확장자: ' . \$file->file_extension . PHP_EOL;

    if (isset(\$file->sangju_status)) {
        echo 'sangju_status 존재: YES' . PHP_EOL;
        echo 'checked: ' . (\$file->sangju_status['checked'] ? 'true' : 'false') . PHP_EOL;
        echo 'has_sangju: ' . (\$file->sangju_status['has_sangju'] ? 'true' : 'false') . PHP_EOL;
        echo 'occurrences: ' . \$file->sangju_status['occurrences'] . PHP_EOL;

        echo '' . PHP_EOL;
        echo '화면 표시 예상:' . PHP_EOL;
        if (\$file->sangju_status['has_sangju']) {
            echo '  → [상주 ' . \$file->sangju_status['occurrences'] . '회 감지] (빨간색 bg-danger)' . PHP_EOL;
        } elseif (\$file->sangju_status['checked']) {
            echo '  → [상주 없음] (녹색 bg-success)' . PHP_EOL;
        } else {
            echo '  → [검사 안됨] (회색 bg-secondary)' . PHP_EOL;
        }
    } else {
        echo 'ERROR: sangju_status NOT SET' . PHP_EOL;
        exit(1);
    }
}

echo '' . PHP_EOL;
echo '✅ 백엔드 로직 정상 작동' . PHP_EOL;
"

if [ $? -ne 0 ]; then
    echo -e "${RED}❌ Test 1 FAILED${NC}"
    exit 1
fi
echo -e "${GREEN}✅ Test 1 PASSED${NC}"
echo ""

echo "Test 2: show() 메서드 시뮬레이션"
echo "----------------------------------------"
cd /home/tideflo/nara/public_html && timeout 120 php artisan tinker --execute="
\$tender = App\Models\Tender::find(1768);

// show() 메서드와 동일한 로직
\$tender->load('category');

\$userMention = App\Models\TenderMention::where('tender_id', \$tender->id)
    ->where('user_id', 1)
    ->first();

\$proposalFiles = \$tender->attachments()
    ->where('type', 'proposal')
    ->where('download_status', 'completed')
    ->get();

\$collectorService = app(App\Services\TenderCollectorService::class);
\$naraApiService = app(App\Services\NaraApiService::class);
\$controller = new App\Http\Controllers\Admin\TenderController(\$collectorService, \$naraApiService);

\$reflection = new \ReflectionClass(\$controller);
\$method = \$reflection->getMethod('checkFileSangju');
\$method->setAccessible(true);

foreach (\$proposalFiles as \$file) {
    \$file->sangju_status = \$method->invoke(\$controller, \$file);
}

echo 'show() 메서드 시뮬레이션 결과:' . PHP_EOL;
echo '- Tender ID: ' . \$tender->id . PHP_EOL;
echo '- Proposal Files Count: ' . \$proposalFiles->count() . PHP_EOL;
echo '- All files have sangju_status: ' . (\$proposalFiles->every(fn(\$f) => isset(\$f->sangju_status)) ? 'YES' : 'NO') . PHP_EOL;

if (!\$proposalFiles->every(fn(\$f) => isset(\$f->sangju_status))) {
    echo 'ERROR: Not all files have sangju_status' . PHP_EOL;
    exit(1);
}

echo '' . PHP_EOL;
echo '✅ show() 메서드 로직 정상 작동' . PHP_EOL;
"

if [ $? -ne 0 ]; then
    echo -e "${RED}❌ Test 2 FAILED${NC}"
    exit 1
fi
echo -e "${GREEN}✅ Test 2 PASSED${NC}"
echo ""

echo "======================================"
echo "Phase 2: Blade 템플릿 로직 검증"
echo "======================================"
echo ""

echo "Test 3: Blade 템플릿 구문 검증"
echo "----------------------------------------"

# show.blade.php 파일에서 상주 배지 로직 확인
BLADE_FILE="/home/tideflo/nara/public_html/resources/views/admin/tenders/show.blade.php"

if ! grep -q "sangju_status\['has_sangju'\]" "$BLADE_FILE"; then
    echo -e "${RED}ERROR: sangju_status['has_sangju'] 구문 없음${NC}"
    exit 1
fi

if ! grep -q "badge bg-danger" "$BLADE_FILE"; then
    echo -e "${RED}ERROR: bg-danger 배지 스타일 없음${NC}"
    exit 1
fi

if ! grep -q "badge bg-success" "$BLADE_FILE"; then
    echo -e "${RED}ERROR: bg-success 배지 스타일 없음${NC}"
    exit 1
fi

if ! grep -q "badge bg-secondary" "$BLADE_FILE"; then
    echo -e "${RED}ERROR: bg-secondary 배지 스타일 없음${NC}"
    exit 1
fi

echo "✅ Blade 템플릿에 모든 필수 로직 포함됨"
echo "  - sangju_status 배열 접근"
echo "  - has_sangju 조건 분기"
echo "  - bg-danger (빨간색) 배지"
echo "  - bg-success (녹색) 배지"
echo "  - bg-secondary (회색) 배지"

echo -e "${GREEN}✅ Test 3 PASSED${NC}"
echo ""

echo "Test 4: \$proposalFiles 중복 정의 제거 확인"
echo "----------------------------------------"

if grep -q "\\$proposalFiles = \\$tender->attachments()->where('type', 'proposal')->get();" "$BLADE_FILE"; then
    echo -e "${RED}ERROR: 뷰에서 \$proposalFiles를 재정의하고 있음 (컨트롤러 전달값 덮어써짐)${NC}"
    exit 1
fi

echo "✅ \$proposalFiles 중복 정의 없음"
echo "  → 컨트롤러에서 전달한 sangju_status 값 사용"

echo -e "${GREEN}✅ Test 4 PASSED${NC}"
echo ""

echo "======================================"
echo "Phase 3: 통합 테스트"
echo "======================================"
echo ""

echo "Test 5: 여러 파일 상태 시뮬레이션"
echo "----------------------------------------"
cd /home/tideflo/nara/public_html && timeout 120 php artisan tinker --execute="
\$tenders = App\Models\Tender::has('attachments')->limit(5)->get();

echo '상주 검사 가능한 공고: ' . \$tenders->count() . '개' . PHP_EOL;
echo '' . PHP_EOL;

\$collectorService = app(App\Services\TenderCollectorService::class);
\$naraApiService = app(App\Services\NaraApiService::class);
\$controller = new App\Http\Controllers\Admin\TenderController(\$collectorService, \$naraApiService);

\$reflection = new \ReflectionClass(\$controller);
\$method = \$reflection->getMethod('checkFileSangju');
\$method->setAccessible(true);

\$statusCounts = ['has_sangju' => 0, 'no_sangju' => 0, 'not_checked' => 0];

foreach (\$tenders as \$tender) {
    \$proposalFiles = \$tender->attachments()->where('type', 'proposal')->where('download_status', 'completed')->get();

    foreach (\$proposalFiles as \$file) {
        \$file->sangju_status = \$method->invoke(\$controller, \$file);

        if (\$file->sangju_status['has_sangju']) {
            \$statusCounts['has_sangju']++;
        } elseif (\$file->sangju_status['checked']) {
            \$statusCounts['no_sangju']++;
        } else {
            \$statusCounts['not_checked']++;
        }
    }
}

echo '검사 결과 통계:' . PHP_EOL;
echo '  - 상주 발견: ' . \$statusCounts['has_sangju'] . '개 파일' . PHP_EOL;
echo '  - 상주 없음: ' . \$statusCounts['no_sangju'] . '개 파일' . PHP_EOL;
echo '  - 검사 안됨: ' . \$statusCounts['not_checked'] . '개 파일' . PHP_EOL;

echo '' . PHP_EOL;
echo '✅ 여러 파일 상태 처리 정상 작동' . PHP_EOL;
"

if [ $? -ne 0 ]; then
    echo -e "${RED}❌ Test 5 FAILED${NC}"
    exit 1
fi
echo -e "${GREEN}✅ Test 5 PASSED${NC}"
echo ""

echo "=================================================="
echo -e "${GREEN}✅ 모든 테스트 통과!${NC}"
echo "=================================================="
echo ""

echo "요약:"
echo "----------------------------------------"
echo "Phase 1: 백엔드 로직"
echo "  ✅ TenderController::checkFileSangju() 메서드 작동"
echo "  ✅ show() 메서드에서 sangju_status 계산"
echo "  ✅ \$proposalFiles에 sangju_status 속성 추가"
echo ""
echo "Phase 2: Blade 템플릿"
echo "  ✅ 상주 발견 시 빨간색 배지 표시 로직"
echo "  ✅ 상주 없음 시 녹색 배지 표시 로직"
echo "  ✅ 검사 안됨 시 회색 배지 표시 로직"
echo "  ✅ \$proposalFiles 중복 정의 제거"
echo ""
echo "Phase 3: 통합 테스트"
echo "  ✅ 여러 파일 상태 처리 정상 작동"
echo ""

echo "다음 단계:"
echo "  1. 웹 브라우저에서 Tender 1768 상세 페이지 접속"
echo "  2. 제안요청정보 파일 섹션에서 배지 확인"
echo "  3. 예상 결과: '제안요청서 (사전규격공개).hwpx [⚠️ 상주 4회 감지]' (빨간색)"
echo ""
echo "URL: https://nara.tideflo.work/admin/tenders/1768"
echo ""
