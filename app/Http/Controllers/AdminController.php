<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\BanHistory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AdminController extends Controller
{
    // Menampilkan halaman admin.
    public function index(): View
    {
        $now = now();
        $premiumUserIds = User::query()
            ->whereHas('subscriptions', function ($query) use ($now): void {
                $query->where('status', 'active')
                    ->whereNull('cancelled_at')
                    ->where(function ($dates) use ($now): void {
                        $dates->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
                    })
                    ->where(function ($dates) use ($now): void {
                        $dates->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
                    })
                    ->whereHas('plan', function ($plan): void {
                        $plan->where('is_active', true)->where('price', '>', 0);
                    });
            })
            ->pluck('id');

        $totalAccounts = User::query()->count();
        $premiumAccounts = $premiumUserIds->count();
        $freeAccounts = max(0, $totalAccounts - $premiumAccounts);
        $bannedAccounts = User::query()->where('is_banned', true)->count();
        $activeAccounts = max(0, $totalAccounts - $bannedAccounts);

        $onlineVisitors = Schema::hasTable('visitors')
            ? DB::table('visitors')->where('last_seen_at', '>=', $now->copy()->subMinutes(5))->count()
            : 0;
        $todayVisitors = Schema::hasTable('visitors')
            ? DB::table('visitors')->where('first_seen_at', '>=', $now->copy()->startOfDay())->count()
            : 0;
        $totalVisitors = Schema::hasTable('visitors') ? DB::table('visitors')->count() : 0;

        $registrationTrend = User::query()
            ->selectRaw('DATE(created_at) as date, COUNT(*) as total')
            ->where('created_at', '>=', $now->copy()->subDays(29)->startOfDay())
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('total', 'date');

        $visitorTrend = collect();
        if (Schema::hasTable('visitors')) {
            $visitorTrend = DB::table('visitors')
                ->selectRaw('DATE(first_seen_at) as date, COUNT(*) as total')
                ->where('first_seen_at', '>=', $now->copy()->subDays(29)->startOfDay())
                ->groupBy('date')
                ->orderBy('date')
                ->pluck('total', 'date');
        }

        $statusAccounts = [
            'Aktif' => $activeAccounts,
            'Dibanned' => $bannedAccounts,
        ];

        return view('admin.index', [
            'totalAccounts' => $totalAccounts,
            'premiumAccounts' => $premiumAccounts,
            'freeAccounts' => $freeAccounts,
            'bannedAccounts' => $bannedAccounts,
            'activeAccounts' => $activeAccounts,
            'totalVisitors' => $totalVisitors,
            'onlineVisitors' => $onlineVisitors,
            'todayVisitors' => $todayVisitors,
            'registrationTrend' => $registrationTrend,
            'visitorTrend' => $visitorTrend,
            'statusAccounts' => $statusAccounts,
        ]);
    }

    public function accounts(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $now = now();

        $users = User::query()
            ->where('role', '!=', User::ROLE_ADMIN)
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($nested) use ($search): void {
                    $nested->where('name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%');
                });
            })
            ->with(['subscriptions.plan'])
            ->orderByDesc('created_at')
            ->paginate(10)
            ->withQueryString();

        $totalAccounts = User::query()->where('role', '!=', User::ROLE_ADMIN)->count();
        $bannedAccounts = User::query()->where('role', '!=', User::ROLE_ADMIN)->where('is_banned', true)->count();
        $premiumAccounts = User::query()
            ->where('role', '!=', User::ROLE_ADMIN)
            ->whereHas('subscriptions', function ($query) use ($now): void {
                $query->where('status', 'active')
                    ->whereNull('cancelled_at')
                    ->where(function ($dates) use ($now): void {
                        $dates->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
                    })
                    ->where(function ($dates) use ($now): void {
                        $dates->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
                    })
                    ->whereHas('plan', function ($plan): void {
                        $plan->where('is_active', true)->where('price', '>', 0);
                    });
            })
            ->count();

        return view('admin.accounts', [
            'users' => $users,
            'search' => $search,
            'totalAccounts' => $totalAccounts,
            'bannedAccounts' => $bannedAccounts,
            'premiumAccounts' => $premiumAccounts,
            'freeAccounts' => max(0, $totalAccounts - $premiumAccounts),
        ]);
    }

    public function show(User $user): View
    {
        abort_if($user->role === User::ROLE_ADMIN, 403);
        $user->load(['subscriptions.plan', 'bannedBy', 'unbannedBy', 'banHistories.admin']);

        return view('admin.detail', [
            'user' => $user,
            'isPremium' => $user->hasPremiumAccess(),
        ]);
    }

    public function ban(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
        ]);

        abort_if($user->id === auth()->id(), 403);
        abort_if($user->role === User::ROLE_ADMIN, 403);

        if (! $user->is_banned) {
            $now = now();
            $user->forceFill([
                'is_banned' => true,
                'banned_at' => $now,
                'ban_reason' => $validated['reason'],
                'banned_by' => auth()->id(),
                'unbanned_at' => null,
                'unbanned_by' => null,
            ])->save();

            $history = new BanHistory([
                'action' => BanHistory::ACTION_BANNED,
                'reason' => $validated['reason'],
                'acted_at' => $now,
            ]);
            $history->user()->associate($user);
            $history->admin()->associate(auth()->user());
            $history->save();
        }

        return redirect()->route('admin.accounts')->with('status', 'Akun pengguna berhasil dibanned.');
    }

    public function unban(User $user): RedirectResponse
    {
        abort_if($user->id === auth()->id(), 403);
        abort_if($user->role === User::ROLE_ADMIN, 403);

        if ($user->is_banned) {
            $now = now();
            $user->forceFill([
                'is_banned' => false,
                'unbanned_at' => $now,
                'unbanned_by' => auth()->id(),
            ])->save();

            $history = new BanHistory([
                'action' => BanHistory::ACTION_UNBANNED,
                'reason' => null,
                'acted_at' => $now,
            ]);
            $history->user()->associate($user);
            $history->admin()->associate(auth()->user());
            $history->save();
        }

        return redirect()->route('admin.accounts')->with('status', 'Akun pengguna berhasil dipulihkan.');
    }
}
