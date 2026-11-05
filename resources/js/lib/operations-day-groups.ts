export type OperationsDayGroup<T> = {
    date: string;
    sumMinorUnits: number;
    rows: T[];
};

/**
 * Dzieli wiersze strony na grupy dni (nowa grupa przy zmianie daty, kolejność
 * zachowana). Sumę dnia podaje serwer (cały dzień, z filtrami), nie wiersze.
 */
export function groupOperationsByDay<T extends { operation_date: string }>(
    rows: T[],
    daySums: Record<string, number>,
): OperationsDayGroup<T>[] {
    const groups: OperationsDayGroup<T>[] = [];

    for (const row of rows) {
        const last = groups[groups.length - 1];

        if (last !== undefined && last.date === row.operation_date) {
            last.rows.push(row);

            continue;
        }

        groups.push({
            date: row.operation_date,
            sumMinorUnits: daySums[row.operation_date] ?? 0,
            rows: [row],
        });
    }

    return groups;
}
