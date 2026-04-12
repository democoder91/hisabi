<?php

namespace App\Http\Commands\Category\DeleteCategoryCommand;

use App\Domains\Category\Models\Category;
use App\Http\Resources\CategoryResource;
use Illuminate\Http\JsonResponse;

readonly class DeleteCategoryCommandResponse
{
    public function __construct(
        private Category $category
    ) {}

    public function toResponse(): JsonResponse
    {
        return response()->json([
            'category' => new CategoryResource($this->category->loadCount('transactions')),
        ]);
    }
}
