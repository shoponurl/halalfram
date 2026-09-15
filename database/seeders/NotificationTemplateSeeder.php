<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\NotificationEvent;
use App\Models\NotificationTemplate;
use Illuminate\Database\Seeder;

/** Default copy for every lifecycle event (guideline task: a template manager, editable in Filament without a deploy). */
class NotificationTemplateSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->templates() as $row) {
            NotificationTemplate::query()->updateOrCreate(
                ['event' => $row['event'], 'channel' => $row['channel']],
                ['subject' => $row['subject'] ?? null, 'body' => $row['body'], 'active' => true],
            );
        }
    }

    /** @return list<array{event: NotificationEvent, channel: string, subject?: string, body: string}> */
    private function templates(): array
    {
        return [
            ['event' => NotificationEvent::OrderPlaced, 'channel' => 'sms', 'body' => 'Halal Brothers: order {{order_number}} received! Track it: {{tracking_url}}'],
            ['event' => NotificationEvent::OrderPlaced, 'channel' => 'email', 'subject' => 'Order {{order_number}} received', 'body' => "Hi {{customer_name}},\n\nThanks for your order {{order_number}}! We'll text and email you as it's cut, weighed and ready.\n\nTrack your order: {{tracking_url}}"],

            ['event' => NotificationEvent::PaymentAuthorized, 'channel' => 'sms', 'body' => "Halal Brothers: your card hold for order {{order_number}} is confirmed. We'll text you when it's ready."],
            ['event' => NotificationEvent::PaymentAuthorized, 'channel' => 'email', 'subject' => 'Order {{order_number}} confirmed', 'body' => "Hi {{customer_name}},\n\nYour payment hold for order {{order_number}} is confirmed. We'll let you know as soon as it's ready.\n\nTrack your order: {{tracking_url}}"],

            ['event' => NotificationEvent::ReadyForPickup, 'channel' => 'sms', 'body' => 'Halal Brothers: order {{order_number}} is ready for pickup! Details: {{tracking_url}}'],
            ['event' => NotificationEvent::ReadyForPickup, 'channel' => 'email', 'subject' => 'Order {{order_number}} is ready for pickup', 'body' => "Hi {{customer_name}},\n\nYour order {{order_number}} is cut, packed and ready for pickup at the shop.\n\nDetails: {{tracking_url}}"],

            ['event' => NotificationEvent::OutForDelivery, 'channel' => 'sms', 'body' => 'Halal Brothers: order {{order_number}} is out for delivery ({{delivery_window}}). Your handoff code is {{otp}} — give it to the driver.'],
            ['event' => NotificationEvent::OutForDelivery, 'channel' => 'email', 'subject' => 'Order {{order_number}} is out for delivery', 'body' => "Hi {{customer_name}},\n\nYour order {{order_number}} is on its way ({{delivery_window}}). Your handoff code is {{otp}} — give it to the driver, or ask them to snap a photo at the door.\n\nTrack your order: {{tracking_url}}"],

            ['event' => NotificationEvent::Delivered, 'channel' => 'sms', 'body' => 'Halal Brothers: order {{order_number}} was delivered. Enjoy!'],
            ['event' => NotificationEvent::Delivered, 'channel' => 'email', 'subject' => 'Order {{order_number}} delivered', 'body' => "Hi {{customer_name}},\n\nYour order {{order_number}} has been delivered. Thanks for shopping with us!\n\nReceipt: {{tracking_url}}"],

            ['event' => NotificationEvent::DeliveryFailed, 'channel' => 'sms', 'body' => "Halal Brothers: we couldn't deliver order {{order_number}} — no one was home. We'll be in touch to reschedule."],
            ['event' => NotificationEvent::DeliveryFailed, 'channel' => 'email', 'subject' => 'We missed you — order {{order_number}}', 'body' => "Hi {{customer_name}},\n\nOur driver couldn't complete delivery for order {{order_number}}. Your order is back at the shop — we'll reach out to reschedule.\n\nDetails: {{tracking_url}}"],

            ['event' => NotificationEvent::PickupReminder, 'channel' => 'sms', 'body' => "Halal Brothers: reminder — order {{order_number}} is still waiting for pickup. It'll be held for {{pickup_deadline_hours}} hours total."],
            ['event' => NotificationEvent::PickupReminder, 'channel' => 'email', 'subject' => 'Reminder: order {{order_number}} is waiting for pickup', 'body' => "Hi {{customer_name}},\n\nJust a reminder that order {{order_number}} is still waiting for pickup at the shop.\n\nDetails: {{tracking_url}}"],

            ['event' => NotificationEvent::BalanceDue, 'channel' => 'sms', 'body' => "Halal Brothers: order {{order_number}}'s final weight came in above the hold. Balance due: {{balance_due}}. Pay here: {{tracking_url}}"],
            ['event' => NotificationEvent::BalanceDue, 'channel' => 'email', 'subject' => 'Balance due for order {{order_number}}', 'body' => "Hi {{customer_name}},\n\nYour order {{order_number}}'s actual weight came in above the card hold. Balance due: {{balance_due}}.\n\nPay here: {{tracking_url}}"],

            ['event' => NotificationEvent::Refunded, 'channel' => 'sms', 'body' => 'Halal Brothers: a refund of {{refund_amount}} was issued for order {{order_number}}.'],
            ['event' => NotificationEvent::Refunded, 'channel' => 'email', 'subject' => 'Refund issued for order {{order_number}}', 'body' => "Hi {{customer_name}},\n\nA refund of {{refund_amount}} was issued for order {{order_number}}. It should appear on your statement in a few business days.\n\nDetails: {{tracking_url}}"],

            ['event' => NotificationEvent::Completed, 'channel' => 'email', 'subject' => 'Order {{order_number}} complete — thank you!', 'body' => "Hi {{customer_name}},\n\nOrder {{order_number}} is complete. Thanks for shopping with Halal Brothers!\n\nReceipt: {{tracking_url}}"],

            ['event' => NotificationEvent::Shipped, 'channel' => 'sms', 'body' => 'Halal Brothers: order {{order_number}} shipped via {{carrier}}, tracking {{carrier_tracking_number}}: {{carrier_tracking_url}}'],
            ['event' => NotificationEvent::Shipped, 'channel' => 'email', 'subject' => 'Order {{order_number}} has shipped', 'body' => "Hi {{customer_name}},\n\nYour order {{order_number}} has shipped via {{carrier}}, overnight. Tracking number: {{carrier_tracking_number}}\n\nTrack it: {{carrier_tracking_url}}"],

            ['event' => NotificationEvent::ArrivedWarm, 'channel' => 'sms', 'body' => "Halal Brothers: we're sorry order {{order_number}} arrived warm. A full refund of {{refund_amount}} is on its way."],
            ['event' => NotificationEvent::ArrivedWarm, 'channel' => 'email', 'subject' => "We're sorry — order {{order_number}} arrived warm", 'body' => "Hi {{customer_name}},\n\nWe're very sorry to hear order {{order_number}} arrived warm. A full refund of {{refund_amount}} has been issued.\n\nDetails: {{tracking_url}}"],
        ];
    }
}
