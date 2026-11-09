import type { AccountPickerOption } from './account-identity';

export type ImportFormat = 'mbank' | 'bnp_paribas' | 'other';

export type ImportStartAccount = AccountPickerOption & {
    opening_date: string;
};

export type DefaultColumnIndices = {
    date: number;
    description: number;
    amount: number;
    inflow: number;
    outflow: number;
};

function parseCsvLine(line: string, delimiter: string): string[] {
    const cells: string[] = [];
    let current = '';
    let inQuotes = false;

    for (const character of line) {
        if (character === '"') {
            inQuotes = !inQuotes;
            continue;
        }

        if (character === delimiter && !inQuotes) {
            cells.push(current);
            current = '';
            continue;
        }

        current += character;
    }

    cells.push(current);

    return cells;
}

/**
 * Same rule as CsvImportParser::detectDelimiter on the server: a semicolon only
 * when the raw line, quotes included, has more semicolons than commas. The
 * labels must split the line the way the server will, or the chosen column
 * indexes point at other cells in step 2.
 */
function detectDelimiter(line: string): ';' | ',' {
    const semicolons = line.split(';').length - 1;
    const commas = line.split(',').length - 1;

    return semicolons > commas ? ';' : ',';
}

/**
 * Column labels of the first non-empty line: header cells, or "<word> n" for
 * an empty cell or when the file has no header. Empty when the file has no
 * non-empty line.
 */
export function importColumnLabels(
    text: string,
    hasHeader: boolean,
    columnWord: string,
): string[] {
    const firstLine = text.split(/\r?\n/).find((line) => line.trim() !== '');

    if (firstLine === undefined) {
        return [];
    }

    const cells = parseCsvLine(firstLine, detectDelimiter(firstLine));

    return cells.map((cell, index) =>
        hasHeader && cell.trim() !== ''
            ? cell.trim()
            : `${columnWord} ${index + 1}`,
    );
}

export function defaultColumnIndices(count: number): DefaultColumnIndices {
    const last = Math.max(count - 1, 0);

    return {
        date: 0,
        description: Math.min(1, last),
        amount: Math.min(2, last),
        inflow: Math.min(2, last),
        outflow: Math.min(3, last),
    };
}

/**
 * The file extension picks the format only until the user chooses one by hand.
 */
export function formatForChosenFile(
    fileName: string,
    current: ImportFormat,
    chosenByUser: boolean,
): ImportFormat {
    if (chosenByUser) {
        return current;
    }

    const name = fileName.toLowerCase();

    if (name.endsWith('.csv')) {
        return 'mbank';
    }

    if (name.endsWith('.xlsx')) {
        return 'bnp_paribas';
    }

    return current;
}
