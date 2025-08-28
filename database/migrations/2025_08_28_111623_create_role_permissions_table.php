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
        Schema::create('role_permissions', function (Blueprint $table) {
            // [BEGIN nara:role_permissions_pivot]
            $table->id();
            $table->unsignedBigInteger('role_id')->comment('역할 ID');
            $table->unsignedBigInteger('permission_id')->comment('권한 ID');
            $table->timestamps();

            $table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');
            $table->foreign('permission_id')->references('id')->on('permissions')->onDelete('cascade');
            $table->unique(['role_id', 'permission_id'], 'role_permission_unique');
            $table->index('role_id');
            $table->index('permission_id');
            // [END nara:role_permissions_pivot]
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('role_permissions');
    }
};
