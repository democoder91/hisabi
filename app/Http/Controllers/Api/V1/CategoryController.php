<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Category\Services\CategoryService;
use App\Http\Controllers\Controller;
use App\Http\Commands\Category\CreateCategoryCommand\CreateCategoryCommand;
use App\Http\Commands\Category\CreateCategoryCommand\CreateCategoryCommandHandler;
use App\Http\Commands\Category\UpdateCategoryCommand\UpdateCategoryCommand;
use App\Http\Commands\Category\UpdateCategoryCommand\UpdateCategoryCommandHandler;
use App\Http\Commands\Category\DeleteCategoryCommand\DeleteCategoryCommand;
use App\Http\Commands\Category\DeleteCategoryCommand\DeleteCategoryCommandHandler;
use App\Http\Queries\Category\GetAllCategoriesQuery\GetAllCategoriesQuery;
use App\Http\Queries\Category\GetAllCategoriesQuery\GetAllCategoriesQueryHandler;
use App\Http\Requests\Api\V1\CreateCategoryRequest;
use App\Http\Requests\Api\V1\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    private GetAllCategoriesQueryHandler $getAllCategoriesQueryHandler;
    private CreateCategoryCommandHandler $createCategoryCommandHandler;
    private UpdateCategoryCommandHandler $updateCategoryCommandHandler;
    private DeleteCategoryCommandHandler $deleteCategoryCommandHandler;
    private CategoryService $categoryService;

    public function __construct(
        GetAllCategoriesQueryHandler $getAllCategoriesQueryHandler,
        CreateCategoryCommandHandler $createCategoryCommandHandler,
        UpdateCategoryCommandHandler $updateCategoryCommandHandler,
        DeleteCategoryCommandHandler $deleteCategoryCommandHandler,
        CategoryService $categoryService
    ) {
        $this->getAllCategoriesQueryHandler = $getAllCategoriesQueryHandler;
        $this->createCategoryCommandHandler = $createCategoryCommandHandler;
        $this->updateCategoryCommandHandler = $updateCategoryCommandHandler;
        $this->deleteCategoryCommandHandler = $deleteCategoryCommandHandler;
        $this->categoryService = $categoryService;
    }

    public function all(): JsonResponse
    {
        $query = new GetAllCategoriesQuery();

        return $this->getAllCategoriesQueryHandler->handle($query)->toResponse();
    }

    public function show(int $id): JsonResponse
    {
        $category = $this->categoryService->findLedgerCategoryOrFail($id)
            ->loadMissing('user:id,name', 'account')
            ->loadCount('transactions');

        return response()->json([
            'category' => new CategoryResource($category),
        ]);
    }

    public function store(CreateCategoryRequest $request): JsonResponse
    {
        $command = new CreateCategoryCommand($request->validated());

        return $this->createCategoryCommandHandler->handle($command)->toResponse();
    }

    public function update(UpdateCategoryRequest $request, int $id): JsonResponse
    {
        $command = new UpdateCategoryCommand($id, $request->validated());

        return $this->updateCategoryCommandHandler->handle($command)->toResponse();
    }

    public function destroy(int $id): JsonResponse
    {
        $command = new DeleteCategoryCommand($id);

        return $this->deleteCategoryCommandHandler->handle($command)->toResponse();
    }
}
