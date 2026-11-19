<?php

namespace App\Jobs;

use App\Exceptions\ImageClassificationException;
use App\Models\ImageAnalysis;
use App\Models\UsageRecord;
use App\Services\AnalysisQuotaService;
use App\Services\LocalAiDetector;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ClassifyImageAnalysis implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 80;

    public bool $failOnTimeout = true;

    public function __construct(public readonly int $analysisId)
    {
    }

    public function handle(LocalAiDetector $classifier, AnalysisQuotaService $quota): void
    {
        $analysis = ImageAnalysis::query()->find($this->analysisId);

        if (! $analysis instanceof ImageAnalysis || $analysis->status !== 'pending') {
            return;
        }

        $claimed = ImageAnalysis::query()->whereKey($this->analysisId)
            ->where('status', 'pending')
            ->update(['status' => 'processing']);

        if ($claimed === 0) {
            return;
        }

        try {
            $prediction = $classifier->classify($analysis);

            DB::transaction(function () use ($analysis, $prediction): void {
                $lockedAnalysis = ImageAnalysis::query()
                    ->whereKey($analysis->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($lockedAnalysis->status !== 'processing') {
                    return;
                }

                $lockedAnalysis->result_label = $prediction['label'];
                $lockedAnalysis->confidence_score = $prediction['score'];
                $lockedAnalysis->model_name = $prediction['model'];
                $lockedAnalysis->prediction_details = $prediction['details'];
                $lockedAnalysis->status = 'completed';
                $lockedAnalysis->save();
            });
        } catch (ImageClassificationException $exception) {
            $this->markFailed($analysis, $quota);
            Log::warning('Queued local AI classification failed.', [
                'analysis_id' => $analysis->getKey(),
                ...$exception->diagnosticContext(),
            ]);
        } catch (Throwable $exception) {
            $this->markFailed($analysis, $quota);
            Log::error('Unable to complete queued image classification.', [
                'analysis_id' => $analysis->getKey(),
                ...ImageClassificationException::contextFor($exception, 'queue', 'internal_error', 500, null),
            ]);
        }
    }

    public function failed(?Throwable $exception): void
    {
        $analysis = ImageAnalysis::query()->find($this->analysisId);

        if (! $analysis instanceof ImageAnalysis || $analysis->status === 'completed') {
            return;
        }

        $analysis->result_label = null;
        $analysis->confidence_score = null;
        $analysis->model_name = null;
        $analysis->prediction_details = null;
        $analysis->status = 'failed';
        $analysis->save();

        UsageRecord::query()
            ->where('image_analysis_id', $analysis->getKey())
            ->where('usage_type', 'analysis')
            ->delete();

        Log::error('Queued image analysis job failed.', [
            'analysis_id' => $analysis->getKey(),
            'exception_class' => $exception === null ? null : $exception::class,
        ]);
    }

    private function markFailed(ImageAnalysis $analysis, AnalysisQuotaService $quota): void
    {
        DB::transaction(function () use ($analysis, $quota): void {
            $lockedAnalysis = ImageAnalysis::query()
                ->whereKey($analysis->getKey())
                ->lockForUpdate()
                ->first();

            if (! $lockedAnalysis instanceof ImageAnalysis || $lockedAnalysis->status === 'completed') {
                return;
            }

            $lockedAnalysis->result_label = null;
            $lockedAnalysis->confidence_score = null;
            $lockedAnalysis->model_name = null;
            $lockedAnalysis->prediction_details = null;
            $lockedAnalysis->status = 'failed';
            $lockedAnalysis->save();
            $quota->releaseReservation($lockedAnalysis);
        });
    }
}
