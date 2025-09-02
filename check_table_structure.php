<?php

require_once 'bootstrap/app.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== Tenders 테이블 구조 확인 ===\n";

try {
    $columns = DB::select("SHOW COLUMNS FROM tenders");
    
    echo "현재 컬럼 목록:\n";
    foreach ($columns as $column) {
        echo "- {$column->Field}: {$column->Type}\n";
    }
    
    echo "\n총 컬럼 수: " . count($columns) . "개\n";
    
} catch (Exception $e) {
    echo "오류: " . $e->getMessage() . "\n";
}

echo "\n=== 확인 완료 ===\n";