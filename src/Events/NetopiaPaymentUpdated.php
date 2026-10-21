<?php

namespace Gabrieldev\Netopia\Events;

use Gabrieldev\Netopia\PaymentStatus;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Emis la fiecare IPN valid. Ascultă-l ca să marchezi comanda plătită, anulată etc.
 */
class NetopiaPaymentUpdated
{
    use Dispatchable;

    public function __construct(
        public readonly string $orderId,
        public readonly ?string $ntpId,
        public readonly PaymentStatus $status,
        public readonly float $amount,
        public readonly string $currency,
        public readonly array $payload,
    ) {
    }
}
