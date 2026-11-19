<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_a_paginated_user_list_without_sensitive_fields(): void
    {
        $admin = $this->createAdmin();
        $user = User::factory()->create([
            'name' => 'Akun Reguler',
            'email' => 'regular@example.test',
            'password' => 'HighlySecretPassword123',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.users.index'));

        $response->assertOk()
            ->assertViewHas('users')
            ->assertSee('Akun Reguler')
            ->assertSee('regular@example.test')
            ->assertSee('Pengguna')
            ->assertSee(route('admin.users.show', $user->id))
            ->assertSee('href="'.route('admin.users.index').'"', false)
            ->assertDontSee('HighlySecretPassword123')
            ->assertDontSee('remember_token')
            ->assertDontSee('password');
    }

    public function test_guests_and_regular_users_cannot_access_user_management_or_details(): void
    {
        $user = User::factory()->create();

        $this->get(route('admin.users.index'))->assertRedirect(route('auth'));
        $this->get(route('admin.users.show', $user->id))->assertRedirect(route('auth'));

        $this->actingAs($user)
            ->get(route('admin.users.index'))
            ->assertForbidden();
        $this->get(route('admin.users.show', $user->id))->assertForbidden();
    }

    public function test_user_search_matches_name_and_email_and_empty_results_are_explained(): void
    {
        $admin = $this->createAdmin();
        User::factory()->create(['name' => 'Rina Searchable', 'email' => 'rina@example.test']);
        User::factory()->create(['name' => 'Another Person', 'email' => 'unique-search@example.test']);
        User::factory()->create(['name' => 'Different Person', 'email' => 'different@example.test']);

        $this->actingAs($admin)
            ->get(route('admin.users.index', ['search' => 'Rina']))
            ->assertOk()
            ->assertSee('Rina Searchable')
            ->assertDontSee('unique-search@example.test');

        $this->get(route('admin.users.index', ['search' => 'unique-search@example.test']))
            ->assertOk()
            ->assertSee('Another Person')
            ->assertDontSee('Rina Searchable');

        $this->get(route('admin.users.index', ['search' => 'no-match-for-this-search']))
            ->assertOk()
            ->assertSee('Pengguna tidak ditemukan')
            ->assertSee('Reset pencarian dan filter');
    }

    public function test_role_subscription_and_plan_filters_can_be_combined(): void
    {
        $admin = $this->createAdmin();
        $premiumPlan = $this->createPlan('Premium', 'premium');
        $otherPlan = $this->createPlan('Basic', 'basic');

        $activeUser = User::factory()->create([
            'name' => 'Active Premium User',
            'role' => User::ROLE_USER,
        ]);
        $pendingOtherPlanUser = User::factory()->create([
            'name' => 'Pending Other Plan User',
            'role' => User::ROLE_USER,
        ]);
        $adminUser = $this->createAdmin('Admin Candidate');
        $noSubscriptionUser = User::factory()->create(['name' => 'No Subscription User']);

        $this->attachSubscription($activeUser, $premiumPlan, 'active');
        $this->attachSubscription($pendingOtherPlanUser, $premiumPlan, 'pending');
        $this->attachSubscription($pendingOtherPlanUser, $otherPlan, 'active');

        $this->actingAs($admin)
            ->get(route('admin.users.index', [
                'role' => User::ROLE_USER,
                'subscription' => 'active',
                'plan' => $premiumPlan->id,
            ]))
            ->assertOk()
            ->assertSee('Active Premium User')
            ->assertDontSee('Pending Other Plan User')
            ->assertDontSee('Admin Candidate');

        $this->get(route('admin.users.index', ['role' => User::ROLE_ADMIN]))
            ->assertOk()
            ->assertSee('Admin Candidate')
            ->assertDontSee('No Subscription User');

        $this->get(route('admin.users.index', ['subscription' => 'none']))
            ->assertOk()
            ->assertSee('No Subscription User')
            ->assertDontSee('Active Premium User');

        $this->get(route('admin.users.index', ['subscription' => 'pending']))
            ->assertOk()
            ->assertSee('Pending Other Plan User')
            ->assertDontSee('Active Premium User');

        $this->get(route('admin.users.index', ['plan' => $otherPlan->id]))
            ->assertOk()
            ->assertSee('Pending Other Plan User')
            ->assertDontSee('Active Premium User');
    }

    public function test_user_pagination_preserves_search_and_filters(): void
    {
        $admin = $this->createAdmin();
        $plan = $this->createPlan('Pagination Plan', 'pagination-plan');

        for ($index = 1; $index <= 17; $index++) {
            $user = User::factory()->create([
                'name' => 'Pagination User '.$index,
                'email' => 'pagination'.$index.'@example.test',
            ]);
            $this->attachSubscription($user, $plan, 'active');
        }

        $response = $this->actingAs($admin)->get(route('admin.users.index', [
            'search' => 'Pagination',
            'role' => User::ROLE_USER,
            'subscription' => 'active',
            'plan' => $plan->id,
            'page' => 2,
        ]));

        $response->assertOk()
            ->assertSee('Pagination User 2')
            ->assertSee('search=Pagination')
            ->assertSee('role=user')
            ->assertSee('subscription=active')
            ->assertSee('plan='.$plan->id);
    }

    public function test_admin_can_view_user_details_and_latest_analysis_metadata_without_private_files(): void
    {
        $admin = $this->createAdmin();
        $user = User::factory()->create([
            'name' => 'Detail User',
            'email' => 'detail@example.test',
            'password' => 'DetailPasswordSecret123',
        ]);
        $plan = $this->createPlan('Detail Premium', 'detail-premium');
        $subscription = $this->attachSubscription($user, $plan, 'active');
        $analysis = $user->imageAnalyses()->create([
            'original_filename' => 'private-personal-photo.png',
            'image_path' => 'users/1/private-personal-photo.png',
            'mime_type' => 'image/png',
            'file_size' => 2048,
            'model_name' => 'private-model-name',
        ]);
        $analysis->status = 'completed';
        $analysis->result_label = 'REAL';
        $analysis->confidence_score = 0.125;
        $analysis->save();

        $this->actingAs($admin)
            ->get(route('admin.users.show', $user->id))
            ->assertOk()
            ->assertSee('Detail User')
            ->assertSee('detail@example.test')
            ->assertSee('Terverifikasi')
            ->assertSee('Detail Premium')
            ->assertSee('Aktif')
            ->assertSee($subscription->starts_at->format('d/m/Y H:i'))
            ->assertSee('#'.$analysis->id)
            ->assertSee('REAL')
            ->assertSee('12,50%')
            ->assertDontSee('private-personal-photo.png')
            ->assertDontSee('users/1/private-personal-photo.png')
            ->assertDontSee('private-model-name')
            ->assertDontSee('DetailPasswordSecret123')
            ->assertDontSee('remember_token');
    }

    public function test_user_without_subscription_or_analyses_has_clear_empty_states(): void
    {
        $admin = $this->createAdmin();
        $user = User::factory()->create([
            'name' => 'Free Detail User',
            'email' => 'free-detail@example.test',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.users.show', $user->id))
            ->assertOk()
            ->assertSee('Free Detail User')
            ->assertSee('belum memiliki data subscription.')
            ->assertSee('belum memiliki riwayat analisis.')
            ->assertSee('0')
            ->assertDontSee('Undefined');
    }

    public function test_empty_user_list_does_not_error(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->get(route('admin.users.index', ['search' => 'not-found']))
            ->assertOk()
            ->assertSee('Pengguna tidak ditemukan');
    }

    public function test_admin_sidebar_links_to_users_and_marks_users_navigation_active(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('href="'.route('admin.users.index').'" class="admin-nav-link is-active"', false)
            ->assertSee('href="'.route('admin').'"', false)
            ->assertSee('href="'.route('home').'"', false)
            ->assertSee('action="'.route('logout').'"', false)
            ->assertDontSee('href="/admin/analyses"', false)
            ->assertDontSee('href="/admin/subscriptions"', false);
    }

    private function createAdmin(?string $name = null): User
    {
        $admin = User::factory()->create([
            'name' => $name ?? 'Dashboard Admin',
            'email' => fake()->unique()->safeEmail(),
        ]);
        $admin->role = User::ROLE_ADMIN;
        $admin->save();

        return $admin;
    }

    private function createPlan(string $name, string $slug): Plan
    {
        return Plan::query()->create([
            'name' => $name,
            'slug' => $slug,
            'price' => 29000,
            'currency' => 'IDR',
            'billing_interval' => 'monthly',
            'analysis_limit' => null,
            'is_active' => true,
        ]);
    }

    private function attachSubscription(User $user, Plan $plan, string $status): Subscription
    {
        $subscription = new Subscription([
            'plan_id' => $plan->id,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);
        $subscription->status = $status;
        $user->subscriptions()->save($subscription);

        return $subscription;
    }
}
