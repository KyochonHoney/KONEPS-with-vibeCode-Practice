{{-- [BEGIN nara:admin_tenders_show] --}}
@extends('layouts.app')

@section('title', '입찰공고 상세 - ' . $tender->title)

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <!-- 페이지 헤더 -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item">
                                <a href="{{ route('admin.tenders.index') }}">입찰공고 관리</a>
                            </li>
                            <li class="breadcrumb-item active">공고 상세</li>
                        </ol>
                    </nav>
                    <h1 class="h3 text-gray-800">입찰공고 상세정보</h1>
                </div>
                <div>
                    <a href="{{ route('admin.tenders.index') }}" class="btn btn-secondary">
                        <i class="bi bi-arrow-left me-1"></i>
                        목록으로
                    </a>
                    @if($tender->source_url && $tender->source_url !== '#')
                        <a href="{{ $tender->source_url }}" target="_blank" class="btn btn-primary">
                            <i class="bi bi-box-arrow-up-right me-1"></i>
                            원본 보기
                        </a>
                    @endif
                </div>
            </div>

            <div class="row">
                <!-- 기본 정보 -->
                <div class="col-lg-8">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">기본 정보</h6>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-sm-3">
                                    <strong>공고번호:</strong>
                                </div>
                                <div class="col-sm-9">
                                    {{ $tender->tender_no }}
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-sm-3">
                                    <strong>제목:</strong>
                                </div>
                                <div class="col-sm-9">
                                    <h5 class="text-primary">{{ $tender->title }}</h5>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-sm-3">
                                    <strong>내용:</strong>
                                </div>
                                <div class="col-sm-9">
                                    <div class="border rounded p-3 bg-light">
                                        {!! nl2br(e($tender->content)) !!}
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-sm-3">
                                    <strong>발주기관:</strong>
                                </div>
                                <div class="col-sm-9">
                                    <span class="badge badge-info">{{ $tender->agency }}</span>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-sm-3">
                                    <strong>분류:</strong>
                                </div>
                                <div class="col-sm-9">
                                    <span class="badge badge-secondary">
                                        {{ $tender->category->name ?? '미분류' }}
                                    </span>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-sm-3">
                                    <strong>지역:</strong>
                                </div>
                                <div class="col-sm-9">
                                    {{ $tender->region ?? '전국' }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 상태 및 부가 정보 -->
                <div class="col-lg-4">
                    <!-- 상태 정보 -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">상태 정보</h6>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <strong>현재 상태:</strong><br>
                                <span class="{{ $tender->status_class }} fs-6">
                                    {{ $tender->status_label }}
                                </span>
                            </div>
                            
                            <div class="mb-3">
                                <strong>예산:</strong><br>
                                <span class="h5 text-success">
                                    {{ $tender->formatted_budget }}
                                    <small class="text-muted">({{ $tender->currency }})</small>
                                </span>
                            </div>
                            
                            <div class="mb-3">
                                <strong>공고 기간:</strong><br>
                                <span class="text-muted">{{ $tender->period }}</span>
                            </div>
                            
                            @if($tender->days_remaining !== null)
                                <div class="mb-3">
                                    <strong>남은 기간:</strong><br>
                                    <span class="badge {{ $tender->days_remaining <= 3 ? 'bg-danger' : 'bg-warning' }}">
                                        D-{{ $tender->days_remaining }}
                                    </span>
                                </div>
                            @endif
                            
                            <div class="mb-3">
                                <strong>수집일시:</strong><br>
                                <small class="text-muted">
                                    {{ $tender->collected_at ? $tender->collected_at->format('Y-m-d H:i:s') : '알 수 없음' }}
                                </small>
                            </div>
                        </div>
                    </div>

                    <!-- 상태 변경 -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">상태 변경</h6>
                        </div>
                        <div class="card-body">
                            <form id="statusUpdateForm">
                                @csrf
                                <div class="mb-3">
                                    <select class="form-select" id="status" name="status">
                                        <option value="active" {{ $tender->status == 'active' ? 'selected' : '' }}>진행중</option>
                                        <option value="closed" {{ $tender->status == 'closed' ? 'selected' : '' }}>마감</option>
                                        <option value="cancelled" {{ $tender->status == 'cancelled' ? 'selected' : '' }}>취소</option>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-primary btn-sm w-100">
                                    <i class="bi bi-check-circle me-1"></i>
                                    상태 업데이트
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- 액션 버튼 -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">작업</h6>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <button type="button" class="btn btn-warning" id="analyzeBtn">
                                    <i class="bi bi-cpu me-1"></i>
                                    AI 분석 실행
                                </button>
                                <button type="button" class="btn btn-info" id="generateProposalBtn">
                                    <i class="bi bi-file-text me-1"></i>
                                    제안서 생성
                                </button>
                                <hr>
                                <button type="button" class="btn btn-outline-danger" id="deleteBtn">
                                    <i class="bi bi-trash me-1"></i>
                                    공고 삭제
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 메타데이터 (개발자용) -->
            @if($tender->metadata && auth()->user()->role === 'super_admin')
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-secondary">메타데이터 (개발자 전용)</h6>
                    </div>
                    <div class="card-body">
                        <div class="bg-light border rounded p-3">
                            <pre class="mb-0"><code>{{ json_encode($tender->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</code></pre>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    // 상태 업데이트
    $('#statusUpdateForm').submit(function(e) {
        e.preventDefault();
        
        const formData = $(this).serialize();
        const $btn = $(this).find('button[type="submit"]');
        
        $btn.prop('disabled', true);
        
        $.ajax({
            url: '{{ route("admin.tenders.update_status", $tender) }}',
            method: 'PATCH',
            data: formData,
            success: function(response) {
                alert(response.message);
                location.reload();
            },
            error: function(xhr) {
                const response = xhr.responseJSON;
                alert(response ? response.message : '상태 업데이트 실패');
            },
            complete: function() {
                $btn.prop('disabled', false);
            }
        });
    });

    // AI 분석 실행 (placeholder)
    $('#analyzeBtn').click(function() {
        alert('AI 분석 기능은 Phase 3에서 구현됩니다.');
    });

    // 제안서 생성 (placeholder)
    $('#generateProposalBtn').click(function() {
        alert('제안서 생성 기능은 Phase 4에서 구현됩니다.');
    });

    // 삭제 버튼
    $('#deleteBtn').click(function() {
        if (confirm('정말 이 공고를 삭제하시겠습니까?\n삭제된 데이터는 복구할 수 없습니다.')) {
            $.ajax({
                url: '{{ route("admin.tenders.destroy", $tender) }}',
                method: 'DELETE',
                data: {
                    _token: '{{ csrf_token() }}'
                },
                success: function(response) {
                    alert(response.message);
                    window.location.href = '{{ route("admin.tenders.index") }}';
                },
                error: function(xhr) {
                    const response = xhr.responseJSON;
                    alert(response ? response.message : '삭제 실패');
                }
            });
        }
    });
});
</script>
@endpush
{{-- [END nara:admin_tenders_show] --}}