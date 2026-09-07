<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;

class CategoryPolicy
{
    public function update(User $user, Category $category): bool
    {
        return $this->ownsDraftCategory($user, $category);
    }

    public function delete(User $user, Category $category): bool
    {
        return $this->ownsDraftCategory($user, $category);
    }

    private function ownsDraftCategory(User $user, Category $category): bool
    {
        $budget = $category->budget;

        return $user->id === $budget->user_id && $budget->isDraft();
    }
}
