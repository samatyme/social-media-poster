<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->string('title')->nullable();
            $table->longText('base_content')->nullable();
            $table->string('status')->default('draft');
            // draft, pending_approval, approved, scheduled, publishing,
            // published, partially_published, failed, cancelled
            $table->string('approval_status')->default('not_required');
            // not_required, pending, approved, rejected
            $table->timestamp('scheduled_at')->nullable();
            $table->string('timezone')->default('UTC');
            $table->timestamp('published_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('approval_notes')->nullable();
            $table->boolean('is_recurring')->default(false);
            $table->json('recurring_config')->nullable();
            $table->json('tags')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'scheduled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
