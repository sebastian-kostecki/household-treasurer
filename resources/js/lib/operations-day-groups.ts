export type OperationsDayGroup<T> = {
    date: string;
    sumMinorUnits: number;
    rows: T[];
};

export type DayGroup<T> = {
    date: string;
    rows: T[];
};

/**
 * Dzieli wiersze strony na grupy dni (nowa grupa przy zmianie daty, kolejność
 * zachowana). Transfery własne i ruchy kapitału mają wiersz dnia bez sumy.
 */
export function groupRowsByDay<T>(
    rows: T[],
    dateOf: (row: T) => string,
): DayGroup<T>[] {
    const groups: DayGroup<T>[] = [];

    for (const row of rows) {
        const date = dateOf(row);
        const last = groups[groups.length - 1];

        if (last !== undefined && last.date === date) {
            last.rows.push(row);

            continue;
        }

        groups.push({ date, rows: [row] });
    }

    return groups;
}

/**
 * Grupy dni operacji finansowych. Sumę dnia podaje serwer (cały dzień, z filtrami), nie wiersze.
 */
export function groupOperationsByDay<T extends { operation_date: string }>(
    rows: T[],
    daySums: Record<string, number>,
): OperationsDayGroup<T>[] {
    return groupRowsByDay(rows, (row) => row.operation_date).map((group) => ({
        ...group,
        sumMinorUnits: daySums[group.date] ?? 0,
    }));
}
