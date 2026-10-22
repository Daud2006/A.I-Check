<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'role')) {
                $table->string('role', 20)->default('user');
            }

            if (! Schema::hasColumn('users', 'avatar')) {
                $table->string('avatar')->nullable();
            }
        });

        Schema::create('image_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('original_filename');
            $table->string('image_path', 500)->nullable();
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('file_size');
            $table->string('model_name')->nullable();
            $table->string('status', 20)->default('pending');
            $table->string('result_label', 50)->nullable();
            $table->decimal('confidence_score', 7, 6)->nullable();
            $table->timestamps();
            $table->index('user_id', 'image_analyses_user_id_index');
            $table->index('status', 'image_analyses_status_index');
            $table->index('created_at', 'image_analyses_created_at_index');
        });

        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('slug', 100)->unique();
            $table->text('description')->nullable();
            $table->decimal('price', 12, 2)->unsigned()->default(0);
            $table->char('currency', 3)->default('IDR');
            $table->string('billing_interval', 20);
            $table->unsignedInteger('analysis_limit')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->string('status', 20);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->index('user_id', 'subscriptions_user_id_index');
            $table->index('plan_id', 'subscriptions_plan_id_index');
            $table->index('status', 'subscriptions_status_index');
            $table->index('created_at', 'subscriptions_created_at_index');
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('provider', 100)->nullable();
            $table->string('provider_transaction_id')->nullable();
            $table->decimal('amount', 12, 2)->unsigned();
            $table->char('currency', 3)->default('IDR');
            $table->string('status', 20);
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->unique(
                ['provider', 'provider_transaction_id'],
                'payments_provider_transaction_unique'
            );
            $table->index('user_id', 'payments_user_id_index');
            $table->index('subscription_id', 'payments_subscription_id_index');
            $table->index('status', 'payments_status_index');
        });

        Schema::create('usage_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('image_analysis_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->string('usage_type', 50);
            $table->unsignedInteger('quantity')->default(1);
            $table->timestamp('created_at')->useCurrent();
            $table->index(['user_id', 'created_at'], 'usage_records_user_created_at_index');
            $table->index('image_analysis_id', 'usage_records_image_analysis_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_records');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('image_analyses');
        Schema::dropIfExists('plans');

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'avatar')) {
                $table->dropColumn('avatar');
            }

            if (Schema::hasColumn('users', 'role')) {
                $table->dropColumn('role');
            }
        });
    }
};
