{{-- [BEGIN nara:analyses_index] --}}
@extends('layouts.app')

@section('title', 'AI 분석 결과 관리')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 text-gray-800">
                    <i class="bi bi-cpu me-2"></i>AI 분석 결과 관리
                </h1>
                <div>
                    <a href="{{ route('admin.tenders.index') }}" class="btn btn-primary">
                        <i class="bi bi-file-text me-1"></i>
                        공고 관리
                    </a>
                </div>
            </div>

            <!-- 통계 카드 -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-primary shadow h-100">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                        전체 분석
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        {{ $stats['total_analyses'] ?? 0 }}
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="bi bi-graph-up fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-success shadow h-100">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                        적극 추천
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        {{ $stats['highly_recommended'] ?? 0 }}
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="bi bi-hand-thumbs-up fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-info shadow h-100">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                        평균 점수
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        {{ $stats['average_score'] ?? 0 }}점
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="bi bi-bar-chart fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-warning shadow h-100">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                        최근 7일
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        {{ $stats['recent_count'] ?? 0 }}
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="bi bi-calendar-week fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 필터 및 검색 -->
            <div class="card shadow mb-4">
                <div class="card-body">
                    <form method="GET" action="{{ route('admin.analyses.index') }}">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="recommendation" class="form-label">추천도 필터</label>
                                <select class="form-select" id="recommendation" name="recommendation">
                                    <option value="">전체</option>
                                    <option value="highly_recommended" {{ request('recommendation') === 'highly_recommended' ? 'selected' : '' }}>
                                        적극 추천 (80점 이상)
                                    </option>
                                    <option value="recommended" {{ request('recommendation') === 'recommended' ? 'selected' : '' }}>
                                        추천 (60-79점)
                                    </option>
                                    <option value="consider" {{ request('recommendation') === 'consider' ? 'selected' : '' }}>
                                        검토 권장 (40-59점)
                                    </option>
                                    <option value="not_recommended" {{ request('recommendation') === 'not_recommended' ? 'selected' : '' }}>
                                        비추천 (40점 미만)
                                    </option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="min_score" class="form-label">최소 점수</label>
                                <input type="number" class="form-control" id="min_score" name="min_score" 
                                       min="0" max="100" value="{{ request('min_score') }}" placeholder="0-100">
                            </div>
                            <div class="col-md-4 mb-3 d-flex align-items-end">
                                <div class="btn-group w-100">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-search me-1"></i>검색
                                    </button>
                                    <a href="{{ route('admin.analyses.index') }}" class="btn btn-outline-secondary">
                                        <i class="bi bi-arrow-clockwise me-1"></i>초기화
                                    </a>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- 분석 결과 목록 -->
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="bi bi-list me-2"></i>분석 결과 목록
                        <span class="text-muted">(총 {{ $analyses->total() }}건)</span>
                    </h6>
                </div>
                <div class="card-body">
                    @if($analyses->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead>
                                    <tr>
                                        <th width="5%">ID</th>
                                        <th width="30%">공고명</th>
                                        <th width="10%">공고번호</th>
                                        <th width="8%">총점</th>
                                        <th width="10%">추천도</th>
                                        <th width="10%">기술적 적합성</th>
                                        <th width="10%">사업 영역</th>
                                        <th width="10%">분석일시</th>
                                        <th width="7%">액션</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($analyses as $analysis)
                                        <tr>
                                            <td>{{ $analysis->id }}</td>
                                            <td>
                                                <a href="{{ route('admin.analyses.show', $analysis) }}" 
                                                   class="text-decoration-none">
                                                    {{ Str::limit($analysis->tender->title, 60) }}
                                                </a>
                                            </td>
                                            <td>
                                                <small class="text-muted">{{ $analysis->tender->tender_no }}</small>
                                            </td>
                                            <td>
                                                <strong class="{{ $analysis->score_color_class }}">
                                                    {{ $analysis->total_score }}점
                                                </strong>
                                            </td>
                                            <td>
                                                <span class="badge {{ $analysis->score_color_class === 'text-success' ? 'bg-success' : ($analysis->score_color_class === 'text-info' ? 'bg-info' : ($analysis->score_color_class === 'text-warning' ? 'bg-warning' : 'bg-danger')) }}">
                                                    {{ $analysis->recommendation_text }}
                                                </span>
                                            </td>
                                            <td>{{ $analysis->technical_score }}점</td>
                                            <td>{{ $analysis->experience_score }}점</td>
                                            <td>
                                                <small>{{ $analysis->completed_at ? $analysis->completed_at->format('m-d H:i') : '처리중' }}</small>
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <a href="{{ route('admin.analyses.show', $analysis) }}" 
                                                       class="btn btn-sm btn-outline-primary" title="상세보기">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    <a href="{{ route('admin.tenders.show', $analysis->tender) }}" 
                                                       class="btn btn-sm btn-outline-info" title="원본 공고">
                                                        <i class="bi bi-file-text"></i>
                                                    </a>
                                                    <button type="button" 
                                                            class="btn btn-sm btn-outline-danger delete-btn" 
                                                            data-id="{{ $analysis->id }}" title="삭제">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <!-- 페이징 -->
                        <div class="d-flex justify-content-center mt-3">
                            {{ $analyses->links('custom.pagination.bootstrap-4') }}
                        </div>
                    @else
                        <div class="text-center py-5">
                            <i class="bi bi-inbox text-gray-300" style="font-size: 4rem;"></i>
                            <h5 class="text-gray-600 mt-3">분석 결과가 없습니다</h5>
                            <p class="text-gray-500">공고를 분석하여 결과를 확인해보세요.</p>
                            <a href="{{ route('admin.tenders.index') }}" class="btn btn-primary">
                                <i class="bi bi-file-text me-1"></i>공고 관리로 이동
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    // 삭제 버튼
    $(document).on('click', '.delete-btn', function() {
        const analysisId = $(this).data('id');
        
        if (confirm('이 분석 결과를 정말 삭제하시겠습니까?\n삭제된 데이터는 복구할 수 없습니다.')) {
            $.ajax({
                url: `/admin/analyses/${analysisId}`,
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    if (response.success) {
                        showAlert('success', response.message);
                        setTimeout(() => {
                            location.reload();
                        }, 1500);
                    } else {
                        showAlert('danger', response.message || '삭제에 실패했습니다.');
                    }
                },
                error: function(xhr) {
                    const response = xhr.responseJSON;
                    showAlert('danger', response?.message || '삭제 중 오류가 발생했습니다.');
                }
            });
        }
    });
    
    // 알림 메시지 표시 함수
    function showAlert(type, message) {
        const alertClass = `alert alert-${type} alert-dismissible fade show`;
        const alertHtml = `
            <div class="${alertClass}" role="alert">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
        
        // 기존 알림 제거
        $('.alert').remove();
        
        // 새 알림 추가
        $('.container-fluid').prepend(alertHtml);
        
        // 5초 후 자동 제거
        setTimeout(() => {
            $('.alert').alert('close');
        }, 5000);
    }
});
</script>
@endpush
{{-- [END nara:analyses_index] --}}