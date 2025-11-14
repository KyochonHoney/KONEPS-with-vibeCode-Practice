<?php

// [BEGIN nara:tender_model]
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

/**
 * 입찰공고 모델
 * 
 * @package App\Models
 */
class Tender extends Model
{
    use HasFactory;

    /**
     * 테이블명
     */
    protected $table = 'tenders';

    /**
     * 대량 할당 가능한 속성들 (기본 필드 + 109개 API 필드)
     */
    protected $fillable = [
        // 기본 필드
        'tender_no',
        'title',
        'content',
        'agency',
        'total_budget',      // 사업금액 (추정가격 + 부가세)
        'allocated_budget',  // 추정가격 (부가세 제외)
        'vat',              // 부가세
        'currency',
        'start_date',
        'end_date',
        'category_id',
        'region',
        'status',
        'is_favorite',
        'is_unsuitable',
        'unsuitable_reason',
        'source_url',
        'detail_url',
        'collected_at',
        'metadata',
        
        // 나라장터 API 109개 필드
        'bid_ntce_ord', 're_ntce_yn', 'rgst_ty_nm', 'ntce_kind_nm', 'intrbid_yn', 'ref_no',
        'ntce_instt_cd', 'dminstt_cd', 'bid_methd_nm', 'cntrct_cncls_mthd_nm',
        'ntce_instt_ofcl_nm', 'ntce_instt_ofcl_tel_no', 'ntce_instt_ofcl_email_adrs', 'exctv_nm',
        'bid_qlfct_rgst_dt', 'bid_begin_dt', 'bid_clse_dt', 'openg_dt', 'rbid_openg_dt',
        'cmmn_spldmd_agrmnt_rcptdoc_methd', 'cmmn_spldmd_agrmnt_clse_dt', 'cmmn_spldmd_corp_rgn_lmt_yn',
        'ntce_spec_doc_url1', 'ntce_spec_doc_url2', 'ntce_spec_doc_url3', 'ntce_spec_doc_url4',
        'ntce_spec_doc_url5', 'ntce_spec_doc_url6', 'ntce_spec_doc_url7', 'ntce_spec_doc_url8',
        'ntce_spec_doc_url9', 'ntce_spec_doc_url10',
        'ntce_spec_file_nm1', 'ntce_spec_file_nm2', 'ntce_spec_file_nm3', 'ntce_spec_file_nm4',
        'ntce_spec_file_nm5', 'ntce_spec_file_nm6', 'ntce_spec_file_nm7', 'ntce_spec_file_nm8',
        'ntce_spec_file_nm9', 'ntce_spec_file_nm10',
        'rbid_permsn_yn', 'bid_prtcpt_lmt_yn',
        'pq_appl_doc_rcpt_mthd_nm', 'pq_appl_doc_rcpt_dt',
        'tp_eval_appl_mthd_nm', 'tp_eval_appl_clse_dt', 'tp_eval_yn',
        'jntcontrct_duty_rgn_nm1', 'jntcontrct_duty_rgn_nm2', 'jntcontrct_duty_rgn_nm3',
        'rgn_duty_jntcontrct_rt', 'dtls_bid_yn',
        'prearng_prce_dcsn_mthd_nm', 'tot_prdprc_num', 'drwt_prdprc_num', 'asign_bdgt_amt', 'openg_plce',
        'dcmtg_oprtn_dt', 'dcmtg_oprtn_plce',
        'bid_ntce_dtl_url', 'bid_ntce_url_original',
        'bid_prtcpt_fee_paymnt_yn', 'bid_prtcpt_fee', 'bid_grntymny_paymnt_yn',
        'crdtr_nm', 'ppsw_gnrl_srvce_yn', 'srvce_div_nm',
        'prdct_clsfc_lmt_yn', 'mnfct_yn', 'purchs_obj_prdct_list',
        'unty_ntce_no', 'cmmn_spldmd_methd_cd', 'cmmn_spldmd_methd_nm',
        'std_ntce_doc_url',
        'brffc_bidprc_permsn_yn', 'dsgnt_cmpt_yn', 'arslt_cmpt_yn', 'pq_eval_yn', 'ntce_dscrpt_yn',
        'rsrvtn_prce_re_mkng_mthd_nm',
        'arslt_appl_doc_rcpt_mthd_nm', 'arslt_reqstdoc_rcpt_dt',
        'order_plan_unty_no',
        'sucsfbid_lwlt_rate', 'sucsfbid_mthd_cd', 'sucsfbid_mthd_nm',
        'rgst_dt', 'chg_dt', 'chg_ntce_rsn',
        'bf_spec_rgst_no', 'info_biz_yn',
        'dminstt_ofcl_email_adrs', 'indstryty_lmt_yn',
        'vat_amount', 'induty_vat',
        'rgn_lmt_bid_locplc_jdgm_bss_cd', 'rgn_lmt_bid_locplc_jdgm_bss_nm',
        'pub_prcrmnt_lrg_clsfc_nm', 'pub_prcrmnt_mid_clsfc_nm', 'pub_prcrmnt_clsfc_no', 'pub_prcrmnt_clsfc_nm'
    ];

    /**
     * 데이터 타입 캐스팅
     */
    protected $casts = [
        'total_budget' => 'decimal:2',
        'allocated_budget' => 'decimal:2',
        'vat' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
        'collected_at' => 'datetime',
        'metadata' => 'array',
        'is_favorite' => 'boolean',
        'is_unsuitable' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * 분류와의 관계
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(TenderCategory::class, 'category_id');
    }

    /**
     * 첨부파일들과의 관계
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class, 'tender_id');
    }

    /**
     * 한글파일만 가져오는 관계
     */
    public function hwpAttachments(): HasMany
    {
        return $this->attachments()->where(function($query) {
            $query->where('file_type', 'hwp')
                  ->orWhere('mime_type', 'application/x-hwp')
                  ->orWhere('original_name', 'LIKE', '%.hwp');
        });
    }

    /**
     * AI 분석 결과들과의 관계
     */
    public function analyses(): HasMany
    {
        return $this->hasMany(Analysis::class, 'tender_id');
    }

    /**
     * 제안서들과의 관계
     */
    public function proposals(): HasMany
    {
        return $this->hasMany(Proposal::class, 'tender_id');
    }

    /**
     * 멘션(메모)들과의 관계
     */
    public function mentions(): HasMany
    {
        return $this->hasMany(TenderMention::class, 'tender_id');
    }

    /**
     * 활성 상태 입찰공고 스코프
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * 마감된 입찰공고 스코프
     */
    public function scopeClosed($query)
    {
        return $query->where('status', 'closed');
    }

    /**
     * 즐겨찾기 입찰공고 스코프
     */
    public function scopeFavorite($query)
    {
        return $query->where('is_favorite', true);
    }

    /**
     * 비적합 입찰공고 스코프 (회사 기술과 맞지 않음)
     */
    public function scopeUnsuitable($query)
    {
        return $query->where('is_unsuitable', true);
    }

    /**
     * 용역 분류 입찰공고 스코프
     */
    public function scopeService($query)
    {
        return $query->whereHas('category', function($q) {
            $q->where('name', '용역');
        });
    }

    /**
     * 기간별 필터링 스코프
     */
    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->where('start_date', '>=', $startDate)
                     ->where('end_date', '<=', $endDate);
    }

    /**
     * 예산 범위별 필터링 스코프
     */
    public function scopeByBudgetRange($query, $minBudget = null, $maxBudget = null)
    {
        if ($minBudget) {
            $query->where('total_budget', '>=', $minBudget);
        }

        if ($maxBudget) {
            $query->where('total_budget', '<=', $maxBudget);
        }

        return $query;
    }

    /**
     * 검색 스코프 (제목, 내용, 기관명)
     */
    public function scopeSearch($query, $keyword)
    {
        return $query->where(function($q) use ($keyword) {
            $q->where('title', 'like', "%{$keyword}%")
              ->orWhere('content', 'like', "%{$keyword}%")
              ->orWhere('agency', 'like', "%{$keyword}%");
        });
    }

    /**
     * 입찰 마감일까지 남은 일수 계산 (날짜 기준, 시간 무시)
     */
    public function getDaysRemainingAttribute(): ?int
    {
        // 우선순위: bid_clse_dt (입찰마감일시) > end_date
        $bidCloseDate = $this->bid_clse_dt;
        $fallbackDate = $this->end_date;
        
        $targetDate = $bidCloseDate ?: $fallbackDate;
        
        if (!$targetDate) {
            return null;
        }
        
        // 날짜만 비교 (시간 무시)
        $closeDate = Carbon::parse($targetDate)->startOfDay();
        $today = Carbon::now()->startOfDay();
        
        // 날짜 기준으로만 비교
        $diffDays = $today->diffInDays($closeDate, false);
        
        // 마감일이 오늘보다 과거인 경우
        if ($closeDate->isBefore($today)) {
            return -1; // 마감됨을 의미
        }
        
        // 오늘이면 0 (D-Day), 내일이면 1 (D-1)
        return (int) $diffDays;
    }

    /**
     * 입찰 마감 여부 확인 (날짜 기준, 당일은 마감 아님)
     */
    public function getIsExpiredAttribute(): bool
    {
        // 우선순위: bid_clse_dt (입찰마감일시) > end_date
        $bidCloseDate = $this->bid_clse_dt;
        $fallbackDate = $this->end_date;
        
        $targetDate = $bidCloseDate ?: $fallbackDate;
        
        if (!$targetDate) {
            return false;
        }
        
        // 날짜만 비교 (시간 무시) - 마감일 당일까지는 마감 아님
        $closeDate = Carbon::parse($targetDate)->startOfDay();
        $today = Carbon::now()->startOfDay();
        
        // 마감일이 오늘보다 과거일 때만 마감으로 처리
        return $closeDate->isBefore($today);
    }

    /**
     * 예산을 포맷된 문자열로 반환 (하위 호환성)
     */
    public function getFormattedBudgetAttribute(): string
    {
        return $this->formatted_total_budget;
    }

    /**
     * 사업금액(총 예산)을 포맷된 문자열로 반환
     */
    public function getFormattedTotalBudgetAttribute(): string
    {
        if (!$this->total_budget) {
            return '미공개';
        }

        $budget = $this->total_budget;

        if ($budget >= 100000000) { // 1억 이상
            return number_format($budget / 100000000, 1) . '억원';
        } elseif ($budget >= 10000) { // 1만 이상
            return number_format($budget / 10000) . '만원';
        } else {
            return number_format($budget) . '원';
        }
    }

    /**
     * 추정가격을 포맷된 문자열로 반환
     */
    public function getFormattedAllocatedBudgetAttribute(): string
    {
        if (!$this->allocated_budget) {
            return '미공개';
        }

        $budget = $this->allocated_budget;

        if ($budget >= 100000000) { // 1억 이상
            return number_format($budget / 100000000, 1) . '억원';
        } elseif ($budget >= 10000) { // 1만 이상
            return number_format($budget / 10000) . '만원';
        } else {
            return number_format($budget) . '원';
        }
    }

    /**
     * 부가세를 포맷된 문자열로 반환
     */
    public function getFormattedVatAttribute(): string
    {
        if (!$this->vat) {
            return '미공개';
        }

        $vat = $this->vat;

        if ($vat >= 100000000) { // 1억 이상
            return number_format($vat / 100000000, 1) . '억원';
        } elseif ($vat >= 10000) { // 1만 이상
            return number_format($vat / 10000) . '만원';
        } else {
            return number_format($vat) . '원';
        }
    }

    /**
     * 부가세율 계산 (%)
     */
    public function getVatRateAttribute(): ?float
    {
        if (!$this->allocated_budget || !$this->vat) {
            return null;
        }
        return round(($this->vat / $this->allocated_budget) * 100, 2);
    }

    /**
     * 상태를 한글로 반환
     */
    public function getStatusLabelAttribute(): string
    {
        switch($this->status) {
            case 'pending': return '공고중';
            case 'active': return '진행중';
            case 'closed': return '마감';
            case 'opened': return '개찰완료';
            case 'completed': return '완료';
            case 'cancelled': return '취소';
            default: return '알수없음';
        }
    }

    /**
     * 상태별 부트스트랩 클래스 반환
     */
    public function getStatusClassAttribute(): string
    {
        switch($this->status) {
            case 'pending': return 'badge bg-info text-white';
            case 'active': return 'badge bg-success text-white';
            case 'closed': return 'badge bg-warning text-dark';
            case 'opened': return 'badge bg-primary text-white';
            case 'completed': return 'badge bg-secondary text-white';
            case 'cancelled': return 'badge bg-danger text-white';
            default: return 'badge bg-dark text-white';
        }
    }

    /**
     * 공고 기간 문자열 반환
     */
    public function getPeriodAttribute(): string
    {
        if (!$this->start_date || !$this->end_date) {
            return '기간 미정';
        }
        
        $start = Carbon::parse($this->start_date);
        $end = Carbon::parse($this->end_date);
        
        return $start->format('Y.m.d') . ' ~ ' . $end->format('Y.m.d');
    }

    /**
     * 메타데이터에서 특정 값 가져오기
     */
    public function getMetaValue(string $key, $default = null)
    {
        if (!is_array($this->metadata)) {
            return $default;
        }
        
        return $this->metadata[$key] ?? $default;
    }

    /**
     * 짧은 제목 반환 (최대 길이 제한)
     */
    public function getShortTitleAttribute(): string
    {
        if (mb_strlen($this->title) <= 50) {
            return $this->title;
        }
        
        return mb_substr($this->title, 0, 47) . '...';
    }

    /**
     * 나라장터 상세 페이지 URL 반환 (API 값 우선 사용)
     */
    public function getDetailUrlAttribute(): string
    {
        // DB에 실제 detail_url 값이 있으면 그것을 사용 (API에서 받은 값)
        $dbValue = $this->attributes['detail_url'] ?? null;
        if (!empty($dbValue) && $dbValue !== '#') {
            return $dbValue;
        }
        
        // fallback: tender_no로 URL 생성
        if (!empty($this->tender_no)) {
            return "https://www.g2b.go.kr:8082/ep/invitation/publish/bidInfoDtl.do?bidno={$this->tender_no}";
        }
        
        return '#';
    }

    /**
     * 최근 수집된 입찰공고 조회
     */
    public static function getRecentTenders(int $limit = 10)
    {
        return static::with('category')
                    ->latest('collected_at')
                    ->limit($limit)
                    ->get();
    }

    /**
     * 마감임박 입찰공고 조회 (D-day 3일 이내)
     */
    public static function getUrgentTenders(int $days = 3, int $limit = 10)
    {
        $targetDate = Carbon::today()->addDays($days);
        
        return static::active()
                    ->where('end_date', '<=', $targetDate)
                    ->where('end_date', '>=', Carbon::today())
                    ->orderBy('end_date')
                    ->limit($limit)
                    ->get();
    }

    /**
     * 입찰 일정 정보 (포맷팅된)
     */
    public function getFormattedBidScheduleAttribute(): array
    {
        return [
            'bid_begin' => $this->bid_begin_dt ? Carbon::parse($this->bid_begin_dt)->format('Y-m-d H:i') : null,
            'bid_close' => $this->bid_clse_dt ? Carbon::parse($this->bid_clse_dt)->format('Y-m-d H:i') : null,
            'opening' => $this->openg_dt ? Carbon::parse($this->openg_dt)->format('Y-m-d H:i') : null,
            'rebid_opening' => $this->rbid_openg_dt ? Carbon::parse($this->rbid_openg_dt)->format('Y-m-d H:i') : null,
        ];
    }

    /**
     * 예산 상세 정보 (포맷팅된)
     *
     * 나라장터 API 구조:
     * - asign_bdgt_amt: 부가세 포함 총 예산
     * - vat_amount: 부가세 금액
     * - 배정예산(순수): 총 예산 - 부가세
     */
    public function getFormattedBudgetDetailsAttribute(): array
    {
        $totalBudget = $this->asign_bdgt_amt ? (int)$this->asign_bdgt_amt : 0;  // 부가세 포함 총액
        $vat = $this->vat_amount ? (int)$this->vat_amount : 0;
        $netBudget = $totalBudget - $vat;  // 순수 배정예산 (부가세 제외)

        return [
            'total' => $totalBudget > 0 ? number_format($totalBudget) . '원' : null,
            'assign_budget' => $netBudget > 0 ? number_format($netBudget) . '원' : null,
            'vat' => $vat > 0 ? number_format($vat) . '원' : null,
        ];
    }

    /**
     * 첨부파일 목록 (URL과 파일명)
     */
    public function getAttachmentFilesAttribute(): array
    {
        $files = [];
        for ($i = 1; $i <= 10; $i++) {
            $url = $this->{"ntce_spec_doc_url{$i}"};
            $name = $this->{"ntce_spec_file_nm{$i}"};

            if (!empty($url) && !empty($name)) {
                // JSON 문자열 형태로 저장된 경우 안전하게 추출
                $cleanUrl = $this->safeExtractString($url);
                $cleanName = $this->safeExtractString($name);

                // URL과 파일명이 유효한 경우만 추가
                if (!empty($cleanUrl) && !empty($cleanName)) {
                    $files[] = [
                        'url' => $cleanUrl,
                        'name' => $cleanName,
                        'seq' => $i
                    ];
                }
            }
        }
        return $files;
    }

    /**
     * 공고 분류 정보 (계층적)
     */
    public function getClassificationInfoAttribute(): array
    {
        return [
            'large' => $this->safeExtractString($this->pub_prcrmnt_lrg_clsfc_nm),
            'middle' => $this->safeExtractString($this->pub_prcrmnt_mid_clsfc_nm),
            'detail' => $this->safeExtractString($this->pub_prcrmnt_clsfc_nm),
            'code' => $this->safeExtractString($this->pub_prcrmnt_clsfc_no),
        ];
    }

    /**
     * 입찰 방식 및 계약 정보
     */
    public function getBidMethodInfoAttribute(): array
    {
        return [
            'bid_method' => $this->safeExtractString($this->bid_methd_nm),
            'contract_method' => $this->safeExtractString($this->cntrct_cncls_mthd_nm),
            'international' => $this->safeExtractString($this->intrbid_yn) === 'Y' ? '국제입찰' : '국내입찰',
            'rebid_allowed' => $this->safeExtractString($this->rbid_permsn_yn) === 'Y' ? '재입찰허용' : '재입찰불허',
        ];
    }

    /**
     * 담당자 정보
     */
    public function getOfficialInfoAttribute(): array
    {
        return [
            'name' => $this->safeExtractString($this->ntce_instt_ofcl_nm),
            'phone' => $this->safeExtractString($this->ntce_instt_ofcl_tel_no),
            'email' => $this->safeExtractString($this->ntce_instt_ofcl_email_adrs),
            'institution' => $this->agency,
        ];
    }

    /**
     * JSON 배열에서 안전하게 문자열 추출
     */
    public function safeExtractString($data): string
    {
        if (is_array($data)) {
            if (empty($data)) {
                return '';
            }
            return (string) reset($data);
        }

        if (is_string($data)) {
            // JSON 배열 형태인지 확인 (예: ["value"])
            if (preg_match('/^\["(.+)"\]$/', $data, $matches)) {
                $extracted = $matches[1];
                // JSON 이스케이프 제거 (\/ -> /)
                return str_replace('\\/', '/', $extracted);
            }

            // JSON 이스케이프만 있는 경우도 처리
            if (strpos($data, '\\/') !== false) {
                return str_replace('\\/', '/', $data);
            }
        }

        return (string) $data;
    }

    /**
     * 등록/변경 일시 정보
     */
    public function getRegistrationInfoAttribute(): array
    {
        return [
            'registered' => $this->rgst_dt ? Carbon::parse($this->rgst_dt)->format('Y-m-d H:i:s') : null,
            'changed' => $this->chg_dt ? Carbon::parse($this->chg_dt)->format('Y-m-d H:i:s') : null,
            'change_reason' => $this->chg_ntce_rsn,
        ];
    }

    /**
     * 일정 기반 자동 상태 계산 (날짜 기준)
     */
    public function getAutoStatusAttribute(): string
    {
        $today = Carbon::now()->startOfDay();
        
        // 입찰 시작일 기준 (날짜만 비교)
        if ($this->bid_begin_dt) {
            $bidStartDate = Carbon::parse($this->bid_begin_dt)->startOfDay();
            if ($today->isBefore($bidStartDate)) {
                return 'pending'; // 공고중
            }
        }
        
        // 개찰일자 우선 확인 (날짜만 비교)
        if ($this->openg_dt) {
            $openingDate = Carbon::parse($this->openg_dt)->startOfDay();
            
            if ($today->isAfter($openingDate)) {
                // 재입찰 개찰이 있는 경우
                if ($this->rbid_openg_dt) {
                    $rebidOpeningDate = Carbon::parse($this->rbid_openg_dt)->startOfDay();
                    if ($today->isAfter($rebidOpeningDate)) {
                        return 'completed'; // 완료 (재입찰 개찰 후)
                    }
                }
                return 'opened'; // 개찰완료
            }
            
            // 개찰일이 오늘이면 개찰 당일
            if ($today->isSameDay($openingDate)) {
                return 'opened'; // 개찰완료
            }
        }
        
        // 입찰 마감일 확인 (날짜만 비교)
        if ($this->bid_clse_dt) {
            $bidEndDate = Carbon::parse($this->bid_clse_dt)->startOfDay();
            
            // 마감일이 오늘보다 과거면 마감
            if ($today->isAfter($bidEndDate)) {
                return 'closed'; // 마감
            }
            
            // 마감일이 오늘이면 여전히 활성 (D-Day)
            if ($today->isSameDay($bidEndDate)) {
                return 'active'; // 진행중 (D-Day)
            }
        }
        
        return 'active'; // 기본값
    }

    /**
     * 상태 자동 업데이트
     */
    public function updateAutoStatus(): bool
    {
        $newStatus = $this->auto_status;
        
        if ($this->status !== $newStatus) {
            $this->status = $newStatus;
            return $this->save();
        }
        
        return false; // 변경사항 없음
    }

    /**
     * D-Day 형식의 마감일 표시 문자열
     */
    public function getDdayDisplayAttribute(): string
    {
        $daysRemaining = $this->days_remaining;
        
        if ($daysRemaining === null) {
            return '미정';
        }
        
        if ($daysRemaining === -1) {
            return '마감';
        }
        
        if ($daysRemaining === 0) {
            return 'D-Day';
        }
        
        return 'D-' . $daysRemaining;
    }

    /**
     * D-Day 색상 클래스 반환 (CSS 클래스 기반)
     */
    public function getDdayColorClassAttribute(): string
    {
        $daysRemaining = $this->days_remaining;
        
        if ($daysRemaining === null) {
            return 'text-muted'; // 미정
        }
        
        if ($daysRemaining === -1) {
            return 'dday-display dday-expired'; // 마감
        }
        
        if ($daysRemaining === 0) {
            return 'dday-display dday-today'; // D-Day (빨간색, 깜박임)
        }
        
        if ($daysRemaining <= 1) {
            return 'dday-display dday-urgent'; // D-1 이하 (노란색)
        }
        
        if ($daysRemaining <= 3) {
            return 'dday-display dday-warning'; // D-3 이하 (주황색)
        }
        
        return 'dday-display dday-normal'; // 여유있음 (녹색)
    }

    /**
     * 실제 입찰 마감일시 반환 (포맷팅)
     */
    public function getFormattedBidCloseDateAttribute(): ?string
    {
        $bidCloseDate = $this->bid_clse_dt;
        
        if (!$bidCloseDate) {
            return $this->end_date ? $this->end_date->format('Y-m-d') : null;
        }
        
        return Carbon::parse($bidCloseDate)->format('Y-m-d H:i');
    }

    /**
     * 세부업종코드 반환 (metadata에서 추출)
     */
    public function getClassificationCodeAttribute(): ?string
    {
        if (!$this->metadata) {
            return null;
        }
        
        $metadata = json_decode($this->metadata, true);
        $code = $metadata['pubPrcrmntClsfcNo'] ?? null;
        
        // 배열인 경우 첫 번째 값 사용 (XML 파싱 시 배열이 될 수 있음)
        if (is_array($code)) {
            return empty($code) ? null : (string)$code[0];
        }
        
        return empty($code) ? null : (string)$code;
    }

    /**
     * 세부업종코드 설명 반환
     */
    public function getClassificationNameAttribute(): string
    {
        $code = $this->classification_code;
        
        if (!$code) {
            return '미분류';
        }

        // 세부업종코드 매핑
        $codeMap = [
            '81112002' => '데이터처리서비스',
            '81112299' => '소프트웨어유지및지원서비스',
            '81111811' => '운영위탁서비스',
            '81111899' => '정보시스템유지관리서비스',
            '81112199' => '인터넷지원개발서비스',
            '81111598' => '패키지소프트웨어개발및도입서비스',
            '81111599' => '정보시스템개발서비스',
            '81151699' => '공간정보DB구축서비스'
        ];

        return $codeMap[$code] ?? "기타({$code})";
    }

}
// [END nara:tender_model]