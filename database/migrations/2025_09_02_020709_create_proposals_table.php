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
        Schema::create('proposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tender_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->longText('content');
            $table->string('template_version')->default('v1.0');
            $table->json('ai_analysis_data')->nullable();
            $table->enum('status', ['processing', 'completed', 'failed'])->default('processing');
            $table->integer('processing_time')->nullable(); // milliseconds
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();
            
            $table->index(['tender_id', 'status']);
            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('proposals');
    }
};
