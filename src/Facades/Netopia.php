<?php

namespace Gabrieldev\Netopia\Facades;

use Gabrieldev\Netopia\NetopiaClient;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \Gabrieldev\Netopia\StartPaymentResult startPayment(array $order, array $overrides = [])
 * @method static \Gabrieldev\Netopia\PaymentStatus status(string $ntpId, string $orderId)
 * @method static string baseUrl()
 */
class Netopia extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return NetopiaClient::class;
    }
}
