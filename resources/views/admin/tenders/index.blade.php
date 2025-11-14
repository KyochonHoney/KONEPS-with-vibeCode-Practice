{{-- [BEGIN nara:admin_tenders_index] --}}
@extends('layouts.app')

@section('title', '입찰공고 관리')

@section('content')
<style>
    /* 미묘한 배경색 */
    .row-favorite {
        background-color: rgba(255, 245, 200, 0.5) !important; /* 연한 노랑 */
    }
    .row-unsuitable {
        background-color: rgba(255, 200, 200, 0.3) !important; /* 연한 빨강 */
    }
    .row-favorite.row-unsuitable {
        background: linear-gradient(90deg, rgba(255, 245, 200, 0.5) 50%, rgba(255, 200, 200, 0.3) 50%) !important; /* 노랑+빨강 반반 */
    }
</style>
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 text-gray-800">입찰공고 관리</h1>
                <div>
                    <a href="{{ route('admin.tenders.collect') }}" class="btn btn-primary">
                        <i class="bi bi-cloud-download me-1"></i>
                        데이터 수집
                    </a>
                    <button type="button" class="btn btn-warning" id="bulkAnalyzeBtn" disabled>
                        <i class="bi bi-cpu me-1"></i>
                        AI 일괄 분석
                    </button>
                    <button type="button" class="btn btn-success" id="testApiBtn">
                        <i class="bi bi-wifi me-1"></i>
                        API 테스트
                    </button>
                    <a href="{{ route('admin.analyses.index') }}" class="btn btn-info">
                        <i class="bi bi-graph-up me-1"></i>
                        분석 결과
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
                                        전체 공고
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        {{ $stats['total_records'] ?? 0 }}
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="bi bi-file-text fa-2x text-gray-300"></i>
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
                                        활성 공고
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        {{ $stats['active_count'] ?? 0 }}
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="bi bi-check-circle fa-2x text-gray-300"></i>
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
                                        오늘 수집
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        {{ $stats['today_count'] ?? 0 }}
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="bi bi-calendar-day fa-2x text-gray-300"></i>
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
                                        마지막 업데이트
                                    </div>
                                    <div class="h6 mb-0 font-weight-bold text-gray-800">
                                        {{ isset($stats['last_updated']) ? $stats['last_updated'] : '없음' }}
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="bi bi-clock fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 업종코드 패턴별 통계 카드 -->
            <div class="row mb-4">
                @foreach($industryStats as $stat)
                <div class="col-xl-3 col-md-6 mb-3">
                    <div class="card border-left-info shadow h-100">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                        {{ $stat['name'] }}
                                    </div>
                                    <div class="h6 mb-0 font-weight-bold text-gray-800">
                                        {{ $stat['count'] }}건
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <a href="{{ route('admin.tenders.index', ['industry_pattern' => $stat['pattern']]) }}" 
                                       class="btn btn-sm btn-outline-info">
                                        <i class="bi bi-funnel"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>

            <!-- 검색 및 필터 -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">검색 및 필터</h6>
                </div>
                <div class="card-body">
                    <form method="GET" action="{{ route('admin.tenders.index') }}">
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label for="search" class="form-label">검색어</label>
                                <input type="text" class="form-control" id="search" name="search"
                                       value="{{ request('search') }}" placeholder="제목, 기관명, 공고번호">
                            </div>
                            <div class="col-md-2 mb-3">
                                <label for="status" class="form-label">상태</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="">전체</option>
                                    <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>진행중</option>
                                    <option value="closed" {{ request('status') == 'closed' ? 'selected' : '' }}>마감</option>
                                    <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>취소</option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label d-block">&nbsp;</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="favorites_only" name="favorites_only" value="1"
                                           {{ request('favorites_only') == '1' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="favorites_only">
                                        <i class="bi bi-star-fill text-warning"></i> 즐겨찾기
                                    </label>
                                </div>
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" id="has_mention" name="has_mention" value="1"
                                           {{ request('has_mention') == '1' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="has_mention">
                                        <i class="bi bi-chat-left-text-fill text-info"></i> 메모
                                    </label>
                                </div>
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" id="unsuitable_only" name="unsuitable_only" value="1"
                                           {{ request('unsuitable_only') == '1' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="unsuitable_only">
                                        <i class="bi bi-x-circle-fill text-danger"></i> 비적합 공고
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="industry_pattern" class="form-label">업종코드</label>
                                <select class="form-select" id="industry_pattern" name="industry_pattern">
                                    <option value="">전체 업종</option>
                                    <option value="81112002" {{ request('industry_pattern') == '81112002' ? 'selected' : '' }}>데이터처리/빅데이터분석서비스</option>
                                    <option value="81112299" {{ request('industry_pattern') == '81112299' ? 'selected' : '' }}>소프트웨어유지및지원서비스</option>
                                    <option value="81111811" {{ request('industry_pattern') == '81111811' ? 'selected' : '' }}>운영위탁서비스</option>
                                    <option value="81111899" {{ request('industry_pattern') == '81111899' ? 'selected' : '' }}>정보시스템유지관리서비스</option>
                                    <option value="81112199" {{ request('industry_pattern') == '81112199' ? 'selected' : '' }}>인터넷지원개발서비스</option>
                                    <option value="81111598" {{ request('industry_pattern') == '81111598' ? 'selected' : '' }}>패키지소프트웨어/정보시스템개발서비스</option>
                                    <option value="81111599" {{ request('industry_pattern') == '81111599' ? 'selected' : '' }}>시스템소프트웨어개발서비스</option>
                                    <option value="81151699" {{ request('industry_pattern') == '81151699' ? 'selected' : '' }}>공간정보DB구축서비스</option>
                                    <option value="EMPTY" {{ request('industry_pattern') == 'EMPTY' ? 'selected' : '' }}>기타 (업종상세코드 없음)</option>
                                </select>
                            </div>
                            <div class="col-md-1 mb-3 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-search"></i>
                                </button>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-2 mb-3">
                                <label for="date_type" class="form-label">날짜 유형</label>
                                <select class="form-select" id="date_type" name="date_type">
                                    <option value="">선택안함</option>
                                    <option value="deadline" {{ request('date_type') == 'deadline' ? 'selected' : '' }}>마감일</option>
                                    <option value="registered" {{ request('date_type') == 'registered' ? 'selected' : '' }}>등록일시</option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="date_range" class="form-label">기간 선택</label>
                                <input type="text" class="form-control" id="date_range" name="date_range"
                                       value="{{ request('date_range') }}" placeholder="날짜 범위 선택">
                                <input type="hidden" id="date_start" name="date_start" value="{{ request('date_start') }}">
                                <input type="hidden" id="date_end" name="date_end" value="{{ request('date_end') }}">
                            </div>
                            <div class="col-md-4 mb-3 d-flex align-items-end">
                                <a href="{{ route('admin.tenders.index') }}" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-clockwise"></i> 필터 초기화
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- 입찰공고 목록 -->
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">입찰공고 목록</h6>
                    <small class="text-muted">총 {{ $tenders->total() }}건</small>
                </div>
                <div class="card-body">
                    @if($tenders->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th width="3%">
                                            <input type="checkbox" id="selectAll">
                                        </th>
                                        <th width="3%">
                                            <i class="bi bi-star-fill text-warning" title="즐겨찾기"></i>
                                        </th>
                                        <th width="3%">
                                            <i class="bi bi-chat-left-text-fill text-info" title="메모"></i>
                                        </th>
                                        <th width="3%">
                                            <i class="bi bi-hand-thumbs-down-fill text-danger" title="비적합"></i>
                                        </th>
                                        <th width="9%">공고번호</th>
                                        <th width="26%">제목</th>
                                        <th width="12%">기관</th>
                                        <th width="9%">예산</th>
                                        <th width="9%">등록일시</th>
                                        <th width="9%">마감일</th>
                                        <th width="7%">상태</th>
                                        <th width="7%">AI 분석</th>
                                        <th width="6%">액션</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($tenders as $tender)
                                        @php
                                            $rowClass = '';
                                            if ($tender->is_favorite) $rowClass .= 'row-favorite ';
                                            if ($tender->is_unsuitable) $rowClass .= 'row-unsuitable ';
                                        @endphp
                                        <tr class="{{ trim($rowClass) }}">
                                            <td>
                                                <input type="checkbox" class="tender-checkbox" value="{{ $tender->id }}">
                                            </td>
                                            <td class="text-center">
                                                <button type="button"
                                                        class="btn btn-sm btn-link p-0 favorite-btn"
                                                        data-tender-id="{{ $tender->id }}"
                                                        data-is-favorite="{{ $tender->is_favorite ? '1' : '0' }}"
                                                        title="{{ $tender->is_favorite ? '즐겨찾기 제거' : '즐겨찾기 추가' }}">
                                                    <i class="bi {{ $tender->is_favorite ? 'bi-star-fill text-warning' : 'bi-star text-muted' }}"
                                                       style="font-size: 1.2rem; cursor: pointer;"></i>
                                                </button>
                                            </td>
                                            <td class="text-center">
                                                @php
                                                    $mention = $tender->mentions()->where('user_id', auth()->id())->first();
                                                    $hasMention = $mention && !empty(trim($mention->mention));
                                                @endphp
                                                @if($hasMention)
                                                    <i class="bi bi-chat-left-text-fill text-info"
                                                       style="font-size: 1.2rem;"
                                                       title="메모 있음"></i>
                                                @else
                                                    <i class="bi bi-chat-left-text text-muted"
                                                       style="font-size: 1.2rem;"
                                                       title="메모 없음"></i>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                <button class="btn btn-link p-0 toggle-unsuitable-btn"
                                                        data-tender-id="{{ $tender->id }}"
                                                        data-unsuitable="{{ $tender->is_unsuitable ? 'true' : 'false' }}"
                                                        style="border: none; background: none;">
                                                    @if($tender->is_unsuitable)
                                                        <i class="bi bi-hand-thumbs-down-fill text-danger"
                                                           style="font-size: 1.2rem;"
                                                           title="비적합 공고: {{ $tender->unsuitable_reason ?? '이유 없음' }} (클릭하여 적합으로 변경)"
                                                           data-bs-toggle="tooltip"
                                                           data-bs-placement="top"
                                                           data-bs-html="true"></i>
                                                    @else
                                                        <i class="bi bi-hand-thumbs-down text-muted"
                                                           style="font-size: 1.2rem; opacity: 0.3;"
                                                           title="적합 (클릭하여 비적합으로 표시)"></i>
                                                    @endif
                                                </button>
                                            </td>
                                            <td>
                                                <small class="text-muted">{{ $tender->tender_no }}</small>
                                            </td>
                                            <td>
                                                <a href="{{ route('admin.tenders.show', $tender) }}" 
                                                   class="text-decoration-none">
                                                    {{ $tender->short_title }}
                                                </a>
                                                <br>
                                                <small class="text-muted">
                                                    <i class="bi bi-tag"></i> {{ $tender->category->name ?? '미분류' }}
                                                    @if($tender->classification_code)
                                                        <br><small class="text-info">{{ $tender->classification_name }}</small>
                                                    @endif
                                                </small>
                                            </td>
                                            <td>{{ $tender->agency }}</td>
                                            <td>
                                                <strong>{{ $tender->formatted_total_budget }}</strong>
                                                @if($tender->allocated_budget)
                                                    <br>
                                                    <small class="text-muted">추정가: {{ $tender->formatted_allocated_budget }}</small>
                                                @endif
                                            </td>
                                            <td>
                                                @if($tender->start_date)
                                                    <small>{{ \Carbon\Carbon::parse($tender->start_date)->format('Y-m-d') }}</small>
                                                    <br>
                                                    <small class="text-muted">{{ \Carbon\Carbon::parse($tender->start_date)->format('H:i') }}</small>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($tender->formatted_bid_close_date)
                                                    {{ $tender->formatted_bid_close_date }}
                                                    <br>
                                                    <small class="{{ $tender->dday_color_class }}">
                                                        {{ $tender->dday_display }}
                                                    </small>
                                                @else
                                                    <span class="text-muted">미정</span>
                                                @endif
                                            </td>
                                            <td>
                                                <span class="{{ $tender->status_class }}">
                                                    {{ $tender->status_label }}
                                                </span>
                                            </td>
                                            <td>
                                                <div class="analysis-status" data-tender-id="{{ $tender->id }}">
                                                    <span class="badge bg-secondary">미분석</span>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <a href="{{ route('admin.tenders.show', $tender) }}" 
                                                       class="btn btn-sm btn-outline-primary" title="상세보기">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    @if($tender->detail_url && $tender->detail_url !== '#')
                                                        <a href="{{ $tender->detail_url }}" 
                                                           target="_blank" 
                                                           class="btn btn-sm btn-outline-warning" title="나라장터 원본">
                                                            <i class="bi bi-box-arrow-up-right"></i>
                                                        </a>
                                                    @endif
                                                    <button type="button" 
                                                            class="btn btn-sm btn-outline-danger delete-btn" 
                                                            data-id="{{ $tender->id }}" title="삭제">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <!-- 페이지네이션 -->
                        <div class="mt-4">
                            {{ $tenders->links('custom.pagination.bootstrap-4') }}
                        </div>

                        <!-- 일괄 작업 -->
                        <div class="row mt-3">
                            <div class="col-md-6">
                                <div class="input-group">
                                    <select class="form-select" id="bulkAction">
                                        <option value="">일괄 작업 선택</option>
                                        <option value="active">활성으로 변경</option>
                                        <option value="closed">마감으로 변경</option>
                                        <option value="cancelled">취소로 변경</option>
                                    </select>
                                    <button class="btn btn-primary" type="button" id="bulkActionBtn" disabled>
                                        실행
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-6 text-end">
                                <small class="text-muted">
                                    선택된 항목: <span id="selectedCount">0</span>개
                                </small>
                            </div>
                        </div>
                    @else
                        <div class="text-center py-5">
                            <i class="bi bi-inbox display-1 text-muted"></i>
                            <h4 class="text-muted mt-3">입찰공고가 없습니다</h4>
                            <p class="text-muted">
                                데이터 수집을 실행하거나 검색 조건을 변경해보세요.
                            </p>
                            <a href="{{ route('admin.tenders.collect') }}" class="btn btn-primary">
                                <i class="bi bi-cloud-download me-1"></i>
                                데이터 수집하기
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
.border-left-primary {
    border-left: 0.25rem solid #4e73df !important;
}
.border-left-success {
    border-left: 0.25rem solid #1cc88a !important;
}
.border-left-info {
    border-left: 0.25rem solid #36b9cc !important;
}
.border-left-warning {
    border-left: 0.25rem solid #f6c23e !important;
}
.text-xs {
    font-size: 0.7rem;
}
.shadow {
    box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15) !important;
}

/* 페이지네이션 스타일 개선 */
.pagination {
    margin-bottom: 0;
}

.page-link {
    border: 1px solid #dee2e6;
    color: #4e73df;
    padding: 0.5rem 0.75rem;
    margin-left: -1px;
}

.page-link:hover {
    color: #224abe;
    background-color: #e9ecef;
    border-color: #dee2e6;
}

.page-item.active .page-link {
    background-color: #4e73df;
    border-color: #4e73df;
    color: white;
}

.page-item.disabled .page-link {
    color: #6c757d;
    background-color: #fff;
    border-color: #dee2e6;
}

.pagination .page-link {
    border-radius: 0.25rem;
    margin: 0 2px;
}

/* D-Day 관련 스타일 개선 */
.dday-display {
    font-weight: bold;
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 0.85rem;
}

.dday-today {
    background-color: #dc3545;
    color: white;
    animation: blink 1.5s infinite;
}

.dday-urgent {
    background-color: #ffc107;
    color: #000;
}

.dday-warning {
    background-color: #fd7e14;
    color: white;
}

.dday-normal {
    background-color: #198754;
    color: white;
}

.dday-expired {
    background-color: #6c757d;
    color: white;
    text-decoration: line-through;
}

@keyframes blink {
    0%, 50% { opacity: 1; }
    51%, 100% { opacity: 0.3; }
}
</style>
@endpush

@push('scripts')
<!-- Date Range Picker CSS & JS -->
<link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css" />
<script type="text/javascript" src="https://cdn.jsdelivr.net/momentjs/latest/moment.min.js"></script>
<script type="text/javascript" src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>

<script>
$(document).ready(function() {
    // Bootstrap 툴팁 초기화
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Date Range Picker 초기화
    $('#date_range').daterangepicker({
        autoUpdateInput: false,
        locale: {
            format: 'YYYY-MM-DD',
            separator: ' ~ ',
            applyLabel: '적용',
            cancelLabel: '취소',
            fromLabel: '시작',
            toLabel: '종료',
            customRangeLabel: '직접선택',
            weekLabel: 'W',
            daysOfWeek: ['일', '월', '화', '수', '목', '금', '토'],
            monthNames: ['1월', '2월', '3월', '4월', '5월', '6월', '7월', '8월', '9월', '10월', '11월', '12월'],
            firstDay: 0
        },
        ranges: {
            '오늘': [moment(), moment()],
            '어제': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
            '최근 7일': [moment().subtract(6, 'days'), moment()],
            '최근 30일': [moment().subtract(29, 'days'), moment()],
            '이번 달': [moment().startOf('month'), moment().endOf('month')],
            '지난 달': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
        }
    });

    // 날짜 선택 시
    $('#date_range').on('apply.daterangepicker', function(ev, picker) {
        $(this).val(picker.startDate.format('YYYY-MM-DD') + ' ~ ' + picker.endDate.format('YYYY-MM-DD'));
        $('#date_start').val(picker.startDate.format('YYYY-MM-DD'));
        $('#date_end').val(picker.endDate.format('YYYY-MM-DD'));
    });

    // 취소 시
    $('#date_range').on('cancel.daterangepicker', function(ev, picker) {
        $(this).val('');
        $('#date_start').val('');
        $('#date_end').val('');
    });

    // 페이지 로드 시 기존 값이 있으면 표시
    @if(request('date_start') && request('date_end'))
        $('#date_range').val('{{ request("date_start") }} ~ {{ request("date_end") }}');
    @endif

    // 전체 선택 체크박스
    $('#selectAll').change(function() {
        $('.tender-checkbox').prop('checked', $(this).prop('checked'));
        updateSelectedCount();
    });

    // 개별 체크박스
    $(document).on('change', '.tender-checkbox', function() {
        updateSelectedCount();
        
        if ($('.tender-checkbox:checked').length < $('.tender-checkbox').length) {
            $('#selectAll').prop('checked', false);
        } else {
            $('#selectAll').prop('checked', true);
        }
    });

    // API 테스트
    $('#testApiBtn').click(function() {
        const $btn = $(this);
        $btn.prop('disabled', true);
        
        $.get('{{ route("admin.tenders.test_api") }}')
            .done(function(response) {
                alert(response.message);
            })
            .fail(function(xhr) {
                const response = xhr.responseJSON;
                alert(response ? response.message : 'API 테스트 실패');
            })
            .always(function() {
                $btn.prop('disabled', false);
            });
    });

    // 일괄 작업 실행
    $('#bulkActionBtn').click(function() {
        const selectedIds = $('.tender-checkbox:checked').map(function() {
            return $(this).val();
        }).get();
        
        const action = $('#bulkAction').val();
        
        if (selectedIds.length === 0) {
            alert('작업할 항목을 선택해주세요.');
            return;
        }
        
        if (!action) {
            alert('작업을 선택해주세요.');
            return;
        }
        
        if (confirm(`선택된 ${selectedIds.length}개 항목의 상태를 변경하시겠습니까?`)) {
            $.ajax({
                url: '{{ route("admin.tenders.bulk_update_status") }}',
                method: 'PATCH',
                data: {
                    _token: '{{ csrf_token() }}',
                    tender_ids: selectedIds,
                    status: action
                },
                success: function(response) {
                    alert(response.message);
                    location.reload();
                },
                error: function(xhr) {
                    const response = xhr.responseJSON;
                    alert(response ? response.message : '작업 실패');
                }
            });
        }
    });

    // 삭제 버튼
    $(document).on('click', '.delete-btn', function() {
        const tenderId = $(this).data('id');
        
        if (confirm('정말 삭제하시겠습니까?')) {
            $.ajax({
                url: `/admin/tenders/${tenderId}`,
                method: 'DELETE',
                data: {
                    _token: '{{ csrf_token() }}'
                },
                success: function(response) {
                    alert(response.message);
                    location.reload();
                },
                error: function(xhr) {
                    const response = xhr.responseJSON;
                    alert(response ? response.message : '삭제 실패');
                }
            });
        }
    });

    function updateSelectedCount() {
        const count = $('.tender-checkbox:checked').length;
        $('#selectedCount').text(count);
        $('#bulkActionBtn').prop('disabled', count === 0);
        $('#bulkAnalyzeBtn').prop('disabled', count === 0);
    }
    
    // 페이지 로딩 시 AI 분석 상태 확인
    loadAnalysisStatuses();
    
    // AI 분석 상태 로딩
    function loadAnalysisStatuses() {
        const tenderIds = [];
        $('.analysis-status').each(function() {
            tenderIds.push($(this).data('tender-id'));
        });
        
        if (tenderIds.length === 0) return;
        
        $.ajax({
            url: '{{ route("admin.analyses.check_status") }}',
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                tender_ids: tenderIds
            },
            success: function(response) {
                Object.entries(response).forEach(([tenderId, status]) => {
                    const $statusDiv = $(`.analysis-status[data-tender-id="${tenderId}"]`);
                    if (status.has_analysis) {
                        const scoreClass = getScoreColorClass(status.score);
                        const badgeClass = getBadgeClass(status.score);
                        $statusDiv.html(`
                            <span class="${badgeClass}" title="점수: ${status.score}점">
                                ${status.score}점
                            </span>
                        `);
                    }
                });
            },
            error: function(xhr) {
                console.error('Failed to load analysis statuses:', xhr);
            }
        });
    }
    
    // AI 일괄 분석
    $('#bulkAnalyzeBtn').click(function() {
        const selectedIds = $('.tender-checkbox:checked').map(function() {
            return parseInt($(this).val());
        }).get();
        
        if (selectedIds.length === 0) {
            alert('분석할 공고를 선택해주세요.');
            return;
        }
        
        if (selectedIds.length > 10) {
            alert('한 번에 최대 10개까지만 분석할 수 있습니다.');
            return;
        }
        
        const $btn = $(this);
        const originalText = $btn.html();
        
        $btn.prop('disabled', true);
        $btn.html('<i class="bi bi-arrow-clockwise me-1"></i>AI 분석 중...');
        
        $.ajax({
            url: '{{ route("admin.analyses.bulk_analyze") }}',
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                tender_ids: selectedIds
            },
            success: function(response) {
                if (response.success) {
                    showAlert('success', response.message);
                    // 분석 상태 다시 로딩
                    setTimeout(() => {
                        loadAnalysisStatuses();
                    }, 1000);
                } else {
                    showAlert('danger', response.message || 'AI 일괄 분석에 실패했습니다.');
                }
            },
            error: function(xhr) {
                const response = xhr.responseJSON;
                showAlert('danger', response?.message || 'AI 일괄 분석 중 오류가 발생했습니다.');
                console.error('Bulk Analysis Error:', xhr);
            },
            complete: function() {
                setTimeout(() => {
                    $btn.prop('disabled', false);
                    $btn.html(originalText);
                }, 2000);
            }
        });
    });
    
    // 유틸리티 함수들
    function getScoreColorClass(score) {
        if (score >= 80) return 'text-success';
        if (score >= 60) return 'text-info';
        if (score >= 40) return 'text-warning';
        return 'text-danger';
    }
    
    function getBadgeClass(score) {
        if (score >= 80) return 'badge bg-success';
        if (score >= 60) return 'badge bg-info';
        if (score >= 40) return 'badge bg-warning';
        return 'badge bg-danger';
    }
    
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

    // 즐겨찾기 토글
    $(document).on('click', '.favorite-btn', function() {
        const $btn = $(this);
        const tenderId = $btn.data('tender-id');
        const isFavorite = $btn.data('is-favorite') === '1';

        $.ajax({
            url: `/admin/tenders/${tenderId}/toggle-favorite`,
            method: 'PATCH',
            data: {
                _token: '{{ csrf_token() }}'
            },
            success: function(response) {
                if (response.success) {
                    // 별표 아이콘 업데이트
                    const $icon = $btn.find('i');
                    const $row = $btn.closest('tr');

                    if (response.is_favorite) {
                        $icon.removeClass('bi-star text-muted')
                             .addClass('bi-star-fill text-warning');
                        $btn.attr('title', '즐겨찾기 제거');
                        $btn.data('is-favorite', '1');
                        // 행에 미묘한 회색-노랑 배경 추가
                        $row.addClass('row-favorite');
                    } else {
                        $icon.removeClass('bi-star-fill text-warning')
                             .addClass('bi-star text-muted');
                        $btn.attr('title', '즐겨찾기 추가');
                        $btn.data('is-favorite', '0');
                        // 즐겨찾기 배경 제거
                        $row.removeClass('row-favorite');
                    }

                    // 토스트 메시지 (선택사항)
                    // showAlert('success', response.message);
                }
            },
            error: function(xhr) {
                const response = xhr.responseJSON;
                showAlert('danger', response?.message || '즐겨찾기 토글에 실패했습니다.');
            }
        });
    });

    // 비적합 토글 버튼 클릭 이벤트
    $(document).on('click', '.toggle-unsuitable-btn', function(e) {
        e.preventDefault();
        e.stopPropagation();

        const $btn = $(this);
        const tenderId = $btn.data('tender-id');
        const currentUnsuitable = $btn.data('unsuitable') === 'true';
        const $icon = $btn.find('i');

        $.ajax({
            url: `/admin/tenders/${tenderId}/toggle-unsuitable`,
            method: 'PATCH',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                if (response.success) {
                    // 아이콘 변경
                    if (response.is_unsuitable) {
                        $icon.removeClass('bi-hand-thumbs-down text-muted')
                             .addClass('bi-hand-thumbs-down-fill text-danger')
                             .css('opacity', '1')
                             .attr('title', '비적합 공고 (클릭하여 적합으로 변경)');
                        $btn.data('unsuitable', 'true');
                    } else {
                        $icon.removeClass('bi-hand-thumbs-down-fill text-danger')
                             .addClass('bi-hand-thumbs-down text-muted')
                             .css('opacity', '0.3')
                             .attr('title', '적합 (클릭하여 비적합으로 표시)');
                        $btn.data('unsuitable', 'false');
                    }

                    // 토스트 메시지 (선택사항)
                    // showAlert('success', response.message);
                }
            },
            error: function(xhr) {
                const response = xhr.responseJSON;
                showAlert('danger', response?.message || '비적합 표시 토글에 실패했습니다.');
            }
        });
    });

});
</script>
@endpush
{{-- [END nara:admin_tenders_index] --}}