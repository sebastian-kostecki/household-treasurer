import { describe, expect, it } from 'vite-plus/test';
import { groupOperationsByDay, groupRowsByDay } from './operations-day-groups';

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

describe('groupRowsByDay', () => {
    it('groups consecutive rows by the date the callback returns', () => {
        const rows = [
            { id: 1, transfer_date: '2026-10-28' },
            { id: 2, transfer_date: '2026-10-28' },
            { id: 3, transfer_date: '2026-09-28' },
        ];

        expect(groupRowsByDay(rows, (row) => row.transfer_date)).toEqual([
            { date: '2026-10-28', rows: [rows[0], rows[1]] },
            { date: '2026-09-28', rows: [rows[2]] },
        ]);
    });

    it('starts a new group when a date comes back after another day', () => {
        const rows = [
            { movement_date: '2026-10-27' },
            { movement_date: '2026-10-26' },
            { movement_date: '2026-10-27' },
        ];

        expect(
            groupRowsByDay(rows, (row) => row.movement_date).map(
                (group) => group.date,
            ),
        ).toEqual(['2026-10-27', '2026-10-26', '2026-10-27']);
    });
});
