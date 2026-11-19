<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('image_analyses', function (Blueprint $table): void {
            $table->json('prediction_details')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('image_analyses', function (Blueprint $table): void {
            $table->dropColumn('prediction_details');
        });
    }
};
