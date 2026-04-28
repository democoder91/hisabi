<?php

namespace App\Http\Controllers;

use App\Http\Requests\Billing\BillingUserIndexRequest;
use App\Http\Requests\Billing\StoreManualBillingGrantRequest;
use App\Models\BillingGrantAudit;
use App\Models\BillingProduct;
use App\Models\Subscription;
use App\Models\User;
use App\Services\AI\ConversationCostService;
use App\Services\BillingCatalogService;
use App\Services\BillingGrantService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;

class BillingUserManagementController extends Controller
{
    public function __construct(
        private BillingCatalogService $billingCatalogService,
        private BillingGrantService $billingGrantService,
        private ConversationCostService $conversationCostService,
    ) {}

    public function index(BillingUserIndexRequest $request): Response
    {
        $validated = $request->validated();
        $search = trim((string) ($validated['search'] ?? ''));
        $perPage = (int) ($validated['per_page'] ?? 15);

        $paginator = User::query()
            ->select(['id', 'name', 'email', 'available_credits', 'trial_ends_at', 'is_super'])
            ->with('subscriptions')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($userQuery) use ($search) {
                    $userQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        $conversationTotals = $this->conversationCostService->totalCostForUsers(
            $paginator->getCollection()->pluck('id')->all(),
        );

        return Inertia::render('Billing/Users', [
            'users' => $this->transformUsers($paginator, $conversationTotals),
            'grantOptions' => [
                'creditPackages' => $this->billingCatalogService->creditPackages()
                    ->map(fn (BillingProduct $product): array => $this->transformGrantOption($product))
                    ->all(),
                'subscriptionPlans' => $this->billingCatalogService->subscriptionPlans()
                    ->map(fn (BillingProduct $product): array => $this->transformGrantOption($product))
                    ->all(),
            ],
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
            ],
            'pagination' => [
                'currentPage' => $paginator->currentPage(),
                'lastPage' => $paginator->lastPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
                'hasMorePages' => $paginator->hasMorePages(),
            ],
            'conversationCostCurrency' => $this->conversationCostService->currency(),
            'recentGrantAudits' => $this->recentGrantAudits(),
        ]);
    }

    public function showConversationCosts(User $user): Response
    {
        $breakdown = $this->conversationCostService->conversationBreakdownForUser($user);

        return Inertia::render('Billing/UserConversationCosts', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'conversationCostCurrency' => $breakdown['currency'],
            'summary' => [
                'totalCost' => $breakdown['totalCost'],
                'totalTurns' => $breakdown['totalTurns'],
                'conversationCount' => $breakdown['conversationCount'],
            ],
            'conversations' => $breakdown['conversations'],
        ]);
    }

    public function store(StoreManualBillingGrantRequest $request, User $user): RedirectResponse
    {
        $product = BillingProduct::query()
            ->active()
            ->findOrFail((int) $request->validated('billing_product_id'));

        /** @var User $adminUser */
        $adminUser = $request->user();

        $this->billingGrantService->grantCatalogProduct($adminUser, $user, $product);

        return redirect()->back();
    }

    private function transformUsers(LengthAwarePaginator $paginator, array $conversationTotals): array
    {
        return $paginator->getCollection()
            ->map(function (User $user) use ($conversationTotals): array {
                $subscription = $user->subscriptions
                    ->sortByDesc(fn (Subscription $item): int => $item->renews_at ? (int) $item->renews_at->getTimestamp() : 0)
                    ->first();

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'availableCredits' => (int) $user->available_credits,
                    'trialEndsAt' => $user->trial_ends_at ? $user->trial_ends_at->toISOString() : null,
                    'isSuper' => $user->isSuperUser(),
                    'totalConversationCost' => (float) ($conversationTotals[$user->id] ?? 0.0),
                    'subscription' => $subscription ? [
                        'planName' => $subscription->plan_name,
                        'status' => $subscription->status,
                        'renewsAt' => $subscription->renews_at ? $subscription->renews_at->toISOString() : null,
                    ] : null,
                ];
            })
            ->values()
            ->all();
    }

    private function transformGrantOption(BillingProduct $product): array
    {
        return [
            'id' => $product->id,
            'slug' => $product->slug,
            'name_en' => $product->name_en ?: $product->name,
            'name_ar' => $product->name_ar ?: $product->name,
            'currency' => $product->currency,
            'price' => $product->price,
            'credits' => $product->credits,
            'renews_in_days' => $product->renews_in_days,
        ];
    }

    private function recentGrantAudits(): array
    {
        return BillingGrantAudit::query()
            ->with([
                'adminUser:id,name,email',
                'targetUser:id,name,email',
            ])
            ->latest()
            ->limit(10)
            ->get()
            ->map(function (BillingGrantAudit $audit): array {
                return [
                    'id' => $audit->id,
                    'grantType' => $audit->grant_type,
                    'productName' => (string) (($audit->product_snapshot['name_en'] ?? null) ?: ($audit->product_snapshot['name_ar'] ?? null) ?: 'Billing product'),
                    'adminUser' => [
                        'name' => $audit->adminUser ? $audit->adminUser->name : null,
                        'email' => $audit->adminUser ? $audit->adminUser->email : null,
                    ],
                    'targetUser' => [
                        'name' => $audit->targetUser ? $audit->targetUser->name : null,
                        'email' => $audit->targetUser ? $audit->targetUser->email : null,
                    ],
                    'createdAt' => $audit->created_at ? $audit->created_at->toISOString() : null,
                    'oldValues' => $audit->old_values,
                    'newValues' => $audit->new_values,
                ];
            })
            ->values()
            ->all();
    }
}
