const ROW_ERROR_KEY = /^rows\.\d+\./;

/**
 * The `duplicate_warning_acknowledged` field carries exactly one error, so the
 * UI composes the Polish sentence from `lang/pl/ui.php`; the server text stays
 * as the fallback when the key is missing, so no evidence is lost.
 */
export function duplicateAcknowledgementErrorText(
    serverError: string | undefined,
    operations: Record<string, string>,
): string | undefined {
    if (serverError === undefined || serverError === '') {
        return undefined;
    }

    return operations.import_duplicate_acknowledgement_required ?? serverError;
}

export function firstRowsError(
    errors: Record<string, string>,
): string | undefined {
    if (errors.rows) {
        return errors.rows;
    }

    const key = Object.keys(errors).find((candidate) =>
        ROW_ERROR_KEY.test(candidate),
    );

    return key === undefined ? undefined : errors[key];
}
