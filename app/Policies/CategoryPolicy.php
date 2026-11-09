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

    public function restore(User $user, Category $category): bool
    {
        return $this->ownsCategory($user, $category) && $category->isArchived();
    }

    private function ownsCategory(User $user, Category $category): bool
    {
        return $user->id === $category->user_id;
    }
}
