<?php

namespace App\Support\SheetLoad;

use App\Models\BudgetLine;
use App\Models\Category;
use App\Models\User;
use App\Support\CategoryList\CategoryNameKey;

/**
 * Checks the mapping against the target database: every Category id belongs to the User and has the expected name and type.
 */
final class SheetMappingCategoryCheck
{
    /**
     * @return list<string>
     */
    public function problems(User $user, SheetMapping $mapping): array
    {
        $expected = $mapping->expectedCategories();
        $found = Category::query()
            ->where('user_id', $user->id)
            ->whereIn('id', array_keys($expected))
            ->get()
            ->keyBy('id');
        $problems = [];

        foreach ($expected as $id => $category) {
            $actual = $found->get($id);

            if ($actual === null) {
                $problems[] = "Kategoria {$id} („{$category['name']}”) nie istnieje u tego użytkownika.";
            } elseif ($actual->name !== $category['name'] || $actual->type !== $category['type']) {
                $problems[] = "Kategoria {$id}: oczekiwano „{$category['name']}” ({$category['type']->value}), jest „{$actual->name}” ({$actual->type->value}).";
            }
        }

        return $problems;
    }

    /**
     * The new Categories of the plan that a load must not reuse: an archived Category without a Budget Line whose
     * definition has no `rhythm_start` looks exactly like one the load created, so an unload would delete it (ADR-0102).
     *
     * @return list<string>
     */
    public function newCategoryProblems(User $user, SheetMapping $mapping, SheetLoadPlan $plan): array
    {
        $problems = [];

        foreach (array_keys($plan->categoryTotals()) as $target) {
            if (! $mapping->isNewCategory($target)) {
                continue;
            }

            $existing = $this->existingNewCategory($user, $mapping, (string) $target);

            if ($existing !== null
                && $mapping->newCategories()[(string) $target]['rhythm_start'] === null
                && $existing->archived_at !== null
                && ! BudgetLine::query()->where('category_id', $existing->id)->exists()) {
                $problems[] = "Kategoria „{$existing->name}” (id {$existing->id}) jest zarchiwizowana i nie ma linii w żadnym Budżecie: wycofanie nie odróżniłoby jej od kategorii utworzonej przez wczytanie. Przywróć ją z archiwum albo zmień jej nazwę.";
            }
        }

        return $problems;
    }

    /**
     * The Category that a load would reuse instead of creating the new Category with this key, if there is one.
     */
    public function existingNewCategory(User $user, SheetMapping $mapping, string $key): ?Category
    {
        $new = $mapping->newCategories()[$key];

        return Category::query()
            ->where('user_id', $user->id)
            ->where('type', $new['type'])
            ->where('name_key', CategoryNameKey::of($new['name']))
            ->first();
    }
}
