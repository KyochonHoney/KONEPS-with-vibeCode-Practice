<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Tender;
use App\Models\CompanyProfile;
use App\Services\DynamicProposalGenerator;
use App\Services\AttachmentService;
use App\Services\AiApiService;
use App\Services\ProposalStructureAnalyzer;

echo "🎯 동적 제안서 생성 시스템 테스트\n";
echo "================================\n\n";

try {
    // 최신 공고 선택
    $tender = Tender::with('attachments')->latest('collected_at')->first();
    
    if (!$tender) {
        echo "❌ 공고 데이터가 없습니다.\n";
        exit;
    }
    
    echo "📋 선택된 공고:\n";
    echo "  제목: {$tender->title}\n";
    echo "  공고번호: {$tender->tender_no}\n";
    echo "  발주기관: {$tender->ntce_instt_nm}\n";
    echo "  첨부파일 수: " . $tender->attachments->count() . "개\n\n";
    
    // 회사 프로필 생성 (타이드플로)
    $companyProfile = new CompanyProfile([
        'company_name' => '타이드플로',
        'business_areas' => ['정부기관', '웹개발', 'GIS시스템'],
        'technical_keywords' => [
            'PHP' => 95,
            'Laravel' => 90,
            'JavaScript' => 85,
            'React' => 80,
            'Vue.js' => 75,
            'MySQL' => 90,
            'PostgreSQL' => 85,
            'PostGIS' => 80,
            'GIS' => 85,
            'WebGIS' => 80,
            'OpenLayers' => 75,
            'Leaflet' => 70,
            'Python' => 70,
            'Java' => 60
        ],
        'experiences' => [
            '정부기관 GIS 시스템 구축 경험 15건',
            '웹 기반 데이터베이스 시스템 개발 25건',
            '공공기관 정보시스템 개발 20건',
            'PostGIS 기반 공간정보시스템 구축 12건',
            '모바일 호환 웹시스템 개발 30건'
        ]
    ]);
    
    echo "🏢 회사 프로필 생성 완료 (타이드플로)\n\n";
    
    // 첨부파일 내용 추출
    $attachmentContents = [];
    $attachmentService = new AttachmentService();
    
    if ($tender->attachments->isNotEmpty()) {
        echo "📄 첨부파일 분석 시작...\n";
        
        foreach ($tender->attachments->take(2) as $attachment) {
            try {
                $content = $attachmentService->extractTextContent($attachment);
                $attachmentContents[$attachment->file_name] = $content;
                
                echo "  ✅ {$attachment->file_name}: " . number_format(strlen($content)) . "자 추출\n";
                
            } catch (Exception $e) {
                echo "  ❌ {$attachment->file_name}: {$e->getMessage()}\n";
            }
        }
        echo "\n";
    }
    
    // 동적 제안서 생성 서비스 초기화
    $aiApiService = new AiApiService();
    $structureAnalyzer = new ProposalStructureAnalyzer($aiApiService);
    $dynamicGenerator = new DynamicProposalGenerator($aiApiService, $structureAnalyzer);
    
    echo "🤖 동적 제안서 생성 시작...\n";
    echo "=============================\n\n";
    
    // 동적 제안서 생성 실행
    $proposalResult = $dynamicGenerator->generateDynamicProposal(
        $tender,
        $companyProfile,
        $attachmentContents
    );
    
    echo "✅ 동적 제안서 생성 완료!\n\n";
    
    // 결과 출력
    echo "📊 생성 결과 요약:\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "제안서 제목: {$proposalResult['title']}\n";
    echo "섹션 수: {$proposalResult['sections_generated']}개\n";
    echo "예상 페이지: {$proposalResult['estimated_pages']}페이지\n";
    echo "내용 길이: " . number_format($proposalResult['content_length']) . "자\n";
    echo "신뢰도 점수: {$proposalResult['confidence_score']}%\n";
    echo "생성 품질: {$proposalResult['generation_quality']}\n";
    echo "구조 소스: {$proposalResult['structure_source']}\n";
    echo "동적 생성 여부: " . ($proposalResult['is_dynamic_generated'] ? '예' : '아니오') . "\n\n";
    
    if (!empty($proposalResult['matching_technologies'])) {
        echo "🔧 매칭된 기술:\n";
        foreach ($proposalResult['matching_technologies'] as $tech) {
            echo "  • $tech\n";
        }
        echo "\n";
    }
    
    if (!empty($proposalResult['missing_technologies'])) {
        echo "⚠️ 부족한 기술:\n";
        foreach ($proposalResult['missing_technologies'] as $tech) {
            echo "  • $tech\n";
        }
        echo "\n";
    }
    
    // 제안서 내용 미리보기
    echo "📝 제안서 내용 미리보기:\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo substr($proposalResult['content'], 0, 1000) . "\n";
    if (strlen($proposalResult['content']) > 1000) {
        echo "\n... (총 " . number_format(strlen($proposalResult['content'])) . "자 중 1,000자만 표시)\n";
    }
    echo "\n";
    
    echo "🎉 4단계 동적 제안서 생성 테스트 성공!\n";
    echo "하드코딩 없이 실제 공고 정보를 기반으로 맞춤형 제안서가 생성되었습니다.\n";
    
} catch (Exception $e) {
    echo "❌ 오류 발생: {$e->getMessage()}\n";
    echo "\n스택 트레이스:\n";
    echo $e->getTraceAsString() . "\n";
}