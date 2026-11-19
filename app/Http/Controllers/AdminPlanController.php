<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminPlanController extends Controller
{
    public function index(Request $request): View
    {
        $this->ensureAdmin();

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'active' => ['nullable', Rule::in(['0', '1'])],
        ]);
        $search = trim($filters['search'] ?? '');
        $active = $filters['active'] ?? '';

        $plans = Plan::query()
            ->withCount('subscriptions')
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query->where('name', 'like', '%'.$search.'%')
                    ->orWhere('slug', 'like', '%'.$search.'%');
            }))
            ->when($active !== '', fn ($query) => $query->where('is_active', (bool) $active))
            ->orderByDesc('is_active')
            ->orderBy('price')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('admin.plans.index', [
            'plans' => $plans,
            'filters' => ['search' => $search, 'active' => $active],
        ]);
    }

    public function create(): View
    {
        $this->ensureAdmin();

        return view('admin.plans.create', ['plan' => new Plan(['is_active' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureAdmin();
        $data = $this->validatedPlan($request);
        $plan = Plan::query()->create($data);
        Log::notice('Admin created plan.', ['admin_id' => $request->user()->id, 'plan_id' => $plan->id]);

        return redirect()
            ->route('admin.plans.show', $plan)
            ->with('status', 'Paket berhasil dibuat.');
    }

    public function show(Plan $plan): View
    {
        $this->ensureAdmin();
        $plan->loadCount('subscriptions');

        $paymentCount = Payment::query()
            ->whereHas('subscription', fn ($query) => $query->where('plan_id', $plan->getKey()))
            ->count();

        return view('admin.plans.show', [
            'plan' => $plan,
            'paymentCount' => $paymentCount,
        ]);
    }

    public function edit(Plan $plan): View
    {
        $this->ensureAdmin();

        return view('admin.plans.edit', ['plan' => $plan]);
    }

    public function update(Request $request, Plan $plan): RedirectResponse
    {
        $this->ensureAdmin();
        $data = $this->validatedPlan($request, $plan);
        DB::transaction(function () use ($plan, $data): void {
            $lockedPlan = Plan::query()->lockForUpdate()->findOrFail($plan->id);
            $this->assertSafePlanChange($lockedPlan, $data);
            $lockedPlan->update($data);
        }, 3);
        Log::notice('Admin updated plan.', ['admin_id' => $request->user()->id, 'plan_id' => $plan->id]);

        return redirect()
            ->route('admin.plans.show', $plan)
            ->with('status', 'Paket berhasil diperbarui.');
    }

    public function setActive(Request $request, Plan $plan): RedirectResponse
    {
        $this->ensureAdmin();
        $data = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);
        $active = (bool) $data['is_active'];
        DB::transaction(function () use ($plan, $active): void {
            $lockedPlan = Plan::query()->lockForUpdate()->findOrFail($plan->id);
            $this->assertSafePlanChange($lockedPlan, [
                'is_active' => $active,
                'price' => $lockedPlan->price,
                'billing_interval' => $lockedPlan->billing_interval,
            ]);
            $lockedPlan->update(['is_active' => $active]);
        }, 3);
        Log::notice('Admin changed plan availability.', ['admin_id' => $request->user()->id, 'plan_id' => $plan->id, 'is_active' => $active]);

        return back()->with('status', $active
            ? 'Paket berhasil diaktifkan.'
            : 'Paket berhasil dinonaktifkan.');
    }

    /**
     * @return array{name: string, slug: string, description: ?string, price: string, currency: string, billing_interval: string, analysis_limit: ?int, is_active: bool}
     */
    private function validatedPlan(Request $request, ?Plan $plan = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('plans', 'name')->ignore($plan?->getKey())],
            'slug' => ['required', 'string', 'alpha_dash:ascii', 'max:100', Rule::unique('plans', 'slug')->ignore($plan?->getKey())],
            'description' => ['nullable', 'string', 'max:2000'],
            'price' => ['required', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'currency' => ['required', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
            'billing_interval' => ['required', Rule::in(Plan::BILLING_INTERVALS)],
            'analysis_limit' => ['nullable', 'integer', 'min:0', 'max:4294967295'],
            'is_active' => ['required', 'boolean'],
        ]);

        if ((float) $validated['price'] > 0 && $validated['billing_interval'] === 'none') {
            throw ValidationException::withMessages([
                'billing_interval' => 'Paket berbayar harus menggunakan periode bulanan atau tahunan agar dapat dibeli.',
            ]);
        }

        return [
            ...$validated,
            'price' => (string) $validated['price'],
            'analysis_limit' => $validated['analysis_limit'] === null
                ? null
                : (int) $validated['analysis_limit'],
            'is_active' => (bool) $validated['is_active'],
        ];
    }

    /**
     * @param  array{is_active: bool, price: string, billing_interval: string}  $data
     */
    private function assertSafePlanChange(Plan $plan, array $data): void
    {
        $stoppingEntitlement = ! $data['is_active'] || (float) $data['price'] <= 0;
        $changingPendingTerm = $data['billing_interval'] !== $plan->billing_interval;

        if (! $stoppingEntitlement && ! $changingPendingTerm) {
            return;
        }

        $hasEntitledUsers = Subscription::query()
            ->eligible()
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
            ->where('plan_id', $plan->getKey())
            ->exists();
        $hasPendingPayments = Payment::query()
            ->where('status', 'pending')
            ->whereHas('subscription', fn ($query) => $query->where('plan_id', $plan->getKey()))
            ->exists();

        if ($stoppingEntitlement && $hasEntitledUsers) {
            throw ValidationException::withMessages([
                'is_active' => 'Paket tidak dapat dinonaktifkan atau dibuat gratis selama masih ada langganan yang berlaku atau terjadwal.',
            ]);
        }

        if ($hasPendingPayments && ($stoppingEntitlement || $changingPendingTerm)) {
            throw ValidationException::withMessages([
                'is_active' => 'Tunggu sampai pembayaran tertunda untuk paket ini selesai sebelum menonaktifkan paket atau mengubah periodenya.',
            ]);
        }
    }

    private function ensureAdmin(): void
    {
        abort_unless(auth()->user()?->role === User::ROLE_ADMIN, 403);
    }
}
