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
        Schema::create('tender_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('분류명');
            $table->string('code', 50)->unique()->nullable()->comment('분류코드');
            $table->unsignedBigInteger('parent_id')->nullable()->comment('상위 분류 ID');
            $table->text('description')->nullable()->comment('분류 설명');
            $table->boolean('is_active')->default(true)->comment('활성 상태');
            $table->integer('sort_order')->default(0)->comment('정렬 순서');
            $table->timestamps();
            
            $table->foreign('parent_id')->references('id')->on('tender_categories')->onDelete('set null');
            $table->index('parent_id');
            $table->index('is_active');
            $table->index('sort_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tender_categories');
    }
};
