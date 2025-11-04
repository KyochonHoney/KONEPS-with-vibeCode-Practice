<?php

namespace App\Console\Commands;

use App\Models\Tender;
use App\Services\ProposalFileCrawlerService;
use Illuminate\Console\Command;

class CrawlProposalFilesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tender:crawl-proposal-files {tender_id? : 특정 공고 ID} {--all : 모든 공고 크롤링}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '나라장터 공고의 제안요청정보 파일 크롤링';

    /**
     * Execute the console command.
     */
    public function handle(ProposalFileCrawlerService $crawler)
    {
        $tenderId = $this->argument('tender_id');
        $all = $this->option('all');

        if ($tenderId) {
            // 특정 공고 크롤링
            $tender = Tender::find($tenderId);

            if (!$tender) {
                $this->error("공고 ID {$tenderId}를 찾을 수 없습니다.");
                return 1;
            }

            $this->info("공고 #{$tender->id} ({$tender->tender_no}) 제안요청정보 파일 크롤링 시작...");

            $result = $crawler->crawlProposalFiles($tender);

            if ($result['success']) {
                $this->info("✅ " . $result['message']);
                $this->info("   발견: {$result['files_found']}개, 다운로드: {$result['files_downloaded']}개");

                if (!empty($result['errors'])) {
                    $this->warn("⚠️  일부 오류 발생:");
                    foreach ($result['errors'] as $error) {
                        $this->warn("   - {$error}");
                    }
                }
            } else {
                $this->error("❌ " . $result['message']);
                return 1;
            }

        } elseif ($all) {
            // 모든 공고 크롤링
            $this->info("모든 공고의 제안요청정보 파일 크롤링 시작...");

            $tenders = Tender::where('status', 'active')->get();

            if ($tenders->isEmpty()) {
                $this->warn("활성 공고가 없습니다.");
                return 0;
            }

            $this->info("총 {$tenders->count()}개 공고 크롤링 시작");

            $bar = $this->output->createProgressBar($tenders->count());
            $bar->start();

            $totalSuccess = 0;
            $totalFailed = 0;
            $totalFilesDownloaded = 0;

            foreach ($tenders as $tender) {
                $result = $crawler->crawlProposalFiles($tender);

                if ($result['success']) {
                    $totalSuccess++;
                    $totalFilesDownloaded += $result['files_downloaded'];
                } else {
                    $totalFailed++;
                }

                $bar->advance();
                sleep(2); // API 부하 방지
            }

            $bar->finish();
            $this->newLine(2);

            $this->info("✅ 크롤링 완료");
            $this->info("   성공: {$totalSuccess}개");
            $this->info("   실패: {$totalFailed}개");
            $this->info("   다운로드: {$totalFilesDownloaded}개 파일");

        } else {
            $this->error("공고 ID를 지정하거나 --all 옵션을 사용하세요.");
            $this->info("사용법:");
            $this->info("  php artisan tender:crawl-proposal-files 123");
            $this->info("  php artisan tender:crawl-proposal-files --all");
            return 1;
        }

        return 0;
    }
}
