<?php

namespace App\Http\Controllers;

use App\Models\PaymentTransaction;
use App\Services\BillingGrantService;
use App\Services\PaymobService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymobWebhookController extends Controller
{
    private PaymobService $paymobService;
    private BillingGrantService $billingGrantService;

    public function __construct(PaymobService $paymobService, BillingGrantService $billingGrantService)
    {
        $this->paymobService = $paymobService;
        $this->billingGrantService = $billingGrantService;
    }

    public function handle(Request $request): JsonResponse
    {
        $payload = $request->all();
        $receivedHmac = $request->query('hmac');

        if (! $this->paymobService->isValidTransactionCallback($payload, is_string($receivedHmac) ? $receivedHmac : null)) {
            return response()->json([
                'message' => 'Invalid HMAC signature.',
            ], 403);
        }

        $orderId = (int) data_get($payload, 'obj.order.id');
        $isSuccessful = (bool) data_get($payload, 'obj.success', false);

        $paymentTransaction = PaymentTransaction::query()
            ->where('paymob_order_id', $orderId)
            ->first();

        if (! $paymentTransaction) {
            return response()->json([
                'message' => 'Payment transaction not found.',
            ], 404);
        }

        if (! $isSuccessful) {
            if ($paymentTransaction->status === 'pending') {
                $paymentTransaction->update(['status' => 'failed']);
            }

            return response()->json([
                'status' => 'ignored',
            ]);
        }

        $this->billingGrantService->applySuccessfulPaymentTransaction($paymentTransaction);

        return response()->json([
            'status' => 'processed',
        ]);
    }
}
