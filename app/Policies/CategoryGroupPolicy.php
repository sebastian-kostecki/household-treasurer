<?php

namespace App\Policies;

use App\Models\CategoryGroup;
use App\Models\User;

class CategoryGroupPolicy
{
    public function update(User $user, CategoryGroup $group): bool
    {
        return $user->id === $group->user_id;
    }

    public function delete(User $user, CategoryGroup $group): bool
    {
        return $user->id === $group->user_id;
    }
}
