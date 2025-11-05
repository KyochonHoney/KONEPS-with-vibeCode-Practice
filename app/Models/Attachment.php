<?php

// [BEGIN nara:attachment_model]
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

/**
 * 입찰공고 첨부파일 모델
 * 
 * @package App\Models
 */
class Attachment extends Model
{
    use HasFactory;

    /**
     * 테이블명
     */
    protected $table = 'attachments';

    /**
     * 대량 할당 가능한 속성들
     */
    protected $fillable = [
        'tender_id',
        'file_name',
        'original_name',
        'file_url',
        'file_type',
        'file_size',
        'mime_type',
        'type',
        'download_url',
        'post_data',
        'doc_name',
        'local_path',
        'download_status',
        'download_error',
        'downloaded_at'
    ];

    /**
     * 데이터 타입 캐스팅
     */
    protected $casts = [
        'file_size' => 'integer',
        'downloaded_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * 입찰공고와의 관계
     */
    public function tender(): BelongsTo
    {
        return $this->belongsTo(Tender::class, 'tender_id');
    }

    /**
     * 다운로드된 첨부파일만 조회하는 스코프
     */
    public function scopeDownloaded($query)
    {
        return $query->where('download_status', 'completed')
                     ->whereNotNull('local_path');
    }

    /**
     * 한글파일(HWP) 형식만 조회하는 스코프
     */
    public function scopeHwpFiles($query)
    {
        return $query->where(function($q) {
            $q->where('file_type', 'hwp')
              ->orWhere('mime_type', 'application/x-hwp')
              ->orWhere('original_name', 'LIKE', '%.hwp')
              ->orWhere('file_name', 'LIKE', '%.hwp');
        });
    }

    /**
     * 다운로드 대기중인 파일들 조회하는 스코프
     */
    public function scopePending($query)
    {
        return $query->where('download_status', 'pending');
    }

    /**
     * 다운로드 실패한 파일들 조회하는 스코프
     */
    public function scopeFailed($query)
    {
        return $query->where('download_status', 'failed');
    }

    /**
     * 파일 확장자 추출
     */
    public function getFileExtensionAttribute(): string
    {
        $filename = $this->original_name ?? $this->file_name;
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        return strtolower($extension);
    }

    /**
     * 한글파일 여부 확인
     */
    public function getIsHwpFileAttribute(): bool
    {
        return $this->file_extension === 'hwp' ||
               $this->mime_type === 'application/x-hwp' ||
               str_contains(strtolower($this->original_name ?? ''), '.hwp');
    }

    /**
     * 다운로드 완료 여부 확인
     */
    public function getIsDownloadedAttribute(): bool
    {
        if ($this->download_status !== 'completed' || empty($this->local_path)) {
            return false;
        }

        // Check both possible storage paths
        $path1 = storage_path('app/' . $this->local_path);
        $path2 = storage_path('app/private/' . $this->local_path);

        return file_exists($path1) || file_exists($path2);
    }

    /**
     * 파일 크기를 읽기 좋은 형태로 포맷
     */
    public function getFormattedFileSizeAttribute(): string
    {
        if (!$this->file_size) {
            return '알수없음';
        }

        $size = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];
        $unitIndex = 0;

        while ($size >= 1024 && $unitIndex < count($units) - 1) {
            $size /= 1024;
            $unitIndex++;
        }

        return round($size, 2) . ' ' . $units[$unitIndex];
    }

    /**
     * 다운로드 상태를 한글로 반환
     */
    public function getDownloadStatusLabelAttribute(): string
    {
        return match($this->download_status) {
            'pending' => '대기중',
            'downloading' => '다운로드중',
            'completed' => '완료',
            'failed' => '실패',
            default => '알수없음'
        };
    }

    /**
     * 다운로드 상태별 부트스트랩 클래스 반환
     */
    public function getDownloadStatusClassAttribute(): string
    {
        return match($this->download_status) {
            'pending' => 'badge bg-warning text-dark',
            'downloading' => 'badge bg-info text-white',
            'completed' => 'badge bg-success text-white',
            'failed' => 'badge bg-danger text-white',
            default => 'badge bg-secondary text-white'
        };
    }

    /**
     * 로컬 파일 다운로드 URL 생성
     */
    public function getLocalDownloadUrlAttribute(): ?string
    {
        if (!$this->is_downloaded) {
            return null;
        }

        return route('admin.attachments.download', $this);
    }

    /**
     * 특정 입찰공고의 한글파일만 조회
     */
    public static function getHwpFilesByTender(int $tenderId)
    {
        return static::where('tender_id', $tenderId)
                    ->hwpFiles()
                    ->get();
    }

    /**
     * 다운로드 가능한 모든 한글파일 조회
     */
    public static function getDownloadableHwpFiles(int $limit = 100)
    {
        return static::hwpFiles()
                    ->downloaded()
                    ->with('tender')
                    ->latest('downloaded_at')
                    ->limit($limit)
                    ->get();
    }

    /**
     * 다운로드 통계 정보 조회
     */
    public static function getDownloadStats(): array
    {
        return [
            'total_files' => static::count(),
            'hwp_files' => static::hwpFiles()->count(),
            'downloaded_files' => static::downloaded()->count(),
            'pending_files' => static::pending()->count(),
            'failed_files' => static::failed()->count(),
            'downloaded_hwp_files' => static::hwpFiles()->downloaded()->count(),
        ];
    }
}
// [END nara:attachment_model]