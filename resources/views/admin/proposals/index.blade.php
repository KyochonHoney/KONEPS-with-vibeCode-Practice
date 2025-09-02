@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <!-- 헤더 -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">📝 AI 제안서 관리</h1>
            <p class="mb-0 text-muted">AI 기반 자동 생성 제안서 관리 및 다운로드</p>
        </div>
        <div>
            <a href="{{ route('admin.proposals.create') }}" class="btn btn-primary">
                <i class="fas fa-plus"></i> 새 제안서 생성
            </a>
        </div>
    </div>

    <!-- 통계 카드 -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">총 제안서</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $stats['total'] }}개</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-file-alt fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">완료</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $stats['completed'] }}개</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-check fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">성공률</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $generationStats['success_rate'] }}%</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-percentage fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">평균 처리시간</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ round($generationStats['avg_processing_time']) }}ms</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-clock fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 필터링 및 검색 -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">제안서 목록</h6>
        </div>
        <div class="card-body">
            <form method="GET" class="mb-3">
                <div class="row">
                    <div class="col-md-4">
                        <select name="status" class="form-select">
                            <option value="">전체 상태</option>
                            <option value="processing" {{ request('status') == 'processing' ? 'selected' : '' }}>처리중</option>
                            <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>완료</option>
                            <option value="failed" {{ request('status') == 'failed' ? 'selected' : '' }}>실패</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <input type="text" name="search" class="form-control" placeholder="제안서 제목 또는 공고 검색" value="{{ request('search') }}">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">검색</button>
                    </div>
                </div>
            </form>

            <!-- 제안서 목록 -->
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>제안서 제목</th>
                            <th>공고명</th>
                            <th>상태</th>
                            <th>처리시간</th>
                            <th>생성일시</th>
                            <th>액션</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($proposals as $proposal)
                        <tr>
                            <td>
                                <a href="{{ route('admin.proposals.show', $proposal) }}" class="text-decoration-none">
                                    {{ $proposal->title }}
                                </a>
                            </td>
                            <td>
                                <small class="text-muted">{{ $proposal->tender->tender_no }}</small><br>
                                {{ Str::limit($proposal->tender->title, 50) }}
                            </td>
                            <td>
                                @if($proposal->status == 'completed')
                                    <span class="badge bg-success">완료</span>
                                @elseif($proposal->status == 'processing')
                                    <span class="badge bg-warning">처리중</span>
                                @elseif($proposal->status == 'failed')
                                    <span class="badge bg-danger">실패</span>
                                @endif
                            </td>
                            <td>{{ $proposal->formatted_processing_time }}</td>
                            <td>{{ $proposal->created_at->format('Y-m-d H:i') }}</td>
                            <td>
                                <div class="btn-group" role="group">
                                    <a href="{{ route('admin.proposals.show', $proposal) }}" class="btn btn-sm btn-info">상세</a>
                                    @if($proposal->status == 'completed')
                                        <a href="{{ route('admin.proposals.download', $proposal) }}" class="btn btn-sm btn-success">다운로드</a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="text-center py-4">
                                생성된 제안서가 없습니다.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $proposals->links() }}
        </div>
    </div>
</div>
@endsection