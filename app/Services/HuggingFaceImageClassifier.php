<?php

namespace App\Services;

use App\Exceptions\ImageClassificationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class HuggingFaceImageClassifier
{
    private const MODEL_ID = 'Organika/sdxl-detector';

    private const API_URL = 'https://router.huggingface.co/hf-inference/models/';

    /**
     * @return array{label: string, score: float, model: string}
     */
    public function classify(string $imageBytes): array
    {
        $stage = 'configuration';
        $response = null;

        try {
            $token = config('services.huggingface.token');

            if (! is_string($token) || trim($token) === '') {
                throw new ImageClassificationException(
                    'Analisis AI belum dapat dijalankan karena layanan belum dikonfigurasi.',
                    503,
                    failureType: 'configuration_missing',
                    stage: $stage,
                );
            }

            $token = trim($token);
            $model = config('services.huggingface.image_model');

            if ($model !== self::MODEL_ID) {
                throw new ImageClassificationException(
                    'Konfigurasi model analisis tidak valid.',
                    503,
                    failureType: 'configuration_invalid',
                    stage: $stage,
                );
            }

            if ($imageBytes === '') {
                throw new ImageClassificationException(
                    'Layanan analisis AI tidak dapat membaca gambar.',
                    failureType: 'invalid_input',
                    stage: 'request_setup',
                );
            }

            $timeout = max(5, min(90, (int) config('services.huggingface.timeout', 60)));

            $stage = 'request_setup';
            $request = Http::acceptJson()
                ->withToken($token)
                ->timeout($timeout)
                ->withBody($imageBytes, 'application/octet-stream');

            $stage = 'http_request';
            $response = $request->post(self::API_URL.$model);

            $stage = 'response_decode';
            if (! $response->successful()) {
                if ($response->status() === 402) {
                    throw new ImageClassificationException(
                        'Layanan analisis AI mengalami masalah pembayaran atau akses provider. Administrator perlu memeriksa akun Hugging Face dan pengaturan billing/kredit; penyebab spesifik belum dapat dipastikan.',
                        402,
                        failureType: 'upstream_payment_or_credits',
                        upstreamStatus: $response->status(),
                        stage: $stage,
                    );
                }

                if (in_array($response->status(), [401, 403], true)) {
                    throw new ImageClassificationException(
                        'Layanan analisis AI belum dapat diakses. Administrator perlu memeriksa konfigurasi akses Hugging Face.',
                        503,
                        failureType: 'upstream_authentication',
                        upstreamStatus: $response->status(),
                        stage: $stage,
                    );
                }

                if ($response->status() === 404) {
                    throw new ImageClassificationException(
                        'Model atau provider analisis AI tidak tersedia saat ini.',
                        503,
                        failureType: 'upstream_model_unavailable',
                        upstreamStatus: $response->status(),
                        stage: $stage,
                    );
                }

                if ($response->status() === 429) {
                    throw new ImageClassificationException(
                        'Layanan analisis AI sedang sibuk. Silakan coba kembali.',
                        503,
                        failureType: 'upstream_rate_limited',
                        upstreamStatus: $response->status(),
                        stage: $stage,
                    );
                }

                if ($response->status() === 503) {
                    throw new ImageClassificationException(
                        'Model AI sedang disiapkan. Silakan coba beberapa saat lagi.',
                        503,
                        failureType: 'upstream_unavailable',
                        upstreamStatus: $response->status(),
                        stage: $stage,
                    );
                }

                throw new ImageClassificationException(
                    'Layanan analisis AI gagal memproses gambar. Silakan coba kembali.',
                    in_array($response->status(), [400, 422], true) ? 422 : 502,
                    failureType: 'upstream_http_error',
                    upstreamStatus: $response->status(),
                    stage: $stage,
                );
            }

            $predictions = $response->json();

            if (! is_array($predictions) || ! array_is_list($predictions) || $predictions === []) {
                throw new ImageClassificationException(
                    'Layanan analisis AI mengirim hasil yang tidak dapat diverifikasi.',
                    failureType: 'invalid_response_body',
                    upstreamStatus: $response->status(),
                    stage: $stage,
                );
            }

            $stage = 'prediction_parse';
            $bestPrediction = null;

            foreach ($predictions as $prediction) {
                if (
                    ! is_array($prediction)
                    || ! isset($prediction['label'], $prediction['score'])
                    || ! is_string($prediction['label'])
                    || ! in_array($prediction['label'], ['artificial', 'human'], true)
                    || (! is_float($prediction['score']) && ! is_int($prediction['score']))
                    || ! is_finite((float) $prediction['score'])
                    || $prediction['score'] < 0
                    || $prediction['score'] > 1
                ) {
                    throw new ImageClassificationException(
                        'Layanan analisis AI mengirim hasil yang tidak dapat diverifikasi.',
                        failureType: 'invalid_prediction',
                        upstreamStatus: $response->status(),
                        stage: $stage,
                    );
                }

                if ($bestPrediction === null || $prediction['score'] > $bestPrediction['score']) {
                    $bestPrediction = $prediction;
                }
            }

            return [
                'label' => $bestPrediction['label'],
                'score' => round((float) $bestPrediction['score'], 6),
                'model' => $model,
            ];
        } catch (ConnectionException $exception) {
            $failureType = preg_match('/timed?\s*out|timeout/i', $exception->getMessage()) === 1
                ? 'timeout'
                : 'connection_error';

            throw new ImageClassificationException(
                'Layanan analisis AI tidak merespons. Silakan coba kembali.',
                502,
                $exception,
                $failureType,
                stage: $stage,
            );
        } catch (ImageClassificationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new ImageClassificationException(
                'Analisis belum dapat diselesaikan. Silakan coba kembali.',
                500,
                $exception,
                'internal_error',
                $response?->status(),
                $stage,
            );
        }
    }
}
