<?php

namespace Tests\Feature;

use App\Exceptions\ImageClassificationException;
use App\Models\ImageAnalysis;
use App\Services\LocalAiDetector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LocalAiDetectorTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_engine_evidence_is_passed_through_without_reinterpretation(): void
    {
        Storage::fake('image-analyses');
        config(['services.ai_detector.url' => 'http://127.0.0.1:8000']);
        Http::fake([
            'http://127.0.0.1:8000/detect' => Http::response([
                'results' => [[
                    'verdict' => 'AI-EDITED',
                    'p_ai' => 0.63,
                    'threshold' => 0.72,
                    'low_threshold' => 0.36,
                    'basis' => 'metadata describes a generated region in an otherwise real image',
                    'per_model' => ['detector-a' => 0.67, 'detector-b' => 0.59],
                    'metadata_says_ai' => true,
                    'metadata_generator' => 'ComfyUI',
                    'error' => null,
                ]],
            ]),
        ]);

        $analysis = $this->makeStoredAnalysis();
        $result = app(LocalAiDetector::class)->classify($analysis);

        $this->assertSame('AI-EDITED', $result['label']);
        $this->assertSame(0.63, $result['score']);
        $this->assertSame([
            'basis' => 'metadata describes a generated region in an otherwise real image',
            'threshold' => 0.72,
            'low_threshold' => 0.36,
            'per_model' => ['detector-a' => 0.67, 'detector-b' => 0.59],
            'metadata_says_ai' => true,
            'metadata_generator' => 'ComfyUI',
        ], $result['evidence']);
    }

    public function test_supported_verdicts_are_preserved_and_engine_errors_are_not_predictions(): void
    {
        Storage::fake('image-analyses');
        config(['services.ai_detector.url' => 'http://127.0.0.1:8000']);
        Http::fakeSequence('http://127.0.0.1:8000/detect')
            ->push(['results' => [$this->engineResult('AI-GENERATED', 0.91)]])
            ->push(['results' => [$this->engineResult('REAL', 0.08)]])
            ->push(['results' => [$this->engineResult('UNCERTAIN', 0.51)]])
            ->push(['results' => [[
                ...$this->engineResult('ERROR', null),
                'error' => 'engine failure',
            ]]]);

        $analysis = $this->makeStoredAnalysis();
        $detector = app(LocalAiDetector::class);

        $this->assertSame('AI-GENERATED', $detector->classify($analysis)['label']);
        $this->assertSame('REAL', $detector->classify($analysis)['label']);
        $this->assertSame('UNCERTAIN', $detector->classify($analysis)['label']);

        try {
            $detector->classify($analysis);
            $this->fail('An ERROR response must not be returned as a prediction.');
        } catch (ImageClassificationException $exception) {
            $this->assertSame(502, $exception->httpStatus);
        }
    }

    private function makeStoredAnalysis(): ImageAnalysis
    {
        $path = UploadedFile::fake()->image('detector.png')->store('guests/test', 'image-analyses');

        return new ImageAnalysis([
            'original_filename' => 'detector.png',
            'image_path' => $path,
            'mime_type' => 'image/png',
            'file_size' => 123,
        ]);
    }

    private function engineResult(string $verdict, ?float $score): array
    {
        return [
            'verdict' => $verdict,
            'p_ai' => $score,
            'threshold' => 0.72,
            'low_threshold' => 0.36,
            'basis' => 'pixel ensemble',
            'per_model' => ['detector-a' => 0.5],
            'metadata_says_ai' => false,
            'metadata_generator' => null,
            'error' => null,
        ];
    }
}
