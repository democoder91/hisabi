<?php

namespace App\Http\Controllers;

use App\Http\Requests\Billing\ReorderBillingProductsRequest;
use App\Http\Requests\Billing\StoreCreditPackageRequest;
use App\Http\Requests\Billing\UpdateCreditPackageRequest;
use App\Models\BillingProduct;
use App\Services\BillingCatalogService;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

class BillingCreditPackageController extends Controller
{
    private BillingCatalogService $billingCatalogService;

    public function __construct(BillingCatalogService $billingCatalogService)
    {
        $this->billingCatalogService = $billingCatalogService;
    }

    public function store(StoreCreditPackageRequest $request): RedirectResponse
    {
        $this->billingCatalogService->createCreditPackage($request->validated());

        return redirect()->route('billing.manage');
    }

    public function update(UpdateCreditPackageRequest $request, BillingProduct $creditPackage): RedirectResponse
    {
        $this->billingCatalogService->updateCreditPackage($creditPackage, $request->validated());

        return redirect()->route('billing.manage');
    }

    public function destroy(BillingProduct $creditPackage): RedirectResponse
    {
        $this->billingCatalogService->deleteCreditPackage($creditPackage);

        return redirect()->route('billing.manage');
    }

    public function reorder(ReorderBillingProductsRequest $request): Response
    {
        $this->billingCatalogService->reorderCreditPackages($request->validated('product_ids'));

        if ($request->expectsJson()) {
            return response()->noContent();
        }

        return redirect()->route('billing.manage');
    }
}