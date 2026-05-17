<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->string('file_name');
            $table->string('original_name');
            $table->string('file_path');
            $table->string('public_url')->nullable();
            $table->string('mime_type');
            $table->string('disk')->default('local');
            $table->bigInteger('file_size')->default(0); // bytes
            $table->integer('width')->nullable();
            $table->integer('height')->nullable();
            $table->float('duration')->nullable(); // seconds for video
            $table->string('thumbnail_path')->nullable();
            $table->string('status')->default('ready'); // processing, ready, failed
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'mime_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_assets');
    }
};
