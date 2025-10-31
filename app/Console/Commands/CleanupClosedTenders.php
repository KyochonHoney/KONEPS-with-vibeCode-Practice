<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Tender;
use Illuminate\Support\Facades\Log;

class CleanupClosedTenders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tender:cleanup {--dry-run : 실제 삭제하지 않고 미리보기만}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '마감된 공고를 데이터베이스에서 삭제';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('=== 마감 공고 정리 시작 ===');

        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('⚠️  DRY RUN 모드: 실제 삭제하지 않습니다.');
        }

        // 현재 상태 조회
        $totalBefore = Tender::count();
        $activeBefore = Tender::where('status', 'active')->count();
        $closedCount = Tender::where('status', 'closed')->count();

        $this->info("삭제 전 상태:");
        $this->info("  - 전체 공고: {$totalBefore}건");
        $this->info("  - 활성 공고: {$activeBefore}건");
        $this->info("  - 마감 공고: {$closedCount}건");

        if ($closedCount === 0) {
            $this->info('✅ 삭제할 마감 공고가 없습니다.');
            return 0;
        }

        // 마감된 공고 샘플 표시
        $this->newLine();
        $this->info('삭제 대상 공고 샘플 (최대 10개):');
        $samples = Tender::where('status', 'closed')
            ->orderBy('updated_at', 'desc')
            ->limit(10)
            ->get(['id', 'title', 'status']);

        foreach ($samples as $sample) {
            $title = mb_substr($sample->title, 0, 50);
            $this->line("  - ID {$sample->id}: {$title}...");
        }

        if (!$dryRun) {
            // 실제 삭제 실행
            if (!$this->confirm("정말로 {$closedCount}건의 마감 공고를 삭제하시겠습니까?", false)) {
                $this->warn('취소되었습니다.');
                return 0;
            }

            $this->info('삭제 중...');

            try {
                // 마감된 공고 삭제
                $deleted = Tender::where('status', 'closed')->delete();

                // 삭제 후 상태 조회
                $totalAfter = Tender::count();
                $activeAfter = Tender::where('status', 'active')->count();

                $this->newLine();
                $this->info('✅ 삭제 완료!');
                $this->info("삭제 후 상태:");
                $this->info("  - 전체 공고: {$totalAfter}건 (감소: " . ($totalBefore - $totalAfter) . "건)");
                $this->info("  - 활성 공고: {$activeAfter}건");
                $this->info("  - 삭제된 공고: {$deleted}건");

                // 로그 기록
                Log::info('마감 공고 정리 완료', [
                    'deleted_count' => $deleted,
                    'total_before' => $totalBefore,
                    'total_after' => $totalAfter,
                ]);

            } catch (\Exception $e) {
                $this->error('❌ 삭제 중 오류 발생: ' . $e->getMessage());
                Log::error('마감 공고 정리 실패', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                return 1;
            }
        } else {
            $this->newLine();
            $this->info("✅ DRY RUN 완료: {$closedCount}건의 공고가 삭제될 예정입니다.");
            $this->info('실제 삭제하려면 --dry-run 옵션 없이 실행하세요.');
        }

        return 0;
    }
}
