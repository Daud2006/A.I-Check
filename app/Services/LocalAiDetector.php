<?php

namespace App\Services;

use App\Exceptions\ImageClassificationException;
use App\Models\ImageAnalysis;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class LocalAiDetector
{
    private const STORAGE_DISK = 'image-analyses';

    private const MODEL_ID = 'simple_ai_image_detector';

    private const EXPECTED_ENSEMBLE = [
        'clip-probe',
        'commforensics224',
        'commforensics384',
        'haywoodsloan',
    ];

    private const EXPECTED_LICENSES = [
        'commforensics224' => 'mit',
        'commforensics384' => 'mit',
        'haywoodsloan' => 'apache-2.0',
    ];

    public function classify(ImageAnalysis $analysis): array
    {
        try {
            return $this->classifyWithLocalApi($analysis, $this->privateImagePath($analysis));
        } catch (ImageClassificationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::warning('Local AI classification failed.', [
                'exception_class' => $exception::class,
            ]);

            throw new ImageClassificationException(
                'Analisis gambar belum dapat diselesaikan. Silakan coba kembali.',
                500,
                $exception,
                'local_detector_error',
                stage: 'local_detector',
            );
        }
    }

    private function classifyWithLocalApi(ImageAnalysis $analysis, string $imagePath): array
    {
        $configuration = $this->detectorConfiguration();
        $health = $this->getLocalResponse(
            $configuration,
            '/health',
            $configuration['health_timeout'],
            'health',
        );

        if (
            ! $health->successful()
            || ! is_array($healthPayload = $health->json())
            || ($healthPayload['status'] ?? null) !== 'ok'
            || ($healthPayload['models_loaded'] ?? null) !== true
        ) {
            throw new ImageClassificationException(
                'API deteksi AI lokal belum siap memproses gambar.',
                503,
                failureType: 'local_detector_not_ready',
                upstreamStatus: $health->status(),
                stage: 'detector_health',
            );
        }

        $info = $this->getLocalResponse(
            $configuration,
            '/info',
            $configuration['health_timeout'],
            'info',
        );

        if (! $info->successful() || ! $this->isPermissiveEnsemble($info->json())) {
            throw new ImageClassificationException(
                'Konfigurasi ensemble API deteksi AI lokal tidak sesuai.',
                503,
                failureType: 'unexpected_detector_ensemble',
                upstreamStatus: $info->status(),
                stage: 'detector_info',
            );
        }

        $imageContents = file_get_contents($imagePath);
        if (! is_string($imageContents)) {
            throw new ImageClassificationException(
                'File gambar privat tidak dapat dibaca.',
                404,
                failureType: 'private_image_unreadable',
                stage: 'image_read',
            );
        }

        $filename = is_string($analysis->original_filename) && $analysis->original_filename !== ''
            ? basename(str_replace('\\', '/', $analysis->original_filename))
            : 'image';
        $mimeType = in_array($analysis->mime_type, ['image/jpeg', 'image/png', 'image/webp'], true)
            ? $analysis->mime_type
            : 'application/octet-stream';

        try {
            $response = $this->localRequest($configuration, $configuration['timeout'])
                ->attach('files', $imageContents, $filename, ['Content-Type' => $mimeType])
                ->post($configuration['url'].'/detect');
        } catch (ConnectionException $exception) {
            throw new ImageClassificationException(
                'API deteksi AI lokal tidak dapat menyelesaikan analisis.',
                503,
                $exception,
                'local_detector_request_failed',
                stage: 'detector_inference',
            );
        }

        if (! $response->successful()) {
            throw new ImageClassificationException(
                'API deteksi AI lokal tidak dapat menyelesaikan analisis.',
                $response->status() === 422 ? 422 : 503,
                failureType: 'local_detector_request_failed',
                upstreamStatus: $response->status(),
                stage: 'detector_inference',
            );
        }

        return $this->normalizePrediction($response->json(), $info->json(), 'detect_response');
    }

    private function getLocalResponse(
        array $configuration,
        string $path,
        int $timeout,
        string $stage,
    ): Response
    {
        try {
            return $this->localRequest($configuration, $timeout)
                ->get($configuration['url'].$path);
        } catch (ConnectionException $exception) {
            throw new ImageClassificationException(
                'API deteksi AI lokal tidak dapat dihubungi.',
                503,
                $exception,
                'local_detector_unavailable',
                stage: 'detector_'.$stage,
            );
        }
    }

    private function localRequest(array $configuration, int $timeout): PendingRequest
    {
        return Http::acceptJson()
            ->withOptions(['allow_redirects' => false])
            ->connectTimeout($configuration['connect_timeout'])
            ->timeout($timeout);
    }

    /**
     * @return array{url: string, connect_timeout: int, health_timeout: int, timeout: int}
     */
    private function detectorConfiguration(): array
    {
        $url = trim((string) config('services.ai_detector.url'));
        $parts = parse_url($url);
        $connectTimeout = (int) config('services.ai_detector.connect_timeout');
        $healthTimeout = (int) config('services.ai_detector.health_timeout');
        $timeout = (int) config('services.ai_detector.timeout');

        if (
            ! is_array($parts)
            || ($parts['scheme'] ?? null) !== 'http'
            || ($parts['host'] ?? null) !== '127.0.0.1'
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])
            || isset($parts['path'])
            || (isset($parts['port']) && ($parts['port'] < 1 || $parts['port'] > 65535))
            || $connectTimeout < 1
            || $connectTimeout > 15
            || $healthTimeout < 1
            || $healthTimeout > 15
            || $timeout < 1
            || $timeout >= 75
        ) {
            throw new ImageClassificationException(
                'Konfigurasi API deteksi AI lokal belum valid.',
                503,
                failureType: 'invalid_local_detector_configuration',
                stage: 'detector_configuration',
            );
        }

        return [
            'url' => $url,
            'connect_timeout' => $connectTimeout,
            'health_timeout' => $healthTimeout,
            'timeout' => $timeout,
        ];
    }

    private function isPermissiveEnsemble(mixed $info): bool
    {
        if (! is_array($info) || ! is_array($info['ensemble'] ?? null) || ! is_array($info['licences'] ?? null)) {
            return false;
        }

        $ensemble = $info['ensemble'];
        if (count($ensemble) !== count(self::EXPECTED_ENSEMBLE)) {
            return false;
        }

        sort($ensemble);
        if ($ensemble !== self::EXPECTED_ENSEMBLE) {
            return false;
        }

        foreach (self::EXPECTED_LICENSES as $backend => $license) {
            if (strtolower((string) ($info['licences'][$backend] ?? '')) !== $license) {
                return false;
            }
        }

        return is_string($info['version'] ?? null)
            && is_array($info['available_operating_points'] ?? null)
            && in_array('fpr5', $info['available_operating_points'], true);
    }

    private function normalizePrediction(mixed $payload, mixed $info, string $stage): array
    {
        $result = is_array($payload) && is_array($payload['results'] ?? null)
            ? ($payload['results'][0] ?? null)
            : null;

        if (
            ! is_array($payload)
            || ! is_array($payload['results'] ?? null)
            || count($payload['results']) !== 1
            || ! is_array($result)
            || ! in_array($result['verdict'] ?? null, ['AI-GENERATED', 'AI-EDITED', 'REAL', 'UNCERTAIN'], true)
            || ! $this->isProbability($result['p_ai'] ?? null)
            || ! $this->isProbability($result['threshold'] ?? null)
            || ! array_key_exists('low_threshold', $result)
            || ($result['low_threshold'] !== null && ! $this->isProbability($result['low_threshold']))
            || ! is_string($result['basis'] ?? null)
            || $result['basis'] === ''
            || ! is_array($result['per_model'] ?? null)
            || ! $this->isProbabilityMap($result['per_model'])
            || ! is_bool($result['metadata_says_ai'] ?? null)
            || (! is_string($result['metadata_generator'] ?? null) && ($result['metadata_generator'] ?? null) !== null)
            || ! is_bool($result['truncated'] ?? null)
            || ! in_array($payload['operating_point'] ?? null, ['fpr1', 'fpr5', 'fpr10', 'balanced'], true)
            || ! $this->isNonNegativeNumber($payload['elapsed_ms'] ?? null)
            || ! $this->isPermissiveEnsemble($info)
        ) {
            throw new ImageClassificationException(
                'Hasil deteksi AI tidak dapat diverifikasi.',
                502,
                failureType: 'invalid_local_model_prediction',
                stage: $stage,
            );
        }

        if ($result['low_threshold'] !== null && $result['low_threshold'] > $result['threshold']) {
            throw new ImageClassificationException(
                'Hasil deteksi AI tidak dapat diverifikasi.',
                502,
                failureType: 'invalid_local_model_prediction',
                stage: $stage,
            );
        }

        return [
            'label' => $result['verdict'],
            'score' => round((float) $result['p_ai'], 6),
            'model' => self::MODEL_ID,
            'details' => [
                'model_version' => $info['version'],
                'operating_point' => $payload['operating_point'],
                'threshold' => (float) $result['threshold'],
                'low_threshold' => $result['low_threshold'] === null
                    ? null
                    : (float) $result['low_threshold'],
                'basis' => $result['basis'],
                'per_model' => array_map('floatval', $result['per_model']),
                'metadata_says_ai' => $result['metadata_says_ai'],
                'metadata_generator' => $result['metadata_generator'],
                'truncated' => $result['truncated'],
                'elapsed_ms' => (float) $payload['elapsed_ms'],
            ],
        ];
    }

    private function isProbabilityMap(array $scores): bool
    {
        $models = array_keys($scores);
        sort($models);

        if ($models !== self::EXPECTED_ENSEMBLE) {
            return false;
        }

        foreach ($scores as $score) {
            if (! $this->isProbability($score)) {
                return false;
            }
        }

        return true;
    }

    private function isProbability(mixed $value): bool
    {
        return (is_float($value) || is_int($value))
            && is_finite((float) $value)
            && $value >= 0
            && $value <= 1;
    }

    private function isNonNegativeNumber(mixed $value): bool
    {
        return (is_float($value) || is_int($value))
            && is_finite((float) $value)
            && $value >= 0;
    }

    private function privateImagePath(ImageAnalysis $analysis): string
    {
        if (! is_string($analysis->image_path) || $analysis->image_path === '') {
            throw new ImageClassificationException(
                'File gambar privat tidak tersedia.',
                404,
                failureType: 'private_image_unavailable',
                stage: 'image_lookup',
            );
        }

        $disk = Storage::disk(self::STORAGE_DISK);

        if (! $disk->exists($analysis->image_path)) {
            throw new ImageClassificationException(
                'File gambar privat tidak tersedia.',
                404,
                failureType: 'private_image_unavailable',
                stage: 'image_lookup',
            );
        }

        $storageRoot = realpath($disk->path(''));
        $imagePath = realpath($disk->path($analysis->image_path));

        if ($storageRoot === false || $imagePath === false || ! is_file($imagePath)) {
            throw new ImageClassificationException(
                'File gambar privat tidak dapat dibaca.',
                404,
                failureType: 'private_image_unavailable',
                stage: 'image_lookup',
            );
        }

        $storageRoot = rtrim($storageRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
        $pathPrefixMatches = PHP_OS_FAMILY === 'Windows'
            ? str_starts_with(strtolower($imagePath), strtolower($storageRoot))
            : str_starts_with($imagePath, $storageRoot);

        if (! $pathPrefixMatches) {
            throw new ImageClassificationException(
                'File gambar privat tidak tersedia.',
                404,
                failureType: 'private_image_unavailable',
                stage: 'image_lookup',
            );
        }

        return $imagePath;
    }
}
