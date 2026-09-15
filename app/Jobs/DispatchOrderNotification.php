<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\NotificationEvent;
use App\Mail\OrderNotificationMail;
use App\Models\NotificationLog;
use App\Models\NotificationTemplate;
use App\Models\Order;
use App\Models\SmsConsent;
use App\Sms\SmsGateway;
use App\Support\Cents;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Renders and sends one lifecycle event's notification on every active channel (guideline ch. 6, S06).
 * Dedupe (DoD: a retried job or duplicate trigger sends a message only once) comes from checking
 * App\Models\NotificationLog *inside* a per-(order, event, channel) lock, not from the queue's own
 * retry mechanics — a transient carrier failure leaves no log row, so the job's own retry can still
 * try again; only an already-*succeeded* send is skipped.
 */
final class DispatchOrderNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [10, 60, 300, 900];

    public function __construct(
        public readonly int $orderId,
        public readonly string $event,
    ) {}

    public function handle(SmsGateway $sms): void
    {
        $order = Order::query()->with('deliverySlot')->findOrFail($this->orderId);
        $event = NotificationEvent::from($this->event);

        foreach (['sms', 'email'] as $channel) {
            Cache::lock("notify:{$order->id}:{$this->event}:{$channel}", 60)->block(30, function () use ($order, $event, $channel, $sms): void {
                $this->sendOnce($order, $event, $channel, $sms);
            });
        }
    }

    private function sendOnce(Order $order, NotificationEvent $event, string $channel, SmsGateway $sms): void
    {
        if (NotificationLog::query()->where('order_id', $order->id)->where('event', $event)->where('channel', $channel)->exists()) {
            return;
        }

        $template = NotificationTemplate::query()->active()->where('event', $event)->where('channel', $channel)->first();
        if ($template === null || ! $this->allowed($order, $channel)) {
            return;
        }

        $placeholders = $this->placeholders($order);
        $body = $template->render($placeholders);
        $key = $order->idempotencyKey("notify:{$event->value}:{$channel}");

        $succeeded = $channel === 'sms'
            ? $sms->send($order->customer_phone, $body, $key)->succeeded
            : $this->sendEmail($order, $template->renderSubject($placeholders) ?? $event->label(), $body);

        if (! $succeeded) {
            Log::warning('Order notification failed to send', ['order' => $order->number, 'event' => $event->value, 'channel' => $channel]);

            return;
        }

        NotificationLog::query()->create(['order_id' => $order->id, 'event' => $event, 'channel' => $channel]);
    }

    private function sendEmail(Order $order, string $subject, string $body): bool
    {
        try {
            Mail::to($order->customer_email)->send(new OrderNotificationMail($subject, $body));

            return true;
        } catch (Throwable $e) {
            Log::warning('Order notification email failed', ['order' => $order->number, 'error' => $e->getMessage()]);

            return false;
        }
    }

    /** Transactional messages need no separate consent — only a STOP reply blocks every future SMS. */
    private function allowed(Order $order, string $channel): bool
    {
        if ($channel !== 'sms') {
            return true;
        }

        return SmsConsent::query()->find($order->customer_phone)?->canReceiveSms() ?? true;
    }

    /** @return array<string, string> */
    private function placeholders(Order $order): array
    {
        return [
            'customer_name' => $order->customer_name,
            'order_number' => (string) $order->number,
            'tracking_url' => route('orders.show', ['order' => $order, 'token' => $order->issueFreshAccessToken()]),
            'otp' => (string) $order->delivery_otp,
            'delivery_window' => $order->deliverySlot?->label() ?? '',
            'balance_due' => Cents::format($order->balance_due_cents),
            'refund_amount' => Cents::format($order->refunded_cents),
            'pickup_deadline_hours' => (string) config('catchweight.pickup_writeoff_hours'),
            'carrier' => (string) $order->shipping_carrier,
            'carrier_tracking_number' => (string) $order->tracking_number,
            'carrier_tracking_url' => (string) $order->tracking_url,
        ];
    }

    public function failed(Throwable $e): void
    {
        Log::critical('Order notification job failed after retries', ['order_id' => $this->orderId, 'event' => $this->event, 'error' => $e->getMessage()]);
    }
}
