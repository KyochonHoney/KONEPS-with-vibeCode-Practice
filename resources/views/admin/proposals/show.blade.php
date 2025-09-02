@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <!-- 헤더 -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">📄 제안서 상세보기</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('admin.proposals.index') }}">제안서 관리</a></li>
                    <li class="breadcrumb-item active">제안서 상세</li>
                </ol>
            </nav>
        </div>
        <div>
            <a href="{{ route('admin.proposals.index') }}" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> 목록으로
            </a>
            @if($proposal->status == 'completed')
                <a href="{{ route('admin.proposals.download', $proposal) }}" class="btn btn-success">
                    <i class="fas fa-download"></i> 다운로드
                </a>
            @endif
        </div>
    </div>

    <div class="row">
        <!-- 제안서 정보 -->
        <div class="col-md-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">제안서 내용</h6>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <h4>{{ $proposal->title }}</h4>
                            <p class="text-muted">
                                <i class="fas fa-calendar"></i> {{ $proposal->created_at->format('Y년 m월 d일 H:i') }} 생성
                                @if($proposal->generated_at)
                                | <i class="fas fa-check"></i> {{ $proposal->generated_at->format('Y년 m월 d일 H:i') }} 완료
                                @endif
                            </p>
                        </div>
                    </div>

                    @if($proposal->status == 'completed' && $proposal->content)
                        <div class="proposal-content">
                            <div style="background: #f8f9fa; border-radius: 5px; padding: 1rem; max-height: 600px; overflow-y: auto;">
                                {!! nl2br(e($proposal->content)) !!}
                            </div>
                        </div>
                    @elseif($proposal->status == 'processing')
                        <div class="text-center py-4">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">생성 중...</span>
                            </div>
                            <p class="mt-2">제안서를 생성하고 있습니다...</p>
                        </div>
                    @elseif($proposal->status == 'failed')
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle"></i> 제안서 생성이 실패했습니다.
                            @if(isset($proposal->ai_analysis_data['error']))
                                <br><small>{{ $proposal->ai_analysis_data['error'] }}</small>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- 제안서 메타데이터 -->
        <div class="col-md-4">
            <!-- 기본 정보 -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">기본 정보</h6>
                </div>
                <div class="card-body">
                    <table class="table table-sm table-borderless">
                        <tr>
                            <td><strong>상태:</strong></td>
                            <td>
                                @if($proposal->status == 'completed')
                                    <span class="badge bg-success">완료</span>
                                @elseif($proposal->status == 'processing')
                                    <span class="badge bg-warning">처리중</span>
                                @elseif($proposal->status == 'failed')
                                    <span class="badge bg-danger">실패</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <td><strong>처리시간:</strong></td>
                            <td>{{ $proposal->formatted_processing_time }}</td>
                        </tr>
                        <tr>
                            <td><strong>템플릿 버전:</strong></td>
                            <td>{{ $proposal->template_version ?? 'N/A' }}</td>
                        </tr>
                        <tr>
                            <td><strong>생성자:</strong></td>
                            <td>{{ $proposal->user->name }}</td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- 공고 정보 -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">대상 공고</h6>
                </div>
                <div class="card-body">
                    <h6>{{ Str::limit($proposal->tender->title, 60) }}</h6>
                    <p class="text-muted small">
                        공고번호: {{ $proposal->tender->tender_no }}<br>
                        발주기관: {{ $proposal->tender->agency ?? $proposal->tender->ntce_instt_nm }}<br>
                        예산: {{ $proposal->tender->budget_formatted }}
                    </p>
                    <a href="{{ route('admin.tenders.show', $proposal->tender) }}" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-eye"></i> 공고 상세보기
                    </a>
                </div>
            </div>

            <!-- AI 분석 정보 -->
            @if($proposal->ai_analysis_data)
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">AI 분석 정보</h6>
                </div>
                <div class="card-body">
                    <table class="table table-sm table-borderless">
                        @if(isset($proposal->ai_analysis_data['generation_quality']))
                        <tr>
                            <td><strong>생성 품질:</strong></td>
                            <td>{{ $proposal->ai_analysis_data['generation_quality'] }}</td>
                        </tr>
                        @endif
                        @if(isset($proposal->ai_analysis_data['confidence_score']))
                        <tr>
                            <td><strong>신뢰도:</strong></td>
                            <td>{{ $proposal->ai_analysis_data['confidence_score'] }}점</td>
                        </tr>
                        @endif
                        @if(isset($proposal->ai_analysis_data['sections_count']))
                        <tr>
                            <td><strong>섹션 수:</strong></td>
                            <td>{{ $proposal->ai_analysis_data['sections_count'] }}개</td>
                        </tr>
                        @endif
                        @if(isset($proposal->ai_analysis_data['estimated_pages']))
                        <tr>
                            <td><strong>예상 페이지:</strong></td>
                            <td>{{ $proposal->ai_analysis_data['estimated_pages'] }}페이지</td>
                        </tr>
                        @endif
                    </table>
                </div>
            </div>
            @endif

            <!-- 액션 버튼 -->
            @if($proposal->status == 'completed')
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">액션</h6>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.proposals.regenerate', $proposal) }}" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-warning btn-sm mb-2 w-100" 
                                onclick="return confirm('제안서를 재생성하시겠습니까?')">
                            <i class="fas fa-redo"></i> 재생성
                        </button>
                    </form>
                    
                    <form method="POST" action="{{ route('admin.proposals.destroy', $proposal) }}" class="d-inline">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger btn-sm w-100" 
                                onclick="return confirm('제안서를 삭제하시겠습니까?')">
                            <i class="fas fa-trash"></i> 삭제
                        </button>
                    </form>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection