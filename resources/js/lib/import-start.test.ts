import { describe, expect, it } from 'vite-plus/test';
import {
    defaultColumnIndices,
    formatForChosenFile,
    importColumnLabels,
} from './import-start';

describe('importColumnLabels', () => {
    it('reads header cells split on a semicolon', () => {
        expect(
            importColumnLabels('Data;Opis;Kwota\n1;2;3', true, 'Kolumna'),
        ).toEqual(['Data', 'Opis', 'Kwota']);
    });

    it('reads header cells split on a comma', () => {
        expect(
            importColumnLabels('date,description,amount\n', true, 'Kolumna'),
        ).toEqual(['date', 'description', 'amount']);
    });

    it('keeps a separator inside quotes within one cell', () => {
        expect(
            importColumnLabels('Data;"Opis, długi";Kwota', true, 'Kolumna'),
        ).toEqual(['Data', 'Opis, długi', 'Kwota']);
    });

    // Parity with CsvImportParser::detectDelimiter: the server counts quoted
    // separators too, so this line is split on the comma there.
    it('picks the separator like the server, counting quoted commas', () => {
        expect(
            importColumnLabels('"Opis, długi";Kwota', true, 'Kolumna'),
        ).toEqual(['Opis, długi;Kwota']);
    });

    it('names an empty header cell by its position', () => {
        expect(importColumnLabels('Data;;Kwota', true, 'Kolumna')).toEqual([
            'Data',
            'Kolumna 2',
            'Kwota',
        ]);
    });

    it('names every column by position when there is no header', () => {
        expect(
            importColumnLabels('2026-01-02;Sklep;-10,00', false, 'Kolumna'),
        ).toEqual(['Kolumna 1', 'Kolumna 2', 'Kolumna 3']);
    });

    it('skips leading blank lines', () => {
        expect(importColumnLabels('\n  \r\nA;B', true, 'Kolumna')).toEqual([
            'A',
            'B',
        ]);
    });

    it('returns no labels for a file without a non-empty line', () => {
        expect(importColumnLabels('', true, 'Kolumna')).toEqual([]);
        expect(importColumnLabels('\n  \n', false, 'Kolumna')).toEqual([]);
    });
});

describe('defaultColumnIndices', () => {
    it('spreads the defaults over four or more columns', () => {
        expect(defaultColumnIndices(5)).toEqual({
            date: 0,
            description: 1,
            amount: 2,
            inflow: 2,
            outflow: 3,
        });
    });

    it('never points outside a single-column file', () => {
        expect(defaultColumnIndices(1)).toEqual({
            date: 0,
            description: 0,
            amount: 0,
            inflow: 0,
            outflow: 0,
        });
    });

    it('clamps to the last column of a three-column file', () => {
        expect(defaultColumnIndices(3)).toEqual({
            date: 0,
            description: 1,
            amount: 2,
            inflow: 2,
            outflow: 2,
        });
    });
});

describe('formatForChosenFile', () => {
    it('picks mBank for a csv file', () => {
        expect(formatForChosenFile('wyciag.CSV', 'other', false)).toBe('mbank');
    });

    it('picks BNP Paribas for an xlsx file', () => {
        expect(formatForChosenFile('wyciag.xlsx', 'other', false)).toBe(
            'bnp_paribas',
        );
    });

    it('keeps the current format for another extension', () => {
        expect(formatForChosenFile('wyciag.txt', 'other', false)).toBe('other');
        expect(formatForChosenFile('wyciag.txt', 'mbank', false)).toBe('mbank');
    });

    it('keeps a format chosen by the user, even for a csv file', () => {
        expect(formatForChosenFile('wyciag.csv', 'other', true)).toBe('other');
        expect(formatForChosenFile('wyciag.xlsx', 'mbank', true)).toBe('mbank');
    });
});
