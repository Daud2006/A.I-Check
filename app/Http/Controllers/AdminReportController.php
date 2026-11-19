<?php

namespace App\Http\Controllers;

use App\Models\ImageAnalysis;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\UsageRecord;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminReportController extends Controller
{
    private const AI_LABELS = ['AI-GENERATED', 'AI-EDITED'];

    private const HUMAN_LABELS = ['REAL'];

    private const RESULT_LABELS = ['AI-GENERATED', 'AI-EDITED', 'REAL', 'UNCERTAIN'];

    public function index(Request $request): View
    {
        $this->ensureAdmin();
        $filters = $this->validatedFilters($request);

        $usersInPeriod = $this->userQuery($filters, true);
        $usersByPlan = Subscription::query()
            ->currentlyEntitled(now())
            ->whereHas('user', fn (Builder $users) => $users->where('role', User::ROLE_USER))
            ->join('plans', 'plans.id', '=', 'subscriptions.plan_id')
            ->when($filters['plan_id'] !== '', fn (Builder $query): Builder => $query->where('plans.id', $filters['plan_id']))
            ->select('plans.id', 'plans.name', DB::raw('COUNT(DISTINCT subscriptions.user_id) as users_count'))
            ->groupBy('plans.id', 'plans.name')
            ->orderBy('plans.name')
            ->get();
        $onlineUsersCount = $this->onlineUsersCount();

        $analyses = $this->analysisQuery($filters);
        $analysisCount = (clone $analyses)->count();
        $analysesByStatus = (clone $analyses)
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->orderBy('status')
            ->get();
        $analysesByLabel = (clone $analyses)
            ->select('result_label', DB::raw('COUNT(*) as total'))
            ->groupBy('result_label')
            ->orderBy('result_label')
            ->get();
        $aiAnalysisCount = (clone $analyses)->whereIn('result_label', self::AI_LABELS)->count();
        $humanAnalysisCount = (clone $analyses)->whereIn('result_label', self::HUMAN_LABELS)->count();
        $unclassifiedAnalysisCount = (clone $analyses)
            ->where(function (Builder $query): void {
                $query->whereNull('result_label')->orWhere('result_label', 'UNCERTAIN');
            })
            ->count();

        $usageByUser = UsageRecord::query()
            ->join('users', 'users.id', '=', 'usage_records.user_id')
            ->where('usage_records.usage_type', 'analysis')
            ->when($filters['date_from'] !== '', fn (Builder $query): Builder => $query->where('usage_records.created_at', '>=', CarbonImmutable::parse($filters['date_from'])->startOfDay()))
            ->when($filters['date_to'] !== '', fn (Builder $query): Builder => $query->where('usage_records.created_at', '<', CarbonImmutable::parse($filters['date_to'])->addDay()->startOfDay()))
            ->select('users.id', 'users.name', 'users.email', DB::raw('SUM(usage_records.quantity) as total_usage'))
            ->groupBy('users.id', 'users.name', 'users.email')
            ->orderByDesc('total_usage')
            ->limit(10)
            ->get();
        $recordedUsageCount = UsageRecord::query()
            ->where('usage_type', 'analysis')
            ->when($filters['date_from'] !== '', fn (Builder $query): Builder => $query->where('created_at', '>=', CarbonImmutable::parse($filters['date_from'])->startOfDay()))
            ->when($filters['date_to'] !== '', fn (Builder $query): Builder => $query->where('created_at', '<', CarbonImmutable::parse($filters['date_to'])->addDay()->startOfDay()))
            ->sum('quantity');

        $payments = $this->paymentQuery($filters, true);
        $paymentsByStatus = (clone $payments)
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->orderBy('status')
            ->get();
        $paidRevenueByCurrency = (clone $payments)
            ->where('status', 'paid')
            ->select('currency', DB::raw('SUM(amount) as total_amount'))
            ->groupBy('currency')
            ->orderBy('currency')
            ->get();
        $transactionsByPlan = (clone $payments)
            ->leftJoin('subscriptions', 'subscriptions.id', '=', 'payments.subscription_id')
            ->leftJoin('plans', 'plans.id', '=', 'subscriptions.plan_id')
            ->select(
                'plans.name as plan_name',
                'payments.currency',
                DB::raw('COUNT(*) as transaction_count'),
                DB::raw("SUM(CASE WHEN payments.status = 'paid' THEN payments.amount ELSE 0 END) as paid_revenue")
            )
            ->groupBy('plans.name', 'payments.currency')
            ->orderBy('plans.name')
            ->orderBy('payments.currency')
            ->get();

        return view('admin.reports.index', [
            'filters' => $filters,
            'plans' => Plan::query()->orderBy('name')->get(['id', 'name']),
            'userCount' => $this->userQuery($filters, false)->count(),
            'newUserCount' => (clone $usersInPeriod)->count(),
            'usersByPlan' => $usersByPlan,
            'onlineUsersCount' => $onlineUsersCount,
            'onlineUsersAvailable' => $onlineUsersCount !== null,
            'analysisCount' => $analysisCount,
            'aiAnalysisCount' => $aiAnalysisCount,
            'humanAnalysisCount' => $humanAnalysisCount,
            'unclassifiedAnalysisCount' => $unclassifiedAnalysisCount,
            'analysesByStatus' => $analysesByStatus,
            'analysesByLabel' => $analysesByLabel,
            'usageByUser' => $usageByUser,
            'recordedUsageCount' => (int) $recordedUsageCount,
            'paymentsByStatus' => $paymentsByStatus,
            'paidRevenueByCurrency' => $paidRevenueByCurrency,
            'transactionsByPlan' => $transactionsByPlan,
        ]);
    }

    public function export(Request $request, string $type): StreamedResponse
    {
        $this->ensureAdmin();
        abort_unless(in_array($type, ['users', 'analyses', 'transactions'], true), 404);
        $filters = $this->validatedFilters($request);

        [$headers, $rows] = match ($type) {
            'users' => [
                ['ID', 'Nama', 'Email', 'Terdaftar'],
                $this->userExportQuery($filters),
            ],
            'analyses' => [
                ['ID Analisis', 'Nama Pengguna', 'Email', 'Status', 'Hasil', 'Confidence', 'Model', 'Tipe File', 'Nama File', 'Dibuat'],
                $this->analysisExportQuery($filters),
            ],
            'transactions' => [
                ['ID Transaksi', 'Nama Pengguna', 'Email', 'Paket', 'Jumlah', 'Mata Uang', 'Status', 'Provider', 'ID Provider', 'Dibayar', 'Dibuat'],
                $this->paymentExportQuery($filters),
            ],
        };

        $filename = 'ai-check-'.$type.'-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($headers, $rows): void {
            $output = fopen('php://output', 'wb');

            if ($output === false) {
                throw new \RuntimeException('Unable to open the CSV output stream.');
            }

            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, $headers);

            foreach ($rows->cursor() as $row) {
                fputcsv($output, array_map(
                    fn (mixed $value): string => $this->safeCsvCell($value),
                    array_values($row->getAttributes())
                ));
            }

            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-store',
        ]);
    }

    /**
     * @return array{date_from: string, date_to: string, analysis_status: string, result_label: string, payment_status: string, plan_id: string}
     */
    private function validatedFilters(Request $request): array
    {
        $filters = $request->validate([
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d'],
            'analysis_status' => ['nullable', Rule::in(ImageAnalysis::STATUSES)],
            'result_label' => ['nullable', Rule::in(self::RESULT_LABELS)],
            'payment_status' => ['nullable', Rule::in(Payment::STATUSES)],
            'plan_id' => ['nullable', 'integer', Rule::exists('plans', 'id')],
        ]);
        $dateFrom = $filters['date_from'] ?? '';
        $dateTo = $filters['date_to'] ?? '';

        if ($dateFrom !== '' && $dateTo !== '' && $dateTo < $dateFrom) {
            throw ValidationException::withMessages([
                'date_to' => 'Tanggal akhir tidak boleh sebelum tanggal awal.',
            ]);
        }

        return [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'analysis_status' => $filters['analysis_status'] ?? '',
            'result_label' => $filters['result_label'] ?? '',
            'payment_status' => $filters['payment_status'] ?? '',
            'plan_id' => isset($filters['plan_id']) ? (string) $filters['plan_id'] : '',
        ];
    }

    private function userQuery(array $filters, bool $includeDateRange): Builder
    {
        return User::query()
            ->where('role', User::ROLE_USER)
            ->when($includeDateRange && $filters['date_from'] !== '', fn (Builder $query): Builder => $query->where('created_at', '>=', CarbonImmutable::parse($filters['date_from'])->startOfDay()))
            ->when($includeDateRange && $filters['date_to'] !== '', fn (Builder $query): Builder => $query->where('created_at', '<', CarbonImmutable::parse($filters['date_to'])->addDay()->startOfDay()))
            ->when($filters['plan_id'] !== '', function (Builder $query) use ($filters): void {
                $query->whereHas('subscriptions', fn (Builder $subscriptions): Builder => $subscriptions
                    ->currentlyEntitled(now())
                    ->where('plan_id', $filters['plan_id']));
            });
    }

    private function analysisQuery(array $filters): Builder
    {
        return ImageAnalysis::query()
            ->when($filters['date_from'] !== '', fn (Builder $query): Builder => $query->where('created_at', '>=', CarbonImmutable::parse($filters['date_from'])->startOfDay()))
            ->when($filters['date_to'] !== '', fn (Builder $query): Builder => $query->where('created_at', '<', CarbonImmutable::parse($filters['date_to'])->addDay()->startOfDay()))
            ->when($filters['analysis_status'] !== '', fn (Builder $query): Builder => $query->where('status', $filters['analysis_status']))
            ->when($filters['result_label'] !== '', fn (Builder $query): Builder => $query->where('result_label', $filters['result_label']));
    }

    private function paymentQuery(array $filters, bool $includeStatus): Builder
    {
        return Payment::query()
            ->when($filters['date_from'] !== '', fn (Builder $query): Builder => $query->where('payments.created_at', '>=', CarbonImmutable::parse($filters['date_from'])->startOfDay()))
            ->when($filters['date_to'] !== '', fn (Builder $query): Builder => $query->where('payments.created_at', '<', CarbonImmutable::parse($filters['date_to'])->addDay()->startOfDay()))
            ->when($includeStatus && $filters['payment_status'] !== '', fn (Builder $query): Builder => $query->where('payments.status', $filters['payment_status']))
            ->when($filters['plan_id'] !== '', fn (Builder $query): Builder => $query->whereHas('subscription', fn (Builder $query): Builder => $query->where('plan_id', $filters['plan_id'])));
    }

    private function userExportQuery(array $filters): Builder
    {
        return $this->userQuery($filters, true)
            ->select(['id', 'name', 'email', 'created_at'])
            ->orderBy('id');
    }

    private function analysisExportQuery(array $filters): Builder
    {
        return $this->analysisQuery($filters)
            ->leftJoin('users', 'users.id', '=', 'image_analyses.user_id')
            ->select([
                'image_analyses.id',
                'users.name as user_name',
                'users.email as user_email',
                'image_analyses.status',
                'image_analyses.result_label',
                'image_analyses.confidence_score',
                'image_analyses.model_name',
                'image_analyses.mime_type',
                'image_analyses.original_filename',
                'image_analyses.created_at',
            ])
            ->orderBy('image_analyses.id');
    }

    private function paymentExportQuery(array $filters): Builder
    {
        return $this->paymentQuery($filters, true)
            ->leftJoin('users', 'users.id', '=', 'payments.user_id')
            ->leftJoin('subscriptions', 'subscriptions.id', '=', 'payments.subscription_id')
            ->leftJoin('plans', 'plans.id', '=', 'subscriptions.plan_id')
            ->select([
                'payments.id',
                'users.name as user_name',
                'users.email as user_email',
                'plans.name as plan_name',
                'payments.amount',
                'payments.currency',
                'payments.status',
                'payments.provider',
                'payments.provider_transaction_id',
                'payments.paid_at',
                'payments.created_at',
            ])
            ->orderBy('payments.id');
    }

    private function onlineUsersCount(): ?int
    {
        if (config('session.driver') !== 'database') {
            return null;
        }

        try {
            return DB::table(config('session.table', 'sessions'))
                ->whereIn('user_id', User::query()->select('id')->where('role', User::ROLE_USER)->whereNull('disabled_at'))
                ->where('last_activity', '>=', now()->subMinutes(5)->timestamp)
                ->distinct()
                ->count('user_id');
        } catch (QueryException) {
            return null;
        }
    }

    private function safeCsvCell(mixed $value): string
    {
        $cell = $value === null ? '' : (string) $value;

        return preg_match('/^[\t\r\n ]*[=+\-@]/', $cell) === 1
            ? "'".$cell
            : $cell;
    }

    private function ensureAdmin(): void
    {
        abort_unless(auth()->user()?->role === User::ROLE_ADMIN, 403);
    }
}
