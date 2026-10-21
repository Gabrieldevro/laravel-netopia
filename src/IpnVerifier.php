<?php

namespace Gabrieldev\Netopia;

/**
 * Verifică notificările IPN trimise de NETOPIA.
 *
 * Header-ul „Verification-token” este un JWT semnat RS512 cu cheia privată NETOPIA:
 *  - iss = „NETOPIA Payments”
 *  - aud = semnătura POS-ului tău
 *  - sub = base64(sha512(corpul brut al cererii))
 * Fără dependențe externe: decodare și verificare cu OpenSSL.
 */
class IpnVerifier
{
    public const ISSUER = 'NETOPIA Payments';

    public function __construct(private string $publicKey, private string $posSignature, private int $leeway = 60)
    {
    }

    /**
     * @return array corpul IPN decodat (order, payment)
     *
     * @throws NetopiaException dacă semnătura sau datele nu sunt valide
     */
    public function verify(string $rawBody, ?string $token): array
    {
        if (! $token) {
            throw new NetopiaException('Lipsește header-ul Verification-token.');
        }

        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new NetopiaException('Verification-token nu este un JWT valid.');
        }

        [$h64, $p64, $s64] = $parts;
        $header = json_decode(self::b64urlDecode($h64), true);
        $claims = json_decode(self::b64urlDecode($p64), true);

        if (($header['alg'] ?? null) !== 'RS512' || ! is_array($claims)) {
            throw new NetopiaException('Algoritm de semnare neacceptat.');
        }

        $key = openssl_pkey_get_public($this->loadKey());
        if (! $key) {
            throw new NetopiaException('Cheia publică NETOPIA nu poate fi citită.');
        }

        if (openssl_verify("$h64.$p64", self::b64urlDecode($s64), $key, OPENSSL_ALGO_SHA512) !== 1) {
            throw new NetopiaException('Semnătura IPN este invalidă.');
        }

        if (($claims['iss'] ?? null) !== self::ISSUER) {
            throw new NetopiaException('Emitent IPN neașteptat.');
        }

        $audience = (array) ($claims['aud'] ?? []);
        if (! in_array($this->posSignature, $audience, true)) {
            throw new NetopiaException('IPN-ul nu este pentru acest POS.');
        }

        $now = time();
        if (isset($claims['exp']) && $claims['exp'] + $this->leeway < $now) {
            throw new NetopiaException('Verification-token a expirat.');
        }
        if (isset($claims['nbf']) && $claims['nbf'] - $this->leeway > $now) {
            throw new NetopiaException('Verification-token nu este încă valid.');
        }

        $expectedHash = base64_encode(hash('sha512', $rawBody, true));
        if (! hash_equals($expectedHash, (string) ($claims['sub'] ?? ''))) {
            throw new NetopiaException('Corpul IPN a fost modificat (hash diferit).');
        }

        $body = json_decode($rawBody, true);
        if (! is_array($body)) {
            throw new NetopiaException('Corpul IPN nu este JSON valid.');
        }

        return $body;
    }

    private function loadKey(): string
    {
        $key = trim($this->publicKey);

        return str_starts_with($key, '-----BEGIN') ? $key : (is_file($key) ? file_get_contents($key) : $key);
    }

    private static function b64urlDecode(string $value): string
    {
        return base64_decode(strtr($value, '-_', '+/').str_repeat('=', (4 - strlen($value) % 4) % 4), true) ?: '';
    }
}
