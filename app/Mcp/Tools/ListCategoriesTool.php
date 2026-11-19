<?php

namespace App\Mcp\Tools;

use App\Enums\CategoryType;
use App\Mcp\Tools\Concerns\AnswersForUser;
use App\Models\User;
use App\Support\AppDay;
use App\Support\CategoryList\CategoryChoiceOptions;
use App\Support\OperationsFilterOptions;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('The user\'s Categories with their ids, type and Category Group, so a name can be mapped to the id other tools need. Optionally only income or only expense, or with `for_entry` only the Categories an expense or income can be recorded on.')]
#[IsReadOnly]
#[IsIdempotent]
class ListCategoriesTool extends Tool
{
    use AnswersForUser;

    public function handle(Request $request): ResponseFactory
    {
        $user = $this->userOf($request);
        $today = AppDay::today();

        $validated = $request->validate([
            'type' => ['nullable', Rule::enum(CategoryType::class)],
            'for_entry' => ['nullable', Rule::enum(CategoryType::class)],
        ]);

        $forEntry = $validated['for_entry'] ?? null;

        if ($forEntry !== null) {
            return $this->answer($today, ['categories' => $this->selectable($user, CategoryType::from($forEntry))]);
        }

        $type = $validated['type'] ?? null;

        $categories = array_values(array_filter(
            OperationsFilterOptions::categories($user, null),
            fn (array $category): bool => $type === null || $category['type'] === $type,
        ));

        return $this->answer($today, [
            'categories' => array_map(fn (array $category): array => [
                'id' => $category['id'],
                'name' => $category['name'],
                'type' => $category['type'],
                'group' => $category['group'],
                'is_archived' => $category['is_archived'],
            ], $categories),
        ]);
    }

    /**
     * The Categories an entry of the type can take: active, in a Budget in execution, with a non-archived line.
     *
     * @return list<array<string, mixed>>
     */
    private function selectable(User $user, CategoryType $type): array
    {
        $seen = [];
        $categories = [];

        foreach (CategoryChoiceOptions::forType($user, $type) as $option) {
            $category = $option['category'];

            if (isset($seen[$category->id])) {
                continue;
            }

            $seen[$category->id] = true;
            $categories[] = [
                'id' => $category->id,
                'name' => $category->name,
                'type' => $category->type->value,
                'group' => $category->group === null ? null : ['id' => $category->group->id, 'name' => $category->group->name],
                'is_archived' => false,
            ];
        }

        return $categories;
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'type' => $schema->string()->enum(['income', 'expense'])->description('Only Categories of this type.'),
            'for_entry' => $schema->string()->enum(['income', 'expense'])->description('Only active Categories an operation of this type can be recorded on now (use before add-cash-operation-tool; expense also serves refunds).'),
        ];
    }
}
