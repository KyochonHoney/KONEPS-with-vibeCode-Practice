{{-- [BEGIN nara:user_dashboard] --}}
@extends('layouts.app')

@section('title', '대시보드')

@section('content')
<div class="row">
    <div class="col-12">
        <h1>대시보드</h1>
        <p class="text-muted">안녕하세요, {{ $user->name }}님!</p>
    </div>
</div>

<div class="row g-4">
    <!-- 통계 카드들 -->
    <div class="col-md-4">
        <div class="card text-center">
            <div class="card-body">
                <h5 class="card-title text-primary">전체 공고</h5>
                <h2 class="text-primary">{{ number_format($stats['total_tenders']) }}</h2>
                <p class="text-muted mb-0">수집된 용역공고</p>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card text-center">
            <div class="card-body">
                <h5 class="card-title text-success">분석 완료</h5>
                <h2 class="text-success">{{ number_format($stats['total_analyses']) }}</h2>
                <p class="text-muted mb-0">AI 분석 완료</p>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card text-center">
            <div class="card-body">
                <h5 class="card-title text-info">생성 제안서</h5>
                <h2 class="text-info">{{ number_format($stats['total_proposals']) }}</h2>
                <p class="text-muted mb-0">자동 생성된 제안서</p>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">빠른 메뉴</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <a href="{{ route('admin.tenders.index') }}" class="btn btn-outline-primary w-100">
                            <i class="bi bi-search me-2"></i>
                            용역공고 검색
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="#" class="btn btn-outline-success w-100">
                            <i class="bi bi-graph-up me-2"></i>
                            AI 분석 요청
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="#" class="btn btn-outline-info w-100">
                            <i class="bi bi-file-text me-2"></i>
                            제안서 생성
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="#" class="btn btn-outline-secondary w-100">
                            <i class="bi bi-gear me-2"></i>
                            설정
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">최근 활동</h5>
            </div>
            <div class="card-body">
                <p class="text-muted">아직 활동 내역이 없습니다.</p>
            </div>
        </div>
    </div>
</div>
@endsection
{{-- [END nara:user_dashboard] --}}