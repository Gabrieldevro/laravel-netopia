<?php

namespace Gabrieldev\Netopia\Http;

use Gabrieldev\Netopia\Events\NetopiaPaymentUpdated;
use Gabrieldev\Netopia\IpnVerifier;
use Gabrieldev\Netopia\NetopiaException;
use Gabrieldev\Netopia\PaymentStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class IpnController
{
    public function __invoke(Request $request, IpnVerifier $verifier): JsonResponse
    {
        try {
            $body = $verifier->verify($request->getContent(), $request->header('Verification-token'));
        } catch (NetopiaException $e) {
            Log::warning('NETOPIA IPN respins: '.$e->getMessage());

            return response()->json(['errorType' => 1, 'errorCode' => 1, 'errorMessage' => $e->getMessage()], 400);
        }

        $status = PaymentStatus::tryFrom((int) ($body['payment']['status'] ?? 0));
        if (! $status || empty($body['order']['orderID'])) {
            return response()->json(['errorType' => 1, 'errorCode' => 2, 'errorMessage' => 'IPN incomplet'], 422);
        }

        NetopiaPaymentUpdated::dispatch(
            (string) $body['order']['orderID'],
            isset($body['payment']['ntpID']) ? (string) $body['payment']['ntpID'] : null,
            $status,
            (float) ($body['payment']['amount'] ?? 0),
            (string) ($body['payment']['currency'] ?? ''),
            $body,
        );

        // Răspunsul de confirmare: NETOPIA nu mai retrimite notificarea.
        return response()->json(['errorType' => 0, 'errorCode' => 0, 'errorMessage' => '']);
    }
}
