<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 사업금액(total_budget) 재계산
        // 문제: asignBdgtAmt에는 입찰참가비가 포함되어 있음
        // 해결: total_budget = allocated_budget + vat (추정가격 + 부가세)

        DB::table('tenders')
            ->whereNotNull('allocated_budget')
            ->whereNotNull('vat')
            ->chunkById(100, function ($tenders) {
                foreach ($tenders as $tender) {
                    $correctTotalBudget = $tender->allocated_budget + $tender->vat;

                    DB::table('tenders')
                        ->where('id', $tender->id)
                        ->update(['total_budget' => $correctTotalBudget]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 원복 불가능 (원본 데이터를 알 수 없음)
    }
};
