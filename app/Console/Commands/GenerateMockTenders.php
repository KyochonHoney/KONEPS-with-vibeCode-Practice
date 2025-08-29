<?php

// [BEGIN nara:generate_mock_tenders_command]
namespace App\Console\Commands;

use App\Models\Tender;
use App\Models\TenderCategory;
use Illuminate\Console\Command;
use Carbon\Carbon;

/**
 * Mock 입찰공고 데이터 생성 명령어
 * API 연결 문제 시 시스템 테스트용
 * 
 * @package App\Console\Commands
 */
class GenerateMockTenders extends Command
{
    protected $signature = 'tender:mock-generate 
                            {--count=50 : 생성할 Mock 데이터 개수}
                            {--clean : 기존 데이터 삭제 후 생성}';

    protected $description = 'API 연결 문제 시 시스템 테스트를 위한 Mock 입찰공고 데이터 생성';

    public function handle(): int
    {
        $count = (int) $this->option('count');
        $clean = $this->option('clean');
        
        $this->info("=== Mock 입찰공고 데이터 생성 시작 ===");
        
        if ($clean) {
            $this->info("기존 데이터 삭제 중...");
            // 외래키 제약조건 때문에 truncate 대신 delete 사용
            \DB::statement('SET FOREIGN_KEY_CHECKS=0;');
            Tender::query()->delete();
            \DB::statement('SET FOREIGN_KEY_CHECKS=1;');
            $this->info("✅ 기존 데이터 삭제 완료");
        }
        
        $this->info("Mock 데이터 {$count}건 생성 중...");
        $this->withProgressBar(range(1, $count), function ($i) {
            $this->createMockTender($i);
        });
        
        $this->newLine(2);
        
        $stats = [
            'total' => Tender::count(),
            'active' => Tender::where('status', 'active')->count(),
            'closed' => Tender::where('status', 'closed')->count(),
        ];
        
        $this->info("=== Mock 데이터 생성 완료 ===");
        $this->table(['항목', '개수'], [
            ['전체 공고', $stats['total']],
            ['활성 공고', $stats['active']], 
            ['마감 공고', $stats['closed']],
        ]);
        
        $this->info("🔗 관리자 페이지: https://nara.tideflo.work/admin/tenders");
        
        return Command::SUCCESS;
    }
    
    private function createMockTender(int $index): void
    {
        $categories = [1, 2, 3]; // 용역, 공사, 물품
        $agencies = [
            '한국전력공사', '한국도로공사', '한국철도공사', '한국공항공사',
            '서울특별시', '부산광역시', '대구광역시', '인천광역시',
            '경기도', '강원도', '충청북도', '충청남도', '전라북도', '전라남도',
            '교육부', '과학기술정보통신부', '국토교통부', '환경부', '보건복지부'
        ];
        
        $serviceTypes = [
            'IT시스템 구축', '웹사이트 개발', '모바일앱 개발', '데이터베이스 구축',
            '보안시스템 구축', '네트워크 구축', '클라우드 서비스', 'AI/머신러닝',
            '컨설팅 서비스', '교육훈련 서비스', '마케팅 서비스', '디자인 서비스',
            '유지보수 서비스', '시설관리 서비스', '청소용역', '경비용역'
        ];
        
        // 날짜 범위: 최근 30일 ~ 향후 60일
        $startDate = Carbon::now()->subDays(rand(0, 30));
        $endDate = Carbon::now()->addDays(rand(1, 60));
        
        // 상태 결정
        $status = $endDate->isPast() ? 'closed' : 'active';
        
        Tender::create([
            'tender_no' => sprintf('2025-%04d%04d', rand(1000, 9999), $index),
            'title' => $serviceTypes[array_rand($serviceTypes)] . ' 용역 ' . sprintf('(%04d년도)', 2025),
            'content' => $this->generateContent($serviceTypes[array_rand($serviceTypes)]),
            'agency' => $agencies[array_rand($agencies)],
            'budget' => $this->generateBudget(),
            'currency' => 'KRW',
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'category_id' => $categories[array_rand($categories)],
            'region' => $this->generateRegion(),
            'status' => $status,
            'source_url' => 'https://www.g2b.go.kr/pt/menu/selectSubFrame.do?mock=' . $index,
            'collected_at' => Carbon::now()->subMinutes(rand(1, 1440)),
            'metadata' => json_encode([
                'mock_data' => true,
                'generated_at' => now()->toISOString(),
                'bidNtceNo' => sprintf('2025-%04d%04d', rand(1000, 9999), $index),
                'bidNtceNm' => $serviceTypes[array_rand($serviceTypes)] . ' 용역',
                'dminsttNm' => $agencies[array_rand($agencies)],
                'presmptPrce' => number_format($this->generateBudget()),
            ], JSON_UNESCAPED_UNICODE)
        ]);
    }
    
    private function generateContent(string $serviceType): string
    {
        $contents = [
            'IT시스템 구축' => '통합정보시스템 구축 및 운영을 위한 용역으로, 시스템 분석, 설계, 개발, 테스트, 운영을 포함합니다.',
            '웹사이트 개발' => '반응형 웹사이트 구축 용역으로, UI/UX 디자인, 프론트엔드 및 백엔드 개발을 포함합니다.',
            '모바일앱 개발' => 'iOS 및 Android 모바일 애플리케이션 개발 용역으로, 네이티브 또는 하이브리드 앱 개발을 포함합니다.',
            '보안시스템 구축' => '정보보안시스템 구축 및 운영 용역으로, 보안정책 수립, 보안시스템 구축, 모니터링을 포함합니다.',
            '컨설팅 서비스' => '업무 프로세스 개선 및 디지털 전환을 위한 컨설팅 용역입니다.',
        ];
        
        return $contents[$serviceType] ?? '전문적인 기술 용역 서비스를 제공하는 프로젝트입니다.';
    }
    
    private function generateBudget(): float
    {
        $ranges = [
            [1000000, 5000000],    // 100만원 ~ 500만원
            [5000000, 20000000],   // 500만원 ~ 2천만원  
            [20000000, 100000000], // 2천만원 ~ 1억원
            [100000000, 500000000] // 1억원 ~ 5억원
        ];
        
        $range = $ranges[array_rand($ranges)];
        return rand($range[0], $range[1]);
    }
    
    private function generateRegion(): string
    {
        $regions = [
            '서울특별시', '부산광역시', '대구광역시', '인천광역시', '광주광역시', '대전광역시',
            '울산광역시', '세종특별자치시', '경기도', '강원도', '충청북도', '충청남도',
            '전라북도', '전라남도', '경상북도', '경상남도', '제주특별자치도'
        ];
        
        return $regions[array_rand($regions)];
    }
}
// [END nara:generate_mock_tenders_command]