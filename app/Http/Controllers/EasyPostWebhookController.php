<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Shipping\RecordTrackingUpdate;
use App\Shipping\ShippingGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

final class EasyPostWebhookController extends Controller
{
    public function __invoke(Request $request, ShippingGateway $shipping, RecordTrackingUpdate $recordTrackingUpdate): JsonResponse
    {
        try {
            $update = $shipping->parseTrackingWebhook($request->getContent(), (string) $request->header('X-Hmac-Signature'));
        } catch (RuntimeException) {
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        $recordTrackingUpdate->handle($update);

        return response()->json(['result' => 'ok']);
    }
}
