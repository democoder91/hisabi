<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Budget\Services\BudgetService;
use App\Http\Controllers\Controller;
use App\Http\Queries\Budget\GetBudgetsQuery\GetBudgetsQuery;
use App\Http\Queries\Budget\GetBudgetsQuery\GetBudgetsQueryHandler;
use App\Http\Requests\Api\V1\CreateBudgetRequest;
use App\Http\Requests\Api\V1\UpdateBudgetRequest;
use App\Http\Resources\BudgetResource;
use Illuminate\Http\JsonResponse;

class BudgetController extends Controller
{
    public function __construct(
        private readonly GetBudgetsQueryHandler $getBudgetsQueryHandler,
        private readonly BudgetService $budgetService,
    ) {}

    public function index(): JsonResponse
    {
        $query = new GetBudgetsQuery();

        return $this->getBudgetsQueryHandler->handle($query)->toResponse();
    }

    public function store(CreateBudgetRequest $request): JsonResponse
    {
        $budget = $this->budgetService->create($request->validated());

        return response()->json([
            'budget' => new BudgetResource($budget),
        ], 201);
    }

    public function update(UpdateBudgetRequest $request, int $id): JsonResponse
    {
        $budget = $this->budgetService->findOwnedOrFail($id);
        $budget = $this->budgetService->update($budget, $request->validated());

        return response()->json([
            'budget' => new BudgetResource($budget),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $budget = $this->budgetService->findOwnedOrFail($id);
        $budget = $this->budgetService->delete($budget);

        return response()->json([
            'budget' => new BudgetResource($budget),
        ]);
    }
}
