<?php

namespace App\Support\CsvImport;

use InvalidArgumentException;

/**
 * A statement file the user has to fix (too many rows, no bank header, unreadable file). Its
 * message is translated and shown on the file field. Import code catches only this class, so a
 * library or programming error that happens to be an InvalidArgumentException is not shown to the
 * user as a file problem and reaches the exception handler instead.
 */
class ImportFileRejected extends InvalidArgumentException {}
