<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Animal;
use App\Models\Lot;
use App\Models\QcCheck;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;
use Illuminate\Support\Carbon;

/**
 * Guideline ch. 6, Sprint 07: one-click inspection pack — animals, lots and QC/temperature logs for a
 * date range, plus the traceability (lot → orders) a health inspector would ask for.
 */
final class InspectionPackPdf
{
    public function render(Carbon $from, Carbon $to): DomPdf
    {
        $animals = Animal::query()->whereBetween('slaughter_date', [$from->toDateString(), $to->toDateString()])->orderBy('slaughter_date')->get();
        $lots = Lot::query()->with(['product', 'animal', 'movements'])->whereBetween('pack_date', [$from->toDateString(), $to->toDateString()])->orderBy('pack_date')->get();
        $qcChecks = QcCheck::query()->with('order', 'inspector')->whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()])->orderBy('created_at')->get();

        return Pdf::loadView('compliance.inspection-pack', [
            'from' => $from,
            'to' => $to,
            'animals' => $animals,
            'lots' => $lots,
            'qcChecks' => $qcChecks,
        ])->setPaper('letter');
    }
}
