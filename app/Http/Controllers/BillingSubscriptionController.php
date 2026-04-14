<?php

namespace App\Http\Controllers;

use App\Http\Requests\Billing\ReorderBillingProductsRequest;
use App\Http\Requests\Billing\StoreSubscriptionPlanRequest;
use App\Http\Requests\Billing\UpdateSubscriptionPlanRequest;
use App\Models\BillingProduct;
use App\Services\BillingCatalogService;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

class BillingSubscriptionController extends Controller
{
    private BillingCatalogService $billingCatalogService;

    public function __construct(BillingCatalogService $billingCatalogService)
    {
        $this->billingCatalogService = $billingCatalogService;
    }

    public function store(StoreSubscriptionPlanRequest $request): RedirectResponse
    {
        $this->billingCatalogService->createSubscriptionPlan($request->validated());

        return redirect()->route('billing.manage');
    }

    public function update(UpdateSubscriptionPlanRequest $request, BillingProduct $subscriptionPlan): RedirectResponse
    {
        $this->billingCatalogService->updateSubscriptionPlan($subscriptionPlan, $request->validated());

        return redirect()->route('billing.manage');
    }

    public function destroy(BillingProduct $subscriptionPlan): RedirectResponse
    {
        $this->billingCatalogService->deleteSubscriptionPlan($subscriptionPlan);

        return redirect()->route('billing.manage');
    }

    public function reorder(ReorderBillingProductsRequest $request): Response
    {
        $this->billingCatalogService->reorderSubscriptionPlans($request->validated('product_ids'));

        if ($request->expectsJson()) {
            return response()->noContent();
        }

        return redirect()->route('billing.manage');
    }
}