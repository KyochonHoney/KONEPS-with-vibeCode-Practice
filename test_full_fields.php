<?php

require_once 'bootstrap/app.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Tender;

echo "=== 전체 필드 저장 테스트 ===\n";

try {
    $tender = Tender::first();
    
    if ($tender) {
        echo "✅ 첫 번째 공고 확인:\n";
        echo "- 공고번호: {$tender->tender_no}\n";
        echo "- 공고명: {$tender->title}\n";
        echo "- 공고기관: {$tender->agency}\n";
        echo "- 예산: " . number_format($tender->budget) . " 원\n\n";
        
        echo "📋 새로 추가된 필드들 확인:\n";
        echo "- 공고차수: {$tender->bid_ntce_ord}\n";
        echo "- 재공고여부: {$tender->re_ntce_yn}\n";
        echo "- 등록유형: {$tender->rgst_ty_nm}\n";
        echo "- 공고종류: {$tender->ntce_kind_nm}\n";
        echo "- 입찰방법: {$tender->bid_methd_nm}\n";
        echo "- 계약방법: {$tender->cntrct_cncls_mthd_nm}\n";
        echo "- 담당자명: {$tender->ntce_instt_ofcl_nm}\n";
        echo "- 집행담당자: {$tender->exctv_nm}\n";
        echo "- 개찰일시: {$tender->openg_dt}\n";
        echo "- 예정가격방법: {$tender->prearng_prce_dcsn_mthd_nm}\n";
        echo "- 낙찰방법: {$tender->sucsfbid_mthd_nm}\n";
        echo "- 서비스구분: {$tender->srvce_div_nm}\n";
        echo "- 조달대분류: {$tender->pub_prcrmnt_lrg_clsfc_nm}\n";
        echo "- 조달중분류: {$tender->pub_prcrmnt_mid_clsfc_nm}\n";
        echo "- 조달분류명: {$tender->pub_prcrmnt_clsfc_nm}\n";
        echo "- 등록일시: {$tender->rgst_dt}\n";
        
        echo "\n📊 JSON 필드 확인:\n";
        $jsonFields = ['ntce_instt_ofcl_tel_no', 'ntce_instt_ofcl_email_adrs', 'ntce_spec_doc_url3'];
        foreach ($jsonFields as $field) {
            $value = $tender->$field;
            if (!empty($value)) {
                echo "- {$field}: {$value}\n";
            } else {
                echo "- {$field}: [빈 값]\n";
            }
        }
        
        echo "\n🏢 기관 코드 확인:\n";
        echo "- 공고기관코드: {$tender->ntce_instt_cd}\n";
        echo "- 수요기관코드: {$tender->dminstt_cd}\n";
        
        echo "\n💰 금액 관련 필드:\n";
        echo "- 배정예산: {$tender->asign_bdgt_amt}\n";
        echo "- 참가수수료: {$tender->bid_prtcpt_fee}\n";
        echo "- 부가세: {$tender->vat_amount}\n";
        
    } else {
        echo "❌ 공고 데이터가 없습니다.\n";
    }
    
    echo "\n📈 전체 통계:\n";
    $totalCount = Tender::count();
    $withBidNtceOrd = Tender::whereNotNull('bid_ntce_ord')->count();
    $withNewFields = Tender::whereNotNull('pub_prcrmnt_clsfc_nm')->count();
    
    echo "- 전체 공고: {$totalCount}건\n";
    echo "- 공고차수 있는 건: {$withBidNtceOrd}건\n";
    echo "- 조달분류명 있는 건: {$withNewFields}건\n";
    
    $successRate = $totalCount > 0 ? round(($withNewFields / $totalCount) * 100, 1) : 0;
    echo "- 전체 필드 저장 성공률: {$successRate}%\n";
    
} catch (Exception $e) {
    echo "❌ 오류: " . $e->getMessage() . "\n";
}

echo "\n=== 테스트 완료 ===\n";