<?php

namespace App\Console\Commands;

use App\Models\Tender;
use Illuminate\Console\Command;
use ReflectionClass;

class UpdateTenderCategories extends Command
{
    protected $signature = 'tender:update-categories';
    protected $description = 'Update tender categories based on detail classification codes';

    public function handle()
    {
        $this->info('=== 기존 공고 분류코드 업데이트 ===');
        
        $updated = 0;
        $collector = app(\App\Services\TenderCollectorService::class);

        Tender::whereNull('category_id')
            ->whereNotNull('metadata')
            ->chunk(50, function ($tenders) use (&$updated, $collector) {
                foreach ($tenders as $tender) {
                    $metadata = json_decode($tender->metadata, true);
                    $detailCode = $metadata['pubPrcrmntClsfcNo'] ?? '';
                    
                    if (!empty($detailCode)) {
                        // mapCategoryByDetailCode 메서드를 리플렉션으로 호출
                        $reflection = new ReflectionClass($collector);
                        $method = $reflection->getMethod('mapCategoryByDetailCode');
                        $method->setAccessible(true);
                        $categoryId = $method->invoke($collector, $detailCode);
                        
                        if ($categoryId) {
                            $tender->update(['category_id' => $categoryId]);
                            $updated++;
                            $this->line("업데이트: {$tender->tender_no} -> 카테고리 {$categoryId} (코드: {$detailCode})");
                        }
                    }
                }
            });

        $this->info("총 {$updated}건의 공고 분류 업데이트 완료");
        
        // 결과 확인
        $categories = Tender::with('category')
            ->whereNotNull('category_id')
            ->get()
            ->groupBy('category.name')
            ->map(function ($group) {
                return $group->count();
            });
            
        $this->info('분류별 현황:');
        foreach ($categories as $categoryName => $count) {
            $this->line("- {$categoryName}: {$count}건");
        }
        
        return Command::SUCCESS;
    }
}