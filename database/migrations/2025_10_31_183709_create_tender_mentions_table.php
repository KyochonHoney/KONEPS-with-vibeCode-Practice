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
        Schema::create('tender_mentions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tender_id')->constrained()->onDelete('cascade')->comment('공고 ID');
            $table->foreignId('user_id')->constrained()->onDelete('cascade')->comment('사용자 ID');
            $table->text('mention')->nullable()->comment('사용자 메모');
            $table->timestamps();

            // 복합 유니크 인덱스: 한 사용자당 공고 하나에 하나의 멘션만
            $table->unique(['tender_id', 'user_id']);
            $table->index('tender_id');
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tender_mentions');
    }
};
