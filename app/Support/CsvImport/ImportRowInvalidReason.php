<?php

namespace App\Support\CsvImport;

/**
 * Why a preview row is rejected. The UI turns the code into a Polish sentence
 * through the `operations.import_invalid_reason_<value>` keys in lang/pl/ui.php.
 */
enum ImportRowInvalidReason: string
{
    case MissingDate = 'missing_date';
    case FutureDate = 'future_date';
    case BeforeAccountOpening = 'before_account_opening';
    case MissingDescription = 'missing_description';
    case ForeignCurrency = 'foreign_currency';
    case MissingAmount = 'missing_amount';
    case ZeroAmount = 'zero_amount';
}
