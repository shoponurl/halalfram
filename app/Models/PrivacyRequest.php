<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PrivacyRequestStatus;
use App\Enums\PrivacyRequestType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A CCPA request filed through the public /privacy/requests form (guideline ch. 7, S07). A "delete"
 * request is fulfilled by anonymizing, never hard-deleting — see App\Actions\Compliance\AnonymizeCustomerData.
 *
 * @property int $id
 * @property PrivacyRequestType $type
 * @property string $customer_email
 * @property string|null $customer_name
 * @property string|null $note
 * @property PrivacyRequestStatus $status
 * @property int|null $fulfilled_by
 * @property Carbon|null $fulfilled_at
 * @property-read User|null $fulfiller
 */
class PrivacyRequest extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'type',
        'customer_email',
        'customer_name',
        'note',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => PrivacyRequestType::class,
            'status' => PrivacyRequestStatus::class,
            'fulfilled_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function fulfiller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fulfilled_by');
    }
}
