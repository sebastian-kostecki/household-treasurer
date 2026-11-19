<?php

namespace App\Support\CsvImport;

use RuntimeException;

/**
 * A snapshot the reference set of the suggestion benchmark cannot be built from:
 * an unsupported version, no rows, or a row that does not fit the snapshot.
 */
class ImportSuggestionReferenceException extends RuntimeException {}
