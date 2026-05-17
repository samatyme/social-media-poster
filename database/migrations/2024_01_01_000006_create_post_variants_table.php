<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('social_account_id')->constrained()->cascadeOnDelete();
            $table->string('platform');
            $table->longText('content')->nullable();
            $table->json('hashtags')->nullable();
            $table->text('first_comment')->nullable();
            $table->string('link_url')->nullable();
            $table->string('visibility')->default('public'); // public, private, friends
            $table->string('status')->default('draft');
            // draft, valid, invalid, queued, publishing, published, failed
            $table->json('validation_errors')->nullable();
            $table->json('platform_options')->nullable(); // extra platform-specific settings
            $table->timestamps();

            $table->index(['post_id', 'platform']);
            $table->unique(['post_id', 'social_account_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_variants');
    }
};
