<?php

namespace App\Http\Controllers;

use App\Models\ImageAnalysis;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class HistoryController extends Controller
{
    private const STORAGE_DISK = 'image-analyses';

    public function index(Request $request)
    {
        $analyses = $request->user()
            ->imageAnalyses()
            ->latest('created_at')
            ->get()
            ->map(fn (ImageAnalysis $analysis): array => [
                'id' => $analysis->getKey(),
                'name' => $analysis->original_filename,
                'status' => $analysis->status,
                'label' => $analysis->result_label,
                'score' => $analysis->confidence_score === null
                    ? null
                    : (float) $analysis->confidence_score,
                'date' => $analysis->created_at?->toIso8601String(),
                'imageUrl' => $this->imageUrl($analysis),
            ])
            ->values();

        return view('riwayat.index', ['analyses' => $analyses]);
    }

    public function destroy(Request $request, int $analysisId): JsonResponse
    {
        $analysis = $request->user()->imageAnalyses()->findOrFail($analysisId);
        $imagePath = $analysis->image_path;

        DB::transaction(fn () => $analysis->delete());

        $cleanupComplete = $this->deleteImageIfUnreferenced($imagePath);

        return response()->json([
            'message' => $cleanupComplete
                ? 'Riwayat analisis berhasil dihapus.'
                : 'Riwayat dihapus, tetapi file gambar belum dapat dibersihkan.',
            'cleanup_complete' => $cleanupComplete,
        ]);
    }

    public function destroyAll(Request $request): JsonResponse
    {
        $user = $request->user();

        $imagePaths = DB::transaction(function () use ($user): array {
            $analyses = $user->imageAnalyses()->get();
            $paths = $analyses->pluck('image_path')->filter()->unique()->values()->all();

            $user->imageAnalyses()->delete();

            return $paths;
        });

        $cleanupComplete = true;
        foreach ($imagePaths as $imagePath) {
            $cleanupComplete = $this->deleteImageIfUnreferenced($imagePath) && $cleanupComplete;
        }

        return response()->json([
            'message' => $cleanupComplete
                ? 'Seluruh riwayat analisis berhasil dihapus.'
                : 'Riwayat dihapus, tetapi satu atau lebih file gambar belum dapat dibersihkan.',
            'cleanup_complete' => $cleanupComplete,
        ]);
    }

    private function imageUrl(ImageAnalysis $analysis): ?string
    {
        if (! is_string($analysis->image_path) || $analysis->image_path === '') {
            return null;
        }

        $disk = Storage::disk(self::STORAGE_DISK);
        $root = realpath($disk->path(''));
        $image = realpath($disk->path($analysis->image_path));

        if ($root === false || $image === false || ! is_file($image) || ! $this->isWithinRoot($root, $image)) {
            return null;
        }

        return route('checker.analyses.image', $analysis->getKey());
    }

    private function deleteImageIfUnreferenced(?string $imagePath): bool
    {
        if ($imagePath === null || $imagePath === '') {
            return true;
        }

        if (ImageAnalysis::query()->where('image_path', $imagePath)->exists()) {
            return true;
        }

        try {
            $disk = Storage::disk(self::STORAGE_DISK);
            if (! $disk->exists($imagePath)) {
                return true;
            }

            $root = realpath($disk->path(''));
            $image = realpath($disk->path($imagePath));

            if ($root === false || $image === false || ! is_file($image) || ! $this->isWithinRoot($root, $image)) {
                Log::warning('Unable to safely remove a private analysis image.');

                return false;
            }

            if (! $disk->delete($imagePath)) {
                Log::warning('Unable to remove an unreferenced private analysis image.');

                return false;
            }

            return true;
        } catch (Throwable $exception) {
            Log::warning('Unable to remove an unreferenced private analysis image.', [
                'exception_class' => $exception::class,
            ]);

            return false;
        }
    }

    private function isWithinRoot(string $root, string $path): bool
    {
        $root = rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;

        return PHP_OS_FAMILY === 'Windows'
            ? str_starts_with(strtolower($path), strtolower($root))
            : str_starts_with($path, $root);
    }
}
