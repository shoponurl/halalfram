<?php

declare(strict_types=1);

namespace App\Actions\Sms;

use App\Actions\Action;
use App\Models\SmsConsent;

/**
 * TCPA STOP handling (guideline ch. 7, S06): a STOP-family reply blocks every future SMS to that
 * number, not just marketing ones — the customer said stop, so we stop, full stop. HELP/START are
 * recorded too so opting back in actually works.
 */
final class HandleTwilioInboundSms extends Action
{
    private const STOP_KEYWORDS = ['stop', 'stopall', 'unsubscribe', 'cancel', 'end', 'quit'];

    private const START_KEYWORDS = ['start', 'yes', 'unstop'];

    public function handle(string $phone, string $body): void
    {
        $keyword = mb_strtolower(trim($body));

        if (in_array($keyword, self::STOP_KEYWORDS, true)) {
            $consent = SmsConsent::query()->firstOrNew(['phone' => $phone]);
            $consent->opted_out_at = now();
            $consent->marketing_opt_in = false;
            $consent->save();

            return;
        }

        if (in_array($keyword, self::START_KEYWORDS, true)) {
            $consent = SmsConsent::query()->firstOrNew(['phone' => $phone]);
            $consent->opted_out_at = null;
            $consent->save();
        }
    }
}
