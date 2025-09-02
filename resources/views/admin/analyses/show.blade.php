{{-- [BEGIN nara:analyses_show] --}}
@extends('layouts.app')

@section('title', 'AI 분석 결과 상세')

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
                                <a href="{{ route('admin.analyses.index') }}">AI 분석 결과</a>
                            </li>
                            <li class="breadcrumb-item active">분석 상세</li>
                        </ol>
                    </nav>
                    <h1 class="h3 text-gray-800">
                        <i class="bi bi-cpu me-2"></i>AI 분석 결과 상세
                    </h1>
                </div>
                <div>
                    <a href="{{ route('admin.analyses.index') }}" class="btn btn-secondary">
                        <i class="bi bi-arrow-left me-1"></i>
                        목록으로
                    </a>
                    <a href="{{ route('admin.tenders.show', $analysis->tender) }}" class="btn btn-primary">
                        <i class="bi bi-file-text me-1"></i>
                        원본 공고 보기
                    </a>
                </div>
            </div>

            <div class="row">
                <!-- 좌측 컬럼 - 분석 결과 -->
                <div class="col-lg-8">
                    <!-- 분석 점수 요약 -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="bi bi-graph-up me-2"></i>분석 점수 요약
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-md-3 mb-3">
                                    <div class="card border-left-primary h-100">
                                        <div class="card-body py-3">
                                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                                총점
                                            </div>
                                            <div class="h4 mb-0 font-weight-bold {{ $analysis->score_color_class }}">
                                                {{ $analysis->total_score }}점
                                            </div>
                                            <small class="text-muted">/100점</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <div class="card border-left-success h-100">
                                        <div class="card-body py-3">
                                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                                기술적 적합성
                                            </div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                                {{ $analysis->technical_score }}점
                                            </div>
                                            <small class="text-muted">/40점</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <div class="card border-left-info h-100">
                                        <div class="card-body py-3">
                                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                                사업 영역 적합성
                                            </div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                                {{ $analysis->experience_score }}점
                                            </div>
                                            <small class="text-muted">/25점</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <div class="card border-left-warning h-100">
                                        <div class="card-body py-3">
                                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                                프로젝트 규모
                                            </div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                                {{ $analysis->budget_score }}점
                                            </div>
                                            <small class="text-muted">/20점</small>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- 추천도 -->
                            <div class="text-center mt-4 p-3 rounded" style="background-color: #f8f9fc;">
                                <h5 class="mb-2">추천도</h5>
                                <h3 class="mb-2 {{ $analysis->score_color_class }}">
                                    {{ $analysis->recommendation_text }}
                                </h3>
                                @if(isset($analysis->analysis_data['recommendation']))
                                    <p class="text-muted mb-0">{{ $analysis->analysis_data['recommendation'] }}</p>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- 상세 분석 결과 -->
                    @if($analysis->analysis_data && is_array($analysis->analysis_data))
                        <!-- 기술적 분석 -->
                        @if(isset($analysis->analysis_data['technical_analysis']))
                            <div class="card shadow mb-4">
                                <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-success">
                                        <i class="bi bi-gear me-2"></i>기술적 적합성 분석
                                    </h6>
                                </div>
                                <div class="card-body">
                                    @php $technical = $analysis->analysis_data['technical_analysis'] @endphp
                                    
                                    @if(isset($technical['matched_keywords']) && count($technical['matched_keywords']) > 0)
                                        <div class="mb-3">
                                            <strong class="text-success">매칭된 기술 키워드:</strong>
                                            <div class="mt-2">
                                                @foreach($technical['matched_keywords'] as $keyword)
                                                    <span class="badge bg-success me-1 mb-1">{{ $keyword }}</span>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                    
                                    @if(isset($technical['keyword_count']))
                                        <div class="row">
                                            <div class="col-sm-6">
                                                <p><strong>발견된 키워드:</strong> {{ $technical['keyword_count'] }}개</p>
                                            </div>
                                            <div class="col-sm-6">
                                                <p><strong>전체 키워드:</strong> {{ $technical['total_keywords'] ?? 0 }}개</p>
                                            </div>
                                        </div>
                                    @endif
                                    
                                    @if(isset($technical['analysis']))
                                        <div class="alert alert-light">
                                            <strong>분석 내용:</strong> {{ $technical['analysis'] }}
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endif

                        <!-- 사업 영역 분석 -->
                        @if(isset($analysis->analysis_data['business_analysis']))
                            <div class="card shadow mb-4">
                                <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-info">
                                        <i class="bi bi-building me-2"></i>사업 영역 적합성 분석
                                    </h6>
                                </div>
                                <div class="card-body">
                                    @php $business = $analysis->analysis_data['business_analysis'] @endphp
                                    
                                    @if(isset($business['industry_code']))
                                        <div class="row mb-3">
                                            <div class="col-sm-4">
                                                <strong>업종코드:</strong>
                                            </div>
                                            <div class="col-sm-8">
                                                <span class="badge bg-info">{{ $business['industry_code'] }}</span>
                                            </div>
                                        </div>
                                    @endif
                                    
                                    @if(isset($business['industry_score']) && isset($business['business_area_score']))
                                        <div class="row">
                                            <div class="col-sm-6">
                                                <p><strong>업종 점수:</strong> {{ $business['industry_score'] }}점</p>
                                            </div>
                                            <div class="col-sm-6">
                                                <p><strong>사업 분야 점수:</strong> {{ $business['business_area_score'] }}점</p>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endif

                        <!-- 주요 인사이트 -->
                        @if(isset($analysis->analysis_data['key_insights']) && is_array($analysis->analysis_data['key_insights']))
                            <div class="card shadow mb-4">
                                <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-warning">
                                        <i class="bi bi-lightbulb me-2"></i>주요 인사이트
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <ul class="list-unstyled">
                                        @foreach($analysis->analysis_data['key_insights'] as $insight)
                                            <li class="mb-2">
                                                <i class="bi bi-check-circle-fill text-success me-2"></i>
                                                {{ $insight }}
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            </div>
                        @endif
                    @endif
                </div>

                <!-- 우측 컬럼 - 공고 정보 및 메타데이터 -->
                <div class="col-lg-4">
                    <!-- 공고 기본 정보 -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="bi bi-file-text me-2"></i>분석 대상 공고
                            </h6>
                        </div>
                        <div class="card-body">
                            <h6 class="text-primary">{{ $analysis->tender->title }}</h6>
                            
                            <div class="mt-3">
                                <div class="row mb-2">
                                    <div class="col-sm-5"><strong>공고번호:</strong></div>
                                    <div class="col-sm-7">
                                        <small class="text-muted">{{ $analysis->tender->tender_no }}</small>
                                    </div>
                                </div>
                                
                                <div class="row mb-2">
                                    <div class="col-sm-5"><strong>발주기관:</strong></div>
                                    <div class="col-sm-7">
                                        <small>{{ $analysis->tender->dmnd_instt_nm ?: '미정' }}</small>
                                    </div>
                                </div>
                                
                                <div class="row mb-2">
                                    <div class="col-sm-5"><strong>예산:</strong></div>
                                    <div class="col-sm-7">
                                        <small>{{ $analysis->tender->formatted_budget }}</small>
                                    </div>
                                </div>
                                
                                <div class="row mb-2">
                                    <div class="col-sm-5"><strong>상태:</strong></div>
                                    <div class="col-sm-7">
                                        <span class="{{ $analysis->tender->status_class }}">
                                            {{ $analysis->tender->status_label }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 분석 메타정보 -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-secondary">
                                <i class="bi bi-info-circle me-2"></i>분석 정보
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="row mb-2">
                                <div class="col-sm-5"><strong>분석 ID:</strong></div>
                                <div class="col-sm-7">#{{ $analysis->id }}</div>
                            </div>
                            
                            <div class="row mb-2">
                                <div class="col-sm-5"><strong>분석 일시:</strong></div>
                                <div class="col-sm-7">
                                    <small>{{ $analysis->completed_at ? $analysis->completed_at->format('Y-m-d H:i:s') : '처리중' }}</small>
                                </div>
                            </div>
                            
                            <div class="row mb-2">
                                <div class="col-sm-5"><strong>처리 시간:</strong></div>
                                <div class="col-sm-7">
                                    <small>{{ $analysis->processing_time ?? 0 }}ms</small>
                                </div>
                            </div>
                            
                            <div class="row mb-2">
                                <div class="col-sm-5"><strong>AI 모델:</strong></div>
                                <div class="col-sm-7">
                                    <small>{{ $analysis->ai_model_version ?? 'tideflo-v1.0' }}</small>
                                </div>
                            </div>
                            
                            @if($analysis->user)
                                <div class="row mb-2">
                                    <div class="col-sm-5"><strong>분석 요청자:</strong></div>
                                    <div class="col-sm-7">
                                        <small>{{ $analysis->user->name }}</small>
                                    </div>
                                </div>
                            @endif
                            
                            <div class="row">
                                <div class="col-sm-5"><strong>상태:</strong></div>
                                <div class="col-sm-7">
                                    @if($analysis->status === 'completed')
                                        <span class="badge bg-success">완료</span>
                                    @elseif($analysis->status === 'processing')
                                        <span class="badge bg-warning">처리중</span>
                                    @elseif($analysis->status === 'failed')
                                        <span class="badge bg-danger">실패</span>
                                    @else
                                        <span class="badge bg-secondary">대기</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 액션 버튼들 -->
                    <div class="card shadow">
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <button type="button" class="btn btn-warning" id="reAnalyzeBtn">
                                    <i class="bi bi-arrow-clockwise me-1"></i>
                                    재분석 실행
                                </button>
                                
                                <a href="{{ route('admin.analyses.index') }}" class="btn btn-info">
                                    <i class="bi bi-list me-1"></i>
                                    전체 분석 결과
                                </a>
                                
                                <button type="button" class="btn btn-outline-danger" id="deleteAnalysisBtn">
                                    <i class="bi bi-trash me-1"></i>
                                    분석 결과 삭제
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    // 재분석 실행
    $('#reAnalyzeBtn').click(function() {
        const $btn = $(this);
        const originalText = $btn.html();
        
        if (!confirm('현재 분석 결과를 새로 분석하시겠습니까?')) {
            return;
        }
        
        $btn.prop('disabled', true);
        $btn.html('<i class="bi bi-arrow-clockwise me-1"></i>재분석 중...');
        
        $.ajax({
            url: '{{ route("admin.analyses.analyze", $analysis->tender) }}',
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                if (response.success) {
                    showAlert('success', '재분석이 완료되었습니다. 페이지를 새로고침합니다.');
                    setTimeout(() => {
                        location.reload();
                    }, 2000);
                } else {
                    showAlert('danger', response.message || '재분석에 실패했습니다.');
                }
            },
            error: function(xhr) {
                const response = xhr.responseJSON;
                showAlert('danger', response?.message || '재분석 중 오류가 발생했습니다.');
            },
            complete: function() {
                setTimeout(() => {
                    $btn.prop('disabled', false);
                    $btn.html(originalText);
                }, 2000);
            }
        });
    });
    
    // 분석 결과 삭제
    $('#deleteAnalysisBtn').click(function() {
        if (!confirm('이 분석 결과를 정말 삭제하시겠습니까?\n삭제된 데이터는 복구할 수 없습니다.')) {
            return;
        }
        
        $.ajax({
            url: '{{ route("admin.analyses.destroy", $analysis) }}',
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                if (response.success) {
                    showAlert('success', '분석 결과가 삭제되었습니다. 목록으로 이동합니다.');
                    setTimeout(() => {
                        window.location.href = '{{ route("admin.analyses.index") }}';
                    }, 2000);
                } else {
                    showAlert('danger', response.message || '삭제에 실패했습니다.');
                }
            },
            error: function(xhr) {
                const response = xhr.responseJSON;
                showAlert('danger', response?.message || '삭제 중 오류가 발생했습니다.');
            }
        });
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
{{-- [END nara:analyses_show] --}}