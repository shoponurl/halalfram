<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Payments\HandleStripeWebhook;
use App\Payments\Exceptions\InvalidWebhookSignature;
use App\Payments\Exceptions\PaymentsNotConfigured;
use App\Payments\PaymentGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class StripeWebhookController extends Controller
{
    public function __invoke(Request $request, PaymentGateway $payments, HandleStripeWebhook $handle): JsonResponse
    {
        try {
            // Gap 04: the raw body is only trusted after the signature checks out
            $event = $payments->parseWebhook($request->getContent(), (string) $request->header('Stripe-Signature'));
        } catch (InvalidWebhookSignature) {
            return response()->json(['error' => 'Invalid signature'], 400);
        } catch (PaymentsNotConfigured) {
            return response()->json(['error' => 'Online payments are not configured'], 503);
        }

        return response()->json(['result' => $handle->handle($event)]);
    }
}
