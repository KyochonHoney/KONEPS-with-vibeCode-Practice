{{-- [BEGIN nara:admin_tenders_collect] --}}
@extends('layouts.app')

@section('title', '데이터 수집')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item">
                                <a href="{{ route('admin.tenders.index') }}">입찰공고 관리</a>
                            </li>
                            <li class="breadcrumb-item active">데이터 수집</li>
                        </ol>
                    </nav>
                    <h1 class="h3 text-gray-800">나라장터 데이터 수집</h1>
                </div>
                <div>
                    <a href="{{ route('admin.tenders.index') }}" class="btn btn-secondary">
                        <i class="bi bi-arrow-left me-1"></i>
                        목록으로
                    </a>
                </div>
            </div>

            <div class="row">
                <!-- 수집 현황 -->
                <div class="col-lg-4">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="bi bi-bar-chart-line me-1"></i>
                                수집 현황
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <div class="d-flex justify-content-between">
                                    <span>전체 공고:</span>
                                    <strong>{{ $stats['total_records'] ?? 0 }}건</strong>
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between">
                                    <span>활성 공고:</span>
                                    <strong class="text-success">{{ $stats['active_count'] ?? 0 }}건</strong>
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between">
                                    <span>오늘 수집:</span>
                                    <strong class="text-info">{{ $stats['today_count'] ?? 0 }}건</strong>
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between">
                                    <span>마지막 수집:</span>
                                    <small>{{ $stats['last_updated'] ?? '없음' }}</small>
                                </div>
                            </div>
                            <hr>
                            <div class="text-center">
                                <button type="button" class="btn btn-outline-primary btn-sm" id="refreshStatsBtn">
                                    <i class="bi bi-arrow-clockwise me-1"></i>
                                    현황 새로고침
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- API 연결 상태 -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="bi bi-wifi me-1"></i>
                                API 연결 상태
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="text-center">
                                <div id="apiStatus" class="mb-3">
                                    <i class="bi bi-question-circle display-6 text-muted"></i>
                                    <p class="text-muted mt-2">상태 확인 중...</p>
                                </div>
                                <button type="button" class="btn btn-primary" id="testApiBtn">
                                    <i class="bi bi-wifi me-1"></i>
                                    연결 테스트
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 수집 실행 -->
                <div class="col-lg-8">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="bi bi-cloud-download me-1"></i>
                                수집 실행
                            </h6>
                        </div>
                        <div class="card-body">
                            <form id="collectForm">
                                @csrf
                                <div class="row mb-4">
                                    <div class="col-md-12">
                                        <label class="form-label">수집 범위</label>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="type" id="today" value="today" checked>
                                            <label class="form-check-label" for="today">
                                                <strong>오늘 공고</strong>
                                                <small class="text-muted d-block">오늘 등록된 입찰공고를 수집합니다.</small>
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="type" id="recent" value="recent">
                                            <label class="form-check-label" for="recent">
                                                <strong>최근 7일</strong>
                                                <small class="text-muted d-block">최근 7일간 등록된 입찰공고를 수집합니다.</small>
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="type" id="custom" value="custom">
                                            <label class="form-check-label" for="custom">
                                                <strong>기간 지정</strong>
                                                <small class="text-muted d-block">원하는 기간을 지정하여 수집합니다.</small>
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <!-- 사용자 지정 기간 -->
                                <div id="customDateRange" class="row mb-4" style="display: none;">
                                    <div class="col-md-6">
                                        <label for="start_date" class="form-label">시작일</label>
                                        <input type="date" class="form-control" id="start_date" name="start_date" 
                                               value="{{ date('Y-m-d', strtotime('-7 days')) }}">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="end_date" class="form-label">종료일</label>
                                        <input type="date" class="form-control" id="end_date" name="end_date" 
                                               value="{{ date('Y-m-d') }}">
                                    </div>
                                </div>

                                <div class="text-center">
                                    <button type="submit" class="btn btn-success btn-lg" id="collectBtn">
                                        <i class="bi bi-cloud-download me-1"></i>
                                        데이터 수집 시작
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- 수집 진행 상황 -->
                    <div class="card shadow mb-4" id="progressCard" style="display: none;">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="bi bi-hourglass-split me-1"></i>
                                수집 진행 상황
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="progress mb-3" style="height: 25px;">
                                <div class="progress-bar progress-bar-striped progress-bar-animated" 
                                     role="progressbar" id="progressBar" style="width: 0%">
                                    0%
                                </div>
                            </div>
                            <div id="progressText" class="text-center text-muted">
                                수집 준비 중...
                            </div>
                        </div>
                    </div>

                    <!-- 수집 결과 -->
                    <div class="card shadow mb-4" id="resultCard" style="display: none;">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="bi bi-check-circle me-1"></i>
                                수집 결과
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="row text-center" id="resultContent">
                                <!-- 동적으로 채워질 영역 -->
                            </div>
                            <div class="text-center mt-3">
                                <a href="{{ route('admin.tenders.index') }}" class="btn btn-primary">
                                    <i class="bi bi-list me-1"></i>
                                    수집된 공고 보기
                                </a>
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
    // 페이지 로드 시 API 연결 상태 확인
    checkApiStatus();

    // 수집 범위 변경 시 사용자 지정 날짜 표시/숨김
    $('input[name="type"]').change(function() {
        if ($(this).val() === 'custom') {
            $('#customDateRange').show();
        } else {
            $('#customDateRange').hide();
        }
    });

    // API 연결 테스트
    $('#testApiBtn').click(function() {
        checkApiStatus();
    });

    // 현황 새로고침
    $('#refreshStatsBtn').click(function() {
        location.reload();
    });

    // 수집 실행
    $('#collectForm').submit(function(e) {
        e.preventDefault();
        
        const formData = $(this).serialize();
        const $collectBtn = $('#collectBtn');
        
        // UI 상태 변경
        $collectBtn.prop('disabled', true);
        $('#progressCard').show();
        $('#resultCard').hide();
        
        // 진행률 애니메이션 시작
        animateProgress();
        
        $.ajax({
            url: '{{ route("admin.tenders.execute_collection") }}',
            method: 'POST',
            data: formData,
            success: function(response) {
                // 수집 완료
                showResult(response.stats);
            },
            error: function(xhr) {
                const response = xhr.responseJSON;
                alert(response ? response.message : '수집 실패');
                resetUI();
            }
        });
    });

    function checkApiStatus() {
        const $btn = $('#testApiBtn');
        const $status = $('#apiStatus');
        
        $btn.prop('disabled', true);
        $status.html(`
            <i class="bi bi-hourglass-split display-6 text-warning"></i>
            <p class="text-warning mt-2">연결 확인 중...</p>
        `);
        
        $.get('{{ route("admin.tenders.test_api") }}')
            .done(function(response) {
                $status.html(`
                    <i class="bi bi-check-circle display-6 text-success"></i>
                    <p class="text-success mt-2">${response.message}</p>
                `);
            })
            .fail(function(xhr) {
                const response = xhr.responseJSON;
                $status.html(`
                    <i class="bi bi-x-circle display-6 text-danger"></i>
                    <p class="text-danger mt-2">${response ? response.message : 'API 연결 실패'}</p>
                `);
            })
            .always(function() {
                $btn.prop('disabled', false);
            });
    }

    function animateProgress() {
        const $progressBar = $('#progressBar');
        const $progressText = $('#progressText');
        
        let progress = 0;
        const interval = setInterval(function() {
            progress += Math.random() * 15;
            if (progress > 90) progress = 90;
            
            $progressBar.css('width', progress + '%').text(Math.round(progress) + '%');
            
            const messages = [
                '나라장터 API에 연결 중...',
                '입찰공고 데이터 조회 중...',
                '데이터 처리 및 저장 중...',
                '거의 완료되었습니다...'
            ];
            
            const messageIndex = Math.floor(progress / 25);
            if (messageIndex < messages.length) {
                $progressText.text(messages[messageIndex]);
            }
        }, 500);
        
        // 20초 후 자동으로 완료
        setTimeout(function() {
            clearInterval(interval);
            $progressBar.css('width', '100%').text('100%');
            $progressText.text('수집 완료!');
        }, 20000);
    }

    function showResult(stats) {
        $('#progressCard').hide();
        
        const resultHtml = `
            <div class="col-md-3">
                <div class="text-primary">
                    <i class="bi bi-file-text display-4"></i>
                    <h4 class="mt-2">${stats.total_fetched || 0}</h4>
                    <p>수집된 공고</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="text-success">
                    <i class="bi bi-plus-circle display-4"></i>
                    <h4 class="mt-2">${stats.new_records || 0}</h4>
                    <p>신규 등록</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="text-info">
                    <i class="bi bi-arrow-repeat display-4"></i>
                    <h4 class="mt-2">${stats.updated_records || 0}</h4>
                    <p>업데이트</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="text-${stats.errors > 0 ? 'danger' : 'muted'}">
                    <i class="bi bi-exclamation-triangle display-4"></i>
                    <h4 class="mt-2">${stats.errors || 0}</h4>
                    <p>오류</p>
                </div>
            </div>
        `;
        
        $('#resultContent').html(resultHtml);
        $('#resultCard').show();
        
        resetUI();
    }

    function resetUI() {
        $('#collectBtn').prop('disabled', false);
        $('#progressCard').hide();
        $('#progressBar').css('width', '0%').text('0%');
        $('#progressText').text('수집 준비 중...');
    }
});
</script>
@endpush
{{-- [END nara:admin_tenders_collect] --}}