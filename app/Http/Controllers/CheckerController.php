<?php

namespace App\Http\Controllers;

use App\Exceptions\AnalysisQuotaExceededException;
use App\Exceptions\ImageClassificationException;
use App\Jobs\ClassifyImageAnalysis;
use App\Models\ImageAnalysis;
use App\Models\User;
use App\Services\AnalysisQuotaService;
use App\Services\LocalAiDetector;
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

    private const TRIAL_COOKIE = 'aic_guest_trial';

    // Menampilkan halaman deteksi.
    public function index(Request $request, AnalysisQuotaService $quota): View
    {
        $user = $request->user();
        $trialId = $user ? null : $request->cookie(self::TRIAL_COOKIE);
        if (! $user && ! Str::isUuid($trialId)) {
            $trialId = (string) Str::uuid();
            Cookie::queue(Cookie::forever(self::TRIAL_COOKIE, $trialId));
        }

        return view('deteksi.index', [
            'quota' => $user ? $quota->snapshot($user) : null,
            'trialUsed' => $trialId !== null && Cache::has('guest-trial:'.$trialId),
        ]);
    }

    private function validatedImage(Request $request): UploadedFile
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

        return $validated['image'];
    }

    public function trial(Request $request, LocalAiDetector $detector): JsonResponse
    {
        $trialId = $request->cookie(self::TRIAL_COOKIE);
        if (! Str::isUuid($trialId)) {
            return response()->json(['message' => 'Muat ulang halaman Deteksi Gambar untuk memulai uji coba.'], 409);
        }

        $trialKey = 'guest-trial:'.$trialId;
        $result = Cache::lock($trialKey.':running', 120)->get(function () use ($request, $detector, $trialKey): JsonResponse {
            if (Cache::has($trialKey)) {
                return response()->json([
                    'message' => 'Uji coba gratis sudah digunakan. Masuk atau daftar untuk melanjutkan.',
                    'code' => 'guest_trial_used',
                ], 403);
            }

            $file = $this->validatedImage($request);
            $disk = Storage::disk(self::STORAGE_DISK);
            $storedPath = null;

            try {
                $storedPath = $file->store('guest-trials', self::STORAGE_DISK);
                if (! is_string($storedPath) || $storedPath === '') {
                    throw new RuntimeException('Private guest storage did not return a path.');
                }

                $name = basename(str_replace('\\', '/', $file->getClientOriginalName()));
                $analysis = new ImageAnalysis([
                    'original_filename' => preg_replace('/[\x00-\x1F\x7F]/', '', $name) ?: 'image',
                    'image_path' => $storedPath,
                    'mime_type' => $file->getMimeType(),
                ]);
                $prediction = $detector->classify($analysis);
                if (! Cache::forever($trialKey, true)) {
                    throw new RuntimeException('The guest trial usage could not be recorded.');
                }

                return response()->json([
                    'status' => 'completed',
                    'result_label' => $prediction['label'],
                    'confidence_score' => $prediction['score'],
                    'prediction_details' => $prediction['details'],
                ], 201);
            } catch (ImageClassificationException $exception) {
                Log::warning('Guest image classification failed.', $exception->diagnosticContext());

                return response()->json(['message' => $exception->getMessage()], $exception->httpStatus);
            } catch (Throwable $exception) {
                Log::warning('Guest image trial could not be completed.', ['exception_class' => $exception::class]);

                return response()->json(['message' => 'Gambar belum dapat diperiksa. Silakan coba kembali.'], 500);
            } finally {
                if (is_string($storedPath) && $storedPath !== '') {
                    try {
                        if (! $disk->delete($storedPath)) {
                            Log::warning('Failed to delete a temporary guest image.');
                        }
                    } catch (Throwable $exception) {
                        Log::warning('Failed to delete a temporary guest image.', ['exception_class' => $exception::class]);
                    }
                }
            }
        });

        return $result ?: response()->json(['message' => 'Uji coba sedang diproses. Tunggu sebentar.'], 409);
    }

    public function store(Request $request, AnalysisQuotaService $quota): JsonResponse
    {
        $file = $this->validatedImage($request);
        $user = $request->user();
        $storedPath = null;

        try {
            $storedPath = $file->store('users/'.$user->getKey(), self::STORAGE_DISK);

            if (! is_string($storedPath) || $storedPath === '') {
                throw new RuntimeException('Private image storage did not return a path.');
            }

            $originalName = basename(str_replace('\\', '/', $file->getClientOriginalName()));
            $originalName = preg_replace('/[\x00-\x1F\x7F]/', '', $originalName) ?: 'image';

            [$analysis, $quotaSnapshot] = DB::transaction(function () use ($file, $originalName, $storedPath, $user, $quota): array {
                $lockedUser = User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();
                $analysis = new ImageAnalysis([
                    'original_filename' => Str::limit($originalName, 255, ''),
                    'image_path' => $storedPath,
                    'mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                ]);
                $analysis->status = 'pending';

                $lockedUser->imageAnalyses()->save($analysis);
                $quotaSnapshot = $quota->reserve($lockedUser, $analysis);

                return [$analysis, $quotaSnapshot];
            });
        } catch (AnalysisQuotaExceededException $exception) {
            if (is_string($storedPath) && $storedPath !== '') {
                try {
                    Storage::disk(self::STORAGE_DISK)->delete($storedPath);
                } catch (Throwable $cleanupException) {
                    Log::error('Unable to clean up an upload rejected by the analysis quota.', [
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

            return response()->json([
                'message' => $exception->getMessage(),
                'quota' => $exception->quota,
            ], 429);
        } catch (Throwable $exception) {
            if (is_string($storedPath) && $storedPath !== '') {
                try {
                    Storage::disk(self::STORAGE_DISK)->delete($storedPath);
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
            ClassifyImageAnalysis::dispatch($analysis->getKey())
                ->onQueue($quotaSnapshot['premium'] ? 'premium' : 'default');
        } catch (Throwable $exception) {
            DB::transaction(function () use ($analysis, $quota): void {
                $quota->releaseReservation($analysis);
                $analysis->delete();
            });

            try {
                Storage::disk(self::STORAGE_DISK)->delete($storedPath);
            } catch (Throwable $cleanupException) {
                Log::error('Unable to clean up an analysis that could not be queued.', [
                    ...ImageClassificationException::contextFor(
                        $cleanupException,
                        'controller',
                        'cleanup_failure',
                        500,
                        null,
                    ),
                ]);
            }

            Log::error('Unable to enqueue image analysis.', [
                ...ImageClassificationException::contextFor($exception, 'controller', 'queue_failure', 500, null),
            ]);

            return response()->json([
                'message' => 'Analisis belum dapat dimulai. Silakan coba kembali.',
            ], 503);
        }

        return response()->json([
            'id' => $analysis->getKey(),
            'status' => $analysis->status,
            'status_url' => route('checker.analyses.show', $analysis->getKey()),
            'quota' => $quotaSnapshot,
            'message' => 'Gambar diterima dan masuk antrean analisis.',
        ], 202);
    }

    public function show(Request $request, int $analysisId, AnalysisQuotaService $quota): JsonResponse
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
            'prediction_details' => $analysis->prediction_details,
            'created_at' => $analysis->created_at,
            'quota' => $quota->snapshot($request->user()),
        ]);
    }

    public function image(Request $request, int $analysisId): StreamedResponse
    {
        $analysis = $request->user()->imageAnalyses()->findOrFail($analysisId);
        $disk = Storage::disk(self::STORAGE_DISK);

        if (! is_string($analysis->image_path) || ! $disk->exists($analysis->image_path)) {
            abort(404);
        }

        return $disk->download($analysis->image_path, 'image', [
            'Content-Type' => $analysis->mime_type,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
