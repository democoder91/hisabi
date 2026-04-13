<?php

namespace App\Http\Controllers;

use App\Models\PaymentTransaction;
use App\Models\Subscription;
use App\Services\PaymobService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymobWebhookController extends Controller
{
    private PaymobService $paymobService;

    public function __construct(PaymobService $paymobService)
    {
        $this->paymobService = $paymobService;
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

        DB::transaction(function () use ($paymentTransaction): void {
            if ($paymentTransaction->status === 'success') {
                return;
            }

            $paymentTransaction->update(['status' => 'success']);

            if ($paymentTransaction->credits_added > 0) {
                $paymentTransaction->user()->increment('available_credits', $paymentTransaction->credits_added);
            }

            if ($paymentTransaction->type === 'subscription') {
                $this->activateSubscription($paymentTransaction);
            }
        });

        return response()->json([
            'status' => 'processed',
        ]);
    }

    private function activateSubscription(PaymentTransaction $paymentTransaction): void
    {
        $user = $paymentTransaction->user;
        $renewsInDays = max(1, (int) ($paymentTransaction->renews_in_days ?? 30));

        Subscription::query()->updateOrCreate(
            ['user_id' => $paymentTransaction->user_id],
            [
                'plan_name' => (string) ($paymentTransaction->product_name ?: 'Subscription'),
                'status' => 'active',
                'paymob_order_id' => $paymentTransaction->paymob_order_id,
                'renews_at' => now()->addDays($renewsInDays),
            ],
        );

        if ($user) {
            $user->forceFill([
                'trial_ends_at' => null,
            ])->save();
        }
    }
}
