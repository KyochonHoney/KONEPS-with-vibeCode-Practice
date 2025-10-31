<?php
require 'vendor/autoload.php';
require 'bootstrap/app.php';

$api = new App\Services\NaraApiService();
$total = 0;
$nulls = 0;
$empties = 0;
$samples = [];

echo "=== pubPrcrmntClsfcNo NULL/EMPTY 분석 ===" . PHP_EOL;

for ($page = 1; $page <= 15; $page++) {
    try {
        $result = $api->getTendersByDateRange('20250831', '20250907', $page, 100);
        if (\!isset($result['response']['body']['items'])) break;
        
        $items = $result['response']['body']['items'];
        if (empty($items)) break;
        
        foreach ($items as $item) {
            $total++;
            $code = $item['pubPrcrmntClsfcNo'] ?? null;
            $title = $item['bidNtceNm'] ?? 'N/A';
            
            if (is_null($code)) {
                $nulls++;
                if (count($samples) < 15) $samples[] = "NULL: " . $title;
            } elseif (empty($code) || (is_array($code) && empty($code))) {
                $empties++;
                if (count($samples) < 15) $samples[] = "EMPTY: " . $title;
            }
        }
        
        if ($page % 5 == 0) {
            echo "Page $page: $total checked..." . PHP_EOL;
        }
        
    } catch (Exception $e) {
        echo "Error on page $page: " . $e->getMessage() . PHP_EOL;
        break;
    }
}

echo "===== 결과 =====" . PHP_EOL;
echo "총 공고: $total 건" . PHP_EOL;
echo "NULL 값: $nulls 건" . PHP_EOL;
echo "빈 값: $empties 건" . PHP_EOL;
echo "정상 값: " . ($total - $nulls - $empties) . " 건" . PHP_EOL;

if (\!empty($samples)) {
    echo PHP_EOL . "=== NULL/EMPTY 샘플 ===" . PHP_EOL;
    foreach ($samples as $sample) {
        echo $sample . PHP_EOL;
    }
}
