<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * TCPA opt-in record (guideline ch. 7, S06): a phone's marketing consent and STOP status, independent
 * of any one order. Transactional messages need no separate consent (an active order is itself the
 * "established business relationship"), but a STOP blocks every future send regardless of channel or
 * purpose — see App\Actions\Sms\HandleTwilioInboundSms.
 *
 * @property string $phone
 * @property bool $marketing_opt_in
 * @property Carbon|null $opted_out_at
 */
class SmsConsent extends Model
{
    protected $primaryKey = 'phone';

    protected $keyType = 'string';

    public $incrementing = false;

    /** @var list<string> */
    protected $fillable = ['phone', 'marketing_opt_in', 'opted_out_at'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['marketing_opt_in' => 'boolean', 'opted_out_at' => 'datetime'];
    }

    public function canReceiveSms(): bool
    {
        return $this->opted_out_at === null;
    }
}
