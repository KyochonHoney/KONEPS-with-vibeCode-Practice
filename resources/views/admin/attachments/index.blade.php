{{-- [BEGIN nara:admin_attachments_index] --}}
@extends('layouts.app')

@section('title', '첨부파일 관리')

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
                                <a href="{{ route('admin.dashboard') }}">관리자</a>
                            </li>
                            <li class="breadcrumb-item active">첨부파일 관리</li>
                        </ol>
                    </nav>
                    <h1 class="h3 text-gray-800">첨부파일 관리</h1>
                </div>
            </div>

            <!-- 통계 카드 -->
            <div class="row mb-4">
                <div class="col-xl-2 col-md-6 mb-4">
                    <div class="card border-left-primary shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                        전체 파일
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $stats['total_files'] }}</div>
                                </div>
                                <div class="col-auto">
                                    <i class="bi bi-files text-gray-300" style="font-size: 2rem;"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-2 col-md-6 mb-4">
                    <div class="card border-left-info shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                        한글파일
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $stats['hwp_files'] }}</div>
                                </div>
                                <div class="col-auto">
                                    <i class="bi bi-file-earmark-text text-gray-300" style="font-size: 2rem;"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-2 col-md-6 mb-4">
                    <div class="card border-left-success shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                        다운로드됨
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $stats['downloaded_files'] }}</div>
                                </div>
                                <div class="col-auto">
                                    <i class="bi bi-check-circle text-gray-300" style="font-size: 2rem;"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-2 col-md-6 mb-4">
                    <div class="card border-left-warning shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                        대기 중
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $stats['pending_files'] }}</div>
                                </div>
                                <div class="col-auto">
                                    <i class="bi bi-hourglass text-gray-300" style="font-size: 2rem;"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-2 col-md-6 mb-4">
                    <div class="card border-left-danger shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                        실패
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $stats['failed_files'] }}</div>
                                </div>
                                <div class="col-auto">
                                    <i class="bi bi-exclamation-triangle text-gray-300" style="font-size: 2rem;"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-2 col-md-6 mb-4">
                    <div class="card border-left-secondary shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-secondary text-uppercase mb-1">
                                        다운로드된 HWP
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $stats['downloaded_hwp_files'] }}</div>
                                </div>
                                <div class="col-auto">
                                    <i class="bi bi-file-check text-gray-300" style="font-size: 2rem;"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 필터 및 검색 -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">필터 및 검색</h6>
                </div>
                <div class="card-body">
                    <form method="GET" action="{{ route('admin.attachments.index') }}" id="filterForm">
                        <div class="row">
                            <div class="col-md-3">
                                <label for="status" class="form-label">상태</label>
                                <select class="form-select" name="status" id="status">
                                    <option value="">전체</option>
                                    <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>대기중</option>
                                    <option value="downloading" {{ request('status') === 'downloading' ? 'selected' : '' }}>다운로드중</option>
                                    <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>완료</option>
                                    <option value="failed" {{ request('status') === 'failed' ? 'selected' : '' }}>실패</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="type" class="form-label">파일 타입</label>
                                <select class="form-select" name="type" id="type">
                                    <option value="">전체</option>
                                    <option value="hwp" {{ request('type') === 'hwp' ? 'selected' : '' }}>한글파일만</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="tender_id" class="form-label">공고 ID</label>
                                <input type="number" class="form-control" name="tender_id" id="tender_id" 
                                       value="{{ request('tender_id') }}" placeholder="특정 공고의 첨부파일만">
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary me-2">
                                    <i class="bi bi-search"></i>
                                </button>
                                <a href="{{ route('admin.attachments.index') }}" class="btn btn-outline-secondary">
                                    <i class="bi bi-x-circle"></i>
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- 첨부파일 목록 -->
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">첨부파일 목록</h6>
                    <div>
                        <button type="button" class="btn btn-sm btn-success" id="bulkDownloadBtn" disabled>
                            <i class="bi bi-download me-1"></i>선택 항목 다운로드
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th width="30">
                                        <input type="checkbox" id="selectAll">
                                    </th>
                                    <th>파일명</th>
                                    <th>공고</th>
                                    <th>파일 타입</th>
                                    <th>파일 크기</th>
                                    <th>상태</th>
                                    <th>수집일시</th>
                                    <th width="150">작업</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($attachments as $attachment)
                                    <tr>
                                        <td>
                                            <input type="checkbox" class="attachment-checkbox" 
                                                   value="{{ $attachment->id }}"
                                                   {{ $attachment->download_status === 'pending' ? '' : 'disabled' }}>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                @if($attachment->is_hwp_file)
                                                    <i class="bi bi-file-earmark-text text-info me-2"></i>
                                                @else
                                                    <i class="bi bi-file-earmark text-muted me-2"></i>
                                                @endif
                                                <div>
                                                    <div class="fw-bold">{{ $attachment->original_name }}</div>
                                                    <small class="text-muted">{{ $attachment->file_name }}</small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <a href="{{ route('admin.tenders.show', $attachment->tender) }}" 
                                               class="text-decoration-none">
                                                <small>{{ $attachment->tender->tender_no }}</small><br>
                                                {{ Str::limit($attachment->tender->title, 30) }}
                                            </a>
                                        </td>
                                        <td>
                                            <span class="badge {{ $attachment->is_hwp_file ? 'bg-info text-white' : 'bg-secondary text-white' }}">
                                                {{ strtoupper($attachment->file_extension) }}
                                            </span>
                                        </td>
                                        <td>{{ $attachment->formatted_file_size }}</td>
                                        <td>
                                            <span class="{{ $attachment->download_status_class }}">
                                                {{ $attachment->download_status_label }}
                                            </span>
                                        </td>
                                        <td>
                                            <small>{{ $attachment->created_at->format('Y-m-d H:i') }}</small>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                @if($attachment->is_downloaded)
                                                    <a href="{{ route('admin.attachments.download', $attachment) }}" 
                                                       class="btn btn-outline-success" title="다운로드">
                                                        <i class="bi bi-download"></i>
                                                    </a>
                                                @endif
                                                
                                                @if($attachment->download_status === 'pending' || $attachment->download_status === 'failed')
                                                    <button type="button" class="btn btn-outline-primary force-download-btn" 
                                                            data-id="{{ $attachment->id }}" title="강제 다운로드">
                                                        <i class="bi bi-arrow-repeat"></i>
                                                    </button>
                                                @endif
                                                
                                                <button type="button" class="btn btn-outline-danger delete-btn" 
                                                        data-id="{{ $attachment->id }}" title="삭제">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center py-4">
                                            <div class="text-muted">
                                                <i class="bi bi-files" style="font-size: 3rem;"></i>
                                                <p class="mt-2">첨부파일이 없습니다.</p>
                                                <small>공고 상세페이지에서 '첨부파일 정보 수집' 버튼을 클릭해주세요.</small>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <!-- 페이지네이션 -->
                    @if($attachments->hasPages())
                        <div class="d-flex justify-content-center">
                            {{ $attachments->links('custom.pagination.bootstrap-4') }}
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
    // 전체 선택/해제
    $('#selectAll').change(function() {
        const isChecked = $(this).is(':checked');
        $('.attachment-checkbox:not(:disabled)').prop('checked', isChecked);
        updateBulkDownloadButton();
    });

    // 개별 체크박스 변경 시
    $(document).on('change', '.attachment-checkbox', function() {
        updateBulkDownloadButton();
        
        // 전체 선택 체크박스 상태 업데이트
        const totalCheckboxes = $('.attachment-checkbox:not(:disabled)').length;
        const checkedCheckboxes = $('.attachment-checkbox:checked').length;
        $('#selectAll').prop('checked', totalCheckboxes > 0 && totalCheckboxes === checkedCheckboxes);
    });

    // 대량 다운로드 버튼 상태 업데이트
    function updateBulkDownloadButton() {
        const checkedCount = $('.attachment-checkbox:checked').length;
        $('#bulkDownloadBtn').prop('disabled', checkedCount === 0);
    }

    // 대량 다운로드 실행
    $('#bulkDownloadBtn').click(function() {
        const selectedIds = $('.attachment-checkbox:checked').map(function() {
            return $(this).val();
        }).get();

        if (selectedIds.length === 0) {
            alert('다운로드할 파일을 선택해주세요.');
            return;
        }

        if (!confirm(`선택한 ${selectedIds.length}개 파일을 다운로드하시겠습니까?`)) {
            return;
        }

        const $btn = $(this);
        const originalText = $btn.html();
        
        $btn.prop('disabled', true).html('<i class="spinner-border spinner-border-sm me-1"></i>다운로드 중...');

        $.ajax({
            url: '{{ route("admin.attachments.bulk_download") }}',
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                attachment_ids: selectedIds
            },
            success: function(response) {
                alert(response.message);
                location.reload();
            },
            error: function(xhr) {
                const response = xhr.responseJSON;
                alert(response ? response.message : '대량 다운로드 실패');
            },
            complete: function() {
                $btn.prop('disabled', false).html(originalText);
            }
        });
    });

    // 강제 다운로드
    $(document).on('click', '.force-download-btn', function() {
        const attachmentId = $(this).data('id');
        const $btn = $(this);
        const originalHtml = $btn.html();
        
        $btn.prop('disabled', true).html('<i class="spinner-border spinner-border-sm"></i>');
        
        $.ajax({
            url: `/admin/attachments/force-download/${attachmentId}`,
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}'
            },
            success: function(response) {
                alert(response.message);
                location.reload();
            },
            error: function(xhr) {
                const response = xhr.responseJSON;
                alert(response ? response.message : '다운로드 실패');
            },
            complete: function() {
                $btn.prop('disabled', false).html(originalHtml);
            }
        });
    });

    // 삭제
    $(document).on('click', '.delete-btn', function() {
        const attachmentId = $(this).data('id');
        
        if (!confirm('정말 이 첨부파일을 삭제하시겠습니까?')) {
            return;
        }
        
        $.ajax({
            url: `/admin/attachments/${attachmentId}`,
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
    });

    // 필터 폼 자동 제출
    $('#status, #type').change(function() {
        $('#filterForm').submit();
    });
});
</script>
@endpush
{{-- [END nara:admin_attachments_index] --}}