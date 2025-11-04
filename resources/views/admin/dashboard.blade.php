{{-- [BEGIN nara:admin_dashboard] --}}
@extends('layouts.app')

@section('title', '관리자 대시보드')

@section('content')
<div class="row">
    <div class="col-12">
        <h1>관리자 대시보드</h1>
        <p class="text-muted">시스템 관리 및 모니터링</p>
    </div>
</div>

<div class="row g-4">
    <!-- 관리자 통계 카드들 -->
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h5 class="card-title text-primary">전체 사용자</h5>
                <h2 class="text-primary">{{ number_format($stats['total_users']) }}</h2>
                <p class="text-muted mb-0">등록된 사용자</p>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h5 class="card-title text-success">전체 공고</h5>
                <h2 class="text-success">{{ number_format($stats['total_tenders']) }}</h2>
                <p class="text-muted mb-0">수집된 용역공고</p>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h5 class="card-title text-info">분석 완료</h5>
                <h2 class="text-info">{{ number_format($stats['total_analyses']) }}</h2>
                <p class="text-muted mb-0">AI 분석 완료</p>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h5 class="card-title text-warning">생성 제안서</h5>
                <h2 class="text-warning">{{ number_format($stats['total_proposals']) }}</h2>
                <p class="text-muted mb-0">자동 생성된 제안서</p>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">관리 메뉴</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-2">
                        <a href="{{ route('admin.users.index') }}" class="btn btn-outline-primary w-100">
                            <i class="bi bi-people me-2"></i>
                            사용자 관리
                        </a>
                    </div>
                    <div class="col-md-2">
                        <a href="{{ route('admin.tenders.index') }}" class="btn btn-outline-success w-100">
                            <i class="bi bi-folder me-2"></i>
                            공고 관리
                        </a>
                    </div>
                    <div class="col-md-2">
                        <a href="{{ route('admin.attachments.index') }}" class="btn btn-outline-info w-100">
                            <i class="bi bi-file-text me-2"></i>
                            첨부파일 관리
                        </a>
                    </div>
                    <div class="col-md-2">
                        <a href="{{ route('admin.analyses.index') }}" class="btn btn-outline-warning w-100">
                            <i class="bi bi-graph-up me-2"></i>
                            AI 분석
                        </a>
                    </div>
                    <div class="col-md-2">
                        <a href="{{ route('admin.proposals.index') }}" class="btn btn-outline-secondary w-100">
                            <i class="bi bi-file-earmark-text me-2"></i>
                            제안서 관리
                        </a>
                    </div>
                    <div class="col-md-2">
                        <a href="{{ route('admin.tenders.collect') }}" class="btn btn-outline-danger w-100">
                            <i class="bi bi-cloud-download me-2"></i>
                            데이터 수집
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">시스템 상태</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-6">
                        <div class="d-flex align-items-center">
                            <div class="bg-success rounded-circle me-2" style="width: 12px; height: 12px;"></div>
                            <span>데이터베이스</span>
                        </div>
                    </div>
                    <div class="col-6">
                        <small class="text-success">정상</small>
                    </div>
                </div>
                <hr>
                <div class="row">
                    <div class="col-6">
                        <div class="d-flex align-items-center">
                            <div class="bg-warning rounded-circle me-2" style="width: 12px; height: 12px;"></div>
                            <span>AI 서비스</span>
                        </div>
                    </div>
                    <div class="col-6">
                        <small class="text-warning">구성 필요</small>
                    </div>
                </div>
                <hr>
                <div class="row">
                    <div class="col-6">
                        <div class="d-flex align-items-center">
                            <div class="bg-warning rounded-circle me-2" style="width: 12px; height: 12px;"></div>
                            <span>데이터 수집</span>
                        </div>
                    </div>
                    <div class="col-6">
                        <small class="text-warning">구성 필요</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">최근 활동</h5>
            </div>
            <div class="card-body">
                <p class="text-muted">시스템 활동 로그가 여기에 표시됩니다.</p>
            </div>
        </div>
    </div>
</div>
@endsection
{{-- [END nara:admin_dashboard] --}}