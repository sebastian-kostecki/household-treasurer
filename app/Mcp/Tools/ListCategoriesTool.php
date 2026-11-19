<?php

namespace App\Mcp\Tools;

use App\Enums\CategoryType;
use App\Mcp\Tools\Concerns\AnswersForUser;
use App\Support\AppDay;
use App\Support\OperationsFilterOptions;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('The user\'s Categories with their ids, type and Category Group, so a name can be mapped to the id other tools need. Optionally only income or only expense.')]
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
        ]);

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
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'type' => $schema->string()->enum(['income', 'expense'])->description('Only Categories of this type.'),
        ];
    }
}
