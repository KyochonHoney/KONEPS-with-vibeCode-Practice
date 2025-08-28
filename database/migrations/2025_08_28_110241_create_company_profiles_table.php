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
        Schema::create('company_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('회사명');
            $table->string('business_number', 50)->nullable()->comment('사업자등록번호');
            $table->text('description')->nullable()->comment('회사 설명');
            $table->json('capabilities')->nullable()->comment('보유 역량 정보');
            $table->json('experiences')->nullable()->comment('수행 경험 정보');
            $table->json('certifications')->nullable()->comment('보유 자격/인증');
            $table->integer('employees_count')->nullable()->comment('직원 수');
            $table->year('established_year')->nullable()->comment('설립연도');
            $table->decimal('annual_revenue', 15, 2)->nullable()->comment('연매출');
            $table->string('website')->nullable()->comment('웹사이트');
            $table->json('contact_info')->nullable()->comment('연락처 정보');
            $table->boolean('is_active')->default(true)->comment('활성 상태');
            $table->timestamps();
            
            $table->index('name');
            $table->index('is_active');
            $table->fullText(['name', 'description'], 'idx_search');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_profiles');
    }
};
