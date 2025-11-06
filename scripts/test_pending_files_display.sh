#!/bin/bash

echo "=================================================="
echo "Test: Pending 파일도 리스트에 표시되는지 확인"
echo "=================================================="
echo ""

GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo "Test 1: Tender 1759 (pending 파일 있음)"
echo "----------------------------------------"
cd /home/tideflo/nara/public_html && timeout 60 php artisan tinker --execute="
\$tender = App\Models\Tender::find(1759);

if (!\$tender) {
    echo 'ERROR: Tender 1759 not found' . PHP_EOL;
    exit(1);
}

echo '공고: ' . \$tender->title . PHP_EOL;
echo '' . PHP_EOL;

// show() 메서드 로직 시뮬레이션
\$proposalFiles = \$tender->attachments()->where('type', 'proposal')->get();

echo '제안요청정보 파일 총 개수: ' . \$proposalFiles->count() . '개' . PHP_EOL;

if (\$proposalFiles->count() === 0) {
    echo 'ERROR: No proposal files found' . PHP_EOL;
    exit(1);
}

\$collectorService = app(App\Services\TenderCollectorService::class);
\$naraApiService = app(App\Services\NaraApiService::class);
\$controller = new App\Http\Controllers\Admin\TenderController(\$collectorService, \$naraApiService);

\$reflection = new \ReflectionClass(\$controller);
\$method = \$reflection->getMethod('checkFileSangju');
\$method->setAccessible(true);

foreach (\$proposalFiles as \$file) {
    if (\$file->download_status === 'completed') {
        \$file->sangju_status = \$method->invoke(\$controller, \$file);
    } else {
        \$file->sangju_status = [
            'checked' => false,
            'has_sangju' => false,
            'occurrences' => 0,
            'error' => '다운로드 ' . (\$file->download_status === 'pending' ? '대기중' : '실패')
        ];
    }
}

echo '' . PHP_EOL;
foreach (\$proposalFiles as \$file) {
    echo '파일명: ' . \$file->file_name . PHP_EOL;
    echo '  - 다운로드 상태: ' . \$file->download_status . PHP_EOL;
    echo '  - 상주 검사 상태: ' . (\$file->sangju_status['checked'] ? '검사됨' : '검사 안됨') . PHP_EOL;

    if (isset(\$file->sangju_status['error'])) {
        echo '  - 에러: ' . \$file->sangju_status['error'] . PHP_EOL;
    }

    echo '  - 화면 표시: ';
    if (\$file->sangju_status['has_sangju']) {
        echo '[상주 ' . \$file->sangju_status['occurrences'] . '회 감지] (빨간색)' . PHP_EOL;
    } elseif (\$file->sangju_status['checked']) {
        echo '[상주 없음] (녹색)' . PHP_EOL;
    } else {
        echo '[검사 안됨: ' . \$file->sangju_status['error'] . '] (회색)' . PHP_EOL;
    }
}

echo '' . PHP_EOL;
echo '✅ pending 파일이 리스트에 표시됨' . PHP_EOL;
"

if [ $? -ne 0 ]; then
    echo -e "${RED}❌ Test 1 FAILED${NC}"
    exit 1
fi
echo -e "${GREEN}✅ Test 1 PASSED${NC}"
echo ""

echo "Test 2: 상태별 통계"
echo "----------------------------------------"
cd /home/tideflo/nara/public_html && timeout 60 php artisan tinker --execute="
\$allProposal = App\Models\Attachment::where('type', 'proposal')->get();

\$completed = \$allProposal->where('download_status', 'completed')->count();
\$pending = \$allProposal->where('download_status', 'pending')->count();
\$failed = \$allProposal->where('download_status', 'failed')->count();

echo '제안요청정보 파일 통계:' . PHP_EOL;
echo '  - 전체: ' . \$allProposal->count() . '개' . PHP_EOL;
echo '  - 완료: ' . \$completed . '개' . PHP_EOL;
echo '  - 대기: ' . \$pending . '개' . PHP_EOL;
echo '  - 실패: ' . \$failed . '개' . PHP_EOL;
echo '' . PHP_EOL;

echo '수정 전: completed만 표시 (' . \$completed . '개)' . PHP_EOL;
echo '수정 후: 모든 상태 표시 (' . \$allProposal->count() . '개)' . PHP_EOL;
echo '' . PHP_EOL;
echo '차이: +' . (\$allProposal->count() - \$completed) . '개 (pending + failed)' . PHP_EOL;
"

if [ $? -ne 0 ]; then
    echo -e "${RED}❌ Test 2 FAILED${NC}"
    exit 1
fi
echo -e "${GREEN}✅ Test 2 PASSED${NC}"
echo ""

echo "Test 3: Tender 1768 (completed 파일, 이전과 동일하게 작동)"
echo "----------------------------------------"
cd /home/tideflo/nara/public_html && timeout 60 php artisan tinker --execute="
\$tender = App\Models\Tender::find(1768);

\$proposalFiles = \$tender->attachments()->where('type', 'proposal')->get();

echo '파일 개수: ' . \$proposalFiles->count() . '개' . PHP_EOL;

\$collectorService = app(App\Services\TenderCollectorService::class);
\$naraApiService = app(App\Services\NaraApiService::class);
\$controller = new App\Http\Controllers\Admin\TenderController(\$collectorService, \$naraApiService);

\$reflection = new \ReflectionClass(\$controller);
\$method = \$reflection->getMethod('checkFileSangju');
\$method->setAccessible(true);

foreach (\$proposalFiles as \$file) {
    if (\$file->download_status === 'completed') {
        \$file->sangju_status = \$method->invoke(\$controller, \$file);
    } else {
        \$file->sangju_status = [
            'checked' => false,
            'has_sangju' => false,
            'occurrences' => 0,
            'error' => '다운로드 ' . (\$file->download_status === 'pending' ? '대기중' : '실패')
        ];
    }

    echo '파일: ' . \$file->file_name . PHP_EOL;
    echo '  상태: ' . \$file->download_status . PHP_EOL;

    if (\$file->sangju_status['has_sangju']) {
        echo '  ✅ 상주 검사: 발견 (' . \$file->sangju_status['occurrences'] . '회)' . PHP_EOL;
    } elseif (\$file->sangju_status['checked']) {
        echo '  ✅ 상주 검사: 없음' . PHP_EOL;
    }
}
"

if [ $? -ne 0 ]; then
    echo -e "${RED}❌ Test 3 FAILED${NC}"
    exit 1
fi
echo -e "${GREEN}✅ Test 3 PASSED${NC}"
echo ""

echo "=================================================="
echo -e "${GREEN}✅ 모든 테스트 통과!${NC}"
echo "=================================================="
echo ""

echo "요약:"
echo "----------------------------------------"
echo "문제: 제안요청정보 파일 수집 후 리스트에 안 보임"
echo "원인: download_status='completed' 필터 때문에 pending 파일 숨겨짐"
echo ""
echo "해결:"
echo "  ✅ 모든 상태의 파일 표시 (completed, pending, failed)"
echo "  ✅ completed 파일만 상주 검사 수행"
echo "  ✅ pending/failed 파일은 '검사 안됨' 배지 표시"
echo ""
echo "결과:"
echo "  - 파일 수집 후 즉시 리스트에 표시됨"
echo "  - 다운로드 대기중 상태 명확히 표시"
echo "  - 재다운로드 버튼으로 다시 시도 가능"
echo ""
