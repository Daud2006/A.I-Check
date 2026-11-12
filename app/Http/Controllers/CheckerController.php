<?php

namespace App\Http\Controllers;

use App\Exceptions\ImageClassificationException;
use App\Models\ImageAnalysis;
use App\Models\User;
use App\Services\LocalAiDetector;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class CheckerController extends Controller
{
    private const UPLOAD_MAX_KILOBYTES = 10240;

    private const STORAGE_DISK = 'image-analyses';

    private const GUEST_COOKIE = 'aicheck_guest_usage';

    private const GUEST_LIMIT = 2;

    private const FREE_DAILY_LIMIT = 3;

    private const QUOTA_LOCK_SECONDS = 180;

    private const QUOTA_LOCK_WAIT_SECONDS = 80;

    public function index(Request $request): View
    {
        if (! $request->user() instanceof User) {
            $guestId = $this->guestIdentifier($request);
            if ($guestId === null) {
                $guestId = (string) Str::uuid();
                Cookie::queue(cookie(
                    self::GUEST_COOKIE,
                    $guestId,
                    5256000,
                    '/',
                    null,
                    $request->isSecure(),
                    true,
                    false,
                    'lax',
                ));
            }
        }

        return view('deteksi.index');
    }

    public function store(Request $request, LocalAiDetector $classifier): JsonResponse
    {
        $validated = $request->validate([
            'image' => [
                'required',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:'.self::UPLOAD_MAX_KILOBYTES,
            ],
        ], [
            'image.required' => 'Pilih gambar yang akan diunggah.',
            'image.file' => 'File yang dikirim bukan unggahan yang valid.',
            'image.image' => 'File harus berupa gambar yang valid.',
            'image.mimes' => 'Format gambar yang didukung adalah JPEG, PNG, dan WebP.',
            'image.max' => 'Ukuran gambar maksimal 10 MB.',
            'image.uploaded' => 'Unggahan gagal. Pastikan ukuran gambar maksimal 10 MB.',
        ]);

        $file = $validated['image'];
        $user = $request->user();
        if (! $user instanceof User) {
            $guestId = $this->guestIdentifier($request);
            if ($guestId === null) {
                return response()->json([
                    'code' => 'guest_identity_missing',
                    'message' => 'Muat ulang halaman Deteksi Gambar sebelum mengunggah gambar.',
                ], 428);
            }

            try {
                return Cache::store('database')->lock($this->guestLockKey($guestId), self::QUOTA_LOCK_SECONDS)
                    ->block(self::QUOTA_LOCK_WAIT_SECONDS, function () use ($request, $classifier, $file, $guestId): JsonResponse {
                        $used = (int) Cache::store('database')->get($this->guestUsageKey($guestId), 0);
                        if ($used >= self::GUEST_LIMIT) {
                            return $this->quotaExceededResponse(
                                'guest_limit_reached',
                                'Batas Deteksi Gambar tanpa login telah tercapai. Silakan buat akun atau login untuk melanjutkan Deteksi Gambar dan menikmati fitur A.I-Check.',
                            );
                        }

                        $response = $this->processUpload($request, $classifier, $file, null, false, $guestId);
                        if ($response->getStatusCode() === 201) {
                            Cache::store('database')->put($this->guestUsageKey($guestId), $used + 1, 5256000);
                        }

                        return $response;
                    });
            } catch (LockTimeoutException) {
                return $this->quotaLockUnavailableResponse();
            }
        }

        if ($user->hasPremiumAccess()) {
            return $this->processUpload($request, $classifier, $file, $user, false);
        }

        try {
            return Cache::store('database')->lock('image-analysis:user:'.$user->getKey(), self::QUOTA_LOCK_SECONDS)
                ->block(self::QUOTA_LOCK_WAIT_SECONDS, function () use ($request, $classifier, $file, $user): JsonResponse {
                    if ($this->dailyUsageCount($user) >= self::FREE_DAILY_LIMIT) {
                        return $this->quotaExceededResponse(
                            'free_limit_reached',
                            'Anda telah habis batas Deteksi Gambar dengan akun Free (Gratis). Silakan berlangganan atau tunggu hingga besok hari untuk dapat melakukan Deteksi Gambar kembali.',
                        );
                    }

                    return $this->processUpload($request, $classifier, $file, $user, true);
                });
        } catch (LockTimeoutException) {
            return $this->quotaLockUnavailableResponse();
        }
    }

    private function processUpload(
        Request $request,
        LocalAiDetector $classifier,
        UploadedFile $file,
        ?User $user,
        bool $recordFreeUsage,
        ?string $guestId = null,
    ): JsonResponse {
        $storedPath = null;
        $originalName = basename(str_replace('\\', '/', $file->getClientOriginalName()));
        $originalName = preg_replace('/[\x00-\x1F\x7F]/', '', $originalName) ?: 'image';

        try {
            $directory = $user instanceof User
                ? 'users/'.$user->getKey()
                : 'guests/'.hash('sha256', (string) $guestId);
            $storedPath = $file->store($directory, self::STORAGE_DISK);

            if (! is_string($storedPath) || $storedPath === '') {
                throw new RuntimeException('Private image storage did not return a path.');
            }

            $analysis = new ImageAnalysis([
                'original_filename' => Str::limit($originalName, 255, ''),
                'image_path' => $storedPath,
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
            ]);
            $analysis->status = 'pending';

            if ($user instanceof User) {
                DB::transaction(fn () => $user->imageAnalyses()->save($analysis));
            }
        } catch (Throwable $exception) {
            if (is_string($storedPath) && $storedPath !== '') {
                try {
                    if (! Storage::disk(self::STORAGE_DISK)->delete($storedPath)) {
                        Log::warning('Unable to clean up a failed private image upload.');
                    }
                } catch (Throwable $cleanupException) {
                    Log::error('Unable to clean up a failed private image upload.', [
                        ...ImageClassificationException::contextFor(
                            $cleanupException,
                            'controller',
                            'cleanup_failure',
                            500,
                            null,
                        ),
                    ]);
                }
            }

            Log::error('Unable to save image analysis upload.', [
                ...ImageClassificationException::contextFor($exception, 'controller', 'upload_failure', 500, null),
            ]);

            return response()->json([
                'message' => 'Gambar belum dapat disimpan. Silakan coba kembali.',
            ], 500);
        }

        try {
            $analysis->status = 'processing';
            if ($user instanceof User) {
                $analysis->save();
            }

            $prediction = $classifier->classify($analysis);

            $analysis->result_label = $prediction['label'];
            $analysis->confidence_score = $prediction['score'];
            $analysis->model_name = $prediction['model'];
            $analysis->status = 'completed';

            if ($user instanceof User) {
                DB::transaction(function () use ($analysis, $user, $recordFreeUsage): void {
                    $analysis->save();

                    if ($recordFreeUsage) {
                        $user->usageRecords()->create([
                            'image_analysis_id' => $analysis->getKey(),
                            'usage_type' => 'analysis',
                            'quantity' => 1,
                        ]);
                    }
                });
            }
        } catch (ImageClassificationException $exception) {
            $persistedFailure = $user instanceof User
                ? $this->markAnalysisFailed($analysis)
                : $this->markGuestAnalysisFailed($analysis);

            Log::warning('Local AI image classification failed.', [
                ...$exception->diagnosticContext(),
            ]);

            $this->cleanupGuestImage($user, $storedPath);

            return response()->json([
                'code' => 'analysis_failed',
                'id' => $user instanceof User ? $analysis->getKey() : null,
                'status' => $persistedFailure ? 'failed' : null,
                'message' => $exception->getMessage(),
            ], $exception->httpStatus);
        } catch (Throwable $exception) {
            if ($user instanceof User) {
                $this->markAnalysisFailed($analysis);
            } else {
                $this->markGuestAnalysisFailed($analysis);
            }

            Log::error('Unable to complete image classification.', [
                ...ImageClassificationException::contextFor($exception, 'controller', 'internal_error', 500, null),
            ]);

            $this->cleanupGuestImage($user, $storedPath);

            return response()->json([
                'code' => 'analysis_failed',
                'id' => $user instanceof User ? $analysis->getKey() : null,
                'status' => 'failed',
                'message' => 'Analisis belum dapat diselesaikan. Silakan coba kembali.',
            ], 500);
        }

        $response = response()->json([
            'id' => $analysis->getKey(),
            'status' => $analysis->status,
            'result_label' => $analysis->result_label,
            'confidence_score' => (float) $analysis->confidence_score,
            'model_name' => $analysis->model_name,
            'engine_evidence' => $prediction['evidence'] ?? [],
            'message' => 'Analisis gambar selesai. Hasil merupakan prediksi model, bukan kepastian.',
        ], 201);

        $this->cleanupGuestImage($user, $storedPath);

        return $response;
    }

    private function dailyUsageCount(User $user): int
    {
        $startOfDay = now()->startOfDay();

        return (int) $user->usageRecords()
            ->where('usage_type', 'analysis')
            ->where('created_at', '>=', $startOfDay)
            ->where('created_at', '<', $startOfDay->copy()->addDay())
            ->sum('quantity');
    }

    private function guestIdentifier(Request $request): ?string
    {
        $guestId = $request->cookie(self::GUEST_COOKIE);

        return is_string($guestId) && Str::isUuid($guestId) ? $guestId : null;
    }

    private function guestUsageKey(string $guestId): string
    {
        return 'image-analysis:guest:'.hash('sha256', $guestId);
    }

    private function guestLockKey(string $guestId): string
    {
        return $this->guestUsageKey($guestId).':lock';
    }

    private function quotaExceededResponse(string $code, string $message): JsonResponse
    {
        return response()->json([
            'code' => $code,
            'message' => $message,
        ], 429);
    }

    private function quotaLockUnavailableResponse(): JsonResponse
    {
        return response()->json([
            'code' => 'quota_lock_unavailable',
            'message' => 'Deteksi Gambar sedang diproses. Silakan coba kembali beberapa saat lagi.',
        ], 503);
    }

    private function markGuestAnalysisFailed(ImageAnalysis $analysis): bool
    {
        $analysis->result_label = null;
        $analysis->confidence_score = null;
        $analysis->model_name = null;
        $analysis->status = 'failed';

        return true;
    }

    private function cleanupGuestImage(?User $user, ?string $storedPath): void
    {
        if ($user instanceof User || ! is_string($storedPath) || $storedPath === '') {
            return;
        }

        try {
            if (! Storage::disk(self::STORAGE_DISK)->delete($storedPath)) {
                Log::warning('Unable to clean up a temporary guest image.');
            }
        } catch (Throwable $exception) {
            Log::warning('Unable to clean up a temporary guest image.', [
                'exception_class' => $exception::class,
            ]);
        }
    }

    private function markAnalysisFailed(ImageAnalysis $analysis): bool
    {
        $analysis->result_label = null;
        $analysis->confidence_score = null;
        $analysis->model_name = null;
        $analysis->status = 'failed';

        try {
            return $analysis->save();
        } catch (Throwable $exception) {
            Log::error('Unable to persist failed image analysis status.', [
                ...ImageClassificationException::contextFor($exception, 'controller', 'status_persistence_failure', 500, null),
            ]);

            return false;
        }
    }

    public function show(Request $request, int $analysisId): JsonResponse
    {
        $analysis = $request->user()->imageAnalyses()->findOrFail($analysisId);

        return response()->json([
            'id' => $analysis->getKey(),
            'original_filename' => $analysis->original_filename,
            'mime_type' => $analysis->mime_type,
            'file_size' => $analysis->file_size,
            'status' => $analysis->status,
            'model_name' => $analysis->model_name,
            'result_label' => $analysis->result_label,
            'confidence_score' => $analysis->confidence_score === null
                ? null
                : (float) $analysis->confidence_score,
            'created_at' => $analysis->created_at,
        ]);
    }

    public function image(Request $request, int $analysisId): StreamedResponse
    {
        $analysis = $request->user()->imageAnalyses()->findOrFail($analysisId);
        $disk = Storage::disk(self::STORAGE_DISK);

        if (! is_string($analysis->image_path) || ! $disk->exists($analysis->image_path)) {
            abort(404);
        }

        return $disk->response($analysis->image_path, 'image', [
            'Content-Type' => $analysis->mime_type,
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }
}
