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
        Schema::create('analyses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tender_id')->comment('공고 ID');
            $table->unsignedBigInteger('user_id')->comment('분석 요청 사용자 ID');
            $table->unsignedBigInteger('company_profile_id')->nullable()->comment('회사 프로필 ID');
            $table->decimal('total_score', 5, 2)->comment('총점 (0-100)');
            $table->decimal('technical_score', 5, 2)->nullable()->comment('기술 적합성 점수');
            $table->decimal('experience_score', 5, 2)->nullable()->comment('경험 점수');
            $table->decimal('budget_score', 5, 2)->nullable()->comment('예산 점수');
            $table->decimal('other_score', 5, 2)->nullable()->comment('기타 점수');
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending')->comment('분석 상태');
            $table->json('analysis_data')->nullable()->comment('상세 분석 데이터');
            $table->string('ai_model_version', 50)->nullable()->comment('사용된 AI 모델 버전');
            $table->integer('processing_time')->nullable()->comment('처리 시간 (초)');
            $table->timestamp('started_at')->nullable()->comment('분석 시작 시각');
            $table->timestamp('completed_at')->nullable()->comment('분석 완료 시각');
            $table->timestamps();
            
            $table->foreign('tender_id')->references('id')->on('tenders')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('company_profile_id')->references('id')->on('company_profiles')->onDelete('set null');
            $table->index(['tender_id', 'user_id']);
            $table->index('total_score');
            $table->index('status');
            $table->index('completed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('analyses');
    }
};
