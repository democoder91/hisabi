<?php

namespace App\Http\Controllers;

use App\Http\Requests\Billing\UpdateBillingCatalogRequest;
use App\Services\BillingCatalogService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class BillingManagementController extends Controller
{
    private BillingCatalogService $billingCatalogService;

    public function __construct(BillingCatalogService $billingCatalogService)
    {
        $this->billingCatalogService = $billingCatalogService;
    }

    public function index(): Response
    {
        return Inertia::render('Billing/Manage', $this->billingCatalogService->managementPayload());
    }

    public function update(UpdateBillingCatalogRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $this->billingCatalogService->updateCatalog(
            strtoupper($validated['currency']),
            $validated['credit_packages'],
            $validated['subscription_plans'],
        );

        return redirect()->route('billing.manage');
    }
}
