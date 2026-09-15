<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Enums\PrivacyRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\PrivacyRequestRequest;
use App\Models\PrivacyRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/** Guideline ch. 7, S07: a public CCPA request form — staff review and action it in /admin. */
final class PrivacyRequestController extends Controller
{
    public function create(): View
    {
        return view('shop.legal.data-request');
    }

    public function store(PrivacyRequestRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $privacyRequest = new PrivacyRequest;
        $privacyRequest->type = $data['type'];
        $privacyRequest->customer_email = strtolower((string) $data['customer_email']);
        $privacyRequest->customer_name = $data['customer_name'] ?? null;
        $privacyRequest->note = $data['note'] ?? null;
        $privacyRequest->status = PrivacyRequestStatus::Pending;
        $privacyRequest->save();

        return redirect()->route('privacy-requests.create')->with('status', 'Your request has been received — we\'ll follow up at the email you provided within 45 days, per CCPA.');
    }
}
