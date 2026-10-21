<h1 align="center">💳 laravel-netopia</h1>

<p align="center">Plăți cu cardul prin <b>NETOPIA Payments (API v2)</b> în Laravel: pornirea plății, statusul tranzacției și <b>IPN verificat prin semnătură</b>.<br>
<i>NETOPIA Payments API v2 for Laravel — card payments, transaction status and signed IPN verification.</i></p>

<p align="center">
<img alt="tests" src="https://github.com/Gabrieldevro/laravel-netopia/actions/workflows/tests.yml/badge.svg">
<img alt="Laravel" src="https://img.shields.io/badge/Laravel-11%20%7C%2012-FF2D20?style=flat-square&amp;logo=laravel&amp;logoColor=white">
<img alt="PHP" src="https://img.shields.io/badge/PHP-%E2%89%A58.2-777BB4?style=flat-square&amp;logo=php&amp;logoColor=white">
<img alt="License" src="https://img.shields.io/badge/license-MIT-blue?style=flat-square">
</p>

---

## Ce face

| | |
|---|---|
| 🔗 **Pornire plată** | `Netopia::startPayment()` trimite comanda și întoarce URL-ul paginii de plată NETOPIA |
| 🔎 **Status** | `Netopia::status()` interoghează tranzacția, util ca verificare suplimentară |
| 🔐 **IPN securizat** | verifică JWT-ul `Verification-token` (RS512) cu cheia publică NETOPIA, emitentul, POS-ul, expirarea și **hash-ul SHA-512 al corpului** |
| 📣 **Evenimente** | fiecare IPN valid emite `NetopiaPaymentUpdated`, pe care îl asculți ca să marchezi comanda |
| 🧪 **Sandbox** | trecerea între test și producție se face dintr-o singură variabilă |

Fără SDK extern: doar HTTP client-ul Laravel și OpenSSL.

## Instalare

```bash
composer require gabrieldevro/laravel-netopia
php artisan vendor:publish --tag=netopia-config   # opțional
```

`.env`:

```dotenv
NETOPIA_API_KEY=cheia-din-panoul-netopia
NETOPIA_POS_SIGNATURE=XXXX-XXXX-XXXX-XXXX-XXXX
NETOPIA_SANDBOX=true
NETOPIA_PUBLIC_KEY=/cale/catre/netopia-public.cer   # sau conținutul PEM
NETOPIA_NOTIFY_URL="${APP_URL}/netopia/ipn"
NETOPIA_REDIRECT_URL="${APP_URL}/comanda/finalizata"
```

## Utilizare

### 1. Pornești plata

```php
use Gabrieldev\Netopia\Facades\Netopia;

$result = Netopia::startPayment([
    'orderID'     => $order->number,
    'amount'      => $order->total,          // 249.90 (lei, nu bani)
    'description' => "Comanda #{$order->number}",
    'billing'     => [
        'email' => $order->email, 'phone' => $order->phone,
        'firstName' => $order->first_name, 'lastName' => $order->last_name,
        'city' => $order->city, 'details' => $order->address,
    ],
    'products' => $order->items->map(fn ($i) => [
        'name' => $i->name, 'code' => $i->sku, 'category' => 'produse', 'price' => $i->price, 'vat' => 19,
    ])->all(),
]);

$order->update(['ntp_id' => $result->ntpId]);

return redirect()->away($result->paymentUrl);
```

### 2. Primești confirmarea (IPN)

Pachetul înregistrează automat `POST /netopia/ipn`. Ruta trebuie exclusă de la verificarea CSRF, pentru că NETOPIA nu trimite token:

```php
// bootstrap/app.php
->withMiddleware(fn ($m) => $m->validateCsrfTokens(except: ['netopia/ipn']))
```

Asculți evenimentul:

```php
use Gabrieldev\Netopia\Events\NetopiaPaymentUpdated;

Event::listen(function (NetopiaPaymentUpdated $event) {
    $order = Order::where('number', $event->orderId)->firstOrFail();

    match (true) {
        $event->status->isSuccessful() => $order->markAsPaid($event->ntpId),
        $event->status->isFailed()     => $order->markAsFailed($event->status->name),
        default                        => null, // în așteptare
    };
});
```

Un IPN cu semnătură greșită, corp modificat, alt POS sau token expirat primește `400` și **nu** emite evenimentul.

### Statusuri

`PaymentStatus` este un enum cu toate statusurile API v2 (`Paid`, `Confirmed`, `Pending`, `Declined`, `Canceled`, `Expired` etc.) și metodele `isSuccessful()`, `isFailed()` și `isPending()`.

## Teste

```bash
composer test
```

Testele rulează offline:
- **HTTP-ul către NETOPIA** e simulat cu `Http::fake()`.
- **IPN-ul** e semnat cu o pereche de chei RSA generată la rulare.
- **Cazurile acoperite:** corp modificat, alt POS, alt emitent, token expirat, header lipsă și semnătură cu altă cheie.

> **Notă.** Pachetul folosește API-ul v2 NETOPIA, cu endpoint-urile `/payment/card/start` și `/operation/status`. Testează întotdeauna în sandbox și verifică formatul exact al câmpurilor cu [documentația oficială NETOPIA](https://doc.netopia-payments.com) pentru contul tău înainte de a trece în producție.

---

<p align="center">Făcut de <a href="https://gabrieldev.eu"><b>Gabriel Dev</b></a>. Integrez plăți online (NETOPIA, Stripe) în magazine și platforme Laravel.<br>
Ai nevoie de integrare? <a href="https://gabrieldev.eu/programare-consultatie">Programează o consultație</a>.</p>
