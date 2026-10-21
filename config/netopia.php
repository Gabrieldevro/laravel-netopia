<?php

return [
    // Cheia API generată în panoul NETOPIA (Profil → Securitate → API key).
    'api_key' => env('NETOPIA_API_KEY'),

    // Semnătura punctului de vânzare (POS signature), de forma XXXX-XXXX-XXXX-XXXX-XXXX.
    'pos_signature' => env('NETOPIA_POS_SIGNATURE'),

    // true = mediul de test (sandbox); false = plăți reale.
    'sandbox' => env('NETOPIA_SANDBOX', true),

    // Cheia publică NETOPIA pentru verificarea IPN: cale către fișierul .cer/.pem sau conținutul PEM.
    'public_key' => env('NETOPIA_PUBLIC_KEY'),

    // Unde redirecționează NETOPIA clientul după plată și unde trimite notificarea IPN.
    'redirect_url' => env('NETOPIA_REDIRECT_URL'),
    'notify_url' => env('NETOPIA_NOTIFY_URL'),

    'language' => env('NETOPIA_LANGUAGE', 'ro'),
    'currency' => env('NETOPIA_CURRENCY', 'RON'),

    'endpoints' => [
        'sandbox' => 'https://secure.sandbox.netopia-payments.com',
        'live' => 'https://secure.mobilpay.ro/pay',
    ],

    // Ruta IPN înregistrată de pachet. null = o înregistrezi tu cu Route::netopiaIpn().
    'ipn_route' => env('NETOPIA_IPN_ROUTE', 'netopia/ipn'),

    'timeout' => 30,
];
