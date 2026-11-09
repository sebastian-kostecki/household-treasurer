<?php

namespace App\Policies;

use App\Enums\BudgetPhase;
use App\Models\Category;
use App\Models\User;

class CategoryPolicy
{
    public function update(User $user, Category $category): bool
    {
        return $this->ownsCategory($user, $category);
    }

    public function archive(User $user, Category $category): bool
    {
        return $this->ownsCategory($user, $category)
            && ! $category->isArchived()
            && $category->budgetLines()->whereHas('budget', fn ($budget) => $budget->where('phase', BudgetPhase::Execution))->exists();
    }

    public function restore(User $user, Category $category): bool
    {
        return $this->ownsCategory($user, $category) && $category->isArchived();
    }

    private function ownsCategory(User $user, Category $category): bool
    {
        return $user->id === $category->user_id;
    }
}
