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

    private function ownsCategory(User $user, Category $category): bool
    {
        return $user->id === $category->budget->user_id;
    }
}
