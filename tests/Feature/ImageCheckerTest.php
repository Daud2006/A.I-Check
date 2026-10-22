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
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Process\Factory as ProcessFactory;
use Illuminate\Process\FakeProcessResult;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessTimedOutException as SymfonyProcessTimedOutException;
use Symfony\Component\Process\Process as SymfonyProcess;
use Tests\TestCase;

class ImageCheckerTest extends TestCase
{
    use RefreshDatabase;

    private const MODEL_ID = 'dima806/ai_vs_human_generated_image_detection';

    private const WORKER_SECRET = 'test-only-worker-secret-with-32-bytes';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.local_ai_worker.enabled' => false,
            'services.local_ai_worker.subprocess_fallback' => false,
        ]);

        $this->fakeLocalInference();
    }

    public function test_guest_cannot_upload_or_access_analysis_files(): void
    {
        Storage::fake('image-analyses');

        $this->postJson(route('checker.analyses.store'), [
            'image' => UploadedFile::fake()->image('poster.png'),
        ])->assertUnauthorized();

        $this->getJson(route('checker.analyses.show', 1))->assertUnauthorized();
        $this->get(route('checker.analyses.image', 1))->assertRedirect(route('auth'));
        Process::assertNothingRan();
    }

    public function test_authenticated_user_upload_is_classified_by_local_python_and_saved(): void
    {
        Storage::fake('image-analyses');
        Http::fake();
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson(route('checker.analyses.store'), [
                'image' => UploadedFile::fake()->image('poster.png'),
            ])
            ->assertCreated()
            ->assertJsonPath('status', 'completed')
            ->assertJsonPath('result_label', 'artificial')
            ->assertJsonPath('model_name', self::MODEL_ID);

        $analysis = ImageAnalysis::query()->findOrFail($response->json('id'));
        $this->assertSame($user->id, $analysis->user_id);
        $this->assertSame('poster.png', $analysis->original_filename);
        $this->assertSame('image/png', $analysis->mime_type);
        $this->assertGreaterThan(0, $analysis->file_size);
        $this->assertSame('completed', $analysis->status);
        $this->assertSame(self::MODEL_ID, $analysis->model_name);
        $this->assertSame('artificial', $analysis->result_label);
        $this->assertEqualsWithDelta(0.91, (float) $analysis->confidence_score, 0.000001);
        $this->assertStringNotContainsString('poster.png', $analysis->image_path);
        Storage::disk('image-analyses')->assertExists($analysis->image_path);

        $storedImagePath = realpath(Storage::disk('image-analyses')->path($analysis->image_path));
        $expectedCommand = [
            base_path('ai-service/.venv/Scripts/python.exe'),
            base_path('ai-service/predict.py'),
            $storedImagePath,
            '--json',
        ];
        Process::assertRan(fn (PendingProcess $process): bool => $process->command === $expectedCommand);
        Http::assertNothingSent();
    }

    public function test_ready_worker_classifies_private_upload_without_starting_subprocess(): void
    {
        Storage::fake('image-analyses');
        $this->enableWorker();
        Http::fake([
            'http://127.0.0.1:8765/health' => Http::response([
                'status' => 'ready',
                'model' => self::MODEL_ID,
            ]),
            'http://127.0.0.1:8765/infer' => Http::response([
                'label' => 'ai_generated',
                'score' => 0.91,
                'model' => self::MODEL_ID,
                'inference_time_ms' => 321.5,
            ]),
        ]);

        $response = $this->uploadImage()
            ->assertCreated()
            ->assertJsonPath('status', 'completed')
            ->assertJsonPath('result_label', 'artificial')
            ->assertJsonPath('model_name', self::MODEL_ID);

        $analysis = ImageAnalysis::query()->findOrFail($response->json('id'));
        $this->assertSame('completed', $analysis->status);
        $this->assertSame('artificial', $analysis->result_label);
        $this->assertEqualsWithDelta(0.91, (float) $analysis->confidence_score, 0.000001);
        Storage::disk('image-analyses')->assertExists($analysis->image_path);

        Http::assertSent(fn (HttpRequest $request): bool => $request->url() === 'http://127.0.0.1:8765/health'
            && $request->hasHeader('Authorization', 'Bearer '.self::WORKER_SECRET));
        Http::assertSent(function (HttpRequest $request): bool {
            if ($request->url() !== 'http://127.0.0.1:8765/infer' || $request->method() !== 'POST') {
                return false;
            }

            $root = realpath(Storage::disk('image-analyses')->path(''));
            $imagePath = realpath((string) ($request->data()['path'] ?? ''));

            return $request->hasHeader('Authorization', 'Bearer '.self::WORKER_SECRET)
                && is_string($root)
                && is_string($imagePath)
                && str_starts_with(
                    strtolower(str_replace('\\', '/', $imagePath)),
                    strtolower(rtrim(str_replace('\\', '/', $root), '/').'/'),
                );
        });
        Process::assertNothingRan();
    }

    public function test_worker_authentication_failure_does_not_fallback_by_default(): void
    {
        Storage::fake('image-analyses');
        $this->enableWorker();
        Http::fake([
            'http://127.0.0.1:8765/health' => Http::response(['error' => 'Unauthorized.'], 401),
        ]);

        $response = $this->uploadImage()
            ->assertStatus(503)
            ->assertJsonPath('status', 'failed')
            ->assertJsonMissingPath('result_label');

        $this->assertFailedWithoutPrediction(ImageAnalysis::query()->findOrFail($response->json('id')));
        Http::assertSentCount(1);
        Process::assertNothingRan();
    }

    public function test_worker_configuration_rejects_non_loopback_urls_without_sending_a_request(): void
    {
        Storage::fake('image-analyses');
        $this->enableWorker();
        config(['services.local_ai_worker.url' => 'http://192.168.1.10:8765']);
        Http::fake();

        $response = $this->uploadImage()
            ->assertStatus(503)
            ->assertJsonPath('status', 'failed');

        $this->assertFailedWithoutPrediction(ImageAnalysis::query()->findOrFail($response->json('id')));
        Http::assertNothingSent();
        Process::assertNothingRan();
    }

    public function test_unavailable_worker_uses_subprocess_only_when_explicitly_enabled(): void
    {
        Storage::fake('image-analyses');
        $this->enableWorker(subprocessFallback: true);
        Http::fake([
            'http://127.0.0.1:8765/health' => Http::response(['error' => 'Unavailable.'], 503),
        ]);

        $response = $this->uploadImage()
            ->assertCreated()
            ->assertJsonPath('status', 'completed')
            ->assertJsonPath('result_label', 'artificial');

        $analysis = ImageAnalysis::query()->findOrFail($response->json('id'));
        $this->assertSame('completed', $analysis->status);
        Http::assertSentCount(1);
        Process::assertRan(fn (PendingProcess $process): bool => $process->command !== []);
    }

    public function test_unavailable_worker_fails_analysis_when_fallback_is_disabled(): void
    {
        Storage::fake('image-analyses');
        $this->enableWorker();
        Http::fake([
            'http://127.0.0.1:8765/health' => Http::response(['error' => 'Unavailable.'], 503),
        ]);

        $response = $this->uploadImage()
            ->assertStatus(503)
            ->assertJsonPath('status', 'failed');

        $this->assertFailedWithoutPrediction(ImageAnalysis::query()->findOrFail($response->json('id')));
        Http::assertSentCount(1);
        Process::assertNothingRan();
    }

    public function test_worker_inference_failure_never_starts_a_second_classifier(): void
    {
        Storage::fake('image-analyses');
        $this->enableWorker(subprocessFallback: true);
        Http::fake([
            'http://127.0.0.1:8765/health' => Http::response([
                'status' => 'ready',
                'model' => self::MODEL_ID,
            ]),
            'http://127.0.0.1:8765/infer' => Http::response(['error' => 'Internal error.'], 500),
        ]);

        $response = $this->uploadImage()
            ->assertStatus(503)
            ->assertJsonPath('status', 'failed');

        $this->assertFailedWithoutPrediction(ImageAnalysis::query()->findOrFail($response->json('id')));
        Http::assertSentCount(2);
        Process::assertNothingRan();
    }

    public function test_worker_connection_failure_is_safe_and_does_not_fallback_by_default(): void
    {
        Storage::fake('image-analyses');
        $this->enableWorker();
        Http::fake(fn () => throw new ConnectionException('connection refused'));

        $response = $this->uploadImage()
            ->assertStatus(503)
            ->assertJsonPath('status', 'failed')
            ->assertDontSee('connection refused');

        $this->assertFailedWithoutPrediction(ImageAnalysis::query()->findOrFail($response->json('id')));
        Process::assertNothingRan();
    }

    public function test_worker_inference_timeout_does_not_retry_using_subprocess(): void
    {
        Storage::fake('image-analyses');
        $this->enableWorker(subprocessFallback: true);
        $attemptedRequests = 0;
        Http::fake(function (HttpRequest $request) use (&$attemptedRequests) {
            $attemptedRequests++;

            if ($request->url() === 'http://127.0.0.1:8765/health') {
                return Http::response(['status' => 'ready', 'model' => self::MODEL_ID]);
            }

            throw new ConnectionException('request timed out');
        });

        $response = $this->uploadImage()
            ->assertStatus(504)
            ->assertJsonPath('status', 'failed')
            ->assertDontSee('request timed out');

        $this->assertFailedWithoutPrediction(ImageAnalysis::query()->findOrFail($response->json('id')));
        $this->assertSame(2, $attemptedRequests);
        Process::assertNothingRan();
    }

    public function test_invalid_worker_json_or_prediction_never_creates_a_prediction(): void
    {
        Storage::fake('image-analyses');
        $this->enableWorker(subprocessFallback: true);
        $workerBodies = [
            'not-json',
            [
                'label' => 'unsupported',
                'score' => 1.2,
                'model' => self::MODEL_ID,
                'inference_time_ms' => 2,
            ],
        ];
        $bodyIndex = 0;
        Http::fake(static function (HttpRequest $request) use (&$bodyIndex, $workerBodies) {
            if ($request->url() === 'http://127.0.0.1:8765/health') {
                return Http::response(['status' => 'ready', 'model' => self::MODEL_ID]);
            }

            return Http::response($workerBodies[$bodyIndex++]);
        });

        foreach ($workerBodies as $_) {
            $response = $this->uploadImage()
                ->assertStatus(502)
                ->assertJsonPath('status', 'failed');

            $this->assertFailedWithoutPrediction(ImageAnalysis::query()->findOrFail($response->json('id')));
        }

        Http::assertSentCount(4);
        Process::assertNothingRan();
    }

    public function test_local_model_end_to_end_opt_in(): void
    {
        if (getenv('RUN_LOCAL_AI_E2E') !== '1') {
            $this->markTestSkipped('Set RUN_LOCAL_AI_E2E=1 to run the local-model integration test.');
        }

        if (
            ! is_file(base_path('ai-service/.venv/Scripts/python.exe'))
            || ! is_file(base_path('ai-service/model/model.safetensors'))
        ) {
            $this->markTestSkipped('The local Python environment or model weights are unavailable.');
        }

        Process::swap(new ProcessFactory);
        Storage::fake('image-analyses');
        Http::fake();

        $response = $this->actingAs(User::factory()->create())
            ->postJson(route('checker.analyses.store'), [
                'image' => UploadedFile::fake()->image('real-local-inference.png'),
            ])
            ->assertCreated()
            ->assertJsonPath('status', 'completed')
            ->assertJsonPath('model_name', self::MODEL_ID);

        $analysis = ImageAnalysis::query()->findOrFail($response->json('id'));
        $this->assertContains($analysis->result_label, ['artificial', 'human']);
        $this->assertGreaterThanOrEqual(0, (float) $analysis->confidence_score);
        $this->assertLessThanOrEqual(1, (float) $analysis->confidence_score);
        Http::assertNothingSent();
    }

    public function test_non_image_upload_is_rejected_without_running_python(): void
    {
        Storage::fake('image-analyses');

        $this->actingAs(User::factory()->create())
            ->postJson(route('checker.analyses.store'), [
                'image' => UploadedFile::fake()->create('document.txt', 10, 'text/plain'),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['image']);

        $this->assertDatabaseCount('image_analyses', 0);
        Process::assertNothingRan();
    }

    public function test_image_over_ten_megabytes_is_rejected(): void
    {
        Storage::fake('image-analyses');

        $this->actingAs(User::factory()->create())
            ->postJson(route('checker.analyses.store'), [
                'image' => UploadedFile::fake()->image('large.png')->size(10241),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['image']);

        $this->assertDatabaseCount('image_analyses', 0);
        Process::assertNothingRan();
    }

    public function test_owner_can_view_status_and_download_private_image(): void
    {
        Storage::fake('image-analyses');
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
            ->assertJsonPath('result_label', 'artificial')
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
        Process::assertNothingRan();
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
    }

    public function test_invalid_json_output_marks_analysis_failed_without_a_prediction(): void
    {
        Storage::fake('image-analyses');
        $this->fakeLocalInference('not-json');

        $response = $this->uploadImage()
            ->assertStatus(502)
            ->assertJsonPath('status', 'failed')
            ->assertJsonPath('message', 'Hasil analisis AI lokal tidak dapat diverifikasi. Silakan coba kembali.')
            ->assertDontSee('not-json');

        $analysis = ImageAnalysis::query()->findOrFail($response->json('id'));
        $this->assertFailedWithoutPrediction($analysis);
    }

    public function test_nonzero_python_exit_is_safe_and_does_not_save_a_prediction(): void
    {
        Storage::fake('image-analyses');
        $this->fakeLocalInference(
            '',
            1,
            "run_failed=true error_type=FileNotFoundError\nTraceback includes private path and secret token",
        );
        Log::shouldReceive('warning')
            ->once()
            ->with('Local Python inference process failed.', Mockery::on(function (array $context): bool {
                $this->assertSame('http_request', $context['stage']);
                $this->assertSame(1, $context['process_exit_code']);
                $this->assertSame('FileNotFoundError', $context['python_error_type']);
                $this->assertSame(
                    ['stage', 'process_exit_code', 'python_error_type'],
                    array_keys($context),
                );

                return true;
            }));
        Log::shouldReceive('warning')
            ->once()
            ->with('Local AI image classification failed.', Mockery::type('array'));

        $response = $this->uploadImage()
            ->assertStatus(503)
            ->assertJsonPath('status', 'failed')
            ->assertDontSee('private traceback')
            ->assertDontSee('ai-service');

        $analysis = ImageAnalysis::query()->findOrFail($response->json('id'));
        $this->assertFailedWithoutPrediction($analysis);
    }

    public function test_python_timeout_is_reported_without_internal_details(): void
    {
        Storage::fake('image-analyses');
        Process::fake(function (PendingProcess $pendingProcess) {
            $process = new SymfonyProcess($pendingProcess->command);
            $process->setTimeout(60);
            $timeout = new SymfonyProcessTimedOutException($process, SymfonyProcessTimedOutException::TYPE_GENERAL);

            return new ProcessTimedOutException($timeout, new FakeProcessResult);
        });
        Process::preventStrayProcesses();

        $response = $this->uploadImage()
            ->assertStatus(504)
            ->assertJsonPath('status', 'failed')
            ->assertJsonPath('message', 'Analisis gambar memerlukan waktu terlalu lama. Silakan coba kembali.');

        $this->assertFailedWithoutPrediction(ImageAnalysis::query()->findOrFail($response->json('id')));
    }

    public function test_invalid_python_labels_and_scores_are_rejected(): void
    {
        Storage::fake('image-analyses');
        $invalidPredictions = [
            ['label' => 'possibly_ai', 'score' => 0.9, 'model' => self::MODEL_ID, 'inference_time_ms' => 12.5],
            ['label' => 'ai_generated', 'score' => 1.1, 'model' => self::MODEL_ID, 'inference_time_ms' => 12.5],
        ];
        $predictionIndex = 0;
        $this->fakeLocalInference(static function () use (&$predictionIndex, $invalidPredictions): string {
            return json_encode($invalidPredictions[$predictionIndex++], JSON_THROW_ON_ERROR);
        });

        foreach ($invalidPredictions as $_) {
            $response = $this->uploadImage()
                ->assertStatus(502)
                ->assertJsonPath('status', 'failed');

            $this->assertFailedWithoutPrediction(ImageAnalysis::query()->findOrFail($response->json('id')));
        }
    }

    public function test_database_failure_while_saving_prediction_does_not_leave_a_false_result(): void
    {
        Storage::fake('image-analyses');
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
                'image' => UploadedFile::fake()->image('local-model-test.png'),
            ]);
    }

    private function enableWorker(bool $subprocessFallback = false): void
    {
        config([
            'services.local_ai_worker.enabled' => true,
            'services.local_ai_worker.url' => 'http://127.0.0.1:8765',
            'services.local_ai_worker.secret' => self::WORKER_SECRET,
            'services.local_ai_worker.connect_timeout' => 2,
            'services.local_ai_worker.health_timeout' => 3,
            'services.local_ai_worker.timeout' => 50,
            'services.local_ai_worker.subprocess_fallback' => $subprocessFallback,
        ]);
    }

    /**
     * @param  string|callable(PendingProcess): string  $output
     */
    private function fakeLocalInference(
        string|callable $output = '',
        int $exitCode = 0,
        string $errorOutput = '',
    ): void {
        Process::fake(function (PendingProcess $pendingProcess) use ($output, $exitCode, $errorOutput) {
            $processOutput = is_callable($output) ? $output($pendingProcess) : $output;

            if ($processOutput === '') {
                $processOutput = json_encode([
                    'label' => 'ai_generated',
                    'score' => 0.91,
                    'model' => self::MODEL_ID,
                    'inference_time_ms' => 321.5,
                ], JSON_THROW_ON_ERROR);
            }

            return Process::result($processOutput, $errorOutput, $exitCode);
        });
        Process::preventStrayProcesses();
    }

    private function assertFailedWithoutPrediction(ImageAnalysis $analysis): void
    {
        $this->assertSame('failed', $analysis->status);
        $this->assertNull($analysis->result_label);
        $this->assertNull($analysis->confidence_score);
        $this->assertNull($analysis->model_name);
    }
}
