<?php

namespace App\Http\Controllers;

use App\Domains\Account\Models\Account;
use App\Http\Resources\AccountResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TransactionController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Transaction/Index');
    }

    public function expenseOptions(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'paymentMethods' => AccountResource::collection($this->accessibleAccounts($user)->assets()->get()),
            'categories' => AccountResource::collection($this->accessibleAccounts($user)->expenses()->get()),
            'labels' => [
                'paymentMethods' => 'Paid From',
                'categories' => 'Category',
            ],
        ]);
    }

    public function incomeOptions(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'incomeSources' => AccountResource::collection($this->accessibleAccounts($user)->incomes()->get()),
            'depositAccounts' => AccountResource::collection($this->accessibleAccounts($user)->assets()->get()),
            'labels' => [
                'incomeSources' => 'Source',
                'depositAccounts' => 'Deposited To',
            ],
        ]);
    }

    private function accessibleAccounts(?User $user)
    {
        return Account::query()
            ->accessibleTo($user)
            ->with(['user:id,name', 'sharedUsers:id,name,email'])
            ->withCount('transactions')
            ->orderBy('id');
    }
}
