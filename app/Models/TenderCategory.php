<?php

// [BEGIN nara:tender_category_model]
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 입찰공고 분류 모델
 * 
 * @package App\Models
 */
class TenderCategory extends Model
{
    use HasFactory;

    /**
     * 테이블명
     */
    protected $table = 'tender_categories';

    /**
     * 대량 할당 가능한 속성들
     */
    protected $fillable = [
        'name',
        'code',
        'description'
    ];

    /**
     * 해당 분류의 입찰공고들
     */
    public function tenders(): HasMany
    {
        return $this->hasMany(Tender::class, 'category_id');
    }

    /**
     * 활성 입찰공고 개수
     */
    public function getActiveTendersCountAttribute(): int
    {
        return $this->tenders()->where('status', 'active')->count();
    }

    /**
     * 기본 카테고리 데이터 생성
     */
    public static function createDefaults(): void
    {
        $categories = [
            ['id' => 1, 'name' => '용역', 'code' => 'SERVICE', 'description' => '각종 용역 서비스'],
            ['id' => 2, 'name' => '공사', 'code' => 'CONSTRUCTION', 'description' => '건설 및 공사'],
            ['id' => 3, 'name' => '물품', 'code' => 'GOODS', 'description' => '물품 구매'],
            ['id' => 4, 'name' => '기타', 'code' => 'OTHERS', 'description' => '업종상세코드 없음 또는 분류 불가'],
        ];

        foreach ($categories as $category) {
            static::updateOrCreate(
                ['id' => $category['id']],
                $category
            );
        }
    }
}
// [END nara:tender_category_model]