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
                    @elseif($tender->tender_no)
                        <a href="{{ $tender->detail_url }}" target="_blank" class="btn btn-primary">
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
                                    <span class="badge bg-primary text-white">{{ $tender->agency }}</span>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-sm-3">
                                    <strong>분류:</strong>
                                </div>
                                <div class="col-sm-9">
                                    <span class="badge bg-success text-white">
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

                    <!-- 첨부파일 관리 -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">첨부파일 관리</h6>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <button type="button" class="btn btn-outline-primary" id="collectAttachmentsBtn">
                                    <i class="bi bi-collection me-1"></i>
                                    첨부파일 정보 수집
                                </button>
                                <button type="button" class="btn btn-warning" id="downloadAllAsHwpBtn">
                                    <i class="bi bi-file-earmark-text me-1"></i>
                                    모든 파일을 한글로 변환
                                </button>
                                <div class="btn-group" role="group">
                                    <a href="{{ route('admin.attachments.download_hwp_zip', $tender) }}" 
                                       class="btn btn-success" id="downloadHwpZipBtn">
                                        <i class="bi bi-file-earmark-zip me-1"></i>
                                        변환된 파일 ZIP 다운로드
                                    </a>
                                    <button type="button" class="btn btn-success dropdown-toggle dropdown-toggle-split" 
                                            data-bs-toggle="dropdown" aria-expanded="false">
                                        <span class="visually-hidden">Toggle Dropdown</span>
                                    </button>
                                    <ul class="dropdown-menu">
                                        <li>
                                            <a class="dropdown-item" href="{{ route('admin.attachments.download_hwp_zip', $tender) }}">
                                                <i class="bi bi-file-earmark-zip me-1"></i>
                                                모든 HWP 파일을 ZIP으로
                                            </a>
                                        </li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <a class="dropdown-item" href="#" id="showIndividualFiles">
                                                <i class="bi bi-files me-1"></i>
                                                개별 파일 다운로드
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                                <button type="button" class="btn btn-outline-success" id="downloadHwpBtn">
                                    <i class="bi bi-download me-1"></i>
                                    기존 한글파일만 다운로드
                                </button>
                                <a href="{{ route('admin.attachments.index', ['tender_id' => $tender->id]) }}" 
                                   class="btn btn-outline-info">
                                    <i class="bi bi-files me-1"></i>
                                    첨부파일 목록 보기
                                </a>
                            </div>
                            
                            <!-- 첨부파일 상태 정보 -->
                            <div class="mt-3 p-3 bg-light rounded" id="attachmentStatus" style="display: none;">
                                <div class="row text-center">
                                    <div class="col-4">
                                        <div class="text-muted">전체</div>
                                        <div class="h5 mb-0" id="totalFiles">-</div>
                                    </div>
                                    <div class="col-4">
                                        <div class="text-muted">한글파일</div>
                                        <div class="h5 mb-0 text-primary" id="hwpFiles">-</div>
                                    </div>
                                    <div class="col-4">
                                        <div class="text-muted">다운로드됨</div>
                                        <div class="h5 mb-0 text-success" id="downloadedFiles">-</div>
                                    </div>
                                </div>
                            </div>
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

            <!-- 개별 파일 다운로드 모달 -->
            <div class="modal fade" id="individualFilesModal" tabindex="-1" aria-labelledby="individualFilesModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="individualFilesModalLabel">개별 HWP 파일 다운로드</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div id="individualFilesList">
                                <div class="text-center">
                                    <div class="spinner-border" role="status">
                                        <span class="visually-hidden">로딩 중...</span>
                                    </div>
                                    <div class="mt-2">변환된 파일 목록을 불러오는 중...</div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">닫기</button>
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

@push('styles')
<style>
/* 배지 스타일 개선 - 가독성 향상 */
.badge {
    font-size: 0.875rem;
    padding: 0.5rem 0.75rem;
    font-weight: 500;
    border-radius: 0.375rem;
}

.badge.bg-primary {
    background-color: #4e73df !important;
    color: white !important;
    border: 1px solid #4e73df;
}

.badge.bg-success {
    background-color: #1cc88a !important;
    color: white !important;
    border: 1px solid #1cc88a;
}

.badge.bg-dark {
    background-color: #5a5c69 !important;
    color: white !important;
    border: 1px solid #5a5c69;
}

.badge.bg-danger {
    background-color: #e74a3b !important;
    color: white !important;
    border: 1px solid #e74a3b;
}

.badge.bg-warning {
    background-color: #f6c23e !important;
    color: #212529 !important;
    border: 1px solid #f6c23e;
}

/* 강조 텍스트 스타일 */
.text-primary {
    color: #4e73df !important;
}

.text-success {
    color: #1cc88a !important;
}

/* 카드 헤더 스타일 */
.card-header {
    background-color: #f8f9fc;
    border-bottom: 1px solid #e3e6f0;
}
</style>
@endpush

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

    // 첨부파일 정보 수집
    $('#collectAttachmentsBtn').click(function() {
        const $btn = $(this);
        const originalText = $btn.html();
        
        $btn.prop('disabled', true).html('<i class="spinner-border spinner-border-sm me-1"></i>수집 중...');
        
        $.ajax({
            url: '{{ route("admin.attachments.collect", $tender) }}',
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}'
            },
            success: function(response) {
                alert(response.message);
                updateAttachmentStats();
            },
            error: function(xhr) {
                const response = xhr.responseJSON;
                alert(response ? response.message : '첨부파일 수집 실패');
            },
            complete: function() {
                $btn.prop('disabled', false).html(originalText);
            }
        });
    });

    // 모든 파일을 한글로 변환
    $('#downloadAllAsHwpBtn').click(function() {
        if (!confirm('모든 첨부파일을 한글(.hwp) 형식으로 변환하시겠습니까?\n\nPDF, Word, Excel 등의 파일들이 모두 한글 형식으로 변환됩니다.\n변환 후 "변환된 파일 ZIP 다운로드" 버튼으로 실제 다운로드할 수 있습니다.')) {
            return;
        }
        
        const $btn = $(this);
        const originalText = $btn.html();
        
        $btn.prop('disabled', true).html('<i class="spinner-border spinner-border-sm me-1"></i>변환 및 다운로드 중...');
        
        $.ajax({
            url: '{{ route("admin.attachments.download_all_as_hwp", $tender) }}',
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}'
            },
            success: function(response) {
                alert(response.message);
                updateAttachmentStats();
            },
            error: function(xhr) {
                const response = xhr.responseJSON;
                alert(response ? response.message : '파일 변환 및 다운로드 실패');
            },
            complete: function() {
                $btn.prop('disabled', false).html(originalText);
            }
        });
    });

    // 한글파일만 다운로드 (기존 기능)
    $('#downloadHwpBtn').click(function() {
        const $btn = $(this);
        const originalText = $btn.html();
        
        $btn.prop('disabled', true).html('<i class="spinner-border spinner-border-sm me-1"></i>다운로드 중...');
        
        $.ajax({
            url: '{{ route("admin.attachments.download_hwp", $tender) }}',
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}'
            },
            success: function(response) {
                alert(response.message);
                updateAttachmentStats();
            },
            error: function(xhr) {
                const response = xhr.responseJSON;
                alert(response ? response.message : '한글파일 다운로드 실패');
            },
            complete: function() {
                $btn.prop('disabled', false).html(originalText);
            }
        });
    });

    // 첨부파일 통계 업데이트
    function updateAttachmentStats() {
        $.get('{{ route("admin.attachments.stats") }}', function(stats) {
            $('#totalFiles').text(stats.total_files || 0);
            $('#hwpFiles').text(stats.hwp_files || 0);
            $('#downloadedFiles').text(stats.downloaded_hwp_files || 0);
            $('#attachmentStatus').show();
        });
    }

    // 개별 파일 다운로드 모달
    $('#showIndividualFiles').click(function(e) {
        e.preventDefault();
        $('#individualFilesModal').modal('show');
        loadIndividualFilesList();
    });

    // 개별 파일 목록 로드
    function loadIndividualFilesList() {
        $.ajax({
            url: '{{ route("admin.attachments.index") }}',
            method: 'GET',
            data: {
                tender_id: {{ $tender->id }},
                ajax: 1
            },
            success: function(data) {
                let html = '';
                const attachments = Array.isArray(data.attachments?.data) ? data.attachments.data : [];
                
                if (attachments.length === 0) {
                    html = '<div class="alert alert-info"><i class="bi bi-info-circle me-2"></i>변환된 HWP 파일이 없습니다. 먼저 "모든 파일을 한글로 변환" 버튼을 클릭해주세요.</div>';
                } else {
                    html = '<div class="list-group">';
                    attachments.forEach(function(attachment) {
                        if (attachment.file_type === 'hwp' && attachment.download_status === 'completed') {
                            const fileSizeKB = Math.round(attachment.file_size / 1024) || 0;
                            html += `
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <i class="bi bi-file-earmark-text text-success me-2"></i>
                                        <strong>${attachment.file_name || attachment.original_name}</strong>
                                        <small class="text-muted d-block">원본: ${attachment.original_name} (${fileSizeKB}KB)</small>
                                    </div>
                                    <a href="{{ url('admin/attachments/download-hwp') }}/${attachment.id}" 
                                       class="btn btn-sm btn-success">
                                        <i class="bi bi-download me-1"></i>다운로드
                                    </a>
                                </div>
                            `;
                        }
                    });
                    html += '</div>';
                    
                    if (html === '<div class="list-group"></div>') {
                        html = '<div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-2"></i>다운로드 가능한 HWP 파일이 없습니다.</div>';
                    }
                }
                
                $('#individualFilesList').html(html);
            },
            error: function(xhr) {
                $('#individualFilesList').html('<div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i>파일 목록을 불러오는데 실패했습니다.</div>');
            }
        });
    }

    // 페이지 로드 시 첨부파일 통계 로드
    updateAttachmentStats();

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