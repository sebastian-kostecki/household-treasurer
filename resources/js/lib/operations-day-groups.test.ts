import { describe, expect, it } from 'vite-plus/test';
import { groupOperationsByDay } from './operations-day-groups';

describe('groupOperationsByDay', () => {
    it('groups consecutive rows by date and takes sums from the map', () => {
        const rows = [
            { id: 1, operation_date: '2026-11-04' },
            { id: 2, operation_date: '2026-11-04' },
            { id: 3, operation_date: '2026-11-03' },
        ];

        const groups = groupOperationsByDay(rows, {
            '2026-11-04': 12500,
            '2026-11-03': -4500,
        });

        expect(groups).toHaveLength(2);
        expect(groups[0]).toEqual({
            date: '2026-11-04',
            sumMinorUnits: 12500,
            rows: [rows[0], rows[1]],
        });
        expect(groups[1].sumMinorUnits).toBe(-4500);
        expect(groups[1].rows).toEqual([rows[2]]);
    });

    it('uses zero when the map has no sum for a day', () => {
        const groups = groupOperationsByDay(
            [{ operation_date: '2026-11-04' }],
            {},
        );

        expect(groups[0].sumMinorUnits).toBe(0);
    });

    it('returns no groups for an empty list', () => {
        expect(groupOperationsByDay([], { '2026-11-04': 1 })).toEqual([]);
    });
});
