{{-- [BEGIN nara:admin_tenders_show_enhanced] --}}
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
                    <button type="button"
                            class="btn btn-{{ $tender->is_favorite ? 'warning' : 'outline-secondary' }} favorite-toggle-btn"
                            data-tender-id="{{ $tender->id }}"
                            data-is-favorite="{{ $tender->is_favorite ? '1' : '0' }}"
                            title="{{ $tender->is_favorite ? '즐겨찾기 제거' : '즐겨찾기 추가' }}">
                        <i class="bi {{ $tender->is_favorite ? 'bi-star-fill' : 'bi-star' }} me-1"></i>
                        {{ $tender->is_favorite ? '즐겨찾기' : '즐겨찾기 추가' }}
                    </button>
                    <a href="{{ route('admin.tenders.index') }}" class="btn btn-secondary">
                        <i class="bi bi-arrow-left me-1"></i>
                        목록으로
                    </a>
                    @if($tender->detail_url && $tender->detail_url !== '#')
                        <a href="{{ $tender->detail_url }}" target="_blank" class="btn btn-primary">
                            <i class="bi bi-box-arrow-up-right me-1"></i>
                            나라장터 원본 보기
                        </a>
                    @endif
                </div>
            </div>

            <div class="row">
                <!-- 좌측 컬럼 -->
                <div class="col-lg-8">
                    <!-- 기본 정보 -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="bi bi-info-circle me-2"></i>기본 정보
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-sm-3"><strong>공고번호:</strong></div>
                                <div class="col-sm-9">
                                    <span class="badge bg-info fs-6">{{ $tender->tender_no }}</span>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-sm-3"><strong>제목:</strong></div>
                                <div class="col-sm-9">
                                    <h5 class="text-primary mb-0">{{ $tender->title }}</h5>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-sm-3"><strong>수요기관:</strong></div>
                                <div class="col-sm-9">
                                    <span class="badge bg-primary">{{ $tender->agency }}</span>
                                </div>
                            </div>
                            
                            @if($tender->ntce_kind_nm)
                            <div class="row mb-3">
                                <div class="col-sm-3"><strong>공고종류:</strong></div>
                                <div class="col-sm-9">
                                    @php
                                        $noticeType = $tender->safeExtractString($tender->ntce_kind_nm);
                                        $badgeClass = 'bg-secondary';
                                        if (strpos($noticeType, '재공고') !== false) {
                                            $badgeClass = 'bg-danger text-white';
                                        } elseif (strpos($noticeType, '변경공고') !== false) {
                                            $badgeClass = 'bg-success text-white';
                                        }
                                    @endphp
                                    <span class="badge {{ $badgeClass }}">{{ $noticeType }}</span>
                                </div>
                            </div>
                            @endif
                            
                            @if($tender->classification_info['code'])
                            <div class="row mb-3">
                                <div class="col-sm-3"><strong>업종코드:</strong></div>
                                <div class="col-sm-9">
                                    <code class="bg-light p-1 rounded">{{ $tender->classification_info['code'] }}</code>
                                </div>
                            </div>
                            @endif
                            
                            <div class="row mb-3">
                                <div class="col-sm-3"><strong>수요기관 담당자:</strong></div>
                                <div class="col-sm-9">{{ $tender->exctv_nm ?: '미지정' }}</div>
                            </div>
                            
                            @if($tender->content)
                            <div class="row mb-3">
                                <div class="col-sm-3"><strong>공고내용:</strong></div>
                                <div class="col-sm-9">
                                    <div class="border rounded p-3 bg-light">
                                        {!! nl2br(e($tender->content)) !!}
                                    </div>
                                </div>
                            </div>
                            @endif
                        </div>
                    </div>

                    <!-- 분류 정보 -->
                    @if($tender->classification_info['large'] || $tender->classification_info['middle'])
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="bi bi-tags me-2"></i>분류 정보
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-sm-3"><strong>대분류:</strong></div>
                                <div class="col-sm-9">
                                    <span class="badge bg-success">{{ $tender->classification_info['large'] ?: '미분류' }}</span>
                                </div>
                            </div>
                            @if($tender->classification_info['middle'])
                            <div class="row mb-3">
                                <div class="col-sm-3"><strong>중분류:</strong></div>
                                <div class="col-sm-9">
                                    <span class="badge bg-info">{{ $tender->classification_info['middle'] }}</span>
                                </div>
                            </div>
                            @endif
                            @if($tender->classification_info['detail'])
                            <div class="row mb-3">
                                <div class="col-sm-3"><strong>세부분류:</strong></div>
                                <div class="col-sm-9">{{ $tender->classification_info['detail'] }}</div>
                            </div>
                            @endif
                            @if($tender->classification_info['code'])
                            <div class="row mb-3">
                                <div class="col-sm-3"><strong>분류코드:</strong></div>
                                <div class="col-sm-9">
                                    <code>{{ $tender->classification_info['code'] }}</code>
                                </div>
                            </div>
                            @endif
                        </div>
                    </div>
                    @endif

                    <!-- 입찰 방식 및 계약 정보 -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="bi bi-gear me-2"></i>입찰 방식 및 계약 정보
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-sm-3"><strong>입찰방법:</strong></div>
                                <div class="col-sm-9">
                                    <span class="badge bg-warning text-dark">{{ $tender->bid_method_info['bid_method'] ?: '미지정' }}</span>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-sm-3"><strong>계약방법:</strong></div>
                                <div class="col-sm-9">
                                    <span class="badge bg-secondary">{{ $tender->bid_method_info['contract_method'] ?: '미지정' }}</span>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-sm-3"><strong>입찰구분:</strong></div>
                                <div class="col-sm-9">
                                    <span class="badge {{ $tender->intrbid_yn === 'Y' ? 'bg-danger' : 'bg-success' }}">
                                        {{ $tender->bid_method_info['international'] }}
                                    </span>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-sm-3"><strong>재입찰여부:</strong></div>
                                <div class="col-sm-9">
                                    <span class="badge {{ $tender->rbid_permsn_yn === 'Y' ? 'bg-info' : 'bg-dark' }}">
                                        {{ $tender->bid_method_info['rebid_allowed'] }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 입찰 일정 -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="bi bi-calendar-event me-2"></i>입찰 일정
                            </h6>
                        </div>
                        <div class="card-body">
                            @php $schedule = $tender->formatted_bid_schedule @endphp
                            
                            @if($schedule['bid_begin'])
                            <div class="row mb-3">
                                <div class="col-sm-3"><strong>입찰시작:</strong></div>
                                <div class="col-sm-9">
                                    <i class="bi bi-play-circle text-success me-2"></i>{{ $schedule['bid_begin'] }}
                                </div>
                            </div>
                            @endif
                            
                            @if($schedule['bid_close'])
                            <div class="row mb-3">
                                <div class="col-sm-3"><strong>입찰마감:</strong></div>
                                <div class="col-sm-9">
                                    <i class="bi bi-stop-circle text-danger me-2"></i>{{ $schedule['bid_close'] }}
                                    @if($tender->days_remaining !== null)
                                        <span class="badge {{ $tender->days_remaining <= 3 ? 'bg-danger' : 'bg-warning' }} ms-2">
                                            D-{{ $tender->days_remaining }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                            @endif
                            
                            @if($schedule['opening'])
                            <div class="row mb-3">
                                <div class="col-sm-3"><strong>개찰일시:</strong></div>
                                <div class="col-sm-9">
                                    <i class="bi bi-unlock text-primary me-2"></i>{{ $schedule['opening'] }}
                                </div>
                            </div>
                            @endif
                            
                            @if($schedule['rebid_opening'])
                            <div class="row mb-3">
                                <div class="col-sm-3"><strong>재입찰개찰:</strong></div>
                                <div class="col-sm-9">
                                    <i class="bi bi-arrow-repeat text-info me-2"></i>{{ $schedule['rebid_opening'] }}
                                </div>
                            </div>
                            @endif
                        </div>
                    </div>

                    <!-- 수요기관 담당자 정보 -->
                    @if($tender->official_info['name'])
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="bi bi-person-badge me-2"></i>수요기관 담당자 정보
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-sm-3"><strong>수요기관 담당자:</strong></div>
                                <div class="col-sm-9">
                                    <i class="bi bi-person me-2"></i>{{ $tender->official_info['name'] }}
                                </div>
                            </div>
                            @if($tender->official_info['phone'])
                            <div class="row mb-3">
                                <div class="col-sm-3"><strong>연락처:</strong></div>
                                <div class="col-sm-9">
                                    <i class="bi bi-telephone me-2"></i>
                                    <a href="tel:{{ $tender->official_info['phone'] }}">{{ $tender->official_info['phone'] }}</a>
                                </div>
                            </div>
                            @endif
                            @if($tender->official_info['email'])
                            <div class="row mb-3">
                                <div class="col-sm-3"><strong>이메일:</strong></div>
                                <div class="col-sm-9">
                                    <i class="bi bi-envelope me-2"></i>
                                    <a href="mailto:{{ $tender->official_info['email'] }}">{{ $tender->official_info['email'] }}</a>
                                </div>
                            </div>
                            @endif
                        </div>
                    </div>
                    @endif

                    <!-- 첨부파일 정보 (API에서 제공) -->
                    @if(count($tender->attachment_files) > 0)
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="bi bi-paperclip me-2"></i>첨부파일 정보 ({{ count($tender->attachment_files) }}건)
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="list-group">
                                @foreach($tender->attachment_files as $file)
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <i class="bi bi-file-earmark text-primary me-2"></i>
                                        <strong>{{ $file['name'] }}</strong>
                                        <small class="text-muted d-block">첨부파일 {{ $file['seq'] }}</small>
                                    </div>
                                    <a href="{{ $file['url'] }}" target="_blank" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-download me-1"></i>다운로드
                                    </a>
                                </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    @endif
                </div>

                <!-- 우측 컬럼 -->
                <div class="col-lg-4">
                    <!-- 상태 정보 -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="bi bi-info-square me-2"></i>상태 정보
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <strong>현재 상태:</strong><br>
                                <span class="{{ $tender->status_class }} fs-6">
                                    {{ $tender->status_label }}
                                </span>
                            </div>
                            
                            @if($tender->days_remaining !== null)
                                <div class="mb-3">
                                    <strong>남은 기간:</strong><br>
                                    <span class="badge {{ $tender->days_remaining <= 3 ? 'bg-danger' : 'bg-warning' }} fs-6">
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

                            @if($tender->registration_info['registered'])
                            <div class="mb-3">
                                <strong>등록일시:</strong><br>
                                <small class="text-muted">{{ $tender->registration_info['registered'] }}</small>
                            </div>
                            @endif

                            @if($tender->registration_info['changed'])
                            <div class="mb-3">
                                <strong>변경일시:</strong><br>
                                <small class="text-muted">{{ $tender->registration_info['changed'] }}</small>
                                @if($tender->registration_info['change_reason'])
                                    <br><small class="text-danger">변경사유: {{ $tender->registration_info['change_reason'] }}</small>
                                @endif
                            </div>
                            @endif
                        </div>
                    </div>

                    <!-- 예산 정보 -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="bi bi-currency-dollar me-2"></i>예산 정보
                            </h6>
                        </div>
                        <div class="card-body">
                            @php $budgetDetails = $tender->formatted_budget_details @endphp
                            
                            @if($budgetDetails['assign_budget'])
                            <div class="mb-3">
                                <strong>배정예산:</strong><br>
                                <span class="h5 text-primary">{{ $budgetDetails['assign_budget'] }}</span>
                            </div>
                            @endif
                            
                            @if($budgetDetails['vat'])
                            <div class="mb-3">
                                <strong>부가세:</strong><br>
                                <span class="h6 text-info">{{ $budgetDetails['vat'] }}</span>
                            </div>
                            @endif
                            
                            @if($budgetDetails['total'])
                            <div class="mb-3">
                                <strong>총 예산 (VAT 포함):</strong><br>
                                <span class="h4 text-success">{{ $budgetDetails['total'] }}</span>
                                <small class="text-muted d-block">({{ $tender->currency }})</small>
                            </div>
                            @endif

                            @if(!$budgetDetails['assign_budget'] && $tender->formatted_budget !== '미공개')
                            <div class="mb-3">
                                <strong>예산:</strong><br>
                                <span class="h5 text-success">{{ $tender->formatted_budget }}</span>
                                <small class="text-muted d-block">({{ $tender->currency }})</small>
                            </div>
                            @endif
                        </div>
                    </div>

                    <!-- 지역 정보 -->
                    @if($tender->region)
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="bi bi-geo-alt me-2"></i>지역 정보
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <strong>수행지역:</strong><br>
                                <span class="badge bg-info fs-6">{{ $tender->region }}</span>
                            </div>
                        </div>
                    </div>
                    @endif

                    <!-- 상태 변경 -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="bi bi-pencil-square me-2"></i>상태 변경
                            </h6>
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
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="bi bi-file-earmark-zip me-2"></i>첨부파일 관리
                            </h6>
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
                                <a href="{{ route('admin.attachments.download_hwp_zip', $tender) }}" 
                                   class="btn btn-success" id="downloadHwpZipBtn">
                                    <i class="bi bi-file-earmark-zip me-1"></i>
                                    변환된 파일 ZIP 다운로드
                                </a>
                                <a href="{{ route('admin.attachments.index', ['tender_id' => $tender->id]) }}" 
                                   class="btn btn-outline-info">
                                    <i class="bi bi-files me-1"></i>
                                    첨부파일 목록 보기
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- 액션 버튼 -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="bi bi-lightning me-2"></i>작업
                            </h6>
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

                    <!-- 내 메모 -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="bi bi-chat-left-text me-2"></i>내 메모
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label for="mentionText" class="form-label">
                                    <small class="text-muted">이 공고에 대한 개인 메모를 작성하세요 (어디까지 봤는지, 중요 포인트 등)</small>
                                </label>
                                <textarea
                                    class="form-control"
                                    id="mentionText"
                                    rows="5"
                                    maxlength="5000"
                                    placeholder="예: 2페이지까지 확인 완료, 기술스택 부합, 견적 검토 필요">{{ $userMention->mention ?? '' }}</textarea>
                                <div class="d-flex justify-content-between align-items-center mt-2">
                                    <small class="text-muted">
                                        <span id="charCount">{{ $userMention ? strlen($userMention->mention) : 0 }}</span> / 5000자
                                    </small>
                                    <div>
                                        @if($userMention && $userMention->mention)
                                            <button type="button" class="btn btn-sm btn-outline-danger me-2" id="deleteMentionBtn">
                                                <i class="bi bi-trash me-1"></i>삭제
                                            </button>
                                        @endif
                                        <button type="button" class="btn btn-sm btn-primary" id="saveMentionBtn">
                                            <i class="bi bi-save me-1"></i>저장
                                        </button>
                                    </div>
                                </div>
                            </div>
                            @if($userMention && $userMention->updated_at)
                                <div class="text-end">
                                    <small class="text-muted">
                                        마지막 수정: {{ $userMention->updated_at->format('Y-m-d H:i') }}
                                    </small>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- 메타데이터 (개발자용) -->
            @if($tender->metadata && auth()->user()->role === 'super_admin')
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-secondary">
                            <i class="bi bi-code-square me-2"></i>메타데이터 (개발자 전용)
                        </h6>
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
/* 향상된 배지 스타일 */
.badge {
    font-size: 0.875rem;
    padding: 0.5rem 0.75rem;
    font-weight: 500;
    border-radius: 0.375rem;
}

.badge.fs-6 {
    font-size: 1rem !important;
    padding: 0.6rem 0.9rem;
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

.badge.bg-info {
    background-color: #36b9cc !important;
    color: white !important;
    border: 1px solid #36b9cc;
}

.badge.bg-warning {
    background-color: #f6c23e !important;
    color: #212529 !important;
    border: 1px solid #f6c23e;
}

.badge.bg-danger {
    background-color: #e74a3b !important;
    color: white !important;
    border: 1px solid #e74a3b;
}

.badge.bg-dark {
    background-color: #5a5c69 !important;
    color: white !important;
    border: 1px solid #5a5c69;
}

.badge.bg-secondary {
    background-color: #858796 !important;
    color: white !important;
    border: 1px solid #858796;
}

/* 카드 헤더 스타일 */
.card-header {
    background-color: #f8f9fc;
    border-bottom: 1px solid #e3e6f0;
}

.card-header h6 {
    color: #4e73df;
    font-weight: 600;
}

/* 텍스트 색상 */
.text-primary {
    color: #4e73df !important;
}

.text-success {
    color: #1cc88a !important;
}

.text-info {
    color: #36b9cc !important;
}

/* 리스트 그룹 개선 */
.list-group-item {
    border: 1px solid #e3e6f0;
    padding: 1rem;
}

.list-group-item:hover {
    background-color: #f8f9fc;
}

/* 반응형 개선 */
@media (max-width: 768px) {
    .col-sm-3 {
        margin-bottom: 0.5rem;
        font-weight: bold;
    }
    
    .badge.fs-6 {
        font-size: 0.875rem !important;
        padding: 0.4rem 0.6rem;
    }
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

    // AI 분석 실행
    $('#analyzeBtn').click(function() {
        const $btn = $(this);
        const $icon = $btn.find('i');
        const originalText = $btn.html();
        
        // 버튼 상태 변경
        $btn.prop('disabled', true);
        $icon.removeClass('bi-cpu').addClass('bi-arrow-clockwise');
        $btn.html('<i class="bi bi-arrow-clockwise me-1"></i>AI 분석 중...');
        
        $.ajax({
            url: '{{ route("admin.analyses.analyze", $tender) }}',
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                if (response.success) {
                    // 성공 메시지 표시
                    showAlert('success', response.message);
                    
                    // 분석 결과 표시
                    if (response.analysis) {
                        showAnalysisResult(response.analysis);
                    }
                    
                    // 상세 페이지로 이동 (선택사항)
                    if (response.redirect_url && !response.is_cached) {
                        setTimeout(() => {
                            window.open(response.redirect_url, '_blank');
                        }, 2000);
                    }
                } else {
                    showAlert('danger', response.message || 'AI 분석에 실패했습니다.');
                }
            },
            error: function(xhr) {
                const response = xhr.responseJSON;
                showAlert('danger', response?.message || 'AI 분석 중 오류가 발생했습니다.');
                console.error('AI Analysis Error:', xhr);
            },
            complete: function() {
                // 버튼 상태 복원
                setTimeout(() => {
                    $btn.prop('disabled', false);
                    $btn.html(originalText);
                }, 1000);
            }
        });
    });
    
    // 분석 결과 표시 함수
    function showAnalysisResult(analysis) {
        const scoreClass = getScoreColorClass(analysis.total_score);
        const recommendation = getRecommendationText(analysis.total_score);
        
        const resultHtml = `
            <div class="alert alert-info mt-3" id="analysisResult">
                <h6><i class="bi bi-cpu me-2"></i>AI 분석 결과</h6>
                <div class="row">
                    <div class="col-md-6">
                        <p class="mb-1"><strong>총점:</strong> <span class="${scoreClass}">${analysis.total_score}점</span> (100점 만점)</p>
                        <p class="mb-1"><strong>추천도:</strong> ${recommendation}</p>
                    </div>
                    <div class="col-md-6">
                        <p class="mb-1"><strong>기술적 적합성:</strong> ${analysis.technical_score}점 (40점 만점)</p>
                        <p class="mb-1"><strong>사업 영역 적합성:</strong> ${analysis.experience_score}점 (25점 만점)</p>
                    </div>
                </div>
                <div class="mt-2">
                    <a href="/admin/analyses/${analysis.id}" target="_blank" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-eye me-1"></i>상세 분석 결과 보기
                    </a>
                </div>
            </div>
        `;
        
        // 기존 결과 제거 후 새 결과 표시
        $('#analysisResult').remove();
        $('#analyzeBtn').closest('.card-body').append(resultHtml);
    }
    
    // 점수별 색상 클래스 반환
    function getScoreColorClass(score) {
        if (score >= 80) return 'text-success fw-bold';
        if (score >= 60) return 'text-info fw-bold';
        if (score >= 40) return 'text-warning fw-bold';
        return 'text-danger fw-bold';
    }
    
    // 추천도 텍스트 반환
    function getRecommendationText(score) {
        if (score >= 80) return '<span class="text-success">적극 추천</span>';
        if (score >= 60) return '<span class="text-info">추천</span>';
        if (score >= 40) return '<span class="text-warning">검토 권장</span>';
        return '<span class="text-danger">비추천</span>';
    }
    
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

    // 제안서 생성
    $('#generateProposalBtn').click(function() {
        if (confirm('이 공고에 대한 AI 제안서를 생성하시겠습니까?\n\n처리 시간: 약 30초 - 2분')) {
            // 제안서 생성 페이지로 이동
            window.location.href = '{{ route("admin.proposals.create") }}?tender_id={{ $tender->id }}';
        }
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
        if (!confirm('모든 첨부파일을 한글(.hwp) 형식으로 변환하시겠습니까?\\n\\nPDF, Word, Excel 등의 파일들이 모두 한글 형식으로 변환됩니다.')) {
            return;
        }
        
        const $btn = $(this);
        const originalText = $btn.html();
        
        $btn.prop('disabled', true).html('<i class="spinner-border spinner-border-sm me-1"></i>변환 중...');
        
        $.ajax({
            url: '{{ route("admin.attachments.download_all_as_hwp", $tender) }}',
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}'
            },
            success: function(response) {
                alert(response.message);
            },
            error: function(xhr) {
                const response = xhr.responseJSON;
                alert(response ? response.message : '파일 변환 실패');
            },
            complete: function() {
                $btn.prop('disabled', false).html(originalText);
            }
        });
    });

    // 삭제 버튼
    $('#deleteBtn').click(function() {
        if (confirm('정말 이 공고를 삭제하시겠습니까?\\n삭제된 데이터는 복구할 수 없습니다.')) {
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

    // 즐겨찾기 토글 버튼
    $('.favorite-toggle-btn').click(function() {
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
                    // 버튼 스타일 및 텍스트 업데이트
                    const $icon = $btn.find('i');

                    if (response.is_favorite) {
                        $btn.removeClass('btn-outline-secondary').addClass('btn-warning');
                        $icon.removeClass('bi-star').addClass('bi-star-fill');
                        $btn.attr('title', '즐겨찾기 제거');
                        $btn.contents().last()[0].textContent = ' 즐겨찾기';
                        $btn.data('is-favorite', '1');
                    } else {
                        $btn.removeClass('btn-warning').addClass('btn-outline-secondary');
                        $icon.removeClass('bi-star-fill').addClass('bi-star');
                        $btn.attr('title', '즐겨찾기 추가');
                        $btn.contents().last()[0].textContent = ' 즐겨찾기 추가';
                        $btn.data('is-favorite', '0');
                    }

                    // 성공 메시지 (선택사항)
                    showToast('success', response.message);
                }
            },
            error: function(xhr) {
                const response = xhr.responseJSON;
                showToast('danger', response?.message || '즐겨찾기 토글에 실패했습니다.');
            }
        });
    });

    // 토스트 메시지 표시 함수
    function showToast(type, message) {
        const alertClass = `alert alert-${type} alert-dismissible fade show position-fixed`;
        const alertHtml = `
            <div class="${alertClass}" role="alert" style="top: 20px; right: 20px; z-index: 9999; min-width: 300px;">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;

        $('body').append(alertHtml);

        setTimeout(() => {
            $('.alert').alert('close');
        }, 3000);
    }

    // 메모 글자 수 카운터
    $('#mentionText').on('input', function() {
        const length = $(this).val().length;
        $('#charCount').text(length);
    });

    // 메모 저장 버튼
    $('#saveMentionBtn').click(function() {
        const $btn = $(this);
        const mention = $('#mentionText').val().trim();
        const originalText = $btn.html();

        $btn.prop('disabled', true).html('<i class="spinner-border spinner-border-sm me-1"></i>저장 중...');

        $.ajax({
            url: '{{ route("admin.tenders.store_mention", $tender) }}',
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                mention: mention
            },
            success: function(response) {
                if (response.success) {
                    showToast('success', response.message);

                    // 삭제 버튼이 없으면 추가
                    if ($('#deleteMentionBtn').length === 0 && mention) {
                        const deleteBtn = `
                            <button type="button" class="btn btn-sm btn-outline-danger me-2" id="deleteMentionBtn">
                                <i class="bi bi-trash me-1"></i>삭제
                            </button>
                        `;
                        $btn.before(deleteBtn);
                    }

                    // 페이지 새로고침 (마지막 수정 시간 업데이트를 위해)
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                }
            },
            error: function(xhr) {
                const response = xhr.responseJSON;
                showToast('danger', response?.message || '메모 저장에 실패했습니다.');
            },
            complete: function() {
                $btn.prop('disabled', false).html(originalText);
            }
        });
    });

    // 메모 삭제 버튼 (동적 이벤트 바인딩)
    $(document).on('click', '#deleteMentionBtn', function() {
        if (!confirm('정말 이 메모를 삭제하시겠습니까?')) {
            return;
        }

        const $btn = $(this);
        const originalText = $btn.html();

        $btn.prop('disabled', true).html('<i class="spinner-border spinner-border-sm me-1"></i>삭제 중...');

        $.ajax({
            url: '{{ route("admin.tenders.destroy_mention", $tender) }}',
            method: 'DELETE',
            data: {
                _token: '{{ csrf_token() }}'
            },
            success: function(response) {
                if (response.success) {
                    showToast('success', response.message);
                    $('#mentionText').val('');
                    $('#charCount').text('0');
                    $btn.remove();
                }
            },
            error: function(xhr) {
                const response = xhr.responseJSON;
                showToast('danger', response?.message || '메모 삭제에 실패했습니다.');
                $btn.prop('disabled', false).html(originalText);
            }
        });
    });
});
</script>
@endpush
{{-- [END nara:admin_tenders_show_enhanced] --}}