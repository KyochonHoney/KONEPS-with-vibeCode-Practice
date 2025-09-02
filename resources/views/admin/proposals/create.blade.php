@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <!-- 헤더 -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">➕ 새 제안서 생성</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('admin.proposals.index') }}">제안서 관리</a></li>
                    <li class="breadcrumb-item active">새 제안서</li>
                </ol>
            </nav>
        </div>
        <div>
            <a href="{{ route('admin.proposals.index') }}" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> 목록으로
            </a>
        </div>
    </div>

    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">제안서 생성 설정</h6>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.proposals.store') }}">
                        @csrf
                        
                        <!-- 공고 선택 -->
                        <div class="mb-4">
                            <label for="tender_id" class="form-label"><strong>대상 공고 선택</strong> <span class="text-danger">*</span></label>
                            
                            @if($tender)
                                <!-- 특정 공고가 선택된 경우 -->
                                <input type="hidden" name="tender_id" value="{{ $tender->id }}">
                                <div class="card border-primary">
                                    <div class="card-body">
                                        <h6 class="card-title">{{ $tender->title }}</h6>
                                        <p class="card-text text-muted">
                                            <small>
                                                📋 공고번호: {{ $tender->tender_no }}<br>
                                                🏢 발주기관: {{ $tender->agency ?? $tender->ntce_instt_nm }}<br>
                                                💰 예산: {{ $tender->budget_formatted }}<br>
                                                📅 마감: {{ $tender->end_date ? $tender->end_date->format('Y-m-d') : 'N/A' }}
                                            </small>
                                        </p>
                                        <a href="{{ route('admin.tenders.show', $tender) }}" class="btn btn-sm btn-outline-primary" target="_blank">
                                            <i class="fas fa-external-link-alt"></i> 공고 상세보기
                                        </a>
                                    </div>
                                </div>
                            @else
                                <!-- 공고 선택 드롭다운 -->
                                <select name="tender_id" id="tender_id" class="form-select" required>
                                    <option value="">제안서를 생성할 공고를 선택해주세요</option>
                                    @foreach(\App\Models\Tender::with('category')->latest('collected_at')->take(50)->get() as $tenderOption)
                                        <option value="{{ $tenderOption->id }}">
                                            {{ Str::limit($tenderOption->title, 80) }} ({{ $tenderOption->tender_no }})
                                        </option>
                                    @endforeach
                                </select>
                                <div class="form-text">
                                    최근 수집된 50개 공고 중에서 선택하실 수 있습니다. 
                                    더 많은 공고는 <a href="{{ route('admin.tenders.index') }}">입찰공고 관리</a>에서 확인 후 생성하세요.
                                </div>
                            @endif
                        </div>

                        <!-- 추가 옵션 -->
                        <div class="mb-4">
                            <label class="form-label"><strong>생성 옵션</strong></label>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="force_refresh" name="force_refresh" value="1">
                                <label class="form-check-label" for="force_refresh">
                                    캐시를 무시하고 새로운 분석 수행
                                </label>
                            </div>
                            <div class="form-text">
                                체크하면 기존 분석 결과를 사용하지 않고 완전히 새로운 분석을 수행합니다.
                            </div>
                        </div>

                        <!-- AI 제안서 생성 프로세스 안내 -->
                        <div class="alert alert-info">
                            <h6><i class="fas fa-robot"></i> AI 제안서 생성 프로세스</h6>
                            <ol class="mb-0">
                                <li><strong>구조 분석</strong>: 공고 내용을 분석하여 최적의 제안서 구조 설계</li>
                                <li><strong>내용 생성</strong>: 타이드플로 회사 정보와 공고 요구사항을 매칭하여 맞춤형 제안서 작성</li>
                                <li><strong>품질 검증</strong>: AI가 생성한 내용의 품질과 적합성 검증</li>
                                <li><strong>최종 완성</strong>: 마크다운 형식으로 제안서 완성 및 다운로드 가능</li>
                            </ol>
                        </div>

                        <!-- 예상 처리 시간 -->
                        <div class="alert alert-warning">
                            <i class="fas fa-clock"></i> <strong>예상 처리 시간:</strong> 약 30초 - 2분
                            <br><small>공고의 복잡도와 서버 상황에 따라 처리 시간이 달라질 수 있습니다.</small>
                        </div>

                        <!-- 버튼 -->
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-lg" onclick="this.disabled=true; this.innerHTML='<i class=\"fas fa-spinner fa-spin\"></i> 생성 중...'; this.form.submit();">
                                <i class="fas fa-magic"></i> AI 제안서 생성 시작
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection