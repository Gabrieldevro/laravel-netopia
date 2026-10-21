<?php

namespace Gabrieldev\Netopia;

/**
 * Răspunsul la pornirea unei plăți: unde trimiți clientul și ID-ul tranzacției NETOPIA.
 */
final class StartPaymentResult
{
    public function __construct(
        public readonly ?string $paymentUrl,
        public readonly ?string $ntpId,
        public readonly ?PaymentStatus $status,
        public readonly string $errorCode,
        public readonly string $errorMessage,
        public readonly array $raw,
    ) {
    }

    public static function fromResponse(array $json): self
    {
        $payment = $json['payment'] ?? [];

        return new self(
            $payment['paymentURL'] ?? null,
            isset($payment['ntpID']) ? (string) $payment['ntpID'] : null,
            isset($payment['status']) ? PaymentStatus::tryFrom((int) $payment['status']) : null,
            (string) ($json['error']['code'] ?? ''),
            (string) ($json['error']['message'] ?? ''),
            $json,
        );
    }

    /** NETOPIA întoarce codul 101 când clientul trebuie redirecționat către pagina de plată. */
    public function requiresRedirect(): bool
    {
        return $this->paymentUrl !== null;
    }
}
