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
        Schema::table('attachments', function (Blueprint $table) {
            $table->string('type')->default('attachment')->after('mime_type')->comment('파일 타입: attachment, proposal');
            $table->string('download_url')->nullable()->after('type')->comment('나라장터 다운로드 URL');
            $table->string('doc_name')->nullable()->after('download_url')->comment('문서명 (제안요청서, 과업지시서 등)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attachments', function (Blueprint $table) {
            $table->dropColumn(['type', 'download_url', 'doc_name']);
        });
    }
};
