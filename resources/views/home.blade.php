{{-- [BEGIN nara:home_page] --}}
@extends('layouts.app')

@section('title', '나라장터 AI 제안서 시스템')

@section('content')
<div class="container-fluid">
    <!-- 히어로 섹션 -->
    <div class="row min-vh-100 align-items-center">
        <div class="col-12">
            <div class="text-center">
                <div class="mb-4">
                    <h1 class="display-4 fw-bold text-primary mb-3">
                        나라장터 AI 제안서 시스템
                    </h1>
                    <p class="lead text-muted mb-4">
                        인공지능 기반 용역공고 분석 및 자동 제안서 생성 플랫폼
                    </p>
                </div>

                <!-- 기능 소개 카드 -->
                <div class="row justify-content-center mb-5">
                    <div class="col-md-10">
                        <div class="row g-4">
                            <div class="col-md-4">
                                <div class="card h-100 border-0 shadow-sm">
                                    <div class="card-body text-center">
                                        <div class="mb-3">
                                            <i class="bi bi-search text-primary" style="font-size: 2rem;"></i>
                                        </div>
                                        <h5 class="card-title">용역공고 수집</h5>
                                        <p class="card-text text-muted">
                                            나라장터에서 실시간으로<br>
                                            용역공고를 자동 수집
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card h-100 border-0 shadow-sm">
                                    <div class="card-body text-center">
                                        <div class="mb-3">
                                            <i class="bi bi-cpu text-success" style="font-size: 2rem;"></i>
                                        </div>
                                        <h5 class="card-title">AI 분석</h5>
                                        <p class="card-text text-muted">
                                            인공지능 기반으로<br>
                                            공고 적합성 분석
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card h-100 border-0 shadow-sm">
                                    <div class="card-body text-center">
                                        <div class="mb-3">
                                            <i class="bi bi-file-text text-info" style="font-size: 2rem;"></i>
                                        </div>
                                        <h5 class="card-title">제안서 자동생성</h5>
                                        <p class="card-text text-muted">
                                            맞춤형 템플릿으로<br>
                                            제안서 자동 생성
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 로그인/회원가입 버튼 -->
                <div class="row justify-content-center">
                    <div class="col-md-6">
                        <div class="d-grid gap-3">
                            <a href="{{ route('login') }}" class="btn btn-primary btn-lg">
                                <i class="bi bi-box-arrow-in-right me-2"></i>
                                로그인
                            </a>
                            <a href="{{ route('register') }}" class="btn btn-outline-primary btn-lg">
                                <i class="bi bi-person-plus me-2"></i>
                                회원가입
                            </a>
                        </div>
                        <div class="mt-4">
                            <small class="text-muted">
                                이미 계정이 있으신가요? 
                                <a href="{{ route('login') }}" class="text-decoration-none">로그인하기</a>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 시스템 특징 섹션 -->
    <div class="row py-5 bg-light">
        <div class="col-12">
            <div class="container">
                <h2 class="text-center mb-5">시스템 특징</h2>
                <div class="row g-4">
                    <div class="col-md-3">
                        <div class="text-center">
                            <div class="mb-3">
                                <i class="bi bi-shield-check text-success" style="font-size: 3rem;"></i>
                            </div>
                            <h5>안전한 보안</h5>
                            <p class="text-muted">3단계 권한 관리 및<br>데이터 보안 시스템</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center">
                            <div class="mb-3">
                                <i class="bi bi-lightning-charge text-warning" style="font-size: 3rem;"></i>
                            </div>
                            <h5>빠른 처리</h5>
                            <p class="text-muted">실시간 공고 수집 및<br>즉시 분석 제공</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center">
                            <div class="mb-3">
                                <i class="bi bi-graph-up-arrow text-info" style="font-size: 3rem;"></i>
                            </div>
                            <h5>정확한 분석</h5>
                            <p class="text-muted">AI 기반 정밀 분석으로<br>최적의 결과 제공</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center">
                            <div class="mb-3">
                                <i class="bi bi-people text-primary" style="font-size: 3rem;"></i>
                            </div>
                            <h5>사용자 친화적</h5>
                            <p class="text-muted">직관적인 인터페이스와<br>쉬운 조작 방법</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
.min-vh-100 {
    min-height: 100vh;
}

.card {
    transition: transform 0.3s ease-in-out;
}

.card:hover {
    transform: translateY(-5px);
}

.bg-light {
    background-color: #f8f9fa !important;
}
</style>
@endpush
{{-- [END nara:home_page] --}}