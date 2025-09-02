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
        // MySQL에서 ENUM 타입 확장
        DB::statement("ALTER TABLE tenders MODIFY COLUMN status ENUM('pending', 'active', 'closed', 'opened', 'completed', 'cancelled') NOT NULL DEFAULT 'active'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 이전 상태로 되돌리기
        DB::statement("ALTER TABLE tenders MODIFY COLUMN status ENUM('active', 'closed', 'cancelled') NOT NULL DEFAULT 'active'");
    }
};
