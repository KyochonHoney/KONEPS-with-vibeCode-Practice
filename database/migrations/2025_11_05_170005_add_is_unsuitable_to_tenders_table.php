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
            $table->boolean('is_unsuitable')->default(false)->after('is_favorite')->comment('비적합 여부 (true=비적합)');
            $table->index('is_unsuitable');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenders', function (Blueprint $table) {
            $table->dropIndex(['is_unsuitable']);
            $table->dropColumn('is_unsuitable');
        });
    }
};
