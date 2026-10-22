<?php

namespace App\Http\Controllers;

use App\Exceptions\ImageClassificationException;
use App\Models\ImageAnalysis;
use App\Services\LocalAiDetector;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

    // Menampilkan halaman deteksi.
    public function index(): View
    {
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
        $storedPath = null;

        try {
            $storedPath = $file->store('users/'.$user->getKey(), self::STORAGE_DISK);

            if (! is_string($storedPath) || $storedPath === '') {
                throw new RuntimeException('Private image storage did not return a path.');
            }

            $originalName = basename(str_replace('\\', '/', $file->getClientOriginalName()));
            $originalName = preg_replace('/[\x00-\x1F\x7F]/', '', $originalName) ?: 'image';

            $analysis = DB::transaction(function () use ($file, $originalName, $storedPath, $user): ImageAnalysis {
                $analysis = new ImageAnalysis([
                    'original_filename' => Str::limit($originalName, 255, ''),
                    'image_path' => $storedPath,
                    'mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                ]);
                $analysis->status = 'pending';

                $user->imageAnalyses()->save($analysis);

                return $analysis;
            });
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
            $analysis->status = 'processing';
            $analysis->save();

            $prediction = $classifier->classify($analysis);

            $analysis->result_label = $prediction['label'];
            $analysis->confidence_score = $prediction['score'];
            $analysis->model_name = $prediction['model'];
            $analysis->status = 'completed';
            $analysis->save();
        } catch (ImageClassificationException $exception) {
            $persistedFailure = $this->markAnalysisFailed($analysis);

            Log::warning('Local AI image classification failed.', [
                ...$exception->diagnosticContext(),
            ]);

            return response()->json([
                'id' => $analysis->getKey(),
                'status' => $persistedFailure ? 'failed' : null,
                'message' => $exception->getMessage(),
            ], $exception->httpStatus);
        } catch (Throwable $exception) {
            $this->markAnalysisFailed($analysis);

            Log::error('Unable to complete image classification.', [
                ...ImageClassificationException::contextFor($exception, 'controller', 'internal_error', 500, null),
            ]);

            return response()->json([
                'id' => $analysis->getKey(),
                'message' => 'Analisis belum dapat diselesaikan. Silakan coba kembali.',
            ], 500);
        }

        return response()->json([
            'id' => $analysis->getKey(),
            'status' => $analysis->status,
            'result_label' => $analysis->result_label,
            'confidence_score' => (float) $analysis->confidence_score,
            'model_name' => $analysis->model_name,
            'message' => 'Analisis gambar selesai. Hasil merupakan prediksi model, bukan kepastian.',
        ], 201);
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

        return $disk->download($analysis->image_path, 'image', [
            'Content-Type' => $analysis->mime_type,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
