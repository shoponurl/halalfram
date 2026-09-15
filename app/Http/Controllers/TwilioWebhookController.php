<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Sms\HandleTwilioInboundSms;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Twilio\Security\RequestValidator;

/** Twilio's inbound-SMS webhook for STOP/START handling (guideline ch. 7, S06). */
final class TwilioWebhookController extends Controller
{
    public function __invoke(Request $request, HandleTwilioInboundSms $handle): Response
    {
        $token = (string) config('services.twilio.token');
        $signature = (string) $request->header('X-Twilio-Signature');
        $validator = new RequestValidator($token);

        if ($token === '' || ! $validator->validate($signature, $request->fullUrl(), $request->all())) {   // security-rules: allow — Twilio signs every POST param; validate() needs the full set
            return response('Invalid signature', 400);
        }

        $from = (string) $request->input('From', '');
        $body = (string) $request->input('Body', '');
        if ($from !== '') {
            $handle->handle($from, $body);
        }

        return response('<Response></Response>', 200)->header('Content-Type', 'text/xml');
    }
}
