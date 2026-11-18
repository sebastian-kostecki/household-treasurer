/// <reference types="node" />
import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { describe, expect, it } from 'vite-plus/test';
import {
    handEntryToSpendChanges,
    previewToSpendReadings,
} from './entry-to-spend-warning';
import type {
    EntryToSpendInputs,
    HandEntryToSpendState,
    ToSpendEntryChange,
    ToSpendReading,
} from './entry-to-spend-warning';

type Scenario = {
    key: string;
    today: string;
    inputs: EntryToSpendInputs;
    dialog: HandEntryToSpendState;
    changes: ToSpendEntryChange[];
    readings: ToSpendReading[];
    derivation: string;
};

type Scenarios = {
    format: number;
    scenarios: Scenario[];
};

// Same file as tests/Feature/EntryToSpendParityTest.php; the inputs and readings are derived by hand from the glossary.
const FIXTURE = join(
    dirname(fileURLToPath(import.meta.url)),
    '../../../tests/Fixtures/amount-invariants/entry-to-spend-scenarios.json',
);

const file = JSON.parse(readFileSync(FIXTURE, 'utf8')) as Scenarios;

describe('entry To Spend parity scenarios', () => {
    it('reads format 1 with scenarios', () => {
        expect(file.format).toBe(1);
        expect(file.scenarios.length).toBeGreaterThan(0);
    });

    for (const scenario of file.scenarios) {
        describe(scenario.key, () => {
            it('previews the hand-computed readings on the hand-written inputs', () => {
                expect(
                    previewToSpendReadings(scenario.inputs, scenario.changes),
                ).toEqual(scenario.readings);
            });

            it('builds the changes of the entry dialog from its state', () => {
                expect(handEntryToSpendChanges(scenario.dialog)).toEqual(
                    scenario.changes,
                );
            });
        });
    }
});
