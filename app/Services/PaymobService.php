<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PaymobService
{
    private const TRANSACTION_HMAC_KEYS = [
        'amount_cents',
        'created_at',
        'currency',
        'error_occured',
        'has_parent_transaction',
        'id',
        'integration_id',
        'is_3d_secure',
        'is_auth',
        'is_capture',
        'is_refunded',
        'is_standalone_payment',
        'is_voided',
        'order.id',
        'owner',
        'pending',
        'source_data.pan',
        'source_data.sub_type',
        'source_data.type',
        'success',
    ];

    public function authenticate(): string
    {
        $apiKey = config('paymob.api_key');

        if (! $apiKey) {
            throw new RuntimeException('Paymob is not configured.');
        }

        $response = $this->request()->post('/api/auth/tokens', [
            'api_key' => $apiKey,
        ]);

        $this->ensureSuccessful($response, 'Unable to authenticate with Paymob.');

        $token = $response->json('token');

        if (! is_string($token) || $token === '') {
            throw new RuntimeException('Paymob authentication did not return a token.');
        }

        return $token;
    }

    public function registerOrder(string $token, int $amountCents, array $items, string $currency): array
    {
        $response = $this->request()->post('/api/ecommerce/orders', [
            'auth_token' => $token,
            'delivery_needed' => false,
            'amount_cents' => $amountCents,
            'currency' => $currency,
            'items' => $items,
        ]);

        $this->ensureSuccessful($response, 'Unable to register a Paymob order.');

        return $response->json();
    }

    public function getPaymentKey(string $token, int $amountCents, int $orderId, array $billingData, string $currency, int $integrationId): string
    {
        $response = $this->request()->post('/api/acceptance/payment_keys', [
            'auth_token' => $token,
            'amount_cents' => $amountCents,
            'expiration' => 3600,
            'order_id' => $orderId,
            'billing_data' => $billingData,
            'currency' => $currency,
            'integration_id' => $integrationId,
            'lock_order_when_paid' => false,
        ]);

        $this->ensureSuccessful($response, 'Unable to generate a Paymob payment key.');

        $paymentKey = $response->json('token');

        if (! is_string($paymentKey) || $paymentKey === '') {
            throw new RuntimeException('Paymob did not return a payment key.');
        }

        return $paymentKey;
    }

    public function generateIframeUrl(string $paymentKeyToken): string
    {
        $iframeId = config('paymob.iframe_id');

        if (! $iframeId) {
            throw new RuntimeException('Paymob iframe is not configured.');
        }

        return sprintf(
            '%s/api/acceptance/iframes/%s?payment_token=%s',
            $this->baseUrl(),
            $iframeId,
            urlencode($paymentKeyToken),
        );
    }

    public function isValidTransactionCallback(array $payload, ?string $receivedHmac): bool
    {
        if (! is_string($receivedHmac) || $receivedHmac === '') {
            return false;
        }

        $expectedHmac = $this->generateTransactionCallbackHmac($payload);

        if ($expectedHmac === null) {
            return false;
        }

        return hash_equals($expectedHmac, $receivedHmac);
    }

    public function generateTransactionCallbackHmac(array $payload): ?string
    {
        $secret = config('paymob.hmac_secret');

        if (! is_string($secret) || $secret === '') {
            return null;
        }

        $transaction = (array) data_get($payload, 'obj', []);
        $concatenated = '';

        foreach (self::TRANSACTION_HMAC_KEYS as $key) {
            $concatenated .= $this->stringifyHmacValue(data_get($transaction, $key));
        }

        return hash_hmac('sha512', $concatenated, $secret);
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl())
            ->timeout(20)
            ->acceptJson();
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('paymob.base_url', 'https://accept.paymob.com'), '/');
    }

    private function ensureSuccessful(Response $response, string $message): void
    {
        if (! $response->successful()) {
            throw new RuntimeException($message);
        }
    }

    private function stringifyHmacValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if ($value === null) {
            return '';
        }

        return (string) $value;
    }
}
