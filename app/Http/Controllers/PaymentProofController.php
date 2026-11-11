<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Hands over a proof of payment, to someone entitled to see it.
 *
 * These used to sit on the public disk, where the only thing between an
 * EcoCash screenshot — phone number, name, amount — and the open internet
 * was an unguessable filename. They are private now, and this is the only
 * way back out: the shop, for checking, and the client who sent it.
 *
 * Served inline rather than redirected to a temporary URL so it works the
 * same on local disk and on S3, and so the check happens on every view
 * rather than once per link.
 */
class PaymentProofController extends Controller
{
    public function __invoke(Request $request, Payment $payment): StreamedResponse
    {
        abort_if($payment->proof_path === null, 404);

        $user = $request->user();

        abort_unless($user !== null && $this->maySee($user, $payment), 403);

        abort_unless(Storage::disk('local')->exists($payment->proof_path), 404);

        return Storage::disk('local')->response(
            $payment->proof_path,
            null,
            ['Cache-Control' => 'private, max-age=0, no-store'],
        );
    }

    /**
     * Whoever has to check it, and whoever sent it. A barber cannot: they do
     * not take money, and the proof carries the client's number, which is
     * deliberately kept from them.
     */
    private function maySee(User $user, Payment $payment): bool
    {
        if ($payment->appointment?->client_id === $user->id) {
            return true;
        }

        return $user->can('payment.take');
    }
}
