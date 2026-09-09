<?php

namespace App\Enums;

enum OperationsListSection: string
{
    case FinancialOperations = 'financial_operations';
    case OwnTransfers = 'own_transfers';
    case CapitalMovements = 'capital_movements';
}
