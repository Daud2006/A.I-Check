<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\LocalAiDetector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class AnalysisQuotaTest extends TestCase
{
    use RefreshDatabase;

    private const GUEST_COOKIE = 'aicheck_guest_usage';

    protected function setUp(): void
    {
        parent::setUp();

        Cache::store('database')->flush();
        Storage::fake('image-analyses');
    }

    public function test_guest_can_use_two_successful_detections_and_limit_survives_refresh(): void
    {
        $guestId = (string) Str::uuid();
        $this->mock(LocalAiDetector::class)
            ->shouldReceive('classify')
            ->twice()
            ->andReturn($this->prediction());

        $this->guestUpload($guestId)->assertCreated();
        $this->guestUpload($guestId)->assertCreated();

        $this->get(route('checker'))
            ->assertOk()
            ->assertDontSee('Sisa kesempatan: 0');

        $this->guestUpload($guestId)
            ->assertStatus(429)
            ->assertJsonPath('code', 'guest_limit_reached')
            ->assertJsonPath('message', 'Batas Deteksi Gambar tanpa login telah tercapai. Silakan buat akun atau login untuk melanjutkan Deteksi Gambar dan menikmati fitur A.I-Check.');
    }

    public function test_guest_detection_failure_does_not_consume_a_chance(): void
    {
        $guestId = (string) Str::uuid();
        $calls = 0;
        $this->mock(LocalAiDetector::class)
            ->shouldReceive('classify')
            ->times(3)
            ->andReturnUsing(function () use (&$calls): array {
                if (++$calls === 1) {
                    throw new RuntimeException('Test classifier failure.');
                }

                return $this->prediction();
            });
        Log::shouldReceive('error')
            ->once()
            ->with('Unable to complete image classification.', Mockery::type('array'));

        $this->guestUpload($guestId)
            ->assertStatus(500)
            ->assertJsonPath('code', 'analysis_failed')
            ->assertJsonPath('status', 'failed')
            ->assertJsonMissingPath('result_label');

        $this->guestUpload($guestId)->assertCreated();
        $this->guestUpload($guestId)->assertCreated();
        $this->guestUpload($guestId)->assertStatus(429);
    }

    public function test_free_user_gets_three_successful_detections_per_day(): void
    {
        $user = User::factory()->create();
        $this->mock(LocalAiDetector::class)
            ->shouldReceive('classify')
            ->times(3)
            ->andReturn($this->prediction());

        $this->userUpload($user)->assertCreated();
        $this->userUpload($user)->assertCreated();
        $this->userUpload($user)->assertCreated();
        $this->userUpload($user)
            ->assertStatus(429)
            ->assertJsonPath('code', 'free_limit_reached')
            ->assertJsonPath('message', 'Anda telah habis batas Deteksi Gambar dengan akun Free (Gratis). Silakan berlangganan atau tunggu hingga besok hari untuk dapat melakukan Deteksi Gambar kembali.');

        $this->assertDatabaseCount('usage_records', 3);
        $this->assertDatabaseCount('image_analyses', 3);
    }

    public function test_free_detection_failure_does_not_consume_a_daily_use(): void
    {
        $user = User::factory()->create();
        $calls = 0;
        $this->mock(LocalAiDetector::class)
            ->shouldReceive('classify')
            ->times(4)
            ->andReturnUsing(function () use (&$calls): array {
                if (++$calls === 1) {
                    throw new RuntimeException('Test classifier failure.');
                }

                return $this->prediction();
            });
        Log::shouldReceive('error')
            ->once()
            ->with('Unable to complete image classification.', Mockery::type('array'));

        $this->userUpload($user)->assertStatus(500);
        $this->userUpload($user)->assertCreated();
        $this->userUpload($user)->assertCreated();
        $this->userUpload($user)->assertCreated();
        $this->userUpload($user)->assertStatus(429);

        $this->assertDatabaseCount('usage_records', 3);
    }

    public function test_free_user_limit_resets_on_the_next_local_day(): void
    {
        $user = User::factory()->create();
        $this->travelTo(now()->startOfDay()->addHours(12));
        $user->usageRecords()->create([
            'usage_type' => 'analysis',
            'quantity' => 3,
            'created_at' => now(),
        ]);

        $this->travelTo(now()->addDay()->startOfDay()->addHour());
        $this->mock(LocalAiDetector::class)
            ->shouldReceive('classify')
            ->once()
            ->andReturn($this->prediction());

        $this->userUpload($user)->assertCreated();
    }

    public function test_premium_user_is_not_limited_by_the_free_quota(): void
    {
        $user = User::factory()->create();
        $plan = Plan::create([
            'name' => 'Premium',
            'slug' => 'premium',
            'price' => 29000,
            'currency' => 'IDR',
            'billing_interval' => 'monthly',
            'analysis_limit' => null,
            'is_active' => true,
        ]);
        $subscription = new Subscription([
            'plan_id' => $plan->id,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);
        $subscription->status = 'active';
        $user->subscriptions()->save($subscription);

        $this->mock(LocalAiDetector::class)
            ->shouldReceive('classify')
            ->times(4)
            ->andReturn($this->prediction());

        for ($attempt = 0; $attempt < 4; $attempt++) {
            $this->userUpload($user)->assertCreated();
        }

        $this->assertDatabaseCount('usage_records', 0);
    }

    public function test_detection_response_includes_actual_engine_evidence(): void
    {
        $user = User::factory()->create();
        $evidence = [
            'basis' => 'pixel ensemble (moderate, 2 models)',
            'threshold' => 0.72,
            'low_threshold' => 0.36,
            'per_model' => ['detector-a' => 0.81, 'detector-b' => 0.68],
            'metadata_says_ai' => false,
            'metadata_generator' => null,
        ];
        $this->mock(LocalAiDetector::class)
            ->shouldReceive('classify')
            ->once()
            ->andReturn($this->prediction(evidence: $evidence));

        $this->userUpload($user)
            ->assertCreated()
            ->assertJsonPath('result_label', 'AI-GENERATED')
            ->assertJsonPath('confidence_score', 0.91)
            ->assertJsonPath('engine_evidence.threshold', 0.72)
            ->assertJsonPath('engine_evidence.per_model.detector-a', 0.81)
            ->assertJsonPath('engine_evidence.basis', 'pixel ensemble (moderate, 2 models)');
    }

    public function test_checker_page_hides_quota_summary_and_keeps_subscription_action(): void
    {
        $this->get(route('checker'))
            ->assertOk()
            ->assertDontSee('Pengunjung memiliki 2 kesempatan Deteksi Gambar total.')
            ->assertSee('data-subscribe-open', false)
            ->assertSee('Deteksi Gambar');
    }

    public function test_free_user_checker_page_does_not_show_remaining_quota(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('checker'))
            ->assertOk()
            ->assertDontSee('Akun Free memiliki 3 kesempatan Deteksi Gambar per hari.');
    }

    public function test_registration_mode_can_be_opened_from_the_guest_limit_link(): void
    {
        $this->get(route('auth', ['mode' => 'register']))
            ->assertOk()
            ->assertSee('data-auth-initial-mode="register"', false)
            ->assertSee('data-auth-open-on-load="true"', false);
    }

    private function guestUpload(string $guestId)
    {
        return $this->withCredentials()
            ->withCookie(self::GUEST_COOKIE, $guestId)
            ->postJson(route('checker.analyses.store'), [
                'image' => UploadedFile::fake()->image('guest.png'),
            ]);
    }

    private function userUpload(User $user)
    {
        return $this->actingAs($user)
            ->postJson(route('checker.analyses.store'), [
                'image' => UploadedFile::fake()->image('free.png'),
            ]);
    }

    private function prediction(array $evidence = []): array
    {
        return [
            'label' => 'AI-GENERATED',
            'score' => 0.91,
            'model' => 'simple_ai_image_detector',
            'evidence' => $evidence,
        ];
    }
}
