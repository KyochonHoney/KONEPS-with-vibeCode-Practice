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
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique()->comment('권한명');
            $table->string('guard_name')->default('web')->comment('가드명');
            $table->string('display_name')->nullable()->comment('표시명');
            $table->text('description')->nullable()->comment('권한 설명');
            $table->timestamps();
            
            $table->index('guard_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};
