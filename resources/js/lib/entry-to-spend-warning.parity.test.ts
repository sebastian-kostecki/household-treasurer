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

// Candidate 1: a Fixed Charge paid back-dated into February moves the March Proposal on the server (March 4850 -> 4820),
// yet the preview has no March reading. The glossary (To Spend) limits the warning to the month of the operation and
// the later months with a plan on which the entry acts through Collected, so the missing reading is the rule.
function previewTitle(key: string): string {
    return key === 'fixed-charge-backdated-proposal'
        ? 'previews only the month of the operation; the missing March reading agrees with the To Spend warning scope in the glossary'
        : key === 'january-joining-category'
          ? 'previews the hand-computed readings on the hand-written inputs (issue #273: a joining Category has no plan in the inputs)'
          : 'previews the hand-computed readings on the hand-written inputs';
}

// Candidate 2 stays skipped until issue #273 is resolved; the Feature test of the same scenario stays active.
const SKIPPED_PREVIEW_SCENARIOS = ['january-joining-category'];

describe('entry To Spend parity scenarios', () => {
    it('reads format 1 with scenarios', () => {
        expect(file.format).toBe(1);
        expect(file.scenarios.length).toBeGreaterThan(0);
    });

    for (const scenario of file.scenarios) {
        describe(scenario.key, () => {
            const previewIt = SKIPPED_PREVIEW_SCENARIOS.includes(scenario.key)
                ? it.skip
                : it;

            previewIt(previewTitle(scenario.key), () => {
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
