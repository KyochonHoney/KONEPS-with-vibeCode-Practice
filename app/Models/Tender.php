<?php

// [BEGIN nara:tender_model]
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

/**
 * 입찰공고 모델
 * 
 * @package App\Models
 */
class Tender extends Model
{
    use HasFactory;

    /**
     * 테이블명
     */
    protected $table = 'tenders';

    /**
     * 대량 할당 가능한 속성들
     */
    protected $fillable = [
        'tender_no',
        'title',
        'content',
        'agency',
        'budget',
        'currency',
        'start_date',
        'end_date',
        'category_id',
        'region',
        'status',
        'source_url',
        'collected_at',
        'metadata'
    ];

    /**
     * 데이터 타입 캐스팅
     */
    protected $casts = [
        'budget' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
        'collected_at' => 'datetime',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * 분류와의 관계
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(TenderCategory::class, 'category_id');
    }

    /**
     * 활성 상태 입찰공고 스코프
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * 마감된 입찰공고 스코프
     */
    public function scopeClosed($query)
    {
        return $query->where('status', 'closed');
    }

    /**
     * 용역 분류 입찰공고 스코프
     */
    public function scopeService($query)
    {
        return $query->whereHas('category', function($q) {
            $q->where('name', '용역');
        });
    }

    /**
     * 기간별 필터링 스코프
     */
    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->where('start_date', '>=', $startDate)
                     ->where('end_date', '<=', $endDate);
    }

    /**
     * 예산 범위별 필터링 스코프
     */
    public function scopeByBudgetRange($query, $minBudget = null, $maxBudget = null)
    {
        if ($minBudget) {
            $query->where('budget', '>=', $minBudget);
        }
        
        if ($maxBudget) {
            $query->where('budget', '<=', $maxBudget);
        }
        
        return $query;
    }

    /**
     * 검색 스코프 (제목, 내용, 기관명)
     */
    public function scopeSearch($query, $keyword)
    {
        return $query->where(function($q) use ($keyword) {
            $q->where('title', 'like', "%{$keyword}%")
              ->orWhere('content', 'like', "%{$keyword}%")
              ->orWhere('agency', 'like', "%{$keyword}%");
        });
    }

    /**
     * 마감일까지 남은 일수 계산
     */
    public function getDaysRemainingAttribute(): ?int
    {
        if (!$this->end_date) {
            return null;
        }
        
        $endDate = Carbon::parse($this->end_date);
        $today = Carbon::today();
        
        if ($endDate->isPast()) {
            return 0;
        }
        
        return $today->diffInDays($endDate);
    }

    /**
     * 마감 여부 확인
     */
    public function getIsExpiredAttribute(): bool
    {
        if (!$this->end_date) {
            return false;
        }
        
        return Carbon::parse($this->end_date)->isPast();
    }

    /**
     * 예산을 포맷된 문자열로 반환
     */
    public function getFormattedBudgetAttribute(): string
    {
        if (!$this->budget) {
            return '미공개';
        }
        
        $budget = $this->budget;
        
        if ($budget >= 100000000) { // 1억 이상
            return number_format($budget / 100000000, 1) . '억원';
        } elseif ($budget >= 10000) { // 1만 이상
            return number_format($budget / 10000) . '만원';
        } else {
            return number_format($budget) . '원';
        }
    }

    /**
     * 상태를 한글로 반환
     */
    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            'active' => '진행중',
            'closed' => '마감',
            'cancelled' => '취소',
            default => '알수없음'
        };
    }

    /**
     * 상태별 부트스트랩 클래스 반환
     */
    public function getStatusClassAttribute(): string
    {
        return match($this->status) {
            'active' => 'badge bg-success',
            'closed' => 'badge bg-secondary', 
            'cancelled' => 'badge bg-danger',
            default => 'badge bg-warning'
        };
    }

    /**
     * 공고 기간 문자열 반환
     */
    public function getPeriodAttribute(): string
    {
        if (!$this->start_date || !$this->end_date) {
            return '기간 미정';
        }
        
        $start = Carbon::parse($this->start_date);
        $end = Carbon::parse($this->end_date);
        
        return $start->format('Y.m.d') . ' ~ ' . $end->format('Y.m.d');
    }

    /**
     * 메타데이터에서 특정 값 가져오기
     */
    public function getMetaValue(string $key, $default = null)
    {
        if (!is_array($this->metadata)) {
            return $default;
        }
        
        return $this->metadata[$key] ?? $default;
    }

    /**
     * 짧은 제목 반환 (최대 길이 제한)
     */
    public function getShortTitleAttribute(): string
    {
        if (mb_strlen($this->title) <= 50) {
            return $this->title;
        }
        
        return mb_substr($this->title, 0, 47) . '...';
    }

    /**
     * 나라장터 상세 페이지 URL 생성
     */
    public function getDetailUrlAttribute(): string
    {
        if (empty($this->tender_no)) {
            return '#';
        }
        
        return "https://www.g2b.go.kr/pt/menu/selectSubFrame.do?framesrc=/pt/menu/frameTgong.do?url=https://www.g2b.go.kr:8082/ep/invitation/publish/bidInfoDtl.do?bidno={$this->tender_no}";
    }

    /**
     * 최근 수집된 입찰공고 조회
     */
    public static function getRecentTenders(int $limit = 10)
    {
        return static::with('category')
                    ->latest('collected_at')
                    ->limit($limit)
                    ->get();
    }

    /**
     * 마감임박 입찰공고 조회 (D-day 3일 이내)
     */
    public static function getUrgentTenders(int $days = 3, int $limit = 10)
    {
        $targetDate = Carbon::today()->addDays($days);
        
        return static::active()
                    ->where('end_date', '<=', $targetDate)
                    ->where('end_date', '>=', Carbon::today())
                    ->orderBy('end_date')
                    ->limit($limit)
                    ->get();
    }
}
// [END nara:tender_model]