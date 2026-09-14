<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Actions\Orders\IssueInvoice;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Shop\Concerns\AuthorizesOrderAccess;
use App\Models\Order;
use App\Support\InvoicePdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class InvoiceController extends Controller
{
    use AuthorizesOrderAccess;

    public function show(Request $request, Order $order, IssueInvoice $issue, InvoicePdf $pdf): Response
    {
        // Staff who can view orders may download any invoice; customers only their own
        $staff = $request->user()?->can(Permission::ViewOrders->value) ?? false;
        if (! $staff) {
            $this->authorizeOrderAccess($request, $order);
        }

        $invoice = $issue->handle($order);

        return $pdf->render($order, $invoice)->download("{$invoice->number}.pdf");
    }
}
