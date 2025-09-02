<?php

// [BEGIN nara:analysis_model]
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 나라장터 공고 AI 분석 결과 모델
 * 
 * @package App\Models
 */
class Analysis extends Model
{
    protected $fillable = [
        'tender_id',
        'user_id',
        'company_profile_id',
        'total_score',
        'technical_score',
        'experience_score',
        'budget_score',
        'other_score',
        'status',
        'analysis_data',
        'ai_model_version',
        'processing_time',
        'started_at',
        'completed_at'
    ];

    protected $casts = [
        'total_score' => 'decimal:2',
        'technical_score' => 'decimal:2',
        'experience_score' => 'decimal:2',
        'budget_score' => 'decimal:2',
        'other_score' => 'decimal:2',
        'analysis_data' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime'
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
     * 회사 프로필과의 관계
     */
    public function companyProfile(): BelongsTo
    {
        return $this->belongsTo(CompanyProfile::class);
    }

    /**
     * 추천도 계산
     */
    public function getRecommendationAttribute(): string
    {
        $score = (float) $this->total_score;
        
        if ($score >= 80) return 'highly_recommended';
        if ($score >= 60) return 'recommended';
        if ($score >= 40) return 'consider';
        return 'not_recommended';
    }

    /**
     * 추천도 한글명
     */
    public function getRecommendationTextAttribute(): string
    {
        return match($this->recommendation) {
            'highly_recommended' => '적극 추천',
            'recommended' => '추천',
            'consider' => '검토 권장',
            'not_recommended' => '비추천'
        };
    }

    /**
     * 점수별 색상 클래스
     */
    public function getScoreColorClassAttribute(): string
    {
        $score = (float) $this->total_score;
        
        if ($score >= 80) return 'text-success';
        if ($score >= 60) return 'text-info';
        if ($score >= 40) return 'text-warning';
        return 'text-danger';
    }

    /**
     * 완료된 분석 조회
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * 최신 분석 조회
     */
    public function scopeLatest($query)
    {
        return $query->orderBy('completed_at', 'desc');
    }
}
// [END nara:analysis_model]
