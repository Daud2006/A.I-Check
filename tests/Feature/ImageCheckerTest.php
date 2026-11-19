<?php

namespace Tests\Feature;

use App\Jobs\ClassifyImageAnalysis;
use App\Models\ImageAnalysis;
use App\Models\User;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class ImageCheckerTest extends TestCase
{
    use RefreshDatabase;

    private const DETECTOR_URL = 'http://127.0.0.1:8766';

    private const MODEL_ID = 'simple_ai_image_detector';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.ai_detector.url' => self::DETECTOR_URL,
            'services.ai_detector.connect_timeout' => 2,
            'services.ai_detector.health_timeout' => 3,
            'services.ai_detector.timeout' => 50,
            'queue.default' => 'database',
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

        $upload = $this->actingAs($user)
            ->postJson(route('checker.analyses.store'), [
                'image' => UploadedFile::fake()->image('poster.png'),
            ])
            ->assertAccepted()
            ->assertJsonPath('status', 'pending');

        $this->processAnalysis($upload->json('id'));
        $response = $this->getJson(route('checker.analyses.show', $upload->json('id')))
            ->assertOk()
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
        $this->assertSame('fpr5', $analysis->prediction_details['operating_point']);
        $this->assertSame('pixel ensemble', $analysis->prediction_details['basis']);
        $this->assertStringNotContainsString('poster.png', $analysis->image_path);
        Storage::disk('image-analyses')->assertExists($analysis->image_path);

        Http::assertSent(fn (HttpRequest $request): bool => $request->url() === self::DETECTOR_URL.'/health'
            && $request->method() === 'GET');
        Http::assertSent(fn (HttpRequest $request): bool => $request->url() === self::DETECTOR_URL.'/info'
            && $request->method() === 'GET');
        Http::assertSent(fn (HttpRequest $request): bool => $request->url() === self::DETECTOR_URL.'/detect'
            && $request->method() === 'POST'
            && $request->isMultipart()
            && $request->hasFile('files', filename: 'poster.png'));
        Http::assertSentCount(3);
    }

    public function test_detector_preserves_ai_edited_verdict(): void
    {
        Storage::fake('image-analyses');
        $this->fakeDetectorPayload($this->detectorPayload([
            'verdict' => 'AI-EDITED',
            'p_ai' => 0.86,
        ]));

        $response = $this->uploadImage()
            ->assertOk()
            ->assertJsonPath('result_label', 'AI-EDITED')
            ->assertJsonPath('confidence_score', 0.86);

        $analysis = ImageAnalysis::query()->findOrFail($response->json('id'));
        $this->assertSame('AI-EDITED', $analysis->result_label);
        $this->assertSame(self::MODEL_ID, $analysis->model_name);
    }

    public function test_detector_preserves_real_verdict_and_ai_probability_without_inverting_it(): void
    {
        Storage::fake('image-analyses');
        $this->fakeDetectorPayload($this->detectorPayload([
            'verdict' => 'REAL',
            'p_ai' => 0.21,
        ]));

        $response = $this->uploadImage()
            ->assertOk()
            ->assertJsonPath('result_label', 'REAL')
            ->assertJsonPath('confidence_score', 0.21);

        $analysis = ImageAnalysis::query()->findOrFail($response->json('id'));
        $this->assertSame('REAL', $analysis->result_label);
        $this->assertEqualsWithDelta(0.21, (float) $analysis->confidence_score, 0.000001);
    }

    public function test_detector_preserves_uncertain_verdict(): void
    {
        Storage::fake('image-analyses');
        $this->fakeDetectorPayload($this->detectorPayload([
            'verdict' => 'UNCERTAIN',
            'p_ai' => 0.61,
        ]));

        $response = $this->uploadImage()
            ->assertOk()
            ->assertJsonPath('result_label', 'UNCERTAIN')
            ->assertJsonPath('confidence_score', 0.61);

        $analysis = ImageAnalysis::query()->findOrFail($response->json('id'));
        $this->assertSame('UNCERTAIN', $analysis->result_label);
        $this->assertEqualsWithDelta(0.61, (float) $analysis->confidence_score, 0.000001);
    }

    public function test_invalid_detector_response_does_not_save_a_prediction(): void
    {
        Storage::fake('image-analyses');
        $this->fakeDetectorPayload([]);

        $response = $this->uploadImage()
            ->assertOk()
            ->assertJsonPath('status', 'failed')
            ->assertJsonPath('result_label', null);

        $this->assertFailedWithoutPrediction(ImageAnalysis::query()->findOrFail($response->json('id')));
    }

    public function test_invalid_detector_predictions_are_rejected(): void
    {
        Storage::fake('image-analyses');
        $predictions = [
            ['verdict' => 'MAYBE'],
            ['p_ai' => 1.1],
            ['per_model' => ['clip-probe' => '0.5']],
            ['low_threshold' => 0.9],
        ];

        foreach ($predictions as $prediction) {
            $this->fakeDetectorPayload($this->detectorPayload($prediction));

            $response = $this->uploadImage()
                ->assertOk()
                ->assertJsonPath('status', 'failed');

            $this->assertFailedWithoutPrediction(ImageAnalysis::query()->findOrFail($response->json('id')));
        }
    }

    public function test_detector_error_is_reported_without_upstream_details(): void
    {
        Storage::fake('image-analyses');
        $this->fakeDetectorPayload(['error' => 'private detector detail'], 500);

        $response = $this->uploadImage()
            ->assertOk()
            ->assertJsonPath('status', 'failed')
            ->assertDontSee('private detector detail');

        $this->assertFailedWithoutPrediction(ImageAnalysis::query()->findOrFail($response->json('id')));
    }

    public function test_detector_connection_failure_is_reported_safely(): void
    {
        Storage::fake('image-analyses');
        Http::fake(fn () => throw new ConnectionException('connection refused'));

        $response = $this->uploadImage()
            ->assertOk()
            ->assertJsonPath('status', 'failed')
            ->assertDontSee('connection refused');

        $this->assertFailedWithoutPrediction(ImageAnalysis::query()->findOrFail($response->json('id')));
    }

    public function test_invalid_detector_configuration_sends_no_request(): void
    {
        Storage::fake('image-analyses');
        config(['services.ai_detector.url' => 'http://example.test']);

        $response = $this->uploadImage()
            ->assertOk()
            ->assertJsonPath('status', 'failed');

        $this->assertFailedWithoutPrediction(ImageAnalysis::query()->findOrFail($response->json('id')));
        Http::assertNothingSent();
    }

    public function test_non_permissive_ensemble_is_rejected_before_image_upload(): void
    {
        Storage::fake('image-analyses');
        $this->fakeDetectorPayload(
            $this->detectorPayload(),
            info: ['ensemble' => ['clip-probe', 'commforensics224', 'commforensics384', 'organika-sdxl']],
        );

        $response = $this->uploadImage()
            ->assertOk()
            ->assertJsonPath('status', 'failed');

        $this->assertFailedWithoutPrediction(ImageAnalysis::query()->findOrFail($response->json('id')));
        Http::assertSentCount(2);
        Http::assertNotSent(fn (HttpRequest $request): bool => $request->url() === self::DETECTOR_URL.'/detect');
    }

    public function test_unexpected_model_license_is_rejected_before_image_upload(): void
    {
        Storage::fake('image-analyses');
        $this->fakeDetectorPayload(
            $this->detectorPayload(),
            info: ['licences' => ['haywoodsloan' => 'CC-BY-NC-3.0']],
        );

        $response = $this->uploadImage()
            ->assertOk()
            ->assertJsonPath('status', 'failed');

        $this->assertFailedWithoutPrediction(ImageAnalysis::query()->findOrFail($response->json('id')));
        Http::assertSentCount(2);
        Http::assertNotSent(fn (HttpRequest $request): bool => $request->url() === self::DETECTOR_URL.'/detect');
    }

    public function test_detector_must_be_ready_before_info_or_image_requests(): void
    {
        Storage::fake('image-analyses');
        $this->fakeDetectorPayload(
            $this->detectorPayload(),
            health: ['status' => 'unavailable', 'models_loaded' => false],
        );

        $response = $this->uploadImage()
            ->assertOk()
            ->assertJsonPath('status', 'failed');

        $this->assertFailedWithoutPrediction(ImageAnalysis::query()->findOrFail($response->json('id')));
        Http::assertSentCount(1);
        Http::assertNotSent(fn (HttpRequest $request): bool => $request->url() !== self::DETECTOR_URL.'/health');
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
        $upload = $this->actingAs($user)
            ->postJson(route('checker.analyses.store'), [
                'image' => UploadedFile::fake()->image('private.png'),
            ])
            ->assertAccepted();
        $analysisId = $upload->json('id');
        $this->processAnalysis($analysisId);

        $this->getJson(route('checker.analyses.show', $analysisId))
            ->assertOk()
            ->assertJsonPath('status', 'completed')
            ->assertJsonPath('result_label', 'AI-GENERATED')
            ->assertJsonPath('model_name', self::MODEL_ID)
            ->assertJsonPath('confidence_score', 0.91)
            ->assertJsonPath('prediction_details.operating_point', 'fpr5')
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
        $upload = $this->actingAs($owner)
            ->postJson(route('checker.analyses.store'), [
                'image' => UploadedFile::fake()->image('owned.png'),
            ])
            ->assertAccepted();
        $analysisId = $upload->json('id');
        $this->processAnalysis($analysisId);

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
            ->with('Unable to complete queued image classification.', Mockery::type('array'));

        $response = $this->uploadImage()
            ->assertOk()
            ->assertJsonPath('status', 'failed');

        $this->assertFailedWithoutPrediction(ImageAnalysis::query()->findOrFail($response->json('id')));
    }

    private function uploadImage()
    {
        $upload = $this->actingAs(User::factory()->create())
            ->postJson(route('checker.analyses.store'), [
                'image' => UploadedFile::fake()->image('detector-test.png'),
            ])
            ->assertAccepted();

        $this->processAnalysis($upload->json('id'));

        return $this->getJson(route('checker.analyses.show', $upload->json('id')));
    }

    private function processAnalysis(int $analysisId): void
    {
        DB::table('jobs')->where('payload', 'like', '%"analysisId":'.$analysisId.'%')->delete();
        ClassifyImageAnalysis::dispatchSync($analysisId);
    }

    private function fakeDetector(): void
    {
        $this->fakeDetectorPayload($this->detectorPayload());
    }

    private function fakeDetectorPayload(
        array $payload,
        int $status = 200,
        array $info = [],
        array $health = [],
    ): void
    {
        $defaultInfo = [
            'version' => '0.1.0',
            'ensemble' => ['clip-probe', 'commforensics224', 'commforensics384', 'haywoodsloan'],
            'licences' => [
                'commforensics224' => 'MIT',
                'commforensics384' => 'MIT',
                'haywoodsloan' => 'Apache-2.0',
            ],
            'available_operating_points' => ['balanced', 'fpr1', 'fpr5', 'fpr10'],
        ];

        Http::fake(function (HttpRequest $request) use ($defaultInfo, $health, $info, $payload, $status) {
            $path = parse_url($request->url(), PHP_URL_PATH);

            if ($path === '/health') {
                return Http::response(array_replace(['status' => 'ok', 'models_loaded' => true], $health));
            }

            if ($path === '/info') {
                return Http::response(array_replace($defaultInfo, $info));
            }

            return Http::response($payload, $status);
        });
    }

    private function detectorPayload(array $result = []): array
    {
        return [
            'results' => [array_replace([
                'filename' => 'detector-test.png',
                'verdict' => 'AI-GENERATED',
                'p_ai' => 0.91,
                'threshold' => 0.7115,
                'low_threshold' => 0.5438,
                'basis' => 'pixel ensemble',
                'per_model' => [
                    'clip-probe' => 0.89,
                    'commforensics224' => 0.92,
                    'commforensics384' => 0.91,
                    'haywoodsloan' => 0.9,
                ],
                'metadata_says_ai' => false,
                'metadata_generator' => null,
                'truncated' => false,
                'error' => null,
            ], $result)],
            'elapsed_ms' => 12,
            'operating_point' => 'fpr5',
        ];
    }

    private function assertFailedWithoutPrediction(ImageAnalysis $analysis): void
    {
        $this->assertSame('failed', $analysis->status);
        $this->assertNull($analysis->result_label);
        $this->assertNull($analysis->confidence_score);
        $this->assertNull($analysis->model_name);
    }
}
