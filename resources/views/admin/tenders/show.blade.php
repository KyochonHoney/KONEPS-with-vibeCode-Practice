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
                    <button type="button"
                            class="btn btn-{{ $tender->is_unsuitable ? 'danger' : 'outline-danger' }} unsuitable-toggle-btn ms-2"
                            data-tender-id="{{ $tender->id }}"
                            data-is-unsuitable="{{ $tender->is_unsuitable ? '1' : '0' }}"
                            title="{{ $tender->is_unsuitable ? '비적합 해제' : '비적합 표시' }}">
                        <i class="bi {{ $tender->is_unsuitable ? 'bi-hand-thumbs-down-fill' : 'bi-hand-thumbs-down' }} me-1"></i>
                        {{ $tender->is_unsuitable ? '비적합 공고' : '비적합 표시' }}
                    </button>
                    @if($tender->is_unsuitable && $tender->unsuitable_reason)
                        <span class="badge bg-danger-subtle text-danger ms-2" style="font-size: 0.85rem; padding: 0.5rem 0.75rem;">
                            <i class="bi bi-exclamation-circle me-1"></i>{{ $tender->unsuitable_reason }}
                        </span>
                    @endif
                    <a href="{{ route('admin.tenders.index') }}" class="btn btn-secondary ms-2">
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
                            @if($tender->cmmn_spldmd_agrmnt_rcptdoc_methd)
                            <div class="row mb-3">
                                <div class="col-sm-3"><strong>입찰방식:</strong></div>
                                <div class="col-sm-9">
                                    <span class="badge bg-info">{{ $tender->cmmn_spldmd_agrmnt_rcptdoc_methd }}</span>
                                </div>
                            </div>
                            @endif
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
                                    <a href="{{ route('admin.tenders.download_attachment', ['tender' => $tender->id, 'seq' => $file['seq']]) }}" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-download me-1"></i>다운로드
                                    </a>
                                </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    @endif

                    <!-- 제안요청정보 파일 (크롤링으로 수집) -->
                    {{-- $proposalFiles는 컨트롤러에서 전달됨 (상주 검사 결과 포함) --}}
                    @if($proposalFiles->count() > 0)
                    <div class="card shadow mb-4 border-info">
                        <div class="card-header py-3 bg-info bg-opacity-10">
                            <h6 class="m-0 font-weight-bold text-info">
                                <i class="bi bi-file-text me-2"></i>제안요청정보 파일 ({{ $proposalFiles->count() }}건)
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="list-group">
                                @foreach($proposalFiles as $file)
                                <div class="list-group-item d-flex justify-content-between align-items-start">
                                    <div class="flex-grow-1">
                                        <div class="d-flex align-items-center mb-1">
                                            @if($file->file_extension === 'hwp')
                                                <i class="bi bi-file-earmark-code text-success me-2"></i>
                                            @elseif($file->file_extension === 'pdf')
                                                <i class="bi bi-file-earmark-pdf text-danger me-2"></i>
                                            @elseif(in_array($file->file_extension, ['doc', 'docx']))
                                                <i class="bi bi-file-earmark-word text-primary me-2"></i>
                                            @elseif(in_array($file->file_extension, ['xls', 'xlsx']))
                                                <i class="bi bi-file-earmark-excel text-success me-2"></i>
                                            @else
                                                <i class="bi bi-file-earmark text-secondary me-2"></i>
                                            @endif
                                            <strong>{{ $file->file_name }}</strong>

                                            {{-- 상주 검사 결과 표시 --}}
                                            @if(isset($file->sangju_status))
                                                @if($file->sangju_status['has_sangju'])
                                                    <span class="badge bg-danger ms-2" title="상주 키워드 감지됨">
                                                        <i class="bi bi-exclamation-triangle-fill me-1"></i>상주 {{ $file->sangju_status['occurrences'] }}회 감지
                                                    </span>
                                                @elseif($file->sangju_status['checked'])
                                                    <span class="badge bg-success ms-2" title="상주 키워드 없음">
                                                        <i class="bi bi-check-circle-fill me-1"></i>상주 없음
                                                    </span>
                                                @else
                                                    <span class="badge bg-secondary ms-2" title="검사 안됨{{ isset($file->sangju_status['error']) ? ': ' . $file->sangju_status['error'] : '' }}">
                                                        <i class="bi bi-question-circle-fill me-1"></i>검사 안됨
                                                    </span>
                                                @endif
                                            @endif
                                        </div>
                                        @if($file->doc_name)
                                            <small class="text-muted d-block">
                                                <i class="bi bi-tag me-1"></i>{{ $file->doc_name }}
                                            </small>
                                        @endif
                                        <small class="text-muted">
                                            <i class="bi bi-clock me-1"></i>{{ $file->created_at->format('Y-m-d H:i') }}
                                        </small>
                                    </div>
                                    <div class="text-end ms-3">
                                        <span class="{{ $file->download_status_class }} mb-2 d-block">
                                            {{ $file->download_status_label }}
                                        </span>
                                        @php
                                            $fileExists = false;
                                            if ($file->local_path) {
                                                // Check both storage_path and private path
                                                $fileExists = file_exists(storage_path('app/' . $file->local_path)) ||
                                                            file_exists(storage_path('app/private/' . $file->local_path));
                                            }
                                        @endphp
                                        @if($fileExists)
                                            <a href="{{ route('admin.attachments.download', $file) }}" class="btn btn-sm btn-outline-info">
                                                <i class="bi bi-download me-1"></i>다운로드
                                            </a>
                                        @elseif($file->download_url)
                                            <button type="button" class="btn btn-sm btn-outline-warning" onclick="forceDownloadFile({{ $file->id }})">
                                                <i class="bi bi-cloud-download me-1"></i>재다운로드
                                            </button>
                                        @else
                                            <button class="btn btn-sm btn-outline-secondary" disabled>
                                                <i class="bi bi-x-circle me-1"></i>링크없음
                                            </button>
                                        @endif
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    @else
                    <div class="card shadow mb-4 border-warning">
                        <div class="card-header py-3 bg-warning bg-opacity-10">
                            <h6 class="m-0 font-weight-bold text-warning">
                                <i class="bi bi-file-text me-2"></i>제안요청정보 파일
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-warning mb-0">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                제안요청정보 파일이 아직 수집되지 않았습니다.
                                <br>
                                <small class="text-muted">아래 "제안요청정보 파일 수집" 버튼을 클릭하여 파일을 수집하세요.</small>
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
                            @if($tender->total_budget || $tender->allocated_budget || $tender->vat)
                                <table class="table table-sm">
                                    @if($tender->total_budget)
                                    <tr>
                                        <th width="150">사업금액</th>
                                        <td>
                                            <strong class="text-primary h5">{{ $tender->formatted_total_budget }}</strong>
                                            <span class="badge bg-info ms-2">추정가 + 부가세</span>
                                            <small class="text-muted d-block">({{ number_format($tender->total_budget) }}원)</small>
                                        </td>
                                    </tr>
                                    @endif

                                    @if($tender->allocated_budget)
                                    <tr>
                                        <th>추정가격</th>
                                        <td>
                                            <strong class="h6">{{ $tender->formatted_allocated_budget }}</strong>
                                            <small class="text-muted d-block">({{ number_format($tender->allocated_budget) }}원)</small>
                                        </td>
                                    </tr>
                                    @endif

                                    @if($tender->vat)
                                    <tr>
                                        <th>부가세</th>
                                        <td>
                                            <strong class="h6">{{ $tender->formatted_vat }}</strong>
                                            @if($tender->vat_rate)
                                                <span class="badge bg-secondary ms-2">{{ $tender->vat_rate }}%</span>
                                            @endif
                                            <small class="text-muted d-block">({{ number_format($tender->vat) }}원)</small>
                                        </td>
                                    </tr>
                                    @endif
                                </table>

                                @if($tender->total_budget && $tender->allocated_budget && $tender->vat)
                                <div class="alert alert-light mt-3 mb-0">
                                    <small class="text-muted">
                                        <i class="bi bi-info-circle me-1"></i>
                                        검증: {{ number_format($tender->allocated_budget + $tender->vat) }}원
                                        @php
                                            $diff = abs($tender->total_budget - ($tender->allocated_budget + $tender->vat));
                                        @endphp
                                        @if($diff < 100)
                                            <span class="text-success">= {{ number_format($tender->total_budget) }}원 ✓</span>
                                        @else
                                            <span class="text-warning">≈ {{ number_format($tender->total_budget) }}원 (차이: {{ number_format($diff) }}원)</span>
                                        @endif
                                    </small>
                                </div>
                                @endif
                            @else
                                <p class="text-muted mb-0">예산 정보가 없습니다.</p>
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

                    <!-- 첨부파일 관리 -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="bi bi-file-earmark-zip me-2"></i>첨부파일 관리
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <button type="button" class="btn btn-outline-info" id="collectProposalFilesBtn">
                                    <i class="bi bi-file-text me-1"></i>
                                    제안요청정보 파일 수집
                                </button>
                                <button type="button" class="btn btn-outline-warning" id="checkSangjuBtn">
                                    <i class="bi bi-search me-1"></i>
                                    "상주" 단어 검사 (비적합 자동판단)
                                </button>
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

    // 제안요청정보 파일 수집
    $('#collectProposalFilesBtn').click(function() {
        if (!confirm('제안요청정보 섹션의 파일을 크롤링하시겠습니까?\n\n페이지를 분석하여 제안요청서, 과업지시서 등을 자동으로 수집합니다.')) {
            return;
        }

        const $btn = $(this);
        const originalText = $btn.html();

        $btn.prop('disabled', true).html('<i class="spinner-border spinner-border-sm me-1"></i>크롤링 중...');

        $.ajax({
            url: '{{ route("admin.tenders.crawl_proposal_files", $tender) }}',
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}'
            },
            success: function(response) {
                if (response.success) {
                    alert(`✅ 성공!\n\n${response.message}\n발견: ${response.files_found}개\n저장: ${response.files_downloaded}개`);
                    // 페이지 새로고침하여 새로 수집된 파일 표시
                    location.reload();
                } else {
                    alert(`❌ 실패\n\n${response.message}`);
                }
            },
            error: function(xhr) {
                const response = xhr.responseJSON;
                alert(response ? response.message : '제안요청정보 파일 크롤링 실패');
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

    // 비적합 공고 토글 버튼
    $('.unsuitable-toggle-btn').click(function() {
        const $btn = $(this);
        const tenderId = $btn.data('tender-id');
        const isUnsuitable = $btn.data('is-unsuitable') === '1';

        $.ajax({
            url: `/admin/tenders/${tenderId}/toggle-unsuitable`,
            method: 'PATCH',
            data: {
                _token: '{{ csrf_token() }}'
            },
            success: function(response) {
                if (response.success) {
                    // 버튼 스타일 및 텍스트 업데이트
                    const $icon = $btn.find('i');

                    if (response.is_unsuitable) {
                        $btn.removeClass('btn-outline-danger').addClass('btn-danger');
                        $icon.removeClass('bi-hand-thumbs-down').addClass('bi-hand-thumbs-down-fill');
                        $btn.attr('title', '비적합 해제');
                        $btn.contents().last()[0].textContent = ' 비적합 공고';
                        $btn.data('is-unsuitable', '1');
                    } else {
                        $btn.removeClass('btn-danger').addClass('btn-outline-danger');
                        $icon.removeClass('bi-hand-thumbs-down-fill').addClass('bi-hand-thumbs-down');
                        $btn.attr('title', '비적합 표시');
                        $btn.contents().last()[0].textContent = ' 비적합 표시';
                        $btn.data('is-unsuitable', '0');
                    }

                    // 성공 메시지
                    showToast('success', response.message);
                }
            },
            error: function(xhr) {
                const response = xhr.responseJSON;
                showToast('danger', response?.message || '비적합 토글에 실패했습니다.');
            }
        });
    });

    // "상주" 단어 검사 버튼
    $('#checkSangjuBtn').click(function() {
        const $btn = $(this);
        const originalHtml = $btn.html();

        // 버튼 비활성화 및 로딩 표시
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>검사 중...');

        $.ajax({
            url: '/admin/tenders/{{ $tender->id }}/check-sangju',
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                $btn.prop('disabled', false).html(originalHtml);

                if (response.success) {
                    if (response.has_sangju) {
                        // "상주" 발견됨 - 비적합으로 표시
                        let detailedMessage = '<strong>✅ "상주" 키워드 발견</strong><br>';
                        detailedMessage += `총 ${response.total_occurrences}회 발견 (검사 파일: ${response.checked_files}/${response.total_files}개)<br><br>`;

                        if (response.found_in_files && response.found_in_files.length > 0) {
                            detailedMessage += '<strong>발견된 파일:</strong><ul class="mb-0 mt-1">';
                            response.found_in_files.forEach(file => {
                                const fileSize = (file.file_size / 1024).toFixed(1); // KB
                                detailedMessage += `<li><strong>${file.file_name}</strong> (${file.file_type}) - ${file.occurrences}회 발견 (${fileSize} KB, ${file.extension})</li>`;
                            });
                            detailedMessage += '</ul>';
                        }

                        showToast('warning', detailedMessage);

                        // 비적합 버튼 업데이트
                        const $unsuitableBtn = $('#toggleUnsuitableBtn');
                        $unsuitableBtn.removeClass('btn-outline-secondary')
                                     .addClass('btn-warning')
                                     .html('<i class="bi bi-hand-thumbs-down-fill me-1"></i>비적합 공고');

                        // 페이지 새로고침 (상태 반영)
                        setTimeout(() => location.reload(), 3000);
                    } else {
                        // "상주" 없음
                        showToast('success', response.message);
                    }
                } else {
                    showToast('danger', response.message || '검사에 실패했습니다.');
                }
            },
            error: function(xhr) {
                $btn.prop('disabled', false).html(originalHtml);
                const response = xhr.responseJSON;
                showToast('danger', response?.message || '"상주" 검사 중 오류가 발생했습니다.');
            }
        });
    });

    // 제안요청정보 파일 강제 재다운로드
    window.forceDownloadFile = function(attachmentId) {
        if (!confirm('서버에 파일이 없어 나라장터에서 다시 다운로드합니다. 계속하시겠습니까?')) {
            return;
        }

        $.ajax({
            url: '/admin/attachments/force-download/' + attachmentId,
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                if (response.success) {
                    showToast('success', response.message);
                    setTimeout(() => location.reload(), 2000);
                } else {
                    showToast('danger', response.message || '파일 다운로드에 실패했습니다.');
                }
            },
            error: function(xhr) {
                const response = xhr.responseJSON;
                showToast('danger', response?.message || '파일 다운로드 중 오류가 발생했습니다.');
            }
        });
    };

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