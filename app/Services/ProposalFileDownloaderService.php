<?php

namespace App\Services;

use App\Models\Tender;
use App\Models\Attachment;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

class ProposalFileDownloaderService
{
    /**
     * 특정 제안요청정보 파일 다운로드 (Playwright 사용)
     */
    public function downloadFile(Attachment $attachment): array
    {
        $result = [
            'success' => false,
            'message' => '',
            'file_size' => 0,
            'local_path' => null
        ];

        try {
            if ($attachment->type !== 'proposal') {
                $result['message'] = '제안요청정보 파일이 아닙니다.';
                return $result;
            }

            if (!$attachment->tender || !$attachment->tender->detail_url) {
                $result['message'] = '공고 상세 URL이 없습니다.';
                return $result;
            }

            if ($attachment->download_status === 'completed') {
                $result['success'] = true;
                $result['message'] = '이미 다운로드 완료된 파일입니다.';
                $result['local_path'] = $attachment->local_path;
                return $result;
            }

            Log::info("제안요청정보 파일 다운로드 시작", [
                'attachment_id' => $attachment->id,
                'file_name' => $attachment->file_name,
                'tender_id' => $attachment->tender_id
            ]);

            // 다운로드 상태 업데이트
            $attachment->update(['download_status' => 'downloading']);

            // 파일 인덱스 찾기 (같은 공고의 제안요청정보 파일 중 몇 번째인지)
            $fileIndex = $attachment->tender->attachments()
                ->where('type', 'proposal')
                ->where('id', '<=', $attachment->id)
                ->count() - 1;

            // Playwright로 실제 파일 다운로드
            $downloadResult = $this->downloadWithPlaywright(
                $attachment->tender->detail_url,
                $fileIndex,
                $attachment->tender_id,
                $attachment->file_name
            );

            if ($downloadResult['success']) {
                // 다운로드 성공 시 메타데이터 업데이트
                $attachment->update([
                    'download_status' => 'completed',
                    'local_path' => $downloadResult['local_path'],
                    'file_size' => $downloadResult['file_size'],
                    'downloaded_at' => now()
                ]);

                $result['success'] = true;
                $result['message'] = '다운로드 완료';
                $result['file_size'] = $downloadResult['file_size'];
                $result['local_path'] = $downloadResult['local_path'];

                Log::info("제안요청정보 파일 다운로드 완료", [
                    'attachment_id' => $attachment->id,
                    'file_size' => $downloadResult['file_size'],
                    'local_path' => $downloadResult['local_path']
                ]);
            } else {
                // 다운로드 실패
                $attachment->update([
                    'download_status' => 'failed',
                    'download_error' => $downloadResult['error']
                ]);

                $result['message'] = '다운로드 실패: ' . $downloadResult['error'];

                Log::error("제안요청정보 파일 다운로드 실패", [
                    'attachment_id' => $attachment->id,
                    'error' => $downloadResult['error']
                ]);
            }

            return $result;

        } catch (\Exception $e) {
            $attachment->update([
                'download_status' => 'failed',
                'download_error' => $e->getMessage()
            ]);

            Log::error("제안요청정보 파일 다운로드 오류", [
                'attachment_id' => $attachment->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $result['message'] = "다운로드 오류: {$e->getMessage()}";
            return $result;
        }
    }

    /**
     * Playwright를 사용한 실제 파일 다운로드
     */
    private function downloadWithPlaywright(string $url, int $fileIndex, int $tenderId, string $expectedFileName): array
    {
        $nodeScript = <<<'JS'
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

(async () => {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ acceptDownloads: true });
  const page = await context.newPage();

  try {
    const url = process.argv[2];
    const fileIndex = parseInt(process.argv[3]);
    const saveDir = process.argv[4];

    await page.goto(url, { waitUntil: 'networkidle', timeout: 30000 });
    await page.waitForTimeout(3000);

    // 다운로드 이벤트 리스너 설정
    const downloadPromise = page.waitForEvent('download', { timeout: 15000 });

    // 파일 링크 클릭
    const selector = `#mf_wfm_container_mainWframe_grdPrpsDmndInfoView_body_tbody tr:nth-child(${fileIndex + 1}) td:nth-child(3) a`;
    const linkElement = page.locator(selector).first();

    if (await linkElement.count() > 0) {
      await linkElement.click();

      // 다운로드 완료 대기
      const download = await downloadPromise;
      const suggestedFilename = download.suggestedFilename();
      const savePath = path.join(saveDir, suggestedFilename);

      await download.saveAs(savePath);

      // 파일 정보 수집
      const stats = fs.statSync(savePath);

      console.log(JSON.stringify({
        success: true,
        saved_path: savePath,
        file_size: stats.size,
        file_name: suggestedFilename
      }));
    } else {
      console.log(JSON.stringify({
        success: false,
        error: 'File link not found'
      }));
    }

  } catch (error) {
    console.log(JSON.stringify({
      success: false,
      error: error.message
    }));
  } finally {
    await browser.close();
  }
})();
JS;

        // 저장 디렉토리 생성
        $saveDir = storage_path('app/proposal_files/' . $tenderId);
        if (!file_exists($saveDir)) {
            mkdir($saveDir, 0755, true);
        }

        // 임시 스크립트 파일 생성
        $scriptPath = storage_path('app/temp/playwright_downloader_' . uniqid() . '.cjs');
        $scriptDir = dirname($scriptPath);

        if (!file_exists($scriptDir)) {
            mkdir($scriptDir, 0755, true);
        }

        file_put_contents($scriptPath, $nodeScript);

        try {
            // Node.js로 Playwright 스크립트 실행
            $result = Process::timeout(120)->run(sprintf(
                "node %s %s %d %s",
                escapeshellarg($scriptPath),
                escapeshellarg($url),
                $fileIndex,
                escapeshellarg($saveDir)
            ));

            if (!$result->successful()) {
                return [
                    'success' => false,
                    'error' => 'Playwright 실행 실패: ' . $result->errorOutput()
                ];
            }

            $output = trim($result->output());
            $data = json_decode($output, true);

            if (!$data) {
                return [
                    'success' => false,
                    'error' => 'Playwright 출력 파싱 실패'
                ];
            }

            if ($data['success']) {
                // storage/app 기준 상대 경로로 변환
                $relativePath = 'proposal_files/' . $tenderId . '/' . basename($data['saved_path']);

                return [
                    'success' => true,
                    'local_path' => $relativePath,
                    'file_size' => $data['file_size']
                ];
            } else {
                return [
                    'success' => false,
                    'error' => $data['error'] ?? '알 수 없는 오류'
                ];
            }

        } finally {
            // 임시 스크립트 파일 삭제
            if (file_exists($scriptPath)) {
                unlink($scriptPath);
            }
        }
    }

    /**
     * 특정 공고의 모든 제안요청정보 파일 다운로드
     */
    public function downloadTenderFiles(Tender $tender): array
    {
        $attachments = $tender->attachments()
            ->where('type', 'proposal')
            ->where('download_status', 'pending')
            ->get();

        $results = [
            'total' => $attachments->count(),
            'success' => 0,
            'failed' => 0,
            'details' => []
        ];

        foreach ($attachments as $attachment) {
            $result = $this->downloadFile($attachment);

            if ($result['success']) {
                $results['success']++;
            } else {
                $results['failed']++;
            }

            $results['details'][] = array_merge([
                'attachment_id' => $attachment->id,
                'file_name' => $attachment->file_name
            ], $result);

            // 서버 부하 방지
            sleep(2);
        }

        return $results;
    }

    /**
     * 여러 공고의 제안요청정보 파일 일괄 다운로드
     */
    public function downloadMultipleTenders(array $tenderIds): array
    {
        $results = [
            'total_tenders' => count($tenderIds),
            'total_files' => 0,
            'success' => 0,
            'failed' => 0,
            'details' => []
        ];

        foreach ($tenderIds as $tenderId) {
            $tender = Tender::find($tenderId);

            if (!$tender) {
                $results['details'][] = [
                    'tender_id' => $tenderId,
                    'success' => false,
                    'message' => '공고를 찾을 수 없습니다.'
                ];
                continue;
            }

            $tenderResult = $this->downloadTenderFiles($tender);
            $results['total_files'] += $tenderResult['total'];
            $results['success'] += $tenderResult['success'];
            $results['failed'] += $tenderResult['failed'];

            $results['details'][] = array_merge([
                'tender_id' => $tenderId,
                'tender_no' => $tender->tender_no
            ], $tenderResult);

            // API 부하 방지
            sleep(3);
        }

        return $results;
    }
}
