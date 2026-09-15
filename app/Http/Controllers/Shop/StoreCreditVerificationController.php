<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Actions\Payments\SendStoreCreditLink;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** S09 self-audit, finding SA-01: store credit is only usable after proving the email is yours. */
final class StoreCreditVerificationController extends Controller
{
    public const SESSION_KEY = 'verified_credit_email';

    public function send(Request $request, SendStoreCreditLink $sendLink): RedirectResponse
    {
        $data = $request->validate(['credit_email' => ['required', 'email:rfc', 'max:190']]);

        $sendLink->handle((string) $data['credit_email']);

        return redirect()->route('checkout.create')
            ->with('status', 'If that email has store credit, we\'ve sent it a link — open it in this browser to apply your credit.');
    }

    public function confirm(Request $request, string $token): RedirectResponse
    {
        $email = SendStoreCreditLink::emailForToken($token);
        if ($email === null) {
            return redirect()->route('checkout.create')->with('status', 'That store credit link has expired — please request a new one.');
        }

        $request->session()->put(self::SESSION_KEY, $email);

        return redirect()->route('checkout.create')->with('status', 'Email confirmed — your store credit is ready to apply.');
    }
}
