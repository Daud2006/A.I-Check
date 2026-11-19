<?php

namespace App\Http\Controllers;

use App\Services\MidtransNotificationProcessor;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use Throwable;

class MidtransWebhookController extends Controller
{
    public function __invoke(Request $request, MidtransNotificationProcessor $processor): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'order_id' => ['required', 'string', 'max:64'],
            'status_code' => ['required', 'string', 'max:10'],
            'gross_amount' => ['required', 'string', 'max:32'],
            'signature_key' => ['required', 'string', 'size:128', 'regex:/^[a-fA-F0-9]{128}$/'],
            'transaction_status' => ['required', 'string', 'max:32'],
            'transaction_id' => ['nullable', 'string', 'max:100'],
            'fraud_status' => ['nullable', 'string', 'max:32'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Invalid payment notification.'], 422);
        }

        $validated = $validator->validated();

        $serverKey = config('midtrans.server_key');

        if (! is_string($serverKey) || trim($serverKey) === '') {
            return response()->json(['message' => 'Payment notification verification is not configured.'], 503);
        }

        $signature = hash(
            'sha512',
            $validated['order_id'].$validated['status_code'].$validated['gross_amount'].$serverKey
        );

        if (! hash_equals($signature, strtolower($validated['signature_key']))) {
            Log::warning('Rejected Midtrans notification with invalid signature.');

            return response()->json(['message' => 'Invalid notification signature.'], 401);
        }

        try {
            $processor->process($validated);
        } catch (ModelNotFoundException) {
            return response()->json(['message' => 'Payment reference not found.'], 404);
        } catch (InvalidArgumentException $exception) {
            Log::warning('Rejected inconsistent Midtrans payment notification.', [
                'exception_class' => $exception::class,
            ]);

            return response()->json(['message' => 'Invalid payment notification.'], 422);
        } catch (Throwable $exception) {
            Log::error('Unable to process Midtrans payment notification.', [
                'exception_class' => $exception::class,
            ]);

            return response()->json(['message' => 'Payment notification could not be processed.'], 500);
        }

        return response()->json(['message' => 'Notification processed.']);
    }
}
