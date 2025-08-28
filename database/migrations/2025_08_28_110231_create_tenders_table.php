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
        Schema::create('tenders', function (Blueprint $table) {
            $table->id();
            $table->string('tender_no', 100)->unique()->comment('공고번호');
            $table->string('title', 500)->comment('공고 제목');
            $table->text('content')->nullable()->comment('공고 내용');
            $table->string('agency')->nullable()->comment('발주기관');
            $table->decimal('budget', 15, 2)->nullable()->comment('예산금액');
            $table->string('currency', 3)->default('KRW')->comment('통화');
            $table->date('start_date')->nullable()->comment('공고시작일');
            $table->date('end_date')->nullable()->comment('공고마감일');
            $table->unsignedBigInteger('category_id')->nullable()->comment('분류 ID');
            $table->string('region', 100)->nullable()->comment('지역');
            $table->enum('status', ['active', 'closed', 'cancelled'])->default('active')->comment('공고상태');
            $table->text('source_url')->nullable()->comment('원본 URL');
            $table->timestamp('collected_at')->nullable()->comment('수집 시각');
            $table->json('metadata')->nullable()->comment('추가 정보');
            $table->timestamps();
            
            $table->foreign('category_id')->references('id')->on('tender_categories')->onDelete('set null');
            $table->index('tender_no');
            $table->index('agency');
            $table->index('budget');
            $table->index('end_date');
            $table->index('category_id');
            $table->index('status');
            $table->index('collected_at');
            $table->fullText(['title', 'content', 'agency'], 'idx_search');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenders');
    }
};
