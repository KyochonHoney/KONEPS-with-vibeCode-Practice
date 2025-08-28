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
        Schema::create('user_roles', function (Blueprint $table) {
            // [BEGIN nara:user_roles_pivot]
            $table->id();
            $table->unsignedBigInteger('user_id')->comment('사용자 ID');
            $table->unsignedBigInteger('role_id')->comment('역할 ID');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');
            $table->unique(['user_id', 'role_id'], 'user_role_unique');
            $table->index('user_id');
            $table->index('role_id');
            // [END nara:user_roles_pivot]
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_roles');
    }
};
