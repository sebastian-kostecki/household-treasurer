<?php

namespace App\Mcp\Tools;

use App\Actions\RecordCashOperation;
use App\Mcp\Tools\Concerns\AnswersForUser;
use App\Support\AppDay;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Records one cash expense, expense refund or income on the user\'s cash account and returns the operation, the account balance and To Spend before and after, and the Available Limit of the Category. Generate one new random `request_id` (8-64 characters) per entry and reuse the very same one when you retry that entry, so it is recorded once. Look the Category up with list-categories-tool (`for_entry`); when more than one Category could fit, ask the user instead of guessing. `amount` is a positive text like "23,50"; `kind` gives the sign. When `to_spend_warning` is true, tell the user that To Spend went below zero.')]
#[IsReadOnly(false)]
#[IsDestructive(false)]
#[IsIdempotent]
class AddCashOperationTool extends Tool
{
    use AnswersForUser;

    /**
     * @var array<string, mixed>|null
     */
    protected ?array $meta = ['anthropic/requiresUserInteraction' => true];

    public function __construct(private readonly RecordCashOperation $recordCashOperation) {}

    public function shouldRegister(): bool
    {
        return filter_var(config('services.mcp.write_enabled'), FILTER_VALIDATE_BOOLEAN);
    }

    public function handle(Request $request): ResponseFactory
    {
        $user = $this->userOf($request);

        $result = $this->recordCashOperation->handle($user, $request->all());

        return $this->answer(AppDay::today(), $result->toArray());
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'request_id' => $schema->string()->min(8)->max(64)->required()->description('One new random id per entry; reuse it unchanged when retrying the same entry.'),
            'kind' => $schema->string()->enum(['expense', 'refund', 'income'])->required()->description('expense, refund of an expense, or income.'),
            'amount' => $schema->string()->required()->description('Positive amount in zł as text, for example "23,50"; the sign comes from kind.'),
            'category_id' => $schema->integer()->required()->description('Id from list-categories-tool with for_entry matching the kind (expense for expense and refund).'),
            'description' => $schema->string()->max(255)->required()->description('What it was, for example "Pieczywo".'),
            'account_id' => $schema->integer()->description('Cash account id; only needed when the user has more than one cash account.'),
            'operation_date' => $schema->string()->format('date')->description('YYYY-MM-DD, not in the future; default is app_day (today).'),
            'counterparty' => $schema->string()->max(255)->description('Payer or payee, optional.'),
        ];
    }
}
