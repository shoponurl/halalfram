<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;

/**
 * The butcher's cutting sheet (guideline M03/S02): which animal, what cut, offal and packing choice,
 * how many packs, whose name — structured, never free text, so nothing gets misread on the floor.
 */
final class CuttingSheetPdf
{
    public function render(Order $order): DomPdf
    {
        $order->loadMissing('items');

        return Pdf::loadView('orders.cutting-sheet', ['order' => $order])->setPaper('letter');
    }
}
