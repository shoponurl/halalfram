<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\OrderItem;
use InvalidArgumentException;

/**
 * The pack label's ZPL (Zebra printer language), guideline ch. 6 Sprint 04. Owner decision: GS1-128,
 * carrying the lot's use-by date (AI 17, fixed 6 digits) and lot number (AI 10, variable — placed
 * last so it never needs an FNC1 separator). Verify on a real printer/scanner before production use —
 * the guideline flags this explicitly; nothing here has been tested against physical hardware.
 */
final class PackLabelZpl
{
    public function render(OrderItem $item): string
    {
        $item->loadMissing(['lot', 'order']);
        $lot = $item->lot;
        if ($lot === null) {
            throw new InvalidArgumentException('Cannot print a pack label for an item with no lot.');
        }
        if ($item->actual_weight_lb === null) {
            throw new InvalidArgumentException('Cannot print a pack label before the item is weighed.');
        }

        $weight = rtrim(rtrim($item->actual_weight_lb->toDecimal(), '0'), '.');
        $useBy = $lot->use_by_date->format('M j, Y');
        $barcodeData = '>;>8'.'(17)'.$lot->use_by_date->format('ymd').'(10)'.$lot->lot_number;

        return <<<ZPL
            ^XA
            ^CI28
            ^FO40,30^A0N,35,35^FDHalal Brothers^FS
            ^FO40,75^A0N,25,25^FD{$item->product_name}^FS
            ^FO40,105^A0N,22,22^FDOrder {$item->order->number}^FS
            ^FO40,130^A0N,28,28^FDWeight: {$weight} lb^FS
            ^FO40,165^A0N,22,22^FDLot: {$lot->lot_number}^FS
            ^FO40,190^A0N,22,22^FDUse by: {$useBy}^FS
            ^FO40,225^BY2
            ^BCN,80,Y,N,N
            ^FD{$barcodeData}^FS
            ^XZ
            ZPL;
    }
}
