<?php

// [BEGIN nara:proposal_model]
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * AI 제안서 모델
 * 
 * @package App\Models
 */
class Proposal extends Model
{
    use HasFactory;

    protected $fillable = [
        'tender_id',
        'user_id',
        'title',
        'content',
        'template_version',
        'ai_analysis_data',
        'status',
        'processing_time',
        'generated_at'
    ];

    protected $casts = [
        'ai_analysis_data' => 'array',
        'generated_at' => 'datetime',
        'processing_time' => 'integer'
    ];

    /**
     * 공고와의 관계
     */
    public function tender(): BelongsTo
    {
        return $this->belongsTo(Tender::class);
    }

    /**
     * 사용자와의 관계
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 진행중인 제안서 조회
     */
    public function scopeProcessing($query)
    {
        return $query->where('status', 'processing');
    }

    /**
     * 완료된 제안서 조회
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * 실패한 제안서 조회
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * 최근 제안서 조회
     */
    public function scopeRecent($query)
    {
        return $query->orderBy('created_at', 'desc');
    }

    /**
     * 제안서 상태별 개수
     */
    public static function getStatusCounts(): array
    {
        return [
            'total' => self::count(),
            'processing' => self::processing()->count(),
            'completed' => self::completed()->count(),
            'failed' => self::failed()->count()
        ];
    }

    /**
     * 제안서 생성 통계
     */
    public static function getGenerationStats(): array
    {
        $completed = self::completed();
        
        return [
            'total_generated' => $completed->count(),
            'avg_processing_time' => $completed->avg('processing_time') ?? 0,
            'success_rate' => self::count() > 0 ? 
                round($completed->count() / self::count() * 100, 1) : 0,
            'today_generated' => $completed->whereDate('created_at', today())->count()
        ];
    }

    /**
     * 처리 시간 포맷팅
     */
    public function getFormattedProcessingTimeAttribute(): string
    {
        if (!$this->processing_time) return 'N/A';
        
        if ($this->processing_time < 1000) {
            return $this->processing_time . 'ms';
        }
        
        return round($this->processing_time / 1000, 1) . 's';
    }

    /**
     * 제안서 요약 정보
     */
    public function getSummaryAttribute(): array
    {
        $aiData = $this->ai_analysis_data ?? [];
        
        return [
            'sections_count' => $aiData['sections_count'] ?? 0,
            'estimated_pages' => $aiData['estimated_pages'] ?? 0,
            'content_length' => strlen($this->content ?? ''),
            'ai_confidence' => $aiData['confidence_score'] ?? 0
        ];
    }

    /**
     * 제안서 내용 미리보기 (첫 200자)
     */
    public function getPreviewAttribute(): string
    {
        if (!$this->content) return '';
        
        $content = strip_tags($this->content);
        return mb_substr($content, 0, 200) . (mb_strlen($content) > 200 ? '...' : '');
    }
}
// [END nara:proposal_model]