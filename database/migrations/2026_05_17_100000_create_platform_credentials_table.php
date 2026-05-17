<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('platform', 30); // facebook, instagram, x, linkedin, tiktok
            $table->text('credentials');    // encrypted JSON: app_id, app_secret, etc.
            $table->timestamps();

            $table->unique(['organization_id', 'platform']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_credentials');
    }
};
