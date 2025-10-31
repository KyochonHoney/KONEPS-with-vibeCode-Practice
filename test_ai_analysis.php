<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Tender;
use App\Models\Attachment;
use App\Services\AiApiService;
use App\Services\AttachmentService;

echo "🎯 AI 과업지시서 분석 테스트 시작\n";
echo "==================================\n\n";

// 최근 공고 중 첨부파일이 있는 것 조회
$tender = Tender::whereHas('attachments', function($query) {
    $query->where('file_name', 'like', '%과업%')
          ->orWhere('file_name', 'like', '%지시서%');
})->with('attachments')->first();

if (!$tender) {
    echo "❌ 과업지시서 첨부파일이 있는 공고를 찾을 수 없습니다.\n";
    
    // 대안: 아무 첨부파일이나 있는 공고 사용
    $tender = Tender::whereHas('attachments')->with('attachments')->first();
    
    if (!$tender) {
        echo "❌ 첨부파일이 있는 공고가 전혀 없습니다.\n";
        exit;
    }
    
    echo "🔄 대신 다른 첨부파일이 있는 공고로 테스트합니다.\n\n";
}

echo "📋 선택된 공고:\n";
echo "  제목: {$tender->title}\n";
echo "  공고번호: {$tender->tender_no}\n";
echo "  발주기관: {$tender->ntce_instt_nm}\n";
echo "  첨부파일 수: " . $tender->attachments->count() . "개\n\n";

// 첫 번째 첨부파일 선택
$attachment = $tender->attachments->first();

if (!$attachment) {
    echo "❌ 첨부파일을 찾을 수 없습니다.\n";
    exit;
}

echo "📄 분석할 첨부파일: {$attachment->file_name}\n";
echo "   파일 크기: " . number_format($attachment->file_size) . " bytes\n";
echo "   URL: {$attachment->file_url}\n\n";

try {
    // AttachmentService로 파일 내용 추출
    echo "📥 파일 내용 추출 중...\n";
    $attachmentService = new AttachmentService();
    $extractedContent = $attachmentService->extractTextContent($attachment);
    
    echo "✅ 내용 추출 완료\n";
    echo "   추출된 문자 수: " . number_format(strlen($extractedContent)) . "자\n";
    echo "   내용 미리보기:\n";
    echo "   " . str_replace("\n", "\n   ", substr($extractedContent, 0, 300)) . "...\n\n";
    
    // AI 분석 서비스 호출
    echo "🤖 AI 과업지시서 분석 시작...\n";
    $aiApiService = new AiApiService();
    
    $analysisResult = $aiApiService->analyzeTaskInstruction(
        $extractedContent,
        [
            'tender_no' => $tender->tender_no,
            'title' => $tender->title,
            'agency' => $tender->ntce_instt_nm,
            'budget' => $tender->budget_amount ?? 0
        ]
    );
    
    echo "✅ AI 분석 완료!\n\n";
    
    // 분석 결과 출력
    echo "🔍 AI 분석 결과:\n";
    echo "=====================================\n\n";
    
    if (isset($analysisResult['project_overview'])) {
        echo "📊 프로젝트 개요:\n";
        echo "   " . str_replace("\n", "\n   ", $analysisResult['project_overview']) . "\n\n";
    }
    
    if (isset($analysisResult['requirements']) && is_array($analysisResult['requirements'])) {
        echo "📋 주요 요구사항:\n";
        foreach ($analysisResult['requirements'] as $i => $req) {
            echo "   " . ($i + 1) . ". $req\n";
        }
        echo "\n";
    }
    
    if (isset($analysisResult['technologies']) && is_array($analysisResult['technologies'])) {
        echo "🔧 기술 요구사항:\n";
        foreach ($analysisResult['technologies'] as $tech) {
            echo "   • $tech\n";
        }
        echo "\n";
    }
    
    if (isset($analysisResult['deliverables']) && is_array($analysisResult['deliverables'])) {
        echo "📦 주요 산출물:\n";
        foreach ($analysisResult['deliverables'] as $deliverable) {
            echo "   • $deliverable\n";
        }
        echo "\n";
    }
    
    if (isset($analysisResult['project_scope'])) {
        echo "🎯 프로젝트 범위:\n";
        echo "   " . str_replace("\n", "\n   ", $analysisResult['project_scope']) . "\n\n";
    }
    
    if (isset($analysisResult['tideflo_match_score'])) {
        echo "🏆 타이드플로 적합성 점수: {$analysisResult['tideflo_match_score']}/10\n\n";
    }
    
    if (isset($analysisResult['match_reasons']) && is_array($analysisResult['match_reasons'])) {
        echo "💡 적합성 근거:\n";
        foreach ($analysisResult['match_reasons'] as $reason) {
            echo "   • $reason\n";
        }
        echo "\n";
    }
    
    // 전체 결과를 JSON으로도 출력
    echo "📄 전체 분석 결과 (JSON):\n";
    echo "=====================================\n";
    echo json_encode($analysisResult, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n\n";
    
    echo "🎉 3단계 AI 분석 테스트 성공!\n";
    echo "실제 첨부파일에서 " . number_format(strlen($extractedContent)) . "자의 내용을 추출하고\n";
    echo "AI가 구조화된 요구사항 분석을 완료했습니다.\n";
    
} catch (Exception $e) {
    echo "❌ 오류 발생: {$e->getMessage()}\n";
    echo "\n스택 트레이스:\n";
    echo $e->getTraceAsString() . "\n";
}