<?php

// [BEGIN nara:company_profile_model]
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 회사 프로필 모델 (AI 분석용)
 * 
 * @package App\Models
 */
class CompanyProfile extends Model
{
    protected $fillable = [
        'name',
        'business_number',
        'description',
        'capabilities',
        'experiences',
        'certifications',
        'employees_count',
        'established_year',
        'annual_revenue',
        'website',
        'contact_info',
        'is_active'
    ];

    protected $casts = [
        'capabilities' => 'array',
        'experiences' => 'array',
        'certifications' => 'array',
        'contact_info' => 'array',
        'annual_revenue' => 'decimal:2',
        'is_active' => 'boolean'
    ];

    /**
     * 분석과의 관계
     */
    public function analyses(): HasMany
    {
        return $this->hasMany(Analysis::class);
    }

    /**
     * 활성 프로필 조회
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * 기본 타이드플로 프로필 생성/조회
     */
    public static function getTideFloProfile(): self
    {
        return self::firstOrCreate(
            ['name' => 'Tideflo'],
            [
                'name' => 'Tideflo',
                'business_number' => '123-45-67890',
                'description' => 'Java 전문 개발회사, 서버/DB 전문, 유지보수 최고 수준, SI 프로젝트 전문',
                'capabilities' => [
                    // 프로그래밍 언어 (실제 전문도 기준 가중치)
                    'technical_keywords' => [
                        'java' => 10,          // 주력 기술 (최고 수준)
                        'spring' => 10,        // Java 전문회사로서 Spring도 최고
                        'php' => 8,            // 서브 기술
                        'mysql' => 9,          // DB 전문
                        'oracle' => 9,         // DB 전문  
                        'postgresql' => 8,     // DB 전문
                        'mariadb' => 8,        // DB 전문
                        'tomcat' => 9,         // 서버 전문
                        'apache' => 8,         // 서버 전문
                        'nginx' => 7,          // 서버 전문
                        'linux' => 8,          // 서버 관리
                        'windows' => 7,        // 서버 관리
                        'api' => 9,            // Java 기반 API 개발 전문
                        'rest' => 9,           // REST API 전문
                        'json' => 8,           // 데이터 처리
                        'xml' => 8,            // 데이터 처리
                        'jsp' => 9,            // Java 웹 개발
                        'servlet' => 9,        // Java 웹 개발
                        'jdbc' => 9,           // Java DB 연동
                        'maven' => 8,          // Java 빌드 도구
                        'gradle' => 7,         // Java 빌드 도구
                        'javascript' => 6,     // 프론트엔드 기본
                        'html' => 6,           // 웹 개발 기본
                        'css' => 5             // 웹 개발 기본
                    ],
                    'business_areas' => [
                        'Java 기반 시스템 개발',
                        'SI 프로젝트 (System Integration)',
                        '시스템 유지보수 및 운영',
                        '서버 시스템 구축',
                        '데이터베이스 설계 및 관리',
                        'API 개발 및 연동',
                        '정부기관 시스템 개발',
                        '대기업 SI 프로젝트',
                        '웹 애플리케이션 개발 (Java 기반)',
                        '레거시 시스템 현대화'
                    ],
                    'budget_range' => [
                        'min' => 10000000,
                        'max' => 1000000000,
                        'preferred_min' => 50000000,
                        'preferred_max' => 500000000
                    ],
                    'location_preferences' => [
                        '서울시' => 10,
                        '경기도' => 8,
                        '인천시' => 7,
                        '전국' => 6
                    ]
                ],
                'experiences' => [
                    'Java 기반 시스템 개발 15년 경력',
                    '정부기관 SI 프로젝트 전문 (10년 이상)',
                    '대기업 시스템 유지보수 전문',
                    'Oracle/MySQL 데이터베이스 전문 관리',
                    '레거시 시스템 현대화 다수 경험',
                    'Spring Framework 전문 개발'
                ],
                'certifications' => [
                    'SW개발업체 신고확인증',
                    '정보보호 관리체계 인증(ISMS)',
                    'AWS Partner 인증',
                    'ISO 27001 인증'
                ],
                'employees_count' => 25,
                'established_year' => 2018,
                'annual_revenue' => 5000000000, // 50억원
                'website' => 'https://tideflo.com',
                'contact_info' => [
                    'email' => 'contact@tideflo.com',
                    'phone' => '02-1234-5678',
                    'address' => '서울시 강남구 테헤란로 123',
                    'ceo' => '홍길동'
                ],
                'is_active' => true
            ]
        );
    }

    /**
     * 기술 키워드 배열 반환 (AI 분석용)
     */
    public function getTechnicalKeywordsAttribute(): array
    {
        return $this->capabilities['technical_keywords'] ?? [];
    }

    /**
     * 사업 영역 배열 반환 (AI 분석용)
     */
    public function getBusinessAreasAttribute(): array
    {
        return $this->capabilities['business_areas'] ?? [];
    }

    /**
     * 예산 범위 배열 반환 (AI 분석용)
     */
    public function getBudgetRangeAttribute(): array
    {
        return $this->capabilities['budget_range'] ?? [];
    }

    /**
     * 지역 선호도 배열 반환 (AI 분석용)
     */
    public function getLocationPreferencesAttribute(): array
    {
        return $this->capabilities['location_preferences'] ?? [];
    }
}
// [END nara:company_profile_model]
