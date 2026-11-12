<?php

namespace Tests\Feature;

use App\Models\ImageAnalysis;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImageDetectorFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_upload_persists_the_current_ai_engine_result_for_the_checker(): void
    {
        Storage::fake('image-analyses');
        config(['services.ai_detector.url' => 'http://127.0.0.1:8000']);

        Http::fake([
            'http://127.0.0.1:8000/detect' => Http::response([
                'results' => [[
                    'verdict' => 'UNCERTAIN',
                    'p_ai' => 0.6072,
                    'threshold' => 0.7241,
                    'low_threshold' => 0.5272,
                    'basis' => 'pixel ensemble is between the operating points',
                    'per_model' => ['detector-a' => 0.61, 'detector-b' => 0.58],
                    'metadata_says_ai' => false,
                    'metadata_generator' => null,
                    'error' => null,
                ]],
            ]),
        ]);

        $response = $this->actingAs(User::factory()->create())
            ->postJson(route('checker.analyses.store'), [
                'image' => UploadedFile::fake()->image('analysis.png'),
            ])
            ->assertCreated()
            ->assertJsonPath('status', 'completed')
            ->assertJsonPath('result_label', 'UNCERTAIN')
            ->assertJsonPath('confidence_score', 0.6072)
            ->assertJsonPath('engine_evidence.basis', 'pixel ensemble is between the operating points')
            ->assertJsonPath('engine_evidence.threshold', 0.7241)
            ->assertJsonPath('engine_evidence.low_threshold', 0.5272)
            ->assertJsonPath('engine_evidence.per_model.detector-a', 0.61)
            ->assertJsonPath(
                'analysis_explanation',
                'Hasil analisis belum cukup kuat untuk menentukan apakah gambar kemungkinan dibuat menggunakan AI atau termasuk kategori REAL/Human. Estimasi probabilitas AI gabungan: 60.72%.',
            );

        $analysis = ImageAnalysis::query()->findOrFail($response->json('id'));
        $this->assertSame('completed', $analysis->status);
        $this->assertSame('UNCERTAIN', $analysis->result_label);
        $this->assertEqualsWithDelta(0.6072, (float) $analysis->confidence_score, 0.000001);
        $this->assertSame($response->json('analysis_explanation'), $analysis->analysis_explanation);
        Storage::disk('image-analyses')->assertExists($analysis->image_path);

        Http::assertSent(fn (HttpRequest $request): bool => $request->method() === 'POST'
            && $request->url() === 'http://127.0.0.1:8000/detect');
    }
}
