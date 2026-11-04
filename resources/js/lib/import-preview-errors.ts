const ROW_ERROR_KEY = /^rows\.\d+\./;

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
