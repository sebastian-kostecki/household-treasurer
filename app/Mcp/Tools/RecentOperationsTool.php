<?php

namespace App\Mcp\Tools;

use App\Http\Requests\IndexOperationsRequest;
use App\Mcp\Tools\Concerns\AnswersForUser;
use App\Models\FinancialOperation;
use App\Support\AppDay;
use App\Support\FinancialOperationRows;
use App\Support\OperationsListQuery;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Arr;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('The newest financial operations (expenses, incomes, refunds), newest first, at most 50 per call. Filter by Category id (see list-categories-tool), account id, a date range and a text search.')]
#[IsReadOnly]
#[IsIdempotent]
class RecentOperationsTool extends Tool
{
    use AnswersForUser;

    private const int MAX_LIMIT = 50;

    private const int DEFAULT_LIMIT = 20;

    public function handle(Request $request): ResponseFactory
    {
        $user = $this->userOf($request);
        $today = AppDay::today();

        $validated = $request->validate([
            ...Arr::only(IndexOperationsRequest::listRules($user->id), ['q', 'category_id', 'account_id', 'date_from', 'date_to']),
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_LIMIT],
        ]);

        $limit = (int) ($validated['limit'] ?? self::DEFAULT_LIMIT);
        $filters = OperationsListQuery::fromValidated(Arr::only($validated, OperationsListQuery::FILTER_KEYS));

        $operations = FinancialOperationRows::query($user, $filters)->limit($limit)->get();

        return $this->answer($today, [
            'limit' => $limit,
            'returned' => $operations->count(),
            'operations' => $operations->map(fn (FinancialOperation $operation): array => [
                ...FinancialOperationRows::row($operation),
                'account_name' => $operation->account->name,
            ])->all(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'category_id' => $schema->integer()->description('Id of one of the user\'s Categories (list-categories-tool).'),
            'account_id' => $schema->integer()->description('Id of one of the user\'s accounts (account-balances-tool).'),
            'date_from' => $schema->string()->format('date')->description('First day, YYYY-MM-DD.'),
            'date_to' => $schema->string()->format('date')->description('Last day, YYYY-MM-DD; not before date_from.'),
            'q' => $schema->string()->max(255)->description('Text searched in the description and the counterparty.'),
            'limit' => $schema->integer()->min(1)->max(self::MAX_LIMIT)->description('How many operations, 1-50; default 20.'),
        ];
    }
}
