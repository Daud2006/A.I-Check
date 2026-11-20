<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'is_banned')) {
                $table->boolean('is_banned')->default(false);
            }
            if (! Schema::hasColumn('users', 'banned_at')) {
                $table->timestamp('banned_at')->nullable();
            }
            if (! Schema::hasColumn('users', 'ban_reason')) {
                $table->text('ban_reason')->nullable();
            }
            if (! Schema::hasColumn('users', 'banned_by')) {
                $table->foreignId('banned_by')->nullable()->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('users', 'unbanned_at')) {
                $table->timestamp('unbanned_at')->nullable();
            }
            if (! Schema::hasColumn('users', 'unbanned_by')) {
                $table->foreignId('unbanned_by')->nullable()->constrained('users')->nullOnDelete();
            }
        });

        if (! Schema::hasTable('ban_histories')) {
            Schema::create('ban_histories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('admin_id')->constrained('users')->cascadeOnDelete();
                $table->string('action', 20);
                $table->text('reason')->nullable();
                $table->timestamp('acted_at');
                $table->timestamps();
                $table->index(['user_id', 'acted_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ban_histories');

        Schema::table('users', function (Blueprint $table) {
            foreach (['banned_by', 'unbanned_by'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropConstrainedForeignId($column);
                }
            }
        });

        Schema::table('users', function (Blueprint $table) {
            foreach (['is_banned', 'banned_at', 'ban_reason', 'unbanned_at'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
