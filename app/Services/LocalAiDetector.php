<?php

namespace App\Services;

use App\Exceptions\ImageClassificationException;
use App\Models\ImageAnalysis;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class LocalAiDetector
{
    private const STORAGE_DISK = 'image-analyses';

    private const MODEL_ID = 'simple_ai_image_detector';

    public function classify(ImageAnalysis $analysis): array
    {
        $stage = 'request_setup';
        $stream = null;

        try {
            $url = rtrim(
                (string) config('services.ai_detector.url', 'http://127.0.0.1:8000'),
                '/'
            );

            $timeout = (int) config('services.ai_detector.timeout', 90);
            $connectTimeout = (int) config('services.ai_detector.connect_timeout', 5);

            if (
                ! filter_var($url, FILTER_VALIDATE_URL)
                || ! is_numeric($timeout)
                || $timeout < 1
                || $timeout >= 75
                || $connectTimeout < 1
            ) {
                throw new ImageClassificationException(
                    'Konfigurasi layanan deteksi AI belum valid.',
                    503,
                    failureType: 'invalid_detector_configuration',
                    stage: $stage,
                );
            }

            $imagePath = $this->privateImagePath($analysis);

            $stream = fopen($imagePath, 'rb');

            if ($stream === false) {
                throw new RuntimeException('Unable to open the stored image.');
            }

            $stage = 'detector_request';

            try {
                $response = Http::acceptJson()
                    ->connectTimeout($connectTimeout)
                    ->timeout($timeout)
                    ->attach(
                        'files',
                        $stream,
                        basename($analysis->original_filename ?: $imagePath)
                    )
                    ->post($url.'/detect');
            } catch (ConnectionException $exception) {
                throw new ImageClassificationException(
                    'Layanan deteksi AI tidak dapat dihubungi atau membutuhkan waktu terlalu lama.',
                    503,
                    $exception,
                    'detector_unavailable',
                    stage: $stage,
                );
            }

            if (! $response->successful()) {
                throw new ImageClassificationException(
                    'Layanan deteksi AI gagal memproses gambar. Silakan coba kembali.',
                    $response->status() === 422 ? 422 : 503,
                    failureType: 'detector_request_failed',
                    upstreamStatus: $response->status(),
                    stage: $stage,
                );
            }

            $stage = 'response_parse';
            $payload = $response->json();

            if (
                ! is_array($payload)
                || ! isset($payload['results'])
                || ! is_array($payload['results'])
                || ! isset($payload['results'][0])
                || ! is_array($payload['results'][0])
            ) {
                throw new ImageClassificationException(
                    'Respons layanan deteksi AI tidak memiliki format yang valid.',
                    502,
                    failureType: 'invalid_detector_response',
                    upstreamStatus: $response->status(),
                    stage: $stage,
                );
            }

            $result = $payload['results'][0];

            $allowedVerdicts = [
                'AI-GENERATED',
                'AI-EDITED',
                'REAL',
                'UNCERTAIN',
            ];

            if (
                ! isset($result['verdict'], $result['p_ai'])
                || ! is_string($result['verdict'])
                || ! in_array($result['verdict'], $allowedVerdicts, true)
                || (! is_float($result['p_ai']) && ! is_int($result['p_ai']))
                || ! is_finite((float) $result['p_ai'])
                || $result['p_ai'] < 0
                || $result['p_ai'] > 1
                || (isset($result['error']) && $result['error'] !== null)
            ) {
                throw new ImageClassificationException(
                    'Hasil deteksi AI tidak dapat diverifikasi.',
                    502,
                    failureType: 'invalid_detector_prediction',
                    upstreamStatus: $response->status(),
                    stage: $stage,
                );
            }

            $evidence = [];
            if (array_key_exists('basis', $result)) {
                if (! is_string($result['basis'])) {
                    throw new ImageClassificationException(
                        'Hasil deteksi AI tidak dapat diverifikasi.',
                        502,
                        failureType: 'invalid_detector_evidence',
                        upstreamStatus: $response->status(),
                        stage: $stage,
                    );
                }
                $evidence['basis'] = $result['basis'];
            }

            foreach (['threshold', 'low_threshold'] as $probabilityField) {
                if (! array_key_exists($probabilityField, $result)) {
                    continue;
                }

                $probability = $result[$probabilityField];
                if (
                    $probability !== null
                    && ((! is_float($probability) && ! is_int($probability))
                        || ! is_finite((float) $probability)
                        || $probability < 0
                        || $probability > 1)
                ) {
                    throw new ImageClassificationException(
                        'Hasil deteksi AI tidak dapat diverifikasi.',
                        502,
                        failureType: 'invalid_detector_evidence',
                        upstreamStatus: $response->status(),
                        stage: $stage,
                    );
                }

                $evidence[$probabilityField] = $probability === null ? null : (float) $probability;
            }

            if (array_key_exists('per_model', $result)) {
                if (! is_array($result['per_model'])) {
                    throw new ImageClassificationException(
                        'Hasil deteksi AI tidak dapat diverifikasi.',
                        502,
                        failureType: 'invalid_detector_evidence',
                        upstreamStatus: $response->status(),
                        stage: $stage,
                    );
                }

                foreach ($result['per_model'] as $modelName => $modelScore) {
                    if (
                        ! is_string($modelName)
                        || (! is_float($modelScore) && ! is_int($modelScore))
                        || ! is_finite((float) $modelScore)
                        || $modelScore < 0
                        || $modelScore > 1
                    ) {
                        throw new ImageClassificationException(
                            'Hasil deteksi AI tidak dapat diverifikasi.',
                            502,
                            failureType: 'invalid_detector_evidence',
                            upstreamStatus: $response->status(),
                            stage: $stage,
                        );
                    }
                }

                $evidence['per_model'] = $result['per_model'];
            }

            if (array_key_exists('metadata_says_ai', $result)) {
                if (! is_bool($result['metadata_says_ai'])) {
                    throw new ImageClassificationException(
                        'Hasil deteksi AI tidak dapat diverifikasi.',
                        502,
                        failureType: 'invalid_detector_evidence',
                        upstreamStatus: $response->status(),
                        stage: $stage,
                    );
                }
                $evidence['metadata_says_ai'] = $result['metadata_says_ai'];
            }

            if (array_key_exists('metadata_generator', $result)) {
                if ($result['metadata_generator'] !== null && ! is_string($result['metadata_generator'])) {
                    throw new ImageClassificationException(
                        'Hasil deteksi AI tidak dapat diverifikasi.',
                        502,
                        failureType: 'invalid_detector_evidence',
                        upstreamStatus: $response->status(),
                        stage: $stage,
                    );
                }
                $evidence['metadata_generator'] = $result['metadata_generator'];
            }

            return [
                'label' => $result['verdict'],
                'score' => round((float) $result['p_ai'], 6),
                'model' => self::MODEL_ID,
                'evidence' => $evidence,
            ];
        } catch (ImageClassificationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::warning('AI detector integration failed.', [
                'stage' => $stage,
                'exception_class' => $exception::class,
            ]);

            throw new ImageClassificationException(
                'Analisis gambar belum dapat diselesaikan. Silakan coba kembali.',
                500,
                $exception,
                'detector_integration_error',
                stage: $stage,
            );
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    private function privateImagePath(ImageAnalysis $analysis): string
    {
        if (! is_string($analysis->image_path) || $analysis->image_path === '') {
            throw new RuntimeException('The analysis has no stored image path.');
        }

        $disk = Storage::disk(self::STORAGE_DISK);

        if (! $disk->exists($analysis->image_path)) {
            throw new RuntimeException('The private image file is unavailable.');
        }

        $storageRoot = realpath($disk->path(''));
        $imagePath = realpath($disk->path($analysis->image_path));

        if ($storageRoot === false || $imagePath === false || ! is_file($imagePath)) {
            throw new RuntimeException('The private image file could not be resolved.');
        }

        $storageRoot = rtrim($storageRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;

        $pathPrefixMatches = PHP_OS_FAMILY === 'Windows'
            ? str_starts_with(strtolower($imagePath), strtolower($storageRoot))
            : str_starts_with($imagePath, $storageRoot);

        if (! $pathPrefixMatches) {
            throw new RuntimeException('The private image path is outside its storage disk.');
        }

        return $imagePath;
    }
}
