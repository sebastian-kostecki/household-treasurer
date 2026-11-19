<?php

namespace App\Models;

use Database\Factories\McpOperationRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The receipt of a cash operation recorded over MCP: which `request_id` of which User created
 * which Financial Operation, so a retry returns the operation instead of a second one (ADR-0110).
 *
 * @property int $id
 * @property int $user_id
 * @property string $request_id
 * @property int $financial_operation_id
 * @property Carbon|null $created_at
 */
#[Fillable([
    'user_id',
    'request_id',
    'financial_operation_id',
])]
class McpOperationRequest extends Model
{
    /** @use HasFactory<McpOperationRequestFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<FinancialOperation, $this>
     */
    public function financialOperation(): BelongsTo
    {
        return $this->belongsTo(FinancialOperation::class);
    }
}
