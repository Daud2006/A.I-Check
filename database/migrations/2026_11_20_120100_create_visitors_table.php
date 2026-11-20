<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('visitors')) {
            Schema::create('visitors', function (Blueprint $table) {
                $table->id();
                $table->string('session_id', 191)->unique();
                $table->string('ip_hash', 64);
                $table->string('user_agent', 255)->nullable();
                $table->timestamp('first_seen_at');
                $table->timestamp('last_seen_at');
                $table->timestamps();
                $table->index('last_seen_at');
                $table->index('first_seen_at');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('visitors');
    }
};
