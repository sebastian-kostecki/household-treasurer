<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;

class CategoryPolicy
{
    public function update(User $user, Category $category): bool
    {
        return $this->ownsCategory($user, $category);
    }

    public function delete(User $user, Category $category): bool
    {
        return $this->ownsCategory($user, $category) && $category->budget->isDraft();
    }

    public function archive(User $user, Category $category): bool
    {
        return $this->ownsCategory($user, $category)
            && $category->budget->isExecution()
            && ! $category->isArchived();
    }

    public function restore(User $user, Category $category): bool
    {
        return $this->ownsCategory($user, $category) && $category->isArchived();
    }

    public function alignCurrentPlan(User $user, Category $category): bool
    {
        return $this->ownsCategory($user, $category)
            && $category->budget->isExecution()
            && ! $category->isArchived();
    }

    private function ownsCategory(User $user, Category $category): bool
    {
        return $user->id === $category->budget->user_id;
    }
}
