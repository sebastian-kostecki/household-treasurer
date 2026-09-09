<?php

namespace App\Actions;

use App\Models\User;
use App\Support\CsvImport\CapitalMovementImportMapper;
use App\Support\CsvImport\CapitalMovementImportValidator;
use App\Support\CsvImport\CapitalMovementInterestImportValidator;
use App\Support\CsvImport\FinancialOperationImportValidator;
use App\Support\CsvImport\ImportIdentityFactory;
use App\Support\CsvImport\OwnTransferImportMapper;
use App\Support\CsvImport\OwnTransferImportSnapshots;
use App\Support\CsvImport\OwnTransferImportValidator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ImportFinancialOperations
{
    public function __construct(
        private ManageFinancialOperation $manageFinancialOperation,
        private ManageOwnTransfer $manageOwnTransfer,
        private ManageCapitalMovement $manageCapitalMovement,
        private ConvertImportPairFinancialOperation $convertImportPairFinancialOperation,
        private AttachImportPairOwnTransfer $attachImportPairOwnTransfer,
        private FinancialOperationImportValidator $financialOperationValidator,
        private OwnTransferImportValidator $ownTransferValidator,
        private CapitalMovementImportValidator $capitalMovementValidator,
        private CapitalMovementInterestImportValidator $capitalMovementInterestValidator,
        private OwnTransferImportMapper $ownTransferMapper,
        private CapitalMovementImportMapper $capitalMovementMapper,
        private ImportIdentityFactory $importIdentityFactory,
        private OwnTransferImportSnapshots $ownTransferImportSnapshots,
    ) {}

    /**
     * @param  list<array{
     *     index: int,
     *     operation_date: string,
     *     description: string,
     *     counterparty?: string|null,
     *     raw_statement_description?: string|null,
     *     amount_minor_units: int,
     *     type: 'own_transfer',
     *     other_account_id: int,
     *     pair_first_leg_kind?: 'financial_operation'|'own_transfer',
     *     pair_first_leg_id?: int,
     * }|array{
     *     index: int,
     *     operation_date: string,
     *     description: string,
     *     counterparty?: string|null,
     *     raw_statement_description?: string|null,
     *     amount_minor_units: int,
     *     type: 'income'|'expense'|'refund',
     *     category_id: int,
     * }|array{
     *     index: int,
     *     operation_date: string,
     *     description: string,
     *     counterparty?: string|null,
     *     raw_statement_description?: string|null,
     *     amount_minor_units: int,
     *     type: 'capital_movement',
     *     term_deposit_id: int,
     *     interest_amount_minor_units?: int|null,
     *     interest_category_id?: int|null,
     * }>  $rows
     * @return array{financial_operations: int, own_transfers: int, capital_movements: int}
     */
    public function handle(User $user, int $accountId, array $rows): array
    {
        if ($rows === []) {
            throw ValidationException::withMessages([
                'rows' => __('Select at least one row to import.'),
            ]);
        }

        return DB::transaction(function () use ($user, $accountId, $rows): array {
            $financialOperations = 0;
            $ownTransfers = 0;
            $capitalMovements = 0;

            foreach ($rows as $row) {
                if ($row['type'] === 'own_transfer') {
                    $pairFirstLegKind = $row['pair_first_leg_kind'] ?? null;

                    if ($pairFirstLegKind === 'financial_operation' && isset($row['pair_first_leg_id'])) {
                        $this->convertImportPairFinancialOperation->handle(
                            $user,
                            $accountId,
                            (int) $row['pair_first_leg_id'],
                            [
                                'operation_date' => $row['operation_date'],
                                'description' => $row['description'],
                                'raw_statement_description' => $row['raw_statement_description'] ?? null,
                                'amount_minor_units' => $row['amount_minor_units'],
                            ],
                            $row['other_account_id'],
                        );
                        $ownTransfers++;

                        continue;
                    }

                    if ($pairFirstLegKind === 'own_transfer' && isset($row['pair_first_leg_id'])) {
                        $this->attachImportPairOwnTransfer->handle(
                            $user,
                            $accountId,
                            (int) $row['pair_first_leg_id'],
                            [
                                'operation_date' => $row['operation_date'],
                                'description' => $row['description'],
                                'raw_statement_description' => $row['raw_statement_description'] ?? null,
                                'amount_minor_units' => $row['amount_minor_units'],
                            ],
                            $row['other_account_id'],
                        );
                        $ownTransfers++;

                        continue;
                    }

                    $validated = $this->ownTransferMapper->map(
                        $accountId,
                        $row['other_account_id'],
                        $row['operation_date'],
                        $row['description'],
                        $row['amount_minor_units'],
                    );
                    $rawStatementDescription = $row['raw_statement_description'] ?? null;
                    $validated = [
                        ...$validated,
                        ...($rawStatementDescription !== null
                            ? $this->ownTransferImportSnapshots->rawStatementDescriptionForImportedAccount(
                                $row['amount_minor_units'],
                                $rawStatementDescription,
                            )
                            : []),
                        ...$this->importIdentityFactory->forOwnTransferImportedAccount(
                            $user,
                            $accountId,
                            $row['operation_date'],
                            $row['amount_minor_units'],
                            $rawStatementDescription,
                        ),
                    ];

                    $errors = $this->ownTransferValidator->validate($user, $validated);

                    if ($errors !== []) {
                        throw ValidationException::withMessages([
                            'rows' => $errors[0],
                        ]);
                    }

                    $this->manageOwnTransfer->create($user, $validated);
                    $ownTransfers++;

                    continue;
                }

                if ($row['type'] === 'capital_movement') {
                    $interestAmountMinorUnits = $row['interest_amount_minor_units'] ?? null;
                    $interestCategoryId = $row['interest_category_id'] ?? null;
                    $interestErrors = $this->capitalMovementInterestValidator->validate(
                        $row['amount_minor_units'],
                        $interestAmountMinorUnits,
                        $interestCategoryId,
                    );

                    if ($interestErrors !== []) {
                        throw ValidationException::withMessages([
                            'rows' => $interestErrors[0],
                        ]);
                    }

                    $splitAmounts = $this->capitalMovementInterestValidator->splitAmounts(
                        $row['amount_minor_units'],
                        $interestAmountMinorUnits,
                    );
                    $capitalAmountMinorUnits = $splitAmounts !== null
                        ? $splitAmounts['capital_amount_minor_units']
                        : abs($row['amount_minor_units']);

                    $validated = $this->capitalMovementMapper->map(
                        $accountId,
                        $row['term_deposit_id'],
                        $row['operation_date'],
                        $row['description'],
                        $row['amount_minor_units'],
                    );
                    $validated['amount_minor_units'] = $capitalAmountMinorUnits;
                    $rawStatementDescription = $row['raw_statement_description'] ?? null;
                    $importIdentity = $this->importIdentityFactory->forCapitalMovement(
                        $user,
                        $accountId,
                        $row['operation_date'],
                        $row['amount_minor_units'],
                        $rawStatementDescription,
                    );
                    $validated = [
                        ...$validated,
                        'raw_statement_description' => $rawStatementDescription,
                        ...$importIdentity,
                    ];

                    $errors = $this->capitalMovementValidator->validate($user, $validated);

                    if ($errors !== []) {
                        throw ValidationException::withMessages([
                            'rows' => $errors[0],
                        ]);
                    }

                    $this->manageCapitalMovement->create($user, $validated);
                    $capitalMovements++;

                    if ($splitAmounts !== null) {
                        $incomeValidated = [
                            'operation_date' => $row['operation_date'],
                            'amount_minor_units' => $splitAmounts['interest_amount_minor_units'],
                            'description' => $row['description'],
                            'counterparty' => $row['counterparty'] ?? null,
                            'raw_statement_description' => $rawStatementDescription,
                            'account_id' => $accountId,
                            'category_id' => $interestCategoryId,
                            ...$importIdentity,
                        ];

                        $incomeErrors = $this->financialOperationValidator->validate(
                            $user,
                            'income',
                            $incomeValidated,
                        );

                        if ($incomeErrors !== []) {
                            throw ValidationException::withMessages([
                                'rows' => $incomeErrors[0],
                            ]);
                        }

                        $this->manageFinancialOperation->createIncome($user, $incomeValidated);
                        $financialOperations++;
                    }

                    continue;
                }

                $amountMinorUnits = match ($row['type']) {
                    'income' => abs($row['amount_minor_units']),
                    'expense' => abs($row['amount_minor_units']),
                    'refund' => -abs($row['amount_minor_units']),
                };

                $validated = [
                    'operation_date' => $row['operation_date'],
                    'amount_minor_units' => $amountMinorUnits,
                    'description' => $row['description'],
                    'counterparty' => $row['counterparty'] ?? null,
                    'raw_statement_description' => $row['raw_statement_description'] ?? null,
                    'account_id' => $accountId,
                    'category_id' => $row['category_id'],
                    ...$this->importIdentityFactory->forFinancialOperation(
                        $user,
                        $accountId,
                        $row['operation_date'],
                        $row['amount_minor_units'],
                        $row['raw_statement_description'] ?? null,
                    ),
                ];

                $errors = $this->financialOperationValidator->validate($user, $row['type'], $validated);

                if ($errors !== []) {
                    throw ValidationException::withMessages([
                        'rows' => $errors[0],
                    ]);
                }

                if ($row['type'] === 'income') {
                    $this->manageFinancialOperation->createIncome($user, $validated);
                } else {
                    $this->manageFinancialOperation->createExpense($user, $validated);
                }

                $financialOperations++;
            }

            return [
                'financial_operations' => $financialOperations,
                'own_transfers' => $ownTransfers,
                'capital_movements' => $capitalMovements,
            ];
        });
    }
}
