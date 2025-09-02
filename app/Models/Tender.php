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
        'budget',
        'currency',
        'start_date',
        'end_date',
        'category_id',
        'region',
        'status',
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
        'budget' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
        'collected_at' => 'datetime',
        'metadata' => 'array',
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
            $query->where('budget', '>=', $minBudget);
        }
        
        if ($maxBudget) {
            $query->where('budget', '<=', $maxBudget);
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
     * 마감일까지 남은 일수 계산
     */
    public function getDaysRemainingAttribute(): ?int
    {
        if (!$this->end_date) {
            return null;
        }
        
        $endDate = Carbon::parse($this->end_date);
        $today = Carbon::today();
        
        if ($endDate->isPast()) {
            return 0;
        }
        
        return $today->diffInDays($endDate);
    }

    /**
     * 마감 여부 확인
     */
    public function getIsExpiredAttribute(): bool
    {
        if (!$this->end_date) {
            return false;
        }
        
        return Carbon::parse($this->end_date)->isPast();
    }

    /**
     * 예산을 포맷된 문자열로 반환
     */
    public function getFormattedBudgetAttribute(): string
    {
        if (!$this->budget) {
            return '미공개';
        }
        
        $budget = $this->budget;
        
        if ($budget >= 100000000) { // 1억 이상
            return number_format($budget / 100000000, 1) . '억원';
        } elseif ($budget >= 10000) { // 1만 이상
            return number_format($budget / 10000) . '만원';
        } else {
            return number_format($budget) . '원';
        }
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
     */
    public function getFormattedBudgetDetailsAttribute(): array
    {
        $assignBudget = $this->asign_bdgt_amt ? (int)$this->asign_bdgt_amt : 0;
        $vat = $this->vat_amount ? (int)$this->vat_amount : 0;
        
        return [
            'assign_budget' => $assignBudget > 0 ? number_format($assignBudget) . '원' : null,
            'vat' => $vat > 0 ? number_format($vat) . '원' : null,
            'total' => ($assignBudget + $vat) > 0 ? number_format($assignBudget + $vat) . '원' : null,
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
                $files[] = [
                    'url' => $url,
                    'name' => $name,
                    'seq' => $i
                ];
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
            // JSON 배열 형태인지 확인
            if (preg_match('/^\["(.+)"\]$/', $data, $matches)) {
                return $matches[1];
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
     * 일정 기반 자동 상태 계산
     */
    public function getAutoStatusAttribute(): string
    {
        $now = Carbon::now();
        
        // 입찰 시작 전
        if ($this->bid_begin_dt && $now->isBefore(Carbon::parse($this->bid_begin_dt))) {
            return 'pending'; // 공고중
        }
        
        // 입찰 진행 중 (입찰시작 ~ 입찰마감)
        if ($this->bid_begin_dt && $this->bid_clse_dt) {
            $bidStart = Carbon::parse($this->bid_begin_dt);
            $bidEnd = Carbon::parse($this->bid_clse_dt);
            
            if ($now->isBetween($bidStart, $bidEnd)) {
                return 'active'; // 진행중
            }
        }
        
        // 입찰 마감 후, 개찰 전
        if ($this->bid_clse_dt && $this->openg_dt) {
            $bidEnd = Carbon::parse($this->bid_clse_dt);
            $opening = Carbon::parse($this->openg_dt);
            
            if ($now->isAfter($bidEnd) && $now->isBefore($opening)) {
                return 'closed'; // 마감
            }
        }
        
        // 개찰 이후
        if ($this->openg_dt && $now->isAfter(Carbon::parse($this->openg_dt))) {
            // 재입찰 개찰이 있는 경우
            if ($this->rbid_openg_dt && $now->isAfter(Carbon::parse($this->rbid_openg_dt))) {
                return 'completed'; // 완료 (재입찰 개찰 후)
            }
            return 'opened'; // 개찰완료
        }
        
        // 기본값 (입찰마감 후)
        if ($this->bid_clse_dt && $now->isAfter(Carbon::parse($this->bid_clse_dt))) {
            return 'closed'; // 마감
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

}
// [END nara:tender_model]