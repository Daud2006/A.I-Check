<?php

namespace Tests\Feature;

use App\Models\ImageAnalysis;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminAnalysisManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_analysis_list_and_sidebar_navigation(): void
    {
        $admin = $this->createAdmin();
        $user = User::factory()->create([
            'name' => 'Analysis Owner',
            'email' => 'analysis-owner@example.test',
        ]);
        $analysis = $this->createAnalysis($user);

        $response = $this->actingAs($admin)->get(route('admin.analyses.index'));

        $response->assertOk()
            ->assertSee('Manajemen Analisis')
            ->assertSee('Analysis Owner')
            ->assertSee('analysis-owner@example.test')
            ->assertSee('#'.$analysis->id)
            ->assertSee(route('admin.analyses.show', $analysis->id))
            ->assertSee('href="'.route('admin.analyses.index').'" class="admin-nav-link is-active"', false)
            ->assertDontSee('href="'.route('admin').'" class="admin-nav-link is-active"', false)
            ->assertDontSee($analysis->image_path)
            ->assertDontSee($analysis->original_filename);
    }

    public function test_guests_and_regular_users_cannot_access_analysis_management(): void
    {
        $analysis = $this->createAnalysis(User::factory()->create());

        $this->get(route('admin.analyses.index'))->assertRedirect(route('auth'));
        $this->get(route('admin.analyses.show', $analysis->id))->assertRedirect(route('auth'));

        $this->actingAs(User::factory()->create())
            ->get(route('admin.analyses.index'))
            ->assertForbidden();
        $this->get(route('admin.analyses.show', $analysis->id))->assertForbidden();
    }

    public function test_analysis_search_matches_user_name_or_email(): void
    {
        $admin = $this->createAdmin();
        $nameMatch = User::factory()->create([
            'name' => 'Unique Analysis Owner',
            'email' => 'ordinary@example.test',
        ]);
        $emailMatch = User::factory()->create([
            'name' => 'Another Owner',
            'email' => 'unique-analysis-email@example.test',
        ]);
        $other = User::factory()->create([
            'name' => 'Unrelated Owner',
            'email' => 'unrelated@example.test',
        ]);
        $this->createAnalysis($nameMatch);
        $this->createAnalysis($emailMatch);
        $this->createAnalysis($other);

        $this->actingAs($admin)
            ->get(route('admin.analyses.index', ['search' => 'Unique Analysis Owner']))
            ->assertOk()
            ->assertSee('Unique Analysis Owner')
            ->assertDontSee('unique-analysis-email@example.test');

        $this->get(route('admin.analyses.index', ['search' => 'unique-analysis-email@example.test']))
            ->assertOk()
            ->assertSee('unique-analysis-email@example.test')
            ->assertDontSee('Unrelated Owner');
    }

    public function test_analysis_filters_apply_to_the_same_analysis_record(): void
    {
        $admin = $this->createAdmin();
        $owner = User::factory()->create([
            'name' => 'Combined Filter Owner',
            'email' => 'combined@example.test',
        ]);
        $matching = $this->createAnalysis(
            $owner,
            'completed',
            'AI-GENERATED',
            'image/png',
            CarbonImmutable::parse('2026-11-02 12:00:00')
        );
        $nonMatching = $this->createAnalysis(
            $owner,
            'failed',
            'REAL',
            'image/jpeg',
            CarbonImmutable::parse('2026-11-03 12:00:00')
        );
        $this->createAnalysis(
            User::factory()->create(),
            'completed',
            'AI-GENERATED',
            'image/png',
            CarbonImmutable::parse('2026-11-02 12:00:00')
        );

        $this->actingAs($admin)
            ->get(route('admin.analyses.index', [
                'search' => 'combined@example.test',
                'status' => 'completed',
                'result_label' => 'AI-GENERATED',
                'date_from' => '2026-11-02',
                'date_to' => '2026-11-02',
                'mime_type' => 'image/png',
            ]))
            ->assertOk()
            ->assertSee('#'.$matching->id)
            ->assertDontSee('#'.$nonMatching->id);
    }

    public function test_analysis_pagination_preserves_search_and_filters(): void
    {
        $admin = $this->createAdmin();
        $owner = User::factory()->create([
            'name' => 'Pagination Analysis Owner',
            'email' => 'pagination-analyses@example.test',
        ]);

        for ($index = 1; $index <= 17; $index++) {
            $this->createAnalysis(
                $owner,
                'completed',
                'REAL',
                'image/png',
                CarbonImmutable::parse('2026-11-01 10:00:00')->addMinutes($index)
            );
        }

        $this->actingAs($admin)
            ->get(route('admin.analyses.index', [
                'search' => 'Pagination Analysis',
                'status' => 'completed',
                'result_label' => 'REAL',
                'mime_type' => 'image/png',
                'page' => 2,
            ]))
            ->assertOk()
            ->assertSee('search=Pagination')
            ->assertSee('status=completed')
            ->assertSee('result_label=REAL')
            ->assertSee('mime_type=image%2Fpng');
    }

    public function test_admin_can_view_analysis_detail_and_score_semantics(): void
    {
        $admin = $this->createAdmin();
        $owner = User::factory()->create([
            'name' => 'Detailed Analysis Owner',
            'email' => 'detail-analysis@example.test',
        ]);
        $analysis = $this->createAnalysis(
            $owner,
            'completed',
            'AI-GENERATED',
            'image/webp',
            CarbonImmutable::parse('2026-11-04 13:15:00'),
            'simple_ai_image_detector',
            0.875
        );

        $this->actingAs($admin)
            ->get(route('admin.analyses.show', $analysis->id))
            ->assertOk()
            ->assertSee('Analisis #'.$analysis->id)
            ->assertSee('Detailed Analysis Owner')
            ->assertSee('detail-analysis@example.test')
            ->assertSee('AI-GENERATED')
            ->assertSee('Probabilitas AI (p_ai)')
            ->assertSee('87,50%')
            ->assertSee('simple_ai_image_detector')
            ->assertSee('Visualisasi lokasi elemen AI belum tersedia pada hasil analisis ini.')
            ->assertDontSee($analysis->image_path)
            ->assertDontSee($analysis->original_filename)
            ->assertDontSee('confidence_score:');
    }

    public function test_non_detector_score_is_not_presented_as_ai_probability(): void
    {
        $analysis = $this->createAnalysis(
            User::factory()->create(),
            'completed',
            'REAL',
            'image/jpeg',
            CarbonImmutable::parse('2026-11-05 13:15:00'),
            'legacy-model',
            0.875
        );

        $this->actingAs($this->createAdmin())
            ->get(route('admin.analyses.show', $analysis->id))
            ->assertOk()
            ->assertSee('Skor tersimpan (confidence_score)')
            ->assertSee('0.875000')
            ->assertDontSee('Probabilitas AI (p_ai)');
    }

    public function test_missing_analysis_detail_returns_not_found(): void
    {
        $this->actingAs($this->createAdmin())
            ->get(route('admin.analyses.show', 999999))
            ->assertNotFound();
    }

    public function test_admin_cannot_retrieve_another_users_private_image_through_owner_route(): void
    {
        Storage::fake('image-analyses');
        $analysis = $this->createAnalysis(User::factory()->create());
        Storage::disk('image-analyses')->put($analysis->image_path, 'private image content');

        $this->actingAs($this->createAdmin())
            ->get(route('checker.analyses.image', $analysis->id))
            ->assertNotFound();
    }

    public function test_analysis_list_shows_empty_state_and_dashboard_and_users_still_work(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->get(route('admin.analyses.index'))
            ->assertOk()
            ->assertSee('Belum ada analisis');

        $this->get(route('admin'))->assertOk();
        $this->get(route('admin.users.index'))->assertOk();
    }

    private function createAdmin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    private function createAnalysis(
        User $user,
        string $status = 'completed',
        ?string $resultLabel = 'REAL',
        string $mimeType = 'image/png',
        ?CarbonImmutable $createdAt = null,
        ?string $modelName = 'simple_ai_image_detector',
        ?float $score = 0.65,
    ): ImageAnalysis {
        $analysis = $user->imageAnalyses()->create([
            'original_filename' => 'private-analysis-image.png',
            'image_path' => 'users/'.$user->id.'/private-analysis-image.png',
            'mime_type' => $mimeType,
            'file_size' => 2048,
            'model_name' => $modelName,
        ]);
        $analysis->status = $status;
        $analysis->result_label = $resultLabel;
        $analysis->confidence_score = $score;

        if ($createdAt !== null) {
            $analysis->created_at = $createdAt;
        }

        $analysis->save();

        return $analysis;
    }
}
