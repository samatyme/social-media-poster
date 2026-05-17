<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('plan')->default('free'); // free, starter, pro, enterprise
            $table->string('status')->default('active'); // active, suspended, cancelled
            $table->string('logo_path')->nullable();
            $table->string('website')->nullable();
            $table->string('timezone')->default('UTC');
            $table->json('settings')->nullable();
            $table->json('feature_flags')->nullable();
            // SaaS limits
            $table->integer('max_social_accounts')->default(5);
            $table->integer('max_scheduled_posts')->default(50);
            $table->integer('max_team_members')->default(3);
            $table->bigInteger('max_storage_bytes')->default(1073741824); // 1GB
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
