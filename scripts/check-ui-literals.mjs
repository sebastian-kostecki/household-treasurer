#!/usr/bin/env node
// Skan literałów UI w oczyszczonych plikach widoku (kontrakt 10x-ui).
// Kolejne przebiegi 10x-ui rozszerzają listę FILES o nowe oczyszczone widoki.
// Argumenty CLI zastępują listę: node scripts/check-ui-literals.mjs <plik>...
import { readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const FILES = [
    'resources/js/pages/Dashboard.vue',
    'resources/js/components/DashboardHoldings.vue',
    'resources/js/components/DashboardNotices.vue',
    'resources/js/components/DashboardCategorySection.vue',
    'resources/js/components/BudgetPlanSummaryStrip.vue',
    'resources/js/components/CategoryMeterRow.vue',
    'resources/js/components/CreateBudgetDialog.vue',
    'resources/js/components/StatTile.vue',
    'resources/js/pages/dev/DashboardKitchenSink.vue',
    'resources/js/components/AppSidebar.vue',
    'resources/js/components/BudgetYearSwitcher.vue',
    'resources/js/components/NavMain.vue',
    'resources/js/components/NavUser.vue',
    'resources/js/components/UserInfo.vue',
    'resources/js/components/UserMenuContent.vue',
    'resources/js/components/AppLogo.vue',
    'resources/js/components/AppLogoIcon.vue',
    'resources/js/components/AppSidebarHeader.vue',
    'resources/js/layouts/app/AppSidebarLayout.vue',
    'resources/js/pages/dev/SidebarKitchenSink.vue',
];

const PALETTE =
    'red|amber|green|blue|slate|gray|zinc|neutral|stone|orange|yellow|lime|emerald|teal|cyan|sky|indigo|violet|purple|fuchsia|pink|rose';
const PREFIXES =
    'bg|text|border(?:-[trblxyse])?|ring|fill|stroke|from|to|via|divide|shadow|outline|decoration|accent|caret|placeholder';
// Hex colour: not an HTML entity (&#128;), not part of a word, not an anchor (href="#add").
const PATTERN = new RegExp(
    String.raw`(?<![&\w])(?<!href=["'])#[0-9a-f]{3,8}\b|(?:rgb|rgba|hsl|hsla|oklch)\(|-\[[0-9.]+(?:px|rem)\]|\b(?:${PREFIXES})-(?:${PALETTE})-[0-9]+|\b(?:${PREFIXES})-(?:white|black)\b|\bdark:`,
    'gi',
);

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const args = process.argv.slice(2);
const files = args.length > 0 ? args : FILES;
let hits = 0;

for (const file of files) {
    let source;
    try {
        source = readFileSync(resolve(root, file), 'utf8');
    } catch {
        console.error(`Brak pliku: ${file}`);
        process.exit(2);
    }
    const lines = source.split('\n');
    lines.forEach((line, index) => {
        for (const match of line.matchAll(PATTERN)) {
            hits += 1;
            // Pokaż całą klasę/token zawierający trafienie (np. text-[11px]).
            const start = line.lastIndexOf(' ', match.index) + 1;
            const end = line.indexOf(' ', match.index + match[0].length);
            const token = line
                .slice(start, end === -1 ? undefined : end)
                .replace(/^["'`]|["'`>]+$/g, '');
            console.log(`${file}:${index + 1}: ${token}`);
        }
    });
}

if (hits > 0) {
    console.log(
        `\nZnaleziono literały (${hits}): użyj tokenu z resources/css/app.css lub klasy komponentu — zob. .docs/design-system.md.`,
    );
    process.exit(1);
}
