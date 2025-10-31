<?php

// [BEGIN nara:ai_config]
return [

    /*
    |--------------------------------------------------------------------------
    | AI Analysis Configuration
    |--------------------------------------------------------------------------
    |
    | AI 기반 분석 시스템의 기본 설정
    |
    */

    'analysis' => [
        'provider' => env('AI_ANALYSIS_PROVIDER', 'mock'), // 실제 API 키 없을 시 mock 사용
        'cache_ttl' => env('AI_ANALYSIS_CACHE_TTL', 3600),
        'retry_attempts' => env('AI_ANALYSIS_RETRY_ATTEMPTS', 3),
        'timeout' => env('AI_ANALYSIS_TIMEOUT', 60),
        'fallback_to_mock' => env('AI_FALLBACK_TO_MOCK', true), // API 실패 시 Mock 사용
    ],

    /*
    |--------------------------------------------------------------------------
    | OpenAI API Configuration
    |--------------------------------------------------------------------------
    |
    | OpenAI GPT API 설정
    |
    */

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'organization' => env('OPENAI_ORGANIZATION'),
        'model' => env('OPENAI_MODEL', 'gpt-4-turbo-preview'),
        'max_tokens' => env('OPENAI_MAX_TOKENS', 4096),
        'temperature' => env('OPENAI_TEMPERATURE', 0.3),
    ],

    /*
    |--------------------------------------------------------------------------
    | Claude API Configuration
    |--------------------------------------------------------------------------
    |
    | Anthropic Claude API 설정
    |
    */

    'claude' => [
        'api_key' => env('CLAUDE_API_KEY'),
        'model' => env('CLAUDE_MODEL', 'claude-3-5-sonnet-20241022'),
        'max_tokens' => env('CLAUDE_MAX_TOKENS', 4096),
    ],

    /*
    |--------------------------------------------------------------------------
    | Analysis Templates
    |--------------------------------------------------------------------------
    |
    | 분석 목적별 프롬프트 템플릿
    |
    */

    'templates' => [
        'tender_analysis' => [
            'max_content_length' => 5000,
            'required_fields' => [
                'compatibility_score',
                'technical_match_score', 
                'business_match_score',
                'recommendation'
            ]
        ],
        'attachment_analysis' => [
            'max_content_length' => 3000,
            'required_fields' => [
                'required_technologies',
                'development_scope',
                'complexity_level'
            ]
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | API 사용량 제한 설정
    |
    */

    'rate_limits' => [
        'daily_requests' => env('AI_DAILY_LIMIT', 1000),
        'hourly_requests' => env('AI_HOURLY_LIMIT', 100),
        'concurrent_requests' => env('AI_CONCURRENT_LIMIT', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Fallback Configuration
    |--------------------------------------------------------------------------
    |
    | AI API 실패 시 폴백 설정
    |
    */

    'fallback' => [
        'enabled' => true,
        'use_cached_results' => true,
        'default_compatibility_score' => 50,
        'cache_fallback_duration' => 86400, // 24시간
    ]

];

// [END nara:ai_config]