<?php

namespace Gabrieldev\Netopia;

use Illuminate\Http\Client\Factory as Http;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

/**
 * Client pentru NETOPIA Payments API v2 (plăți cu cardul, status tranzacție).
 */
class NetopiaClient
{
    public function __construct(private Http $http, private array $config)
    {
    }

    public function baseUrl(): string
    {
        return rtrim($this->config['endpoints'][$this->config['sandbox'] ? 'sandbox' : 'live'], '/');
    }

    /**
     * Pornește o plată și întoarce URL-ul unde trebuie redirecționat clientul.
     *
     * $order: orderID, amount, description, billing (email, phone, firstName, lastName, city, country, state, postalCode, details),
     * opțional currency, products[], installments, data[].
     */
    public function startPayment(array $order, array $overrides = []): StartPaymentResult
    {
        foreach (['orderID', 'amount', 'description', 'billing'] as $key) {
            if (! Arr::has($order, $key)) {
                throw new NetopiaException("Lipsește câmpul obligatoriu „{$key}” din comandă.");
            }
        }

        $billing = array_merge([
            'email' => '', 'phone' => '', 'firstName' => '', 'lastName' => '',
            'city' => '', 'country' => 642, 'countryName' => 'Romania', 'state' => '', 'postalCode' => '', 'details' => '',
        ], $order['billing']);

        $payload = array_replace_recursive([
            'config' => [
                'emailTemplate' => '',
                'notifyUrl' => $this->config['notify_url'],
                'redirectUrl' => $this->config['redirect_url'],
                'language' => $this->config['language'],
            ],
            'payment' => [
                'options' => ['installments' => (int) ($order['installments'] ?? 0), 'bonus' => 0],
                'instrument' => null,
                'data' => (object) ($order['data'] ?? []),
            ],
            'order' => [
                'ntpID' => '',
                'posSignature' => $this->config['pos_signature'],
                'dateTime' => Carbon::now()->format('Y-m-d\TH:i:sP'),
                'description' => (string) $order['description'],
                'orderID' => (string) $order['orderID'],
                // NETOPIA așteaptă suma în unitatea monedei (ex. 49.90), nu în bani.
                'amount' => round((float) $order['amount'], 2),
                'currency' => $order['currency'] ?? $this->config['currency'],
                'billing' => $billing,
                'shipping' => $order['shipping'] ?? $billing,
                'products' => $order['products'] ?? [],
                'installments' => ['selected' => 0, 'available' => [0]],
                'data' => (object) [],
            ],
        ], $overrides);

        return StartPaymentResult::fromResponse($this->post('/payment/card/start', $payload));
    }

    /** Statusul curent al unei tranzacții (de folosit ca verificare suplimentară, nu în locul IPN). */
    public function status(string $ntpId, string $orderId): PaymentStatus
    {
        $json = $this->post('/operation/status', [
            'posID' => $this->config['pos_signature'],
            'ntpID' => $ntpId,
            'orderID' => $orderId,
        ]);

        $status = PaymentStatus::tryFrom((int) ($json['payment']['status'] ?? 0));

        if (! $status) {
            throw new NetopiaException('Status necunoscut în răspunsul NETOPIA.', $json);
        }

        return $status;
    }

    private function post(string $path, array $payload): array
    {
        if (empty($this->config['api_key']) || empty($this->config['pos_signature'])) {
            throw new NetopiaException('Setează NETOPIA_API_KEY și NETOPIA_POS_SIGNATURE în .env.');
        }

        $response = $this->http
            ->withHeaders(['Authorization' => $this->config['api_key']])
            ->acceptJson()
            ->timeout($this->config['timeout'] ?? 30)
            ->post($this->baseUrl().$path, $payload);

        $json = $response->json() ?? [];

        if ($response->failed()) {
            throw new NetopiaException(
                'NETOPIA a răspuns cu HTTP '.$response->status().': '.($json['message'] ?? $json['error']['message'] ?? 'eroare necunoscută'),
                $json,
                $response->status(),
            );
        }

        return $json;
    }
}
