<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentTransactionType: string
{
    case Authorization = 'authorization';
    case Capture = 'capture';
    case Cancel = 'cancel';
    case ExtraCharge = 'extra_charge';
    case BalanceLink = 'balance_link';
    case BalancePaid = 'balance_paid';
    case WriteOff = 'write_off';
    case Refund = 'refund';
    case CashReceived = 'cash_received';
}
