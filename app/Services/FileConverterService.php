<?php

// [BEGIN nara:file_converter_service]
namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Exception;

/**
 * 파일 형식 변환 서비스 (모든 파일을 한글 HWP 형식으로 변환)
 * 
 * @package App\Services
 */
class FileConverterService
{
    /**
     * 변환 가능한 파일 형식들
     */
    private const CONVERTIBLE_FORMATS = [
        // 문서 형식
        'pdf' => 'PDF 문서',
        'doc' => 'Microsoft Word 97-2003',
        'docx' => 'Microsoft Word',
        'txt' => '텍스트 파일',
        'rtf' => 'Rich Text Format',
        'odt' => 'OpenDocument Text',
        
        // 스프레드시트 형식
        'xls' => 'Microsoft Excel 97-2003',
        'xlsx' => 'Microsoft Excel',
        'csv' => 'CSV 파일',
        'ods' => 'OpenDocument Spreadsheet',
        
        // 프레젠테이션 형식
        'ppt' => 'Microsoft PowerPoint 97-2003',
        'pptx' => 'Microsoft PowerPoint',
        'odp' => 'OpenDocument Presentation',
        
        // 이미지 형식 (OCR 후 변환)
        'jpg' => 'JPEG 이미지',
        'jpeg' => 'JPEG 이미지',
        'png' => 'PNG 이미지',
        'gif' => 'GIF 이미지',
        'bmp' => 'BMP 이미지',
        'tiff' => 'TIFF 이미지',
        
        // 기타
        'html' => 'HTML 문서',
        'xml' => 'XML 문서',
    ];

    /**
     * 파일을 HWP 형식으로 변환
     * 
     * @param string $sourceFilePath 원본 파일 경로
     * @param string $originalFileName 원본 파일명
     * @return string|null 변환된 HWP 파일 경로
     */
    public function convertToHwp(string $sourceFilePath, string $originalFileName): ?string
    {
        try {
            $fileExtension = strtolower(pathinfo($originalFileName, PATHINFO_EXTENSION));
            
            Log::info('파일 HWP 변환 시작', [
                'original_file' => $originalFileName,
                'source_path' => $sourceFilePath,
                'extension' => $fileExtension
            ]);

            // 이미 HWP 파일인 경우 그대로 반환
            if ($fileExtension === 'hwp') {
                return $sourceFilePath;
            }

            // 파일이 존재하는지 확인
            if (!Storage::exists($sourceFilePath)) {
                throw new Exception('원본 파일을 찾을 수 없습니다: ' . $sourceFilePath);
            }

            // 변환 방식 결정
            $convertedPath = match($fileExtension) {
                // 텍스트 기반 파일들
                'txt', 'csv', 'html', 'xml' => $this->convertTextBasedFileToHwp($sourceFilePath, $originalFileName),
                
                // Microsoft Office 문서들  
                'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx' => $this->convertOfficeFileToHwp($sourceFilePath, $originalFileName),
                
                // PDF 문서
                'pdf' => $this->convertPdfToHwp($sourceFilePath, $originalFileName),
                
                // 이미지 파일들 (OCR 후 변환)
                'jpg', 'jpeg', 'png', 'gif', 'bmp', 'tiff' => $this->convertImageToHwp($sourceFilePath, $originalFileName),
                
                // OpenDocument 형식들
                'odt', 'ods', 'odp' => $this->convertOpenDocumentToHwp($sourceFilePath, $originalFileName),
                
                // RTF 파일
                'rtf' => $this->convertRtfToHwp($sourceFilePath, $originalFileName),
                
                // 기본: 텍스트로 처리
                default => $this->createHwpFromUnsupportedFile($sourceFilePath, $originalFileName)
            };

            if ($convertedPath) {
                Log::info('파일 HWP 변환 완료', [
                    'original_file' => $originalFileName,
                    'converted_path' => $convertedPath
                ]);
            }

            return $convertedPath;

        } catch (Exception $e) {
            Log::error('파일 HWP 변환 실패', [
                'original_file' => $originalFileName,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * 텍스트 기반 파일을 HWP로 변환
     */
    private function convertTextBasedFileToHwp(string $sourceFilePath, string $originalFileName): string
    {
        $content = Storage::get($sourceFilePath);
        
        // 파일 형식에 따른 전처리
        $extension = strtolower(pathinfo($originalFileName, PATHINFO_EXTENSION));
        
        if ($extension === 'html') {
            // HTML 태그 제거 및 텍스트 추출
            $content = strip_tags($content);
            $content = html_entity_decode($content);
        } elseif ($extension === 'xml') {
            // XML을 읽기 쉬운 형태로 변환
            $content = $this->formatXmlContent($content);
        } elseif ($extension === 'csv') {
            // CSV를 표 형태로 변환
            $content = $this->formatCsvContent($content);
        }

        return $this->createHwpFromText($content, $originalFileName);
    }

    /**
     * Microsoft Office 파일을 HWP로 변환
     */
    private function convertOfficeFileToHwp(string $sourceFilePath, string $originalFileName): string
    {
        // 실제 구현에서는 LibreOffice나 온라인 변환 서비스를 사용할 수 있습니다.
        // 여기서는 Mock 변환을 수행합니다.
        
        $mockContent = $this->createMockOfficeContent($originalFileName);
        return $this->createHwpFromText($mockContent, $originalFileName);
    }

    /**
     * PDF를 HWP로 변환 (텍스트 추출 후 변환)
     */
    private function convertPdfToHwp(string $sourceFilePath, string $originalFileName): string
    {
        // 실제 구현에서는 PDF 텍스트 추출 라이브러리 사용
        // 예: smalot/pdfparser, spatie/pdf-to-text 등
        
        $mockContent = $this->createMockPdfContent($originalFileName);
        return $this->createHwpFromText($mockContent, $originalFileName);
    }

    /**
     * 이미지를 HWP로 변환 (OCR 후 텍스트 변환)
     */
    private function convertImageToHwp(string $sourceFilePath, string $originalFileName): string
    {
        // 실제 구현에서는 OCR 서비스 사용 (Google Vision API, Tesseract 등)
        // 여기서는 Mock OCR 결과를 생성합니다.
        
        $ocrContent = $this->performMockOcr($originalFileName);
        return $this->createHwpFromText($ocrContent, $originalFileName);
    }

    /**
     * OpenDocument 파일을 HWP로 변환
     */
    private function convertOpenDocumentToHwp(string $sourceFilePath, string $originalFileName): string
    {
        // OpenDocument는 ZIP 기반 XML 형식이므로 텍스트 추출 가능
        $mockContent = $this->createMockOpenDocumentContent($originalFileName);
        return $this->createHwpFromText($mockContent, $originalFileName);
    }

    /**
     * RTF 파일을 HWP로 변환
     */
    private function convertRtfToHwp(string $sourceFilePath, string $originalFileName): string
    {
        $rtfContent = Storage::get($sourceFilePath);
        // RTF 태그 제거 및 텍스트 추출
        $textContent = $this->extractTextFromRtf($rtfContent);
        return $this->createHwpFromText($textContent, $originalFileName);
    }

    /**
     * 지원되지 않는 파일 형식 처리
     */
    private function createHwpFromUnsupportedFile(string $sourceFilePath, string $originalFileName): string
    {
        $extension = strtolower(pathinfo($originalFileName, PATHINFO_EXTENSION));
        $fileSize = Storage::size($sourceFilePath);
        
        $content = "파일 변환 정보\n\n";
        $content .= "원본 파일명: {$originalFileName}\n";
        $content .= "파일 형식: {$extension}\n";
        $content .= "파일 크기: " . number_format($fileSize) . " bytes\n\n";
        $content .= "이 파일은 직접적인 텍스트 변환이 어려운 형식입니다.\n";
        $content .= "원본 파일을 별도로 확인하시기 바랍니다.\n\n";
        $content .= "변환 일시: " . now()->format('Y-m-d H:i:s') . "\n";

        return $this->createHwpFromText($content, $originalFileName);
    }

    /**
     * 텍스트를 HWP 형식으로 생성 (실제로는 HWP Mock 파일)
     */
    private function createHwpFromText(string $content, string $originalFileName): string
    {
        // HWP 파일 생성 (실제로는 한글 파일 형식으로 생성해야 하지만, 
        // 여기서는 텍스트 기반의 Mock HWP 파일을 생성합니다)
        
        $baseName = pathinfo($originalFileName, PATHINFO_FILENAME);
        $hwpFileName = $baseName . '_converted.hwp';
        $hwpFilePath = 'converted_hwp/' . date('Y/m/d') . '/' . $hwpFileName;

        // HWP Mock 헤더 추가
        $hwpContent = $this->createHwpHeader() . "\n\n" . $content . "\n\n" . $this->createHwpFooter();

        Storage::put($hwpFilePath, $hwpContent);

        return $hwpFilePath;
    }

    /**
     * HWP Mock 헤더 생성
     */
    private function createHwpHeader(): string
    {
        return "HWP Document Format (Mock)\n" .
               "========================================\n" .
               "변환 일시: " . now()->format('Y-m-d H:i:s') . "\n" .
               "변환 시스템: 나라장터 AI 제안서 시스템\n" .
               "========================================";
    }

    /**
     * HWP Mock 푸터 생성
     */
    private function createHwpFooter(): string
    {
        return "========================================\n" .
               "문서 끝\n" .
               "이 문서는 자동으로 HWP 형식으로 변환되었습니다.";
    }

    /**
     * CSV 내용 포맷팅
     */
    private function formatCsvContent(string $content): string
    {
        $lines = explode("\n", $content);
        $formatted = "CSV 데이터 변환\n\n";
        
        foreach ($lines as $index => $line) {
            if (trim($line)) {
                $columns = str_getcsv($line);
                $formatted .= "행 " . ($index + 1) . ": " . implode(" | ", $columns) . "\n";
            }
        }
        
        return $formatted;
    }

    /**
     * XML 내용 포맷팅
     */
    private function formatXmlContent(string $content): string
    {
        // XML 태그를 읽기 쉬운 형태로 변환
        $content = preg_replace('/<([^>]+)>/', "\n[$1]\n", $content);
        $content = strip_tags($content);
        return "XML 문서 변환\n\n" . $content;
    }

    /**
     * Mock Office 내용 생성
     */
    private function createMockOfficeContent(string $fileName): string
    {
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        return match($extension) {
            'doc', 'docx' => "Microsoft Word 문서: {$fileName}\n\n이 문서는 한글 형식으로 변환되었습니다.\n원본 문서의 내용이 여기에 표시됩니다.",
            'xls', 'xlsx' => "Microsoft Excel 스프레드시트: {$fileName}\n\n스프레드시트 데이터:\n표 1 | 표 2 | 표 3\n값 1 | 값 2 | 값 3",
            'ppt', 'pptx' => "Microsoft PowerPoint 프레젠테이션: {$fileName}\n\n슬라이드 1: 제목 슬라이드\n슬라이드 2: 내용 슬라이드\n슬라이드 3: 결론 슬라이드",
            default => "Office 문서: {$fileName}\n\n변환된 문서 내용이 여기에 표시됩니다."
        };
    }

    /**
     * Mock PDF 내용 생성
     */
    private function createMockPdfContent(string $fileName): string
    {
        return "PDF 문서: {$fileName}\n\n" .
               "PDF에서 추출된 텍스트 내용:\n\n" .
               "이 문서는 PDF 파일에서 한글 형식으로 변환되었습니다.\n" .
               "원본 PDF의 텍스트 내용이 여기에 표시됩니다.\n\n" .
               "페이지 1: 첫 번째 페이지 내용\n" .
               "페이지 2: 두 번째 페이지 내용\n" .
               "페이지 3: 세 번째 페이지 내용";
    }

    /**
     * Mock OCR 수행
     */
    private function performMockOcr(string $fileName): string
    {
        return "이미지 파일 OCR 결과: {$fileName}\n\n" .
               "OCR로 추출된 텍스트:\n\n" .
               "이미지에서 인식된 텍스트 내용이 여기에 표시됩니다.\n" .
               "한글, 영어, 숫자 등이 인식되어 텍스트로 변환되었습니다.\n\n" .
               "인식 정확도: 95%\n" .
               "OCR 처리 시간: 2.5초";
    }

    /**
     * Mock OpenDocument 내용 생성
     */
    private function createMockOpenDocumentContent(string $fileName): string
    {
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        return match($extension) {
            'odt' => "OpenDocument 텍스트: {$fileName}\n\n오픈오피스/LibreOffice 문서에서 변환된 내용입니다.",
            'ods' => "OpenDocument 스프레드시트: {$fileName}\n\n표 데이터가 여기에 표시됩니다.",
            'odp' => "OpenDocument 프레젠테이션: {$fileName}\n\n프레젠테이션 내용이 여기에 표시됩니다.",
            default => "OpenDocument 파일: {$fileName}\n\n변환된 내용이 여기에 표시됩니다."
        };
    }

    /**
     * RTF에서 텍스트 추출
     */
    private function extractTextFromRtf(string $rtfContent): string
    {
        // 간단한 RTF 태그 제거
        $text = preg_replace('/\{\*?\\\\[^{}]+}/', '', $rtfContent);
        $text = preg_replace('/\\\\./', '', $text);
        $text = preg_replace('/[{}]/', '', $text);
        $text = trim($text);
        
        return "RTF 문서에서 추출된 텍스트:\n\n" . $text;
    }

    /**
     * 변환 가능한 파일 형식 목록 반환
     */
    public function getSupportedFormats(): array
    {
        return self::CONVERTIBLE_FORMATS;
    }

    /**
     * 파일이 변환 가능한지 확인
     */
    public function isConvertible(string $fileName): bool
    {
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        return array_key_exists($extension, self::CONVERTIBLE_FORMATS);
    }

    /**
     * 변환 통계 조회
     */
    public function getConversionStats(): array
    {
        // 변환된 파일들의 통계 정보 반환
        $convertedFiles = Storage::files('converted_hwp');
        
        return [
            'total_conversions' => count($convertedFiles),
            'conversion_date' => date('Y-m-d'),
            'supported_formats' => count(self::CONVERTIBLE_FORMATS),
        ];
    }
}
// [END nara:file_converter_service]