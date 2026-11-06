<?php

namespace Tests\Feature;

use App\Models\ImageAnalysis;
use App\Models\User;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class ImageCheckerTest extends TestCase
{
    use RefreshDatabase;

    private const DETECTOR_URL = 'http://127.0.0.1:8000';

    private const MODEL_ID = 'simple_ai_image_detector';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.ai_detector.url' => self::DETECTOR_URL,
            'services.ai_detector.connect_timeout' => 5,
            'services.ai_detector.timeout' => 65,
        ]);

        Http::preventStrayRequests();
    }

    public function test_guest_cannot_upload_or_access_analysis_files(): void
    {
        Storage::fake('image-analyses');

        $this->postJson(route('checker.analyses.store'), [
            'image' => UploadedFile::fake()->image('poster.png'),
        ])->assertUnauthorized();

        $this->getJson(route('checker.analyses.show', 1))->assertUnauthorized();
        $this->get(route('checker.analyses.image', 1))->assertRedirect(route('auth'));
        Http::assertNothingSent();
    }

    public function test_authenticated_user_upload_is_classified_and_saved(): void
    {
        Storage::fake('image-analyses');
        $this->fakeDetector();
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson(route('checker.analyses.store'), [
                'image' => UploadedFile::fake()->image('poster.png'),
            ])
            ->assertCreated()
            ->assertJsonPath('status', 'completed')
            ->assertJsonPath('result_label', 'AI-GENERATED')
            ->assertJsonPath('model_name', self::MODEL_ID);

        $analysis = ImageAnalysis::query()->findOrFail($response->json('id'));
        $this->assertSame($user->id, $analysis->user_id);
        $this->assertSame('poster.png', $analysis->original_filename);
        $this->assertSame('image/png', $analysis->mime_type);
        $this->assertGreaterThan(0, $analysis->file_size);
        $this->assertSame('completed', $analysis->status);
        $this->assertSame(self::MODEL_ID, $analysis->model_name);
        $this->assertSame('AI-GENERATED', $analysis->result_label);
        $this->assertEqualsWithDelta(0.91, (float) $analysis->confidence_score, 0.000001);
        $this->assertStringNotContainsString('poster.png', $analysis->image_path);
        Storage::disk('image-analyses')->assertExists($analysis->image_path);

        Http::assertSent(fn (HttpRequest $request): bool => $request->url() === self::DETECTOR_URL.'/detect'
            && $request->method() === 'POST'
            && $request->hasHeader('Content-Type'));
        Http::assertSentCount(1);
    }

    public function test_detector_can_return_each_supported_verdict(): void
    {
        Storage::fake('image-analyses');
        Http::fake(fn () => Http::response([
            'results' => [[
                'verdict' => 'UNCERTAIN',
                'p_ai' => 0.5,
            ]],
        ]));

        $response = $this->uploadImage()
            ->assertCreated()
            ->assertJsonPath('result_label', 'UNCERTAIN')
            ->assertJsonPath('confidence_score', 0.5);

        $analysis = ImageAnalysis::query()->findOrFail($response->json('id'));
        $this->assertSame('UNCERTAIN', $analysis->result_label);
        $this->assertSame(self::MODEL_ID, $analysis->model_name);
    }

    public function test_invalid_detector_response_does_not_save_a_prediction(): void
    {
        Storage::fake('image-analyses');
        Http::fake(fn () => Http::response(['results' => []]));

        $response = $this->uploadImage()
            ->assertStatus(502)
            ->assertJsonPath('status', 'failed')
            ->assertJsonMissingPath('result_label');

        $this->assertFailedWithoutPrediction(ImageAnalysis::query()->findOrFail($response->json('id')));
    }

    public function test_invalid_detector_predictions_are_rejected(): void
    {
        Storage::fake('image-analyses');
        $predictions = [
            ['verdict' => 'MAYBE', 'p_ai' => 0.9],
            ['verdict' => 'AI-GENERATED', 'p_ai' => 1.1],
            ['verdict' => 'REAL', 'p_ai' => '0.5'],
            ['verdict' => 'REAL', 'p_ai' => 0.2, 'error' => 'detector error'],
        ];

        foreach ($predictions as $prediction) {
            Http::fake(fn () => Http::response(['results' => [$prediction]]));

            $response = $this->uploadImage()
                ->assertStatus(502)
                ->assertJsonPath('status', 'failed');

            $this->assertFailedWithoutPrediction(ImageAnalysis::query()->findOrFail($response->json('id')));
        }
    }

    public function test_detector_error_is_reported_without_upstream_details(): void
    {
        Storage::fake('image-analyses');
        Http::fake(fn () => Http::response(['error' => 'private upstream detail'], 500));

        $response = $this->uploadImage()
            ->assertStatus(503)
            ->assertJsonPath('status', 'failed')
            ->assertDontSee('private upstream detail');

        $this->assertFailedWithoutPrediction(ImageAnalysis::query()->findOrFail($response->json('id')));
    }

    public function test_detector_connection_failure_is_reported_safely(): void
    {
        Storage::fake('image-analyses');
        Http::fake(fn () => throw new ConnectionException('connection refused'));

        $response = $this->uploadImage()
            ->assertStatus(503)
            ->assertJsonPath('status', 'failed')
            ->assertDontSee('connection refused');

        $this->assertFailedWithoutPrediction(ImageAnalysis::query()->findOrFail($response->json('id')));
    }

    public function test_invalid_detector_configuration_sends_no_request(): void
    {
        Storage::fake('image-analyses');
        config(['services.ai_detector.url' => 'not-a-url']);

        $response = $this->uploadImage()
            ->assertStatus(503)
            ->assertJsonPath('status', 'failed');

        $this->assertFailedWithoutPrediction(ImageAnalysis::query()->findOrFail($response->json('id')));
        Http::assertNothingSent();
    }

    public function test_non_image_upload_is_rejected_without_calling_detector(): void
    {
        Storage::fake('image-analyses');

        $this->actingAs(User::factory()->create())
            ->postJson(route('checker.analyses.store'), [
                'image' => UploadedFile::fake()->create('document.txt', 10, 'text/plain'),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['image']);

        $this->assertDatabaseCount('image_analyses', 0);
        Http::assertNothingSent();
    }

    public function test_image_over_ten_megabytes_is_rejected_without_calling_detector(): void
    {
        Storage::fake('image-analyses');

        $this->actingAs(User::factory()->create())
            ->postJson(route('checker.analyses.store'), [
                'image' => UploadedFile::fake()->image('large.png')->size(10241),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['image']);

        $this->assertDatabaseCount('image_analyses', 0);
        Http::assertNothingSent();
    }

    public function test_owner_can_view_status_and_download_private_image(): void
    {
        Storage::fake('image-analyses');
        $this->fakeDetector();
        $user = User::factory()->create();
        $response = $this->actingAs($user)
            ->postJson(route('checker.analyses.store'), [
                'image' => UploadedFile::fake()->image('private.png'),
            ])
            ->assertCreated();
        $analysisId = $response->json('id');

        $this->getJson(route('checker.analyses.show', $analysisId))
            ->assertOk()
            ->assertJsonPath('status', 'completed')
            ->assertJsonPath('result_label', 'AI-GENERATED')
            ->assertJsonPath('model_name', self::MODEL_ID)
            ->assertJsonPath('confidence_score', 0.91)
            ->assertJsonMissingPath('image_path');

        $this->get(route('checker.analyses.image', $analysisId))
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename=image')
            ->assertHeader('x-content-type-options', 'nosniff');
    }

    public function test_user_cannot_view_or_download_another_users_analysis(): void
    {
        Storage::fake('image-analyses');
        $this->fakeDetector();
        $owner = User::factory()->create();
        $response = $this->actingAs($owner)
            ->postJson(route('checker.analyses.store'), [
                'image' => UploadedFile::fake()->image('owned.png'),
            ])
            ->assertCreated();
        $analysisId = $response->json('id');

        $this->actingAs(User::factory()->create())
            ->getJson(route('checker.analyses.show', $analysisId))
            ->assertNotFound();

        $this->get(route('checker.analyses.image', $analysisId))->assertNotFound();
    }

    public function test_storage_failure_returns_safe_error_without_creating_analysis(): void
    {
        $user = User::factory()->create();
        $disk = Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('putFileAs')
            ->once()
            ->andThrow(new RuntimeException('Private storage unavailable.'));

        $filesystem = Mockery::mock(FilesystemFactory::class);
        $filesystem->shouldReceive('disk')->with('image-analyses')->andReturn($disk);
        $this->app->instance(FilesystemFactory::class, $filesystem);
        Log::shouldReceive('error')->once();

        $this->actingAs($user)
            ->postJson(route('checker.analyses.store'), [
                'image' => UploadedFile::fake()->image('failed.png'),
            ])
            ->assertStatus(500)
            ->assertJsonPath('message', 'Gambar belum dapat disimpan. Silakan coba kembali.');

        $this->assertDatabaseCount('image_analyses', 0);
        Http::assertNothingSent();
    }

    public function test_database_failure_cleans_up_the_uploaded_file(): void
    {
        Storage::fake('image-analyses');
        $user = User::factory()->create();

        ImageAnalysis::creating(function (): void {
            throw new RuntimeException('Database write failed.');
        });

        Log::shouldReceive('error')->once();

        $this->actingAs($user)
            ->postJson(route('checker.analyses.store'), [
                'image' => UploadedFile::fake()->image('orphan.png'),
            ])
            ->assertStatus(500)
            ->assertJsonPath('message', 'Gambar belum dapat disimpan. Silakan coba kembali.');

        $this->assertDatabaseCount('image_analyses', 0);
        $this->assertSame([], Storage::disk('image-analyses')->allFiles());
        Http::assertNothingSent();
    }

    public function test_database_failure_while_saving_prediction_does_not_leave_a_false_result(): void
    {
        Storage::fake('image-analyses');
        $this->fakeDetector();
        $failCompletedUpdate = true;

        ImageAnalysis::updating(function (ImageAnalysis $analysis) use (&$failCompletedUpdate): void {
            if ($analysis->status === 'completed' && $failCompletedUpdate) {
                $failCompletedUpdate = false;
                throw new RuntimeException('Prediction persistence failed.');
            }
        });

        Log::shouldReceive('error')
            ->once()
            ->with('Unable to complete image classification.', Mockery::type('array'));

        $response = $this->uploadImage()
            ->assertStatus(500)
            ->assertJsonPath('message', 'Analisis belum dapat diselesaikan. Silakan coba kembali.');

        $this->assertFailedWithoutPrediction(ImageAnalysis::query()->findOrFail($response->json('id')));
    }

    private function uploadImage()
    {
        return $this->actingAs(User::factory()->create())
            ->postJson(route('checker.analyses.store'), [
                'image' => UploadedFile::fake()->image('detector-test.png'),
            ]);
    }

    private function fakeDetector(): void
    {
        Http::fake(fn () => Http::response([
            'results' => [[
                'verdict' => 'AI-GENERATED',
                'p_ai' => 0.91,
            ]],
        ]));
    }

    private function assertFailedWithoutPrediction(ImageAnalysis $analysis): void
    {
        $this->assertSame('failed', $analysis->status);
        $this->assertNull($analysis->result_label);
        $this->assertNull($analysis->confidence_score);
        $this->assertNull($analysis->model_name);
    }
}
