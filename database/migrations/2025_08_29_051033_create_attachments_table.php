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
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tender_id')->constrained('tenders')->onDelete('cascade');
            $table->string('file_name'); // 저장된 파일명
            $table->string('original_name'); // 원본 파일명
            $table->text('file_url')->nullable(); // 원본 다운로드 URL
            $table->string('file_type', 20)->nullable(); // 파일 확장자
            $table->bigInteger('file_size')->nullable(); // 파일 크기 (bytes)
            $table->string('mime_type', 100)->nullable(); // MIME 타입
            $table->text('local_path')->nullable(); // 로컬 저장 경로
            $table->enum('download_status', ['pending', 'downloading', 'completed', 'failed'])->default('pending');
            $table->text('download_error')->nullable(); // 다운로드 오류 메시지
            $table->timestamp('downloaded_at')->nullable(); // 다운로드 완료 시간
            $table->timestamps();
            
            // 인덱스 추가
            $table->index(['tender_id', 'download_status']);
            $table->index(['file_type', 'download_status']);
            $table->index('downloaded_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
