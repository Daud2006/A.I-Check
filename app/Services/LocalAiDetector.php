<?php

namespace App\Services;

use App\Exceptions\ImageClassificationException;
use App\Models\ImageAnalysis;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use JsonException;
use RuntimeException;
use Throwable;

class LocalAiDetector
{
    private const MODEL_ID = 'dima806/ai_vs_human_generated_image_detection';

    private const STORAGE_DISK = 'image-analyses';

    private const TIMEOUT_SECONDS = 60;

    private const WORKER_INFERENCE_TIMEOUT_SECONDS = 50;

    private const WORKER_CONNECT_TIMEOUT_SECONDS = 2;

    private const BROWSER_REQUEST_TIMEOUT_SECONDS = 75;

    /**
     * @return array{label: 'artificial'|'human', score: float, model: string}
     */
    public function classify(ImageAnalysis $analysis): array
    {
        if (! config('services.local_ai_worker.enabled', false)) {
            return $this->classifyWithSubprocess($analysis);
        }

        try {
            $this->assertWorkerReady();
        } catch (ImageClassificationException $exception) {
            if (! config('services.local_ai_worker.subprocess_fallback', false)) {
                throw $exception;
            }

            Log::warning('Local AI worker is unavailable; using explicitly enabled subprocess fallback.', [
                ...$exception->diagnosticContext(),
            ]);

            return $this->classifyWithSubprocess($analysis);
        }

        return $this->classifyWithWorker($analysis);
    }

    /**
     * @return array{label: 'artificial'|'human', score: float, model: string}
     */
    private function classifyWithSubprocess(ImageAnalysis $analysis): array
    {
        $stage = 'configuration';

        try {
            $python = $this->pythonExecutable();
            $script = base_path('ai-service/predict.py');
            $modelWeights = base_path('ai-service/model/model.safetensors');

            if (! is_file($script) || ! is_file($modelWeights)) {
                throw new RuntimeException('Local model files are unavailable.');
            }

            $stage = 'request_setup';
            $imagePath = $this->privateImagePath($analysis);

            $stage = 'http_request';
            $result = Process::path(base_path())
                ->timeout(self::TIMEOUT_SECONDS)
                ->run([$python, $script, $imagePath, '--json']);

            if (! $result->successful()) {
                Log::warning('Local Python inference process failed.', [
                    'stage' => $stage,
                    'process_exit_code' => $result->exitCode(),
                    'python_error_type' => $this->pythonErrorType($result->errorOutput()),
                ]);

                throw new ImageClassificationException(
                    'Model analisis lokal belum dapat memproses gambar. Silakan coba kembali.',
                    503,
                    new RuntimeException('Local inference process returned a non-zero exit code.'),
                    'local_process_failed',
                    stage: $stage,
                );
            }

            $stage = 'response_decode';
            try {
                $prediction = json_decode($result->output(), true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                throw new ImageClassificationException(
                    'Hasil analisis AI lokal tidak dapat diverifikasi. Silakan coba kembali.',
                    502,
                    $exception,
                    'invalid_local_response',
                    stage: $stage,
                );
            }

            $stage = 'prediction_parse';
            if (
                ! is_array($prediction)
                || ! isset($prediction['label'], $prediction['score'], $prediction['model'], $prediction['inference_time_ms'])
                || ! is_string($prediction['label'])
                || ! in_array($prediction['label'], ['human', 'ai_generated'], true)
                || (! is_float($prediction['score']) && ! is_int($prediction['score']))
                || ! is_finite((float) $prediction['score'])
                || $prediction['score'] < 0
                || $prediction['score'] > 1
                || $prediction['model'] !== self::MODEL_ID
                || (! is_float($prediction['inference_time_ms']) && ! is_int($prediction['inference_time_ms']))
                || ! is_finite((float) $prediction['inference_time_ms'])
                || $prediction['inference_time_ms'] < 0
            ) {
                throw new ImageClassificationException(
                    'Hasil analisis AI lokal tidak dapat diverifikasi. Silakan coba kembali.',
                    502,
                    failureType: 'invalid_local_prediction',
                    stage: $stage,
                );
            }

            return [
                'label' => $prediction['label'] === 'ai_generated' ? 'artificial' : 'human',
                'score' => round((float) $prediction['score'], 6),
                'model' => self::MODEL_ID,
            ];
        } catch (ImageClassificationException $exception) {
            throw $exception;
        } catch (ProcessTimedOutException $exception) {
            throw new ImageClassificationException(
                'Analisis gambar memerlukan waktu terlalu lama. Silakan coba kembali.',
                504,
                $exception,
                'local_inference_timeout',
                stage: $stage,
            );
        } catch (Throwable $exception) {
            $status = $stage === 'configuration' ? 503 : 500;

            throw new ImageClassificationException(
                'Analisis gambar belum dapat diselesaikan. Silakan coba kembali.',
                $status,
                $exception,
                $stage === 'configuration' ? 'local_model_unavailable' : 'local_inference_error',
                stage: $stage,
            );
        }
    }

    private function pythonErrorType(string $errorOutput): ?string
    {
        if (
            preg_match(
                '/(?:^|\R)run_failed=true error_type=([A-Za-z][A-Za-z0-9_]*)\s*(?:\R|$)/',
                $errorOutput,
                $matches,
            ) !== 1
        ) {
            return null;
        }

        return $matches[1];
    }

    private function assertWorkerReady(): void
    {
        $stage = 'worker_health';

        try {
            $configuration = $this->workerConfiguration();
            $response = Http::withToken($configuration['secret'])
                ->acceptJson()
                ->withOptions([
                    'allow_redirects' => false,
                    'proxy' => ['http' => null, 'https' => null, 'no' => '127.0.0.1'],
                ])
                ->connectTimeout($configuration['connect_timeout'])
                ->timeout($configuration['health_timeout'])
                ->get($configuration['url'].'/health');
        } catch (ImageClassificationException $exception) {
            throw $exception;
        } catch (ConnectionException $exception) {
            throw new ImageClassificationException(
                'Worker AI lokal belum siap. Silakan coba kembali.',
                503,
                $exception,
                'local_worker_unavailable',
                stage: $stage,
            );
        } catch (Throwable $exception) {
            throw new ImageClassificationException(
                'Worker AI lokal belum dapat digunakan. Silakan coba kembali.',
                503,
                $exception,
                'local_worker_unavailable',
                stage: $stage,
            );
        }

        if (! $response->successful()) {
            throw new ImageClassificationException(
                'Worker AI lokal belum siap. Silakan coba kembali.',
                503,
                failureType: 'local_worker_unavailable',
                upstreamStatus: $response->status(),
                stage: $stage,
            );
        }

        try {
            $health = $response->json();
        } catch (Throwable $exception) {
            throw new ImageClassificationException(
                'Status worker AI lokal tidak dapat diverifikasi. Silakan coba kembali.',
                502,
                $exception,
                'invalid_local_worker_health',
                $response->status(),
                $stage,
            );
        }

        if (
            ! is_array($health)
            || ($health['status'] ?? null) !== 'ready'
            || ($health['model'] ?? null) !== self::MODEL_ID
        ) {
            throw new ImageClassificationException(
                'Status worker AI lokal tidak dapat diverifikasi. Silakan coba kembali.',
                502,
                failureType: 'invalid_local_worker_health',
                upstreamStatus: $response->status(),
                stage: $stage,
            );
        }
    }

    /**
     * @return array{label: 'artificial'|'human', score: float, model: string}
     */
    private function classifyWithWorker(ImageAnalysis $analysis): array
    {
        $stage = 'request_setup';

        try {
            $configuration = $this->workerConfiguration();
            $imagePath = $this->privateImagePath($analysis);

            $stage = 'worker_inference';
            try {
                $response = Http::withToken($configuration['secret'])
                    ->acceptJson()
                    ->withOptions([
                        'allow_redirects' => false,
                        'proxy' => ['http' => null, 'https' => null, 'no' => '127.0.0.1'],
                    ])
                    ->connectTimeout($configuration['connect_timeout'])
                    ->timeout($configuration['timeout'])
                    ->post($configuration['url'].'/infer', ['path' => $imagePath]);
            } catch (ConnectionException $exception) {
                throw new ImageClassificationException(
                    'Analisis gambar memerlukan waktu terlalu lama atau worker AI terputus. Silakan coba kembali.',
                    504,
                    $exception,
                    'local_worker_inference_timeout',
                    stage: $stage,
                );
            }

            if (! $response->successful()) {
                $applicationStatus = match ($response->status()) {
                    413, 422 => 422,
                    429 => 503,
                    504 => 504,
                    default => 503,
                };
                $safeMessage = match ($applicationStatus) {
                    422 => 'Gambar tidak dapat diproses. Pastikan gambar valid dan memenuhi batas ukuran.',
                    504 => 'Analisis gambar memerlukan waktu terlalu lama. Silakan coba kembali.',
                    default => 'Worker AI lokal gagal memproses gambar. Silakan coba kembali.',
                };

                throw new ImageClassificationException(
                    $safeMessage,
                    $applicationStatus,
                    failureType: 'local_worker_inference_failed',
                    upstreamStatus: $response->status(),
                    stage: $stage,
                );
            }

            $stage = 'response_decode';
            try {
                $prediction = $response->json();
            } catch (Throwable $exception) {
                throw new ImageClassificationException(
                    'Hasil analisis AI lokal tidak dapat diverifikasi. Silakan coba kembali.',
                    502,
                    $exception,
                    'invalid_local_response',
                    $response->status(),
                    $stage,
                );
            }

            $stage = 'prediction_parse';
            if (
                ! is_array($prediction)
                || ! isset($prediction['label'], $prediction['score'], $prediction['model'], $prediction['inference_time_ms'])
                || ! is_string($prediction['label'])
                || ! in_array($prediction['label'], ['human', 'ai_generated'], true)
                || (! is_float($prediction['score']) && ! is_int($prediction['score']))
                || ! is_finite((float) $prediction['score'])
                || $prediction['score'] < 0
                || $prediction['score'] > 1
                || $prediction['model'] !== self::MODEL_ID
                || (! is_float($prediction['inference_time_ms']) && ! is_int($prediction['inference_time_ms']))
                || ! is_finite((float) $prediction['inference_time_ms'])
                || $prediction['inference_time_ms'] < 0
            ) {
                throw new ImageClassificationException(
                    'Hasil analisis AI lokal tidak dapat diverifikasi. Silakan coba kembali.',
                    502,
                    failureType: 'invalid_local_prediction',
                    upstreamStatus: $response->status(),
                    stage: $stage,
                );
            }

            return [
                'label' => $prediction['label'] === 'ai_generated' ? 'artificial' : 'human',
                'score' => round((float) $prediction['score'], 6),
                'model' => self::MODEL_ID,
            ];
        } catch (ImageClassificationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new ImageClassificationException(
                'Analisis gambar belum dapat diselesaikan. Silakan coba kembali.',
                500,
                $exception,
                'local_worker_error',
                stage: $stage,
            );
        }
    }

    /**
     * @return array{url: string, secret: string, connect_timeout: int, health_timeout: int, timeout: int}
     */
    private function workerConfiguration(): array
    {
        $url = config('services.local_ai_worker.url');
        $secret = config('services.local_ai_worker.secret');
        $connectTimeout = config('services.local_ai_worker.connect_timeout', self::WORKER_CONNECT_TIMEOUT_SECONDS);
        $healthTimeout = config('services.local_ai_worker.health_timeout', 3);
        $timeout = config('services.local_ai_worker.timeout', self::WORKER_INFERENCE_TIMEOUT_SECONDS);
        $urlParts = is_string($url) ? parse_url($url) : false;

        if (
            ! is_array($urlParts)
            || ($urlParts['scheme'] ?? null) !== 'http'
            || strtolower((string) ($urlParts['host'] ?? '')) !== '127.0.0.1'
            || isset($urlParts['user'])
            || isset($urlParts['pass'])
            || isset($urlParts['query'])
            || isset($urlParts['fragment'])
            || (isset($urlParts['port']) && ($urlParts['port'] < 1 || $urlParts['port'] > 65535))
            || (isset($urlParts['path']) && trim($urlParts['path'], '/') !== '')
            || ! is_string($secret)
            || strlen($secret) < 32
            || ! is_numeric($connectTimeout)
            || (int) $connectTimeout < 1
            || ! is_numeric($healthTimeout)
            || (int) $healthTimeout < 1
            || (int) $healthTimeout + self::TIMEOUT_SECONDS >= self::BROWSER_REQUEST_TIMEOUT_SECONDS
            || ! is_numeric($timeout)
            || (int) $timeout < 1
            || (int) $timeout >= self::BROWSER_REQUEST_TIMEOUT_SECONDS
        ) {
            throw new ImageClassificationException(
                'Konfigurasi worker AI lokal belum valid. Silakan hubungi administrator.',
                503,
                failureType: 'invalid_local_worker_configuration',
                stage: 'configuration',
            );
        }

        return [
            'url' => rtrim($url, '/'),
            'secret' => $secret,
            'connect_timeout' => (int) $connectTimeout,
            'health_timeout' => (int) $healthTimeout,
            'timeout' => (int) $timeout,
        ];
    }

    private function pythonExecutable(): string
    {
        $relativePath = PHP_OS_FAMILY === 'Windows'
            ? 'ai-service/.venv/Scripts/python.exe'
            : 'ai-service/.venv/bin/python';
        $executable = base_path($relativePath);

        if (! is_file($executable)) {
            throw new RuntimeException('The isolated Python executable is unavailable.');
        }

        return $executable;
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
