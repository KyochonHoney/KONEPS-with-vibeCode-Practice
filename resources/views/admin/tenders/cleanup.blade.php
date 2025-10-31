@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="bi bi-trash"></i> 마감 공고 정리</h2>
                <div>
                    <a href="{{ route('admin.tenders.index') }}" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> 공고 목록
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- 통계 카드 -->
    <div class="row mb-4">
        <div class="col-md-2">
            <div class="card bg-danger text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title">1일 후 마감</h6>
                            <h4>{{ number_format($stats['expired_1_day']) }}</h4>
                        </div>
                        <i class="bi bi-exclamation-triangle fs-1"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title">7일 후 마감</h6>
                            <h4>{{ number_format($stats['expired_7_days']) }}</h4>
                        </div>
                        <i class="bi bi-clock fs-1"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title">30일 후 마감</h6>
                            <h4>{{ number_format($stats['expired_30_days']) }}</h4>
                        </div>
                        <i class="bi bi-calendar-x fs-1"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card bg-secondary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title">90일 후 마감</h6>
                            <h4>{{ number_format($stats['expired_90_days']) }}</h4>
                        </div>
                        <i class="bi bi-archive fs-1"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title">활성 공고</h6>
                            <h4>{{ number_format($stats['total_active']) }}</h4>
                        </div>
                        <i class="bi bi-check-circle fs-1"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title">전체 공고</h6>
                            <h4>{{ number_format($stats['total_all']) }}</h4>
                        </div>
                        <i class="bi bi-database fs-1"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 정리 실행 패널 -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5><i class="bi bi-gear"></i> 마감 공고 정리 실행</h5>
                </div>
                <div class="card-body">
                    <form id="cleanupForm">
                        <div class="row">
                            <div class="col-md-3">
                                <label for="expired_days" class="form-label">마감 후 기간</label>
                                <select id="expired_days" name="expired_days" class="form-select">
                                    <option value="1">1일 후</option>
                                    <option value="7" selected>7일 후</option>
                                    <option value="30">30일 후</option>
                                    <option value="90">90일 후</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">실행 모드</label>
                                <div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="dry_run" id="dry_run_true" value="1" checked>
                                        <label class="form-check-label" for="dry_run_true">
                                            미리보기 (삭제 안함)
                                        </label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="dry_run" id="dry_run_false" value="0">
                                        <label class="form-check-label" for="dry_run_false">
                                            실제 삭제
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">&nbsp;</label>
                                <div>
                                    <button type="submit" class="btn btn-primary" id="cleanupBtn">
                                        <i class="bi bi-play-circle"></i> 정리 실행
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- 결과 표시 -->
    <div class="row mb-4" id="resultSection" style="display: none;">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5><i class="bi bi-check-circle"></i> 정리 결과</h5>
                </div>
                <div class="card-body" id="resultContent">
                    <!-- 결과가 여기에 표시됩니다 -->
                </div>
            </div>
        </div>
    </div>

    <!-- 최근 마감된 공고 목록 -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5><i class="bi bi-list"></i> 최근 마감된 공고 (7일 후)</h5>
                </div>
                <div class="card-body">
                    @if($stats['recent_expired']->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>공고번호</th>
                                        <th>제목</th>
                                        <th>기관</th>
                                        <th>마감일</th>
                                        <th>접수마감</th>
                                        <th>상태</th>
                                        <th>액션</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($stats['recent_expired'] as $tender)
                                    <tr>
                                        <td>
                                            <a href="{{ route('admin.tenders.show', $tender->id) }}" class="text-decoration-none">
                                                {{ $tender->tender_no }}
                                            </a>
                                        </td>
                                        <td>{{ Str::limit($tender->title, 50) }}</td>
                                        <td>{{ $tender->agency }}</td>
                                        <td>
                                            @if($tender->end_date)
                                                <span class="text-danger">
                                                    {{ Carbon\Carbon::parse($tender->end_date)->format('Y-m-d') }}
                                                </span>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($tender->rcpt_clsr_dt)
                                                <span class="text-danger">
                                                    {{ Carbon\Carbon::parse($tender->rcpt_clsr_dt)->format('Y-m-d H:i') }}
                                                </span>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="badge bg-danger">마감</span>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-danger" onclick="deleteTender({{ $tender->id }}, '{{ $tender->tender_no }}')">
                                                <i class="bi bi-trash"></i> 삭제
                                            </button>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-4">
                            <i class="bi bi-check-circle text-success fs-1"></i>
                            <p class="mt-2 text-muted">최근 7일 내 마감된 공고가 없습니다.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 확인 모달 -->
<div class="modal fade" id="confirmModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">삭제 확인</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>정말로 다음 공고를 삭제하시겠습니까?</p>
                <p><strong id="confirmTenderNo"></strong></p>
                <p class="text-danger">
                    <i class="bi bi-exclamation-triangle"></i>
                    이 작업은 되돌릴 수 없으며, 관련된 모든 데이터가 함께 삭제됩니다.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">취소</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteBtn">삭제</button>
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
// 정리 실행
document.getElementById('cleanupForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const form = e.target;
    const formData = new FormData(form);
    const btn = document.getElementById('cleanupBtn');
    const originalText = btn.innerHTML;
    
    // 버튼 비활성화
    btn.disabled = true;
    btn.innerHTML = '<i class="spinner-border spinner-border-sm"></i> 처리 중...';
    
    fetch('{{ route("admin.tenders.execute_cleanup") }}', {
        method: 'POST',
        body: formData,
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showResult(data, true);
            // 통계 새로고침
            setTimeout(() => {
                location.reload();
            }, 3000);
        } else {
            showResult(data, false);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showResult({message: '처리 중 오류가 발생했습니다.'}, false);
    })
    .finally(() => {
        // 버튼 활성화
        btn.disabled = false;
        btn.innerHTML = originalText;
    });
});

// 결과 표시
function showResult(data, success) {
    const resultSection = document.getElementById('resultSection');
    const resultContent = document.getElementById('resultContent');
    
    let html = '';
    if (success) {
        html = `
            <div class="alert alert-success">
                <i class="bi bi-check-circle"></i> ${data.message}
            </div>
        `;
        
        if (data.data) {
            const result = data.data;
            html += `
                <div class="row">
                    <div class="col-md-3">
                        <div class="text-center">
                            <h5>${result.total_expired || 0}</h5>
                            <small class="text-muted">발견된 마감 공고</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center">
                            <h5>${result.deleted_count || 0}</h5>
                            <small class="text-muted">처리된 공고</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center">
                            <h5>${result.errors || 0}</h5>
                            <small class="text-muted">오류 발생</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center">
                            <h5>${((result.end_time - result.start_time) / 1000).toFixed(2)}초</h5>
                            <small class="text-muted">소요 시간</small>
                        </div>
                    </div>
                </div>
            `;
            
            if (result.deleted_tender_nos && result.deleted_tender_nos.length > 0) {
                html += `
                    <div class="mt-3">
                        <h6>처리된 공고 번호:</h6>
                        <div class="bg-light p-2 rounded">
                            ${result.deleted_tender_nos.slice(0, 10).join(', ')}
                            ${result.deleted_tender_nos.length > 10 ? ' ... 외 ' + (result.deleted_tender_nos.length - 10) + '개' : ''}
                        </div>
                    </div>
                `;
            }
        }
    } else {
        html = `
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle"></i> ${data.message}
            </div>
        `;
    }
    
    resultContent.innerHTML = html;
    resultSection.style.display = 'block';
    resultSection.scrollIntoView({ behavior: 'smooth' });
}

// 개별 공고 삭제
let deleteTarget = null;
function deleteTender(id, tenderNo) {
    deleteTarget = id;
    document.getElementById('confirmTenderNo').textContent = tenderNo;
    new bootstrap.Modal(document.getElementById('confirmModal')).show();
}

document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
    if (!deleteTarget) return;
    
    const btn = this;
    const originalText = btn.innerHTML;
    
    btn.disabled = true;
    btn.innerHTML = '<i class="spinner-border spinner-border-sm"></i> 삭제 중...';
    
    fetch(`/admin/tenders/${deleteTarget}`, {
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // 모달 닫기
            bootstrap.Modal.getInstance(document.getElementById('confirmModal')).hide();
            
            // 성공 메시지 표시
            showResult(data, true);
            
            // 페이지 새로고침
            setTimeout(() => {
                location.reload();
            }, 2000);
        } else {
            showResult(data, false);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showResult({message: '삭제 중 오류가 발생했습니다.'}, false);
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = originalText;
        deleteTarget = null;
    });
});
</script>
@endsection