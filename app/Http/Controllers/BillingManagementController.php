<?php

namespace App\Http\Controllers;

use App\Http\Requests\Billing\UpdateBillingCurrencyRequest;
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

    public function updateCurrency(UpdateBillingCurrencyRequest $request): RedirectResponse
    {
        $this->billingCatalogService->updateCurrency($request->validated('currency'));

        return redirect()->route('billing.manage');
    }
}
