<?php

namespace App\Services;

use App\Models\Tender;
use App\Models\Attachment;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class ProposalFileCrawlerService
{
    /**
     * 특정 공고의 제안요청정보 파일 크롤링 (Playwright 사용)
     */
    public function crawlProposalFiles(Tender $tender): array
    {
        $result = [
            'success' => false,
            'message' => '',
            'files_found' => 0,
            'files_downloaded' => 0,
            'errors' => []
        ];

        try {
            // 공고 상세 페이지 URL
            $detailUrl = $tender->detail_url;

            if (!$detailUrl) {
                $result['message'] = '공고 상세 URL이 없습니다.';
                return $result;
            }

            Log::info("제안요청정보 파일 크롤링 시작", [
                'tender_id' => $tender->id,
                'tender_no' => $tender->tender_no,
                'url' => $detailUrl
            ]);

            // Playwright를 사용하여 JavaScript 렌더링 후 HTML 가져오기
            $files = $this->fetchProposalFilesWithPlaywright($detailUrl);
            $result['files_found'] = count($files);

            if (empty($files)) {
                $result['success'] = true;
                $result['message'] = '제안요청정보에 파일이 없습니다.';
                return $result;
            }

            // 파일 다운로드 및 저장
            foreach ($files as $file) {
                try {
                    $this->downloadAndSaveFile($tender, $file);
                    $result['files_downloaded']++;
                } catch (\Exception $e) {
                    $result['errors'][] = "파일 저장 실패: {$file['file_name']} - {$e->getMessage()}";
                    Log::error("제안요청정보 파일 저장 실패", [
                        'tender_id' => $tender->id,
                        'file' => $file,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            $result['success'] = true;
            $result['message'] = "{$result['files_downloaded']}개 파일 저장 완료";

            Log::info("제안요청정보 파일 크롤링 완료", $result);

            return $result;

        } catch (\Exception $e) {
            Log::error("제안요청정보 파일 크롤링 오류", [
                'tender_id' => $tender->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $result['message'] = "크롤링 오류: {$e->getMessage()}";
            return $result;
        }
    }

    /**
     * Playwright를 사용하여 제안요청정보 파일 정보 가져오기
     */
    private function fetchProposalFilesWithPlaywright(string $url): array
    {
        $nodeScript = <<<'JS'
const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage();

  try {
    await page.goto(process.argv[2], { waitUntil: 'networkidle', timeout: 30000 });
    await page.waitForTimeout(3000);

    // 파일 목록 및 메타데이터 수집
    const fileMetadata = await page.evaluate(() => {
      const rows = document.querySelectorAll('#mf_wfm_container_mainWframe_grdPrpsDmndInfoView_body_tbody tr');
      const result = [];

      rows.forEach((row, index) => {
        const cells = row.querySelectorAll('td');
        if (cells.length >= 3) {
          const docName = cells[1]?.textContent?.trim() || '';
          const fileName = cells[2]?.textContent?.trim() || '';

          if (fileName && !row.style.display.includes('none')) {
            result.push({
              index: index,
              doc_name: docName,
              file_name: fileName,
              download_url: null
            });
          }
        }
      });

      return result;
    });

    // 각 파일에 대해 다운로드 URL 및 POST 파라미터 추출
    for (let i = 0; i < fileMetadata.length; i++) {
      const file = fileMetadata[i];

      try {
        // Network 모니터링 시작
        let downloadUrl = null;
        let postData = null;
        const requestMap = new Map(); // 요청 URL과 POST 데이터 매핑

        const requestHandler = (request) => {
          const reqUrl = request.url();
          if (reqUrl.includes('fileUpload.do') && request.method() === 'POST') {
            const data = request.postData();
            if (data) {
              requestMap.set(reqUrl, data);
            }
          }
        };

        const responseHandler = async (response) => {
          const resUrl = response.url();
          if (resUrl.includes('fileUpload.do')) {
            const contentType = response.headers()['content-type'] || '';
            // 실제 파일 다운로드 응답 감지 (hwp, pdf, doc, zip 등)
            if (contentType.includes('application/') && !contentType.includes('text/html')) {
              downloadUrl = resUrl;
              // 해당 URL의 POST 데이터 찾기
              postData = requestMap.get(resUrl);
            }
          }
        };

        page.on('request', requestHandler);
        page.on('response', responseHandler);

        // 파일 링크 클릭
        const selector = `#mf_wfm_container_mainWframe_grdPrpsDmndInfoView_body_tbody tr:nth-child(${file.index + 1}) td:nth-child(3) a`;
        const linkElement = page.locator(selector).first();

        if (await linkElement.count() > 0) {
          await linkElement.click();
          await page.waitForTimeout(2000);

          if (downloadUrl) {
            file.download_url = downloadUrl;
            file.post_data = postData || null;
          }
        }

        page.off('request', requestHandler);
        page.off('response', responseHandler);

      } catch (clickError) {
        // 클릭 실패 시 계속 진행
        console.error(`File ${file.file_name} click failed:`, clickError.message);
      }
    }

    console.log(JSON.stringify(fileMetadata));

  } catch (error) {
    console.error('Error:', error.message);
    console.log('[]');
  } finally {
    await browser.close();
  }
})();
JS;

        // 임시 스크립트 파일 생성 (.cjs 확장자 사용)
        $scriptPath = storage_path('app/temp/playwright_crawler_' . uniqid() . '.cjs');
        $scriptDir = dirname($scriptPath);

        if (!file_exists($scriptDir)) {
            mkdir($scriptDir, 0755, true);
        }

        file_put_contents($scriptPath, $nodeScript);

        try {
            // Node.js로 Playwright 스크립트 실행 (타임아웃 증가: 클릭 시뮬레이션 시간 고려)
            $result = Process::timeout(120)->run("node {$scriptPath} " . escapeshellarg($url));

            if (!$result->successful()) {
                Log::error("Playwright 실행 실패", [
                    'output' => $result->output(),
                    'error' => $result->errorOutput()
                ]);
                return [];
            }

            $output = trim($result->output());
            $errorOutput = trim($result->errorOutput());

            // 디버깅: stderr 출력 로그
            if (!empty($errorOutput)) {
                Log::debug("Playwright stderr 출력", ['stderr' => $errorOutput]);
            }

            $files = json_decode($output, true);

            if (!is_array($files)) {
                Log::error("Playwright 출력 파싱 실패", [
                    'output' => $output,
                    'error_output' => $errorOutput
                ]);
                return [];
            }

            // 디버깅: 파싱된 파일 정보 로그
            Log::debug("Playwright 파일 정보 파싱 성공", [
                'file_count' => count($files),
                'files' => $files
            ]);

            return $files;

        } finally {
            // 임시 스크립트 파일 삭제
            if (file_exists($scriptPath)) {
                unlink($scriptPath);
            }
        }
    }

    /**
     * 파일 메타데이터 DB 저장
     */
    private function downloadAndSaveFile(Tender $tender, array $fileInfo): void
    {
        // 이미 같은 파일이 있는지 확인
        $existing = Attachment::where('tender_id', $tender->id)
            ->where('file_name', $fileInfo['file_name'])
            ->where('type', 'proposal')
            ->first();

        if ($existing) {
            Log::info("이미 존재하는 파일 스킵", [
                'tender_id' => $tender->id,
                'file_name' => $fileInfo['file_name']
            ]);
            return;
        }

        // 다운로드 URL 및 POST 데이터 가져오기
        $downloadUrl = $fileInfo['download_url'] ?? null;
        $postData = $fileInfo['post_data'] ?? null;

        // 메타데이터 저장
        Attachment::create([
            'tender_id' => $tender->id,
            'file_name' => $fileInfo['file_name'],
            'original_name' => $fileInfo['file_name'],
            'file_url' => $downloadUrl,
            'file_type' => pathinfo($fileInfo['file_name'], PATHINFO_EXTENSION),
            'file_size' => null,
            'mime_type' => $this->getMimeTypeFromExtension($fileInfo['file_name']),
            'type' => 'proposal',
            'download_url' => $downloadUrl,
            'post_data' => $postData,
            'doc_name' => $fileInfo['doc_name'] ?? null,
            'local_path' => null,
            'download_status' => $downloadUrl ? 'pending' : 'no_link',
            'downloaded_at' => null,
        ]);

        Log::info("제안요청정보 파일 메타데이터 저장", [
            'tender_id' => $tender->id,
            'filename' => $fileInfo['file_name'],
            'doc_name' => $fileInfo['doc_name'],
            'download_url' => $downloadUrl ? '있음' : '없음',
            'post_data' => $postData ? '있음' : '없음',
            'url' => $downloadUrl
        ]);
    }

    /**
     * 파일 확장자로 MIME 타입 추측
     */
    private function getMimeTypeFromExtension(string $filename): string
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        $mimeTypes = [
            'hwp' => 'application/x-hwp',
            'hwpx' => 'application/x-hwp',
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'zip' => 'application/zip',
        ];

        return $mimeTypes[$extension] ?? 'application/octet-stream';
    }

    /**
     * 여러 공고의 제안요청정보 파일 일괄 크롤링
     */
    public function crawlMultipleTenders(array $tenderIds): array
    {
        $results = [
            'total' => count($tenderIds),
            'success' => 0,
            'failed' => 0,
            'details' => []
        ];

        foreach ($tenderIds as $tenderId) {
            $tender = Tender::find($tenderId);

            if (!$tender) {
                $results['failed']++;
                $results['details'][] = [
                    'tender_id' => $tenderId,
                    'success' => false,
                    'message' => '공고를 찾을 수 없습니다.'
                ];
                continue;
            }

            $result = $this->crawlProposalFiles($tender);

            if ($result['success']) {
                $results['success']++;
            } else {
                $results['failed']++;
            }

            $results['details'][] = array_merge(['tender_id' => $tenderId], $result);

            // API 부하 방지를 위한 딜레이
            sleep(3);
        }

        return $results;
    }

    /**
     * 개별 첨부파일 재다운로드
     *
     * @param Attachment $attachment
     * @return array
     */
    public function downloadSingleFile(Attachment $attachment): array
    {
        try {
            $tender = $attachment->tender;

            // 공고 상세 페이지 URL
            $detailUrl = $tender->detail_url;

            if (!$detailUrl) {
                return [
                    'success' => false,
                    'message' => '공고 상세 URL이 없습니다.'
                ];
            }

            Log::info('개별 파일 재다운로드 시작', [
                'attachment_id' => $attachment->id,
                'file_name' => $attachment->file_name,
                'url' => $detailUrl
            ]);

            // Playwright로 파일 목록 가져오기
            $files = $this->fetchProposalFilesWithPlaywright($detailUrl);

            // 해당 파일명과 일치하는 파일 찾기
            $targetFile = null;
            foreach ($files as $file) {
                if ($file['file_name'] === $attachment->file_name ||
                    $file['file_name'] === $attachment->original_name) {
                    $targetFile = $file;
                    break;
                }
            }

            if (!$targetFile) {
                return [
                    'success' => false,
                    'message' => '나라장터 페이지에서 해당 파일을 찾을 수 없습니다.'
                ];
            }

            // Attachment 레코드 업데이트 (다운로드 정보 갱신)
            $attachment->update([
                'download_url' => $targetFile['download_url'] ?? null,
                'post_data' => $targetFile['post_data'] ?? null,
                'doc_name' => $targetFile['doc_name'] ?? $attachment->doc_name,
                'download_status' => 'pending'
            ]);

            Log::info('개별 파일 재다운로드 완료', [
                'attachment_id' => $attachment->id,
                'local_path' => $attachment->local_path
            ]);

            return [
                'success' => true,
                'message' => '파일 다운로드가 완료되었습니다.'
            ];

        } catch (\Exception $e) {
            Log::error('개별 파일 재다운로드 오류', [
                'attachment_id' => $attachment->id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => '파일 다운로드 오류: ' . $e->getMessage()
            ];
        }
    }
}
