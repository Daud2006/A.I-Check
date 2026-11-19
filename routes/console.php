<?php

use App\Models\ImageAnalysis;
use App\Models\UsageRecord;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('analyses:recover', function () {
    $cutoff = now()->subMinutes(10);
    $staleAnalyses = ImageAnalysis::query()
        ->whereIn('status', ['pending', 'processing'])
        ->where('updated_at', '<=', $cutoff);
    $recovered = 0;

    $staleAnalyses->select('id')->chunkById(100, function ($analyses) use ($staleAnalyses, &$recovered): void {
        foreach ($analyses as $analysis) {
            $recovered += DB::transaction(function () use ($staleAnalyses, $analysis): int {
                $updated = (clone $staleAnalyses)->whereKey($analysis->getKey())->update([
                    'status' => 'failed',
                    'result_label' => null,
                    'confidence_score' => null,
                    'model_name' => null,
                    'prediction_details' => null,
                ]);

                if ($updated === 1) {
                    UsageRecord::query()->where('image_analysis_id', $analysis->getKey())
                        ->where('usage_type', 'analysis')->delete();
                }

                return $updated;
            }, 3);
        }
    });

    $this->info("Analisis tertahan yang ditangani: {$recovered}");
})->purpose('Mark stale analyses as failed and release their reserved quota');

Schedule::command('analyses:recover')->everyMinute()->withoutOverlapping();
