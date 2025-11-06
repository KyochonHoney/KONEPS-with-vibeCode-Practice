<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tenders', function (Blueprint $table) {
            // 기존 budget을 total_budget으로 이름 변경
            $table->renameColumn('budget', 'total_budget');
        });

        Schema::table('tenders', function (Blueprint $table) {
            // 새 컬럼 추가
            $table->decimal('allocated_budget', 15, 2)->nullable()
                ->after('total_budget')
                ->comment('추정가격 (부가세 제외)');

            $table->decimal('vat', 15, 2)->nullable()
                ->after('allocated_budget')
                ->comment('부가세');

            // 인덱스 업데이트
            $table->index('total_budget');
            $table->index('allocated_budget');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenders', function (Blueprint $table) {
            // 인덱스 제거
            $table->dropIndex(['total_budget']);
            $table->dropIndex(['allocated_budget']);

            // 새 컬럼 제거
            $table->dropColumn(['allocated_budget', 'vat']);
        });

        Schema::table('tenders', function (Blueprint $table) {
            // 컬럼 이름 복원
            $table->renameColumn('total_budget', 'budget');
        });
    }
};
