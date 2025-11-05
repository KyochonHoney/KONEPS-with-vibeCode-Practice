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
        // ENUM에 'no_link' 값 추가
        DB::statement("ALTER TABLE attachments MODIFY COLUMN download_status ENUM('pending','downloading','completed','failed','no_link') NOT NULL DEFAULT 'pending'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // ENUM에서 'no_link' 값 제거
        DB::statement("ALTER TABLE attachments MODIFY COLUMN download_status ENUM('pending','downloading','completed','failed') NOT NULL DEFAULT 'pending'");
    }
};
