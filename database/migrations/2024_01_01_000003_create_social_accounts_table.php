<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('platform'); // facebook, instagram, tiktok, x, linkedin
            $table->string('account_name');
            $table->string('account_handle')->nullable();
            $table->string('external_account_id')->nullable();
            $table->text('access_token')->nullable();   // encrypted
            $table->text('refresh_token')->nullable();  // encrypted
            $table->timestamp('token_expires_at')->nullable();
            $table->string('status')->default('active'); // active, expired, disconnected, error
            $table->string('avatar_url')->nullable();
            $table->json('scopes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('last_verified_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'platform']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_accounts');
    }
};
