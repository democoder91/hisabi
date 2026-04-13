<?php

return [
    'base_url' => env('PAYMOB_BASE_URL', 'https://accept.paymob.com'),
    'api_key' => env('PAYMOB_API_KEY'),
    'integration_id_card' => env('PAYMOB_INTEGRATION_ID_CARD'),
    'iframe_id' => env('PAYMOB_IFRAME_ID'),
    'hmac_secret' => env('PAYMOB_HMAC_SECRET'),
];
