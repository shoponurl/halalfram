<?php

declare(strict_types=1);

namespace App\Payments\Data;

final readonly class WebhookEvent
{
    /**
     * @param  array<string, mixed>  $object  the event's data.object
     */
    public function __construct(
        public string $id,
        public string $type,
        public array $object,
    ) {}

    /** @return array<string, string> */
    public function metadata(): array
    {
        $metadata = $this->object['metadata'] ?? [];

        return is_array($metadata) ? array_map(strval(...), array_filter($metadata, is_scalar(...))) : [];
    }
}
