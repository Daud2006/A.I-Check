<?php

namespace Tests\Feature;

use App\Models\ImageAnalysis;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_history_shows_only_the_authenticated_users_database_analyses(): void
    {
        Storage::fake('image-analyses');
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $ownAnalysis = $this->createAnalysis($owner, 'own.png', 'artificial', 0.91);
        $this->createAnalysis($other, 'other.png', 'human', 0.82);
        Storage::disk('image-analyses')->put($ownAnalysis->image_path, 'image');

        $this->actingAs($owner)
            ->get(route('history'))
            ->assertOk()
            ->assertSee('own.png')
            ->assertDontSee('other.png')
            ->assertSee('imageUrl')
            ->assertSee('history/__ID__');
    }

    public function test_empty_history_is_rendered_from_an_empty_database_result(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('history'))
            ->assertOk()
            ->assertSee('data-analyses="[]"', false)
            ->assertSee('Memuat riwayat analisis...');
    }

    public function test_guest_cannot_view_or_delete_history(): void
    {
        $this->get(route('history'))->assertRedirect(route('auth'));
        $this->deleteJson(route('history.destroy', 1))->assertUnauthorized();
        $this->deleteJson(route('history.destroy-all'))->assertUnauthorized();
    }

    public function test_owner_can_delete_an_analysis_and_its_managed_private_image(): void
    {
        Storage::fake('image-analyses');
        $owner = User::factory()->create();
        $analysis = $this->createAnalysis($owner, 'remove.png');
        Storage::disk('image-analyses')->put($analysis->image_path, 'image');

        $this->actingAs($owner)
            ->deleteJson(route('history.destroy', $analysis->id))
            ->assertOk()
            ->assertJsonPath('cleanup_complete', true);

        $this->assertDatabaseMissing('image_analyses', ['id' => $analysis->id]);
        Storage::disk('image-analyses')->assertMissing($analysis->image_path);
    }

    public function test_user_cannot_delete_another_users_analysis_or_image(): void
    {
        Storage::fake('image-analyses');
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $analysis = $this->createAnalysis($owner, 'owned.png');
        Storage::disk('image-analyses')->put($analysis->image_path, 'image');

        $this->actingAs($other)
            ->deleteJson(route('history.destroy', $analysis->id))
            ->assertNotFound();

        $this->assertDatabaseHas('image_analyses', ['id' => $analysis->id, 'user_id' => $owner->id]);
        Storage::disk('image-analyses')->assertExists($analysis->image_path);
    }

    public function test_deleting_an_analysis_keeps_an_image_referenced_by_another_record(): void
    {
        Storage::fake('image-analyses');
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $first = $this->createAnalysis($owner, 'shared.png');
        $second = $this->createAnalysis($other, 'shared.png');
        Storage::disk('image-analyses')->put($first->image_path, 'image');

        $this->actingAs($owner)
            ->deleteJson(route('history.destroy', $first->id))
            ->assertOk()
            ->assertJsonPath('cleanup_complete', true);

        $this->assertDatabaseMissing('image_analyses', ['id' => $first->id]);
        $this->assertDatabaseHas('image_analyses', ['id' => $second->id]);
        Storage::disk('image-analyses')->assertExists($first->image_path);
    }

    public function test_delete_does_not_remove_a_path_outside_the_private_image_disk(): void
    {
        Storage::fake('image-analyses');
        $disk = Storage::disk('image-analyses');
        $outsidePath = dirname($disk->path('')).DIRECTORY_SEPARATOR.'history-outside.png';
        file_put_contents($outsidePath, 'not owned by the image disk');
        $owner = User::factory()->create();
        $analysis = $this->createAnalysis($owner, 'outside.png');
        $analysis->image_path = '../history-outside.png';
        $analysis->save();

        try {
            $this->actingAs($owner)
                ->deleteJson(route('history.destroy', $analysis->id))
                ->assertOk()
                ->assertJsonPath('cleanup_complete', false);

            $this->assertFileExists($outsidePath);
            $this->assertDatabaseMissing('image_analyses', ['id' => $analysis->id]);
        } finally {
            if (is_file($outsidePath)) {
                unlink($outsidePath);
            }
        }
    }

    public function test_delete_all_removes_only_the_authenticated_users_records_and_unreferenced_files(): void
    {
        Storage::fake('image-analyses');
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $first = $this->createAnalysis($owner, 'first.png');
        $second = $this->createAnalysis($owner, 'second.png');
        $otherAnalysis = $this->createAnalysis($other, 'other.png');
        foreach ([$first, $second, $otherAnalysis] as $analysis) {
            Storage::disk('image-analyses')->put($analysis->image_path, 'image');
        }

        $this->actingAs($owner)
            ->deleteJson(route('history.destroy-all'))
            ->assertOk()
            ->assertJsonPath('cleanup_complete', true);

        $this->assertDatabaseMissing('image_analyses', ['user_id' => $owner->id]);
        $this->assertDatabaseHas('image_analyses', ['id' => $otherAnalysis->id, 'user_id' => $other->id]);
        Storage::disk('image-analyses')->assertMissing($first->image_path);
        Storage::disk('image-analyses')->assertMissing($second->image_path);
        Storage::disk('image-analyses')->assertExists($otherAnalysis->image_path);
    }

    /**
     * @return array<string, mixed>
     */
    private function createAnalysis(
        User $user,
        string $filename,
        ?string $label = null,
        ?float $score = null,
    ): ImageAnalysis
    {
        $analysis = new ImageAnalysis([
            'original_filename' => $filename,
            'image_path' => 'users/test/'.$filename,
            'mime_type' => 'image/png',
            'file_size' => 5,
        ]);
        $analysis->status = $label === null ? 'failed' : 'completed';
        $analysis->result_label = $label;
        $analysis->confidence_score = $score;

        $user->imageAnalyses()->save($analysis);

        return $analysis;
    }
}
