<?php

namespace App\Http\Controllers;

use App\Models\ImageAnalysis;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\LocalAiDetector;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use PDOException;

class AdminController extends Controller
{
    // Menampilkan halaman admin.
    public function index(LocalAiDetector $detector): View
    {
        abort_unless(auth()->user()->role === User::ROLE_ADMIN, 403);

        $now = now();
        $today = $now->copy()->startOfDay();
        $chartStart = $today->copy()->subDays(6);
        $dashboardError = null;
        $onlineUsersCount = null;
        $onlineUsersAvailable = config('session.driver') === 'database';
        $todayOutcomes = collect();
        $freeUserCount = null;
        $monthlyRevenue = collect();

        try {
            $userCount = User::query()->count();
            $analysisCount = ImageAnalysis::query()->count();
            $todayAnalysisCount = ImageAnalysis::query()
                ->where('created_at', '>=', $today)
                ->where('created_at', '<', $today->copy()->addDay())
                ->count();

            $activeSubscriptionUsersCount = $this->activeSubscriptionsQuery($now)
                ->distinct()
                ->count('user_id');
            $freeUserCount = User::query()->where('role', User::ROLE_USER)
                ->whereDoesntHave('subscriptions', fn (Builder $query) => $query->currentlyEntitled($now))->count();
            $todayOutcomes = ImageAnalysis::query()->where('created_at', '>=', $today)
                ->where('created_at', '<', $today->copy()->addDay())
                ->whereIn('status', ['completed', 'failed'])
                ->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
            $monthlyRevenue = Payment::query()->where('status', 'paid')
                ->where('paid_at', '>=', $today->copy()->startOfMonth())
                ->where('paid_at', '<', $today->copy()->addMonthNoOverflow()->startOfMonth())
                ->selectRaw('environment, currency, SUM(amount) as total')
                ->groupBy('environment', 'currency')->orderBy('environment')->orderBy('currency')->get();

            $successfulPaymentCount = Payment::query()
                ->where('status', 'paid')
                ->count();
            $pendingPaymentCount = Payment::query()
                ->where('status', 'pending')
                ->count();
            $paidRevenueByCurrency = Payment::query()
                ->where('status', 'paid')
                ->selectRaw('environment, currency, SUM(amount) as total_amount')
                ->groupBy('currency', 'environment')
                ->orderBy('currency')
                ->orderBy('environment')
                ->get()
                ->map(fn (Payment $payment): array => [
                    'environment' => $payment->environment,
                    'currency' => $payment->currency,
                    'amount' => (string) $payment->total_amount,
                ])
                ->all();

            $analysisCountsByDate = ImageAnalysis::query()
                ->where('created_at', '>=', $chartStart)
                ->where('created_at', '<', $today->copy()->addDay())
                ->selectRaw('DATE(created_at) as analysis_date, COUNT(*) as total')
                ->groupBy('analysis_date')
                ->pluck('total', 'analysis_date');

            $analysisChart = [];
            for ($daysAgo = 6; $daysAgo >= 0; $daysAgo--) {
                $date = $today->copy()->subDays($daysAgo);
                $analysisChart[] = [
                    'date' => $date->toDateString(),
                    'label' => $date->format('d/m'),
                    'total' => (int) $analysisCountsByDate->get($date->toDateString(), 0),
                ];
            }

            $recentUsers = User::query()
                ->select(['id', 'name', 'email', 'created_at'])
                ->where('role', User::ROLE_USER)
                ->withExists([
                    'subscriptions as has_active_subscription' => fn (Builder $query): Builder => $this->activeSubscriptionConstraints($query, $now),
                ])
                ->latest('created_at')
                ->limit(5)
                ->get();

            $recentAnalyses = ImageAnalysis::query()
                ->with('user:id,name')
                ->latest('created_at')
                ->limit(5)
                ->get();
        } catch (PDOException $exception) {
            Log::error('Unable to load Admin dashboard data.', [
                'exception_class' => $exception::class,
            ]);

            $dashboardError = 'Data dashboard belum dapat dimuat. Silakan coba kembali beberapa saat lagi.';
            $userCount = null;
            $analysisCount = null;
            $todayAnalysisCount = null;
            $activeSubscriptionUsersCount = null;
            $successfulPaymentCount = null;
            $pendingPaymentCount = null;
            $paidRevenueByCurrency = [];
            $analysisChart = [];
            $recentUsers = collect();
            $recentAnalyses = collect();
        }

        if ($onlineUsersAvailable) {
            try {
                $onlineUsersCount = DB::table(config('session.table', 'sessions'))
                    ->whereNotNull('user_id')
                    ->where('last_activity', '>=', $now->copy()->subMinutes(5)->timestamp)
                    ->distinct()
                    ->count('user_id');
            } catch (PDOException $exception) {
                Log::warning('Unable to load online user count for Admin dashboard.', [
                    'exception_class' => $exception::class,
                ]);
                $onlineUsersAvailable = false;
            }
        }

        return view('admin.index', [
            'dashboardError' => $dashboardError,
            'detectorReady' => $detector->isReady(),
            'todayOutcomes' => $todayOutcomes,
            'freeUserCount' => $freeUserCount,
            'monthlyRevenue' => $monthlyRevenue,
            'userCount' => $userCount,
            'analysisCount' => $analysisCount,
            'todayAnalysisCount' => $todayAnalysisCount,
            'onlineUsersCount' => $onlineUsersCount,
            'onlineUsersAvailable' => $onlineUsersAvailable,
            'activeSubscriptionUsersCount' => $activeSubscriptionUsersCount,
            'successfulPaymentCount' => $successfulPaymentCount,
            'pendingPaymentCount' => $pendingPaymentCount,
            'paidRevenueByCurrency' => $paidRevenueByCurrency,
            'analysisChart' => $analysisChart,
            'recentUsers' => $recentUsers,
            'recentAnalyses' => $recentAnalyses,
        ]);
    }

    public function users(Request $request): View
    {
        $this->ensureAdmin();

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', Rule::in(User::ROLES)],
            'subscription' => ['nullable', Rule::in([...Subscription::STATUSES, 'none'])],
            'plan' => ['nullable', 'integer', Rule::exists('plans', 'id')],
            'account' => ['nullable', Rule::in(['free', 'premium'])],
        ]);

        $search = trim($validated['search'] ?? '');
        $role = $validated['role'] ?? '';
        $subscriptionStatus = $validated['subscription'] ?? '';
        $planId = $validated['plan'] ?? '';
        $account = $validated['account'] ?? '';
        $now = now();

        $users = User::query()
            ->select(['id', 'name', 'email', 'role', 'created_at', 'disabled_at'])
            ->with(['latestSubscription' => function (HasOne $query): void {
                $query->select([
                    'subscriptions.id',
                    'subscriptions.user_id',
                    'subscriptions.plan_id',
                    'subscriptions.status',
                    'subscriptions.starts_at',
                    'subscriptions.ends_at',
                    'subscriptions.cancelled_at',
                    'subscriptions.created_at',
                    'subscriptions.plan_terms',
                ])->with('plan:id,name');
            }])
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%');
                });
            })
            ->when($role !== '', fn (Builder $query): Builder => $query->where('role', $role))
            ->when($account === 'premium', fn (Builder $query): Builder => $query->whereHas('subscriptions', fn (Builder $subscriptions) => $subscriptions->currentlyEntitled($now)))
            ->when($account === 'free', fn (Builder $query): Builder => $query->whereDoesntHave('subscriptions', fn (Builder $subscriptions) => $subscriptions->currentlyEntitled($now)))
            ->when($subscriptionStatus === 'none', fn (Builder $query): Builder => $query->whereDoesntHave('subscriptions'))
            ->when(
                ($subscriptionStatus !== '' && $subscriptionStatus !== 'none') || $planId !== '',
                function (Builder $query) use ($subscriptionStatus, $planId): void {
                    $query->whereHas(
                        'subscriptions',
                        function (Builder $subscriptionQuery) use ($subscriptionStatus, $planId): Builder {
                            return $subscriptionQuery
                                ->when(
                                    $subscriptionStatus !== '' && $subscriptionStatus !== 'none',
                                    fn (Builder $query): Builder => $query->where('status', $subscriptionStatus)
                                )
                                ->when($planId !== '', fn (Builder $query): Builder => $query->where('plan_id', $planId));
                        }
                    );
                }
            )
            ->latest('created_at')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'plans' => Plan::query()->orderBy('name')->get(['id', 'name']),
            'filters' => [
                'search' => $search,
                'role' => $role,
                'subscription' => $subscriptionStatus,
                'plan' => (string) $planId,
                'account' => $account,
            ],
        ]);
    }

    public function createUser(): View
    {
        $this->ensureAdmin();

        return view('admin.users.create');
    }

    public function storeUser(Request $request): RedirectResponse
    {
        $this->ensureAdmin();
        $data = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ], [
            'email.unique' => 'Email tersebut sudah terdaftar.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
        ], [
            'name' => 'nama',
            'password' => 'kata sandi awal',
        ]);

        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);
        Log::notice('Admin created user account.', ['admin_id' => $request->user()->id, 'user_id' => $user->id]);

        return redirect()->route('admin.users.show', $user->id)->with('status', 'Akun pengguna berhasil dibuat.');
    }

    public function editUser(int $userId): View
    {
        $this->ensureAdmin();
        $user = User::query()->where('role', User::ROLE_USER)->findOrFail($userId);

        return view('admin.users.create', ['editingUser' => $user]);
    }

    public function updateUser(Request $request, int $userId): RedirectResponse
    {
        $this->ensureAdmin();
        $user = User::query()->where('role', User::ROLE_USER)->findOrFail($userId);
        $data = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
        ]);
        DB::transaction(function () use ($user, $data): void {
            if ($user->email !== $data['email']) {
                DB::table(config('auth.passwords.users.table', 'password_reset_tokens'))->where('email', $user->email)->delete();
                $user->email_verified_at = null;
            }
            $user->fill($data)->save();
        });
        Log::notice('Admin updated user account.', ['admin_id' => $request->user()->id, 'user_id' => $userId]);

        return redirect()->route('admin.users.show', $userId)->with('status', 'Akun pengguna diperbarui.');
    }

    public function destroyUser(Request $request, int $userId): RedirectResponse
    {
        $this->ensureAdmin();
        $disabled = DB::transaction(function () use ($userId): bool {
            $user = User::query()->where('role', User::ROLE_USER)->lockForUpdate()->findOrFail($userId);
            $hasHistory = $user->imageAnalyses()->exists() || $user->usageRecords()->exists()
                || $user->subscriptions()->exists() || $user->payments()->exists();

            if ($hasHistory) {
                $user->disabled_at ??= now();
                $user->setRememberToken(null);
                $user->save();
            }

            if (config('session.driver') === 'database') {
                $connection = DB::connection(config('session.connection'));
                $connection->table(config('session.table', 'sessions'))->where('user_id', $userId)->delete();
            }
            DB::table(config('auth.passwords.users.table', 'password_reset_tokens'))->where('email', $user->email)->delete();

            if (! $hasHistory) {
                $user->delete();
            }

            return $hasHistory;
        }, 3);
        Log::notice($disabled ? 'Admin disabled user account.' : 'Admin deleted user account.', [
            'admin_id' => $request->user()->id, 'user_id' => $userId,
        ]);

        return redirect()->route('admin.users.index')->with('status', $disabled ? 'Akun dinonaktifkan. Riwayat tetap tersimpan.' : 'Akun dihapus.');
    }

    public function enableUser(Request $request, int $userId): RedirectResponse
    {
        $this->ensureAdmin();
        $user = User::query()->where('role', User::ROLE_USER)->findOrFail($userId);
        $user->disabled_at = null;
        $user->save();
        Log::notice('Admin enabled user account.', ['admin_id' => $request->user()->id, 'user_id' => $userId]);

        return redirect()->route('admin.users.show', $userId)->with('status', 'Akun pengguna diaktifkan kembali.');
    }

    public function showUser(int $userId): View
    {
        $this->ensureAdmin();

        $user = User::query()
            ->select(['id', 'name', 'email', 'role', 'email_verified_at', 'disabled_at', 'created_at', 'updated_at'])
            ->findOrFail($userId);

        $subscriptions = $user->subscriptions()
            ->with('plan:id,name,price,currency,billing_interval')
            ->latest('created_at')
            ->latest('id')
            ->limit(10)
            ->get([
                'id',
                'user_id',
                'plan_id',
                'status',
                'starts_at',
                'ends_at',
                'cancelled_at',
                'created_at',
                'plan_terms',
            ]);

        $analysisCount = $user->imageAnalyses()->count();
        $recentAnalyses = $user->imageAnalyses()
            ->latest('created_at')
            ->latest('id')
            ->limit(5)
            ->get([
                'id',
                'mime_type',
                'status',
                'result_label',
                'confidence_score',
                'created_at',
            ]);

        return view('admin.users.show', [
            'user' => $user,
            'subscriptions' => $subscriptions,
            'analysisCount' => $analysisCount,
            'recentAnalyses' => $recentAnalyses,
        ]);
    }

    public function analyses(Request $request): View
    {
        $this->ensureAdmin();

        $resultLabels = ImageAnalysis::query()
            ->whereNotNull('result_label')
            ->distinct()
            ->orderBy('result_label')
            ->pluck('result_label')
            ->all();
        $mimeTypes = ImageAnalysis::query()
            ->whereNotNull('mime_type')
            ->distinct()
            ->orderBy('mime_type')
            ->pluck('mime_type')
            ->all();

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(ImageAnalysis::STATUSES)],
            'result_label' => ['nullable', 'string', 'max:50', Rule::in($resultLabels)],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d'],
            'mime_type' => ['nullable', 'string', 'max:100', Rule::in($mimeTypes)],
        ]);

        $dateFrom = $validated['date_from'] ?? '';
        $dateTo = $validated['date_to'] ?? '';

        if ($dateFrom !== '' && $dateTo !== '' && $dateTo < $dateFrom) {
            throw ValidationException::withMessages([
                'date_to' => 'Tanggal akhir tidak boleh sebelum tanggal awal.',
            ]);
        }

        $search = trim($validated['search'] ?? '');
        $status = $validated['status'] ?? '';
        $resultLabel = $validated['result_label'] ?? '';
        $mimeType = $validated['mime_type'] ?? '';

        $analyses = ImageAnalysis::query()
            ->select([
                'id',
                'user_id',
                'mime_type',
                'status',
                'result_label',
                'confidence_score',
                'model_name',
                'created_at',
            ])
            ->with('user:id,name,email')
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->whereHas('user', function (Builder $userQuery) use ($search): void {
                    $userQuery->where('name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%');
                });
            })
            ->when($status !== '', fn (Builder $query): Builder => $query->where('status', $status))
            ->when($resultLabel !== '', fn (Builder $query): Builder => $query->where('result_label', $resultLabel))
            ->when($mimeType !== '', fn (Builder $query): Builder => $query->where('mime_type', $mimeType))
            ->when($dateFrom !== '', fn (Builder $query): Builder => $query->where(
                'created_at',
                '>=',
                CarbonImmutable::parse($dateFrom)->startOfDay()
            ))
            ->when($dateTo !== '', fn (Builder $query): Builder => $query->where(
                'created_at',
                '<',
                CarbonImmutable::parse($dateTo)->addDay()->startOfDay()
            ))
            ->latest('created_at')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.analyses.index', [
            'analyses' => $analyses,
            'resultLabels' => $resultLabels,
            'mimeTypes' => $mimeTypes,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'result_label' => $resultLabel,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'mime_type' => $mimeType,
            ],
        ]);
    }

    public function showAnalysis(int $analysisId): View
    {
        $this->ensureAdmin();

        $analysis = ImageAnalysis::query()
            ->select([
                'id',
                'user_id',
                'mime_type',
                'file_size',
                'model_name',
                'status',
                'result_label',
                'confidence_score',
                'created_at',
                'updated_at',
            ])
            ->with('user:id,name,email')
            ->findOrFail($analysisId);

        return view('admin.analyses.show', [
            'analysis' => $analysis,
        ]);
    }

    public function transactions(Request $request): View
    {
        $this->ensureAdmin();

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(Payment::STATUSES)],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $search = trim($validated['search'] ?? '');
        $status = $validated['status'] ?? '';
        $dateFrom = $validated['date_from'] ?? '';
        $dateTo = $validated['date_to'] ?? '';

        if ($dateFrom !== '' && $dateTo !== '' && $dateTo < $dateFrom) {
            throw ValidationException::withMessages([
                'date_to' => 'Tanggal akhir tidak boleh sebelum tanggal awal.',
            ]);
        }

        $payments = Payment::query()
            ->select([
                'id',
                'user_id',
                'subscription_id',
                'provider',
                'provider_transaction_id',
                'amount',
                'currency',
                'status',
                'paid_at',
                'created_at',
            ])
            ->with(['user:id,name,email', 'subscription.plan:id,name'])
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    if (ctype_digit($search)) {
                        $query->where('id', (int) $search);
                    }

                    $query->orWhere('provider_transaction_id', 'like', '%'.$search.'%')
                        ->orWhereHas('user', function (Builder $userQuery) use ($search): void {
                            $userQuery->where('name', 'like', '%'.$search.'%')
                                ->orWhere('email', 'like', '%'.$search.'%');
                        });
                });
            })
            ->when($status !== '', fn (Builder $query): Builder => $query->where('status', $status))
            ->when($dateFrom !== '', fn (Builder $query): Builder => $query->where(
                'created_at',
                '>=',
                CarbonImmutable::parse($dateFrom)->startOfDay()
            ))
            ->when($dateTo !== '', fn (Builder $query): Builder => $query->where(
                'created_at',
                '<',
                CarbonImmutable::parse($dateTo)->addDay()->startOfDay()
            ))
            ->latest('created_at')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.transactions.index', [
            'payments' => $payments,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
        ]);
    }

    public function showTransaction(int $paymentId): View
    {
        $this->ensureAdmin();

        $payment = Payment::query()
            ->with(['user:id,name,email', 'subscription.plan:id,name,billing_interval'])
            ->findOrFail($paymentId);

        return view('admin.transactions.show', [
            'payment' => $payment,
        ]);
    }

    private function ensureAdmin(): void
    {
        abort_unless(auth()->user()?->role === User::ROLE_ADMIN, 403);
    }

    private function activeSubscriptionsQuery(CarbonInterface $now): Builder
    {
        return $this->activeSubscriptionConstraints(Subscription::query(), $now);
    }

    private function activeSubscriptionConstraints(Builder $query, CarbonInterface $now): Builder
    {
        return $query->currentlyEntitled($now);
    }
}
