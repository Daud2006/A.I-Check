<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\ImageAnalysis;
use App\Models\Subscription;
use App\Models\User;
use App\Services\AnalysisQuotaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminAccountAndGiftTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admin_creates_a_regular_user_with_a_hashed_password(): void
    {
        $data = [
            'name' => 'Akun Contoh',
            'email' => 'account-test@example.test',
            'password' => 'Example1234',
            'password_confirmation' => 'Example1234',
            'role' => User::ROLE_ADMIN,
        ];

        $this->actingAs(User::factory()->create())->post(route('admin.users.store'), $data)->assertForbidden();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAs($admin)->get(route('admin.users.create'))->assertOk()->assertSee('Kata sandi awal');
        $response = $this->post(route('admin.users.store'), $data);
        $user = User::query()->where('email', $data['email'])->sole();
        $response->assertRedirect(route('admin.users.show', $user->id));
        $this->assertSame(User::ROLE_USER, $user->role);
        $this->assertTrue(Hash::check($data['password'], $user->password));
        $this->assertAuthenticatedAs($admin);
        $this->get(route('admin.users.show', $user->id))->assertDontSee($user->password);
    }

    public function test_gift_is_idempotent_and_grants_the_original_quota_without_a_payment(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $user = User::factory()->create(['name' => 'Penerima Hadiah']);
        $plan = Plan::query()->create([
            'name' => 'Premium Plus', 'slug' => 'premium-plus', 'price' => 49000,
            'currency' => 'IDR', 'billing_interval' => 'monthly', 'analysis_limit' => 60, 'is_active' => true,
        ]);
        $data = ['user_id' => $user->id, 'plan_id' => $plan->id, 'reference' => (string) Str::uuid(), 'confirm' => '1'];
        $this->actingAs($user)->post(route('admin.plans.gift.store'), $data)->assertForbidden();
        $this->actingAs($admin)->get(route('admin.plans.gift.create', ['search' => $user->email]))
            ->assertOk()->assertSee($user->name);
        for ($attempt = 0; $attempt < 2; $attempt++) {
            $this->post(route('admin.plans.gift.store'), $data)->assertRedirect(route('admin.subscriptions.index'));
        }
        $gift = Subscription::query()->sole();
        $this->assertEquals($admin->id, $gift->granted_by);
        $this->assertSame($gift->starts_at->copy()->addMonthNoOverflow()->toDateString(), $gift->ends_at->toDateString());
        $this->assertDatabaseCount('payments', 0);

        $plan->update(['analysis_limit' => 120]);
        $this->assertSame(60, app(AnalysisQuotaService::class)->snapshot($user)['limit']);
        $this->get(route('admin.subscriptions.index', ['source' => 'gift']))->assertOk()->assertSee('Premium Plus');
    }

    public function test_admin_removes_unlinked_accounts_and_disables_accounts_with_history(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $empty = User::factory()->create();
        $owner = User::factory()->create(['password' => 'Example1234']);
        $analysis = new ImageAnalysis(['original_filename' => 'demo.png', 'mime_type' => 'image/png', 'file_size' => 50]);
        $analysis->status = 'completed';
        $owner->imageAnalyses()->save($analysis);
        $usage = $owner->usageRecords()->make(['usage_type' => 'analysis', 'quantity' => 1]);
        $usage->imageAnalysis()->associate($analysis);
        $usage->save();

        $this->actingAs($owner)->delete(route('admin.analyses.destroy', $analysis->id))->assertForbidden();
        $this->actingAs($admin)->delete(route('admin.users.destroy', $empty->id))->assertRedirect(route('admin.users.index'));
        $this->assertModelMissing($empty);
        $this->delete(route('admin.users.destroy', $owner->id))->assertRedirect(route('admin.users.index'));
        $this->assertNotNull($owner->fresh()->disabled_at);
        $this->assertModelExists($analysis);
        $this->delete(route('admin.analyses.destroy', $analysis->id))->assertRedirect(route('admin.analyses.index'));
        $this->assertModelMissing($analysis);
        $this->assertNull($usage->fresh()->image_analysis_id);

        $this->actingAs($owner->fresh())->getJson(route('profile'))->assertForbidden();
        $this->post(route('login'), ['email' => $owner->email, 'password' => 'Example1234'])->assertSessionHasErrors('email');
        $this->actingAs($admin)->patch(route('admin.users.enable', $owner->id))->assertRedirect(route('admin.users.show', $owner->id));
        $this->assertNull($owner->fresh()->disabled_at);
    }
}
