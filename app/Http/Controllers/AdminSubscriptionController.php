<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminSubscriptionController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->role === User::ROLE_ADMIN, 403);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'source' => ['nullable', Rule::in(['payment', 'gift'])],
        ]);
        $search = trim($filters['search'] ?? '');
        $source = $filters['source'] ?? '';
        $subscriptions = Subscription::query()
            ->with(['user:id,name,email', 'plan:id,name', 'payments:id,subscription_id,status'])
            ->when($search !== '', fn (Builder $query) => $query->whereHas('user', fn (Builder $user) => $user
                ->where(fn (Builder $match) => $match->where('name', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%'))))
            ->when($source === 'gift', fn (Builder $query) => $query->whereNotNull('granted_by'))
            ->when($source === 'payment', fn (Builder $query) => $query->whereHas('payments'))
            ->latest('id')->paginate(15)->withQueryString();

        return view('admin.subscriptions.index', compact('subscriptions', 'search', 'source'));
    }

    public function createGift(Request $request): View
    {
        abort_unless($request->user()->role === User::ROLE_ADMIN, 403);
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100']]);
        $search = trim($filters['search'] ?? '');
        $users = $search === '' ? collect() : User::query()
            ->where('role', User::ROLE_USER)
            ->where(fn (Builder $match) => $match->where('name', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%'))
            ->orderBy('name')->limit(20)->get(['id', 'name', 'email']);
        $plans = $this->giftablePlans()->orderBy('price')->get(['id', 'name', 'billing_interval']);
        $reference = (string) Str::uuid();

        return view('admin.subscriptions.gift', compact('search', 'users', 'plans', 'reference'));
    }

    public function storeGift(Request $request): RedirectResponse
    {
        abort_unless($request->user()->role === User::ROLE_ADMIN, 403);
        $data = $request->validate([
            'user_id' => ['required', 'integer'],
            'plan_id' => ['required', 'integer'],
            'reference' => ['required', 'uuid'],
            'confirm' => ['accepted'],
        ]);
        $adminId = $request->user()->id;

        $gift = DB::transaction(function () use ($data, $adminId): Subscription {
            $user = User::query()->whereKey($data['user_id'])->where('role', User::ROLE_USER)->lockForUpdate()->first();
            if (! $user) {
                throw ValidationException::withMessages(['user_id' => 'Pilih akun pengguna yang tersedia.']);
            }

            $existing = Subscription::query()->where('grant_reference', $data['reference'])->first();
            if ($existing) {
                abort_unless($existing->granted_by === $adminId
                    && $existing->user_id === $user->id && $existing->plan_id === (int) $data['plan_id'], 409);

                return $existing;
            }

            $plan = $this->giftablePlans()->whereKey($data['plan_id'])->lockForUpdate()->first();
            if (! $plan) {
                throw ValidationException::withMessages(['plan_id' => 'Pilih paket Premium yang masih aktif.']);
            }

            $startsAt = Subscription::nextStartFor($user, CarbonImmutable::now());
            if ($startsAt === null) {
                throw ValidationException::withMessages(['user_id' => 'Langganan pengguna ini belum memiliki tanggal berakhir.']);
            }

            $subscription = new Subscription([
                'plan_id' => $plan->id,
                'starts_at' => $startsAt,
                'ends_at' => $plan->billing_interval === 'yearly' ? $startsAt->addYearNoOverflow() : $startsAt->addMonthNoOverflow(),
            ]);
            $subscription->status = 'active';
            $subscription->granted_by = $adminId;
            $subscription->grant_reference = $data['reference'];
            $subscription->plan_terms = $plan->subscriptionTerms();
            $user->subscriptions()->save($subscription);

            return $subscription;
        }, 3);

        if ($gift->wasRecentlyCreated) {
            Log::notice('Admin granted subscription gift.', [
                'admin_id' => $adminId, 'user_id' => $gift->user_id, 'subscription_id' => $gift->id, 'plan_id' => $gift->plan_id,
            ]);
        }

        return redirect()->route('admin.subscriptions.index')->with('status', 'Hadiah paket tercatat.');
    }

    private function giftablePlans(): Builder
    {
        return Plan::query()->where('is_active', true)->where('price', '>', 0)
            ->whereIn('billing_interval', ['monthly', 'yearly']);
    }
}
