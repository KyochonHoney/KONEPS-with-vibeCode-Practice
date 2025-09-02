<?php

require_once 'bootstrap/app.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Tender;

echo "=== 기존 데이터의 개별 필드 업데이트 ===\n";

try {
    $tenders = Tender::whereNotNull('metadata')
                     ->whereNull('bid_ntce_ord')
                     ->limit(10)
                     ->get();
    
    echo "업데이트 대상: " . $tenders->count() . "건\n\n";
    
    foreach ($tenders as $tender) {
        $metadata = json_decode($tender->metadata, true);
        
        if ($metadata) {
            echo "공고번호: {$tender->tender_no}\n";
            
            // 개별 필드 업데이트
            $updateData = [
                'bid_ntce_ord' => $metadata['bidNtceOrd'] ?? '',
                're_ntce_yn' => $metadata['reNtceYn'] ?? '',
                'rgst_ty_nm' => $metadata['rgstTyNm'] ?? '',
                'ntce_kind_nm' => $metadata['ntceKindNm'] ?? '',
                'bid_methd_nm' => $metadata['bidMethdNm'] ?? '',
                'cntrct_cncls_mthd_nm' => $metadata['cntrctCnclsMthdNm'] ?? '',
                'ntce_instt_cd' => $metadata['ntceInsttCd'] ?? '',
                'dminstt_cd' => $metadata['dminsttCd'] ?? '',
                'pub_prcrmnt_clsfc_nm' => $metadata['pubPrcrmntClsfcNm'] ?? '',
                'sucsfbid_mthd_nm' => $metadata['sucsfbidMthdNm'] ?? '',
            ];
            
            $tender->update($updateData);
            
            echo "- bid_ntce_ord: {$updateData['bid_ntce_ord']}\n";
            echo "- rgst_ty_nm: {$updateData['rgst_ty_nm']}\n";
            echo "- bid_methd_nm: {$updateData['bid_methd_nm']}\n";
            echo "- 업데이트 완료\n\n";
        }
    }
    
    echo "✅ 업데이트 완료!\n";
    
} catch (Exception $e) {
    echo "❌ 오류: " . $e->getMessage() . "\n";
}

echo "\n=== 업데이트 완료 ===\n";