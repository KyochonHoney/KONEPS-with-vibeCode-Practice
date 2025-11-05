<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ProposalFileDownloaderService;
use App\Models\Tender;
use App\Models\Attachment;

class DownloadProposalFiles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'proposal:download
                            {--tender= : 특정 공고 ID}
                            {--attachment= : 특정 첨부파일 ID}
                            {--all : 모든 대기중인 파일 다운로드}
                            {--limit= : 다운로드할 파일 수 제한 (기본: 10)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '제안요청정보 파일 다운로드';

    /**
     * Execute the console command.
     */
    public function handle(ProposalFileDownloaderService $downloader)
    {
        $this->info('제안요청정보 파일 다운로드 시작...');
        $this->newLine();

        // 특정 첨부파일 다운로드
        if ($attachmentId = $this->option('attachment')) {
            $attachment = Attachment::find($attachmentId);

            if (!$attachment) {
                $this->error("첨부파일 ID {$attachmentId}를 찾을 수 없습니다.");
                return Command::FAILURE;
            }

            $this->info("파일 다운로드 중: {$attachment->file_name}");
            $result = $downloader->downloadFile($attachment);

            if ($result['success']) {
                $this->info("✅ 다운로드 완료: {$result['local_path']}");
                $this->info("   파일 크기: " . number_format($result['file_size']) . " bytes");
                return Command::SUCCESS;
            } else {
                $this->error("❌ 다운로드 실패: {$result['message']}");
                return Command::FAILURE;
            }
        }

        // 특정 공고의 파일 다운로드
        if ($tenderId = $this->option('tender')) {
            $tender = Tender::find($tenderId);

            if (!$tender) {
                $this->error("공고 ID {$tenderId}를 찾을 수 없습니다.");
                return Command::FAILURE;
            }

            $this->info("공고 번호: {$tender->bid_ntce_no}");
            $result = $downloader->downloadTenderFiles($tender);

            $this->newLine();
            $this->info("다운로드 완료:");
            $this->info("  전체: {$result['total']}개");
            $this->info("  성공: {$result['success']}개");
            $this->info("  실패: {$result['failed']}개");

            return $result['failed'] > 0 ? Command::FAILURE : Command::SUCCESS;
        }

        // 모든 대기중인 파일 다운로드
        if ($this->option('all')) {
            $limit = $this->option('limit') ?? 10;

            $attachments = Attachment::where('type', 'proposal')
                ->where('download_status', 'pending')
                ->with('tender')
                ->limit($limit)
                ->get();

            if ($attachments->isEmpty()) {
                $this->info('다운로드할 파일이 없습니다.');
                return Command::SUCCESS;
            }

            $this->info("총 {$attachments->count()}개 파일 다운로드 예정");
            $this->newLine();

            $bar = $this->output->createProgressBar($attachments->count());
            $bar->start();

            $success = 0;
            $failed = 0;

            foreach ($attachments as $attachment) {
                $result = $downloader->downloadFile($attachment);

                if ($result['success']) {
                    $success++;
                } else {
                    $failed++;
                }

                $bar->advance();
            }

            $bar->finish();
            $this->newLine(2);

            $this->info("다운로드 완료:");
            $this->info("  성공: {$success}개");
            $this->info("  실패: {$failed}개");

            return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
        }

        // 옵션이 없으면 도움말 표시
        $this->error('옵션을 지정해주세요: --attachment, --tender, 또는 --all');
        $this->info('예시:');
        $this->info('  php artisan proposal:download --attachment=123');
        $this->info('  php artisan proposal:download --tender=456');
        $this->info('  php artisan proposal:download --all --limit=5');

        return Command::INVALID;
    }
}
