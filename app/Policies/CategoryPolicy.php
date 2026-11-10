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

    public function archive(User $user, Category $category): bool
    {
        return $this->ownsCategory($user, $category) && ! $category->isArchived();
    }

    /**
     * Ownership only: a Category with a history is refused by DeleteCategory,
     * with a message instead of a 403.
     */
    public function delete(User $user, Category $category): bool
    {
        return $this->ownsCategory($user, $category);
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
