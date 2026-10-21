<?php

namespace Gabrieldev\Netopia\Tests;

use Gabrieldev\Netopia\Events\NetopiaPaymentUpdated;
use Gabrieldev\Netopia\Facades\Netopia;
use Gabrieldev\Netopia\IpnVerifier;
use Gabrieldev\Netopia\NetopiaException;
use Gabrieldev\Netopia\NetopiaServiceProvider;
use Gabrieldev\Netopia\PaymentStatus;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase;

class NetopiaTest extends TestCase
{
    private const POS = 'AAAA-BBBB-CCCC-DDDD-EEEE';

    private static string $privateKey = '';

    private static string $publicKey = '';

    public static function setUpBeforeClass(): void
    {
        // Pereche de chei generată pentru teste, în locul cheii reale NETOPIA.
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, self::$privateKey);
        self::$publicKey = openssl_pkey_get_details($key)['key'];
    }

    protected function getPackageProviders($app): array
    {
        return [NetopiaServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('netopia', array_merge($app['config']->get('netopia'), [
            'api_key' => 'test-api-key',
            'pos_signature' => self::POS,
            'sandbox' => true,
            'public_key' => self::$publicKey,
            'notify_url' => 'https://magazin.ro/netopia/ipn',
            'redirect_url' => 'https://magazin.ro/comanda/finalizata',
        ]));
    }

    private function jwt(array $claims, ?string $key = null): string
    {
        $b64 = fn (string $s) => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
        $head = $b64(json_encode(['typ' => 'JWT', 'alg' => 'RS512']));
        $body = $b64(json_encode($claims));
        openssl_sign("$head.$body", $signature, $key ?? self::$privateKey, OPENSSL_ALGO_SHA512);

        return "$head.$body.".$b64($signature);
    }

    private function ipnBody(int $status = 3): string
    {
        return json_encode([
            'order' => ['orderID' => 'CMD-1001'],
            'payment' => ['ntpID' => '1234567', 'status' => $status, 'amount' => 249.9, 'currency' => 'RON', 'message' => 'Tranzacție aprobată'],
        ]);
    }

    private function tokenFor(string $body, array $override = []): string
    {
        return $this->jwt(array_merge([
            'iss' => IpnVerifier::ISSUER,
            'aud' => [self::POS],
            'iat' => time(),
            'nbf' => time(),
            'exp' => time() + 300,
            'sub' => base64_encode(hash('sha512', $body, true)),
        ], $override));
    }

    public function test_start_payment_sends_the_expected_request(): void
    {
        Http::fake([
            '*/payment/card/start' => Http::response([
                'error' => ['code' => '101', 'message' => 'Redirect user to payment page'],
                'payment' => ['method' => 'card', 'ntpID' => '1234567', 'status' => 1, 'amount' => 249.9, 'currency' => 'RON',
                    'paymentURL' => 'https://secure.sandbox.netopia-payments.com/ui/card?p=abc'],
            ]),
        ]);

        $result = Netopia::startPayment([
            'orderID' => 'CMD-1001',
            'amount' => 249.9,
            'description' => 'Comanda #1001',
            'billing' => ['email' => 'client@exemplu.ro', 'phone' => '0743543100', 'firstName' => 'Ana', 'lastName' => 'Pop', 'city' => 'București'],
            'products' => [['name' => 'Lumânare', 'code' => 'L1', 'category' => 'decor', 'price' => 249.9, 'vat' => 0]],
        ]);

        $this->assertTrue($result->requiresRedirect());
        $this->assertSame('https://secure.sandbox.netopia-payments.com/ui/card?p=abc', $result->paymentUrl);
        $this->assertSame('1234567', $result->ntpId);
        $this->assertSame(PaymentStatus::New, $result->status);

        Http::assertSent(function (Request $request) {
            $order = $request['order'];

            return $request->url() === 'https://secure.sandbox.netopia-payments.com/payment/card/start'
                && $request->header('Authorization')[0] === 'test-api-key'
                && $order['posSignature'] === self::POS
                && $order['amount'] === 249.9
                && $order['currency'] === 'RON'
                && $order['billing']['country'] === 642
                && $request['config']['notifyUrl'] === 'https://magazin.ro/netopia/ipn';
        });
    }

    public function test_live_mode_uses_the_production_endpoint(): void
    {
        config(['netopia.sandbox' => false]);
        $this->app->forgetInstance(\Gabrieldev\Netopia\NetopiaClient::class);
        Netopia::clearResolvedInstances();

        $this->assertSame('https://secure.mobilpay.ro/pay', Netopia::baseUrl());
    }

    public function test_status_and_http_errors(): void
    {
        Http::fake([
            '*/operation/status' => Http::sequence()
                ->push(['payment' => ['status' => 5]])
                ->push(['message' => 'Unauthorized'], 401),
        ]);

        $this->assertSame(PaymentStatus::Confirmed, Netopia::status('1234567', 'CMD-1001'));
        $this->assertTrue(PaymentStatus::Confirmed->isSuccessful());

        $this->expectException(NetopiaException::class);
        Netopia::status('1234567', 'CMD-1001');
    }

    public function test_missing_order_fields_are_rejected_before_any_request(): void
    {
        Http::fake();
        $this->expectException(NetopiaException::class);

        try {
            Netopia::startPayment(['orderID' => 'X']);
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_ipn_verifier_accepts_valid_and_rejects_tampered_tokens(): void
    {
        $verifier = new IpnVerifier(self::$publicKey, self::POS);
        $body = $this->ipnBody();

        $this->assertSame('CMD-1001', $verifier->verify($body, $this->tokenFor($body))['order']['orderID']);

        $cases = [
            'corp modificat' => fn () => $verifier->verify(str_replace('249.9', '1.0', $body), $this->tokenFor($body)),
            'alt POS' => fn () => $verifier->verify($body, $this->tokenFor($body, ['aud' => ['ALT-POS']])),
            'alt emitent' => fn () => $verifier->verify($body, $this->tokenFor($body, ['iss' => 'Altcineva'])),
            'expirat' => fn () => $verifier->verify($body, $this->tokenFor($body, ['exp' => time() - 3600])),
            'fără token' => fn () => $verifier->verify($body, null),
            'semnat cu altă cheie' => function () use ($verifier, $body) {
                $other = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
                openssl_pkey_export($other, $otherPem);

                return $verifier->verify($body, $this->jwt(['iss' => IpnVerifier::ISSUER, 'aud' => [self::POS], 'sub' => base64_encode(hash('sha512', $body, true))], $otherPem));
            },
        ];

        foreach ($cases as $name => $case) {
            try {
                $case();
                $this->fail("Ar fi trebuit respins: $name");
            } catch (NetopiaException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_ipn_route_dispatches_event_and_acknowledges(): void
    {
        Event::fake([NetopiaPaymentUpdated::class]);
        $body = $this->ipnBody(3);

        $this->call('POST', '/netopia/ipn', [], [], [], ['HTTP_VERIFICATION_TOKEN' => $this->tokenFor($body), 'CONTENT_TYPE' => 'application/json'], $body)
            ->assertOk()
            ->assertExactJson(['errorType' => 0, 'errorCode' => 0, 'errorMessage' => '']);

        Event::assertDispatched(NetopiaPaymentUpdated::class, fn ($e) => $e->orderId === 'CMD-1001'
            && $e->status === PaymentStatus::Paid && $e->amount === 249.9 && $e->ntpId === '1234567');
    }

    public function test_ipn_route_rejects_forged_notifications(): void
    {
        Event::fake([NetopiaPaymentUpdated::class]);
        $body = $this->ipnBody(3);

        $this->call('POST', '/netopia/ipn', [], [], [], ['HTTP_VERIFICATION_TOKEN' => 'a.b.c', 'CONTENT_TYPE' => 'application/json'], $body)
            ->assertStatus(400);

        Event::assertNotDispatched(NetopiaPaymentUpdated::class);
    }
}
