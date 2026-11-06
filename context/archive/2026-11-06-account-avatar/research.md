---
date: 2026-11-06T23:20:00+01:00
researcher: Claude Sonnet 5.5
git_commit: 148c1f38b161f1bfdf3a4b61d3c9e255730f748e
branch: development
repository: household-treasurer
topic: "account-avatar: where a financial Account is shown without a bank logo, and what blocks one shared avatar"
tags: [research, codebase, account-avatar, AccountMarkCompact, AccountIdentityBlock, mark_url, design-system, 10x-ui]
status: complete
last_updated: 2026-11-06
last_updated_by: Claude Sonnet 5.5
---

# Research: account-avatar

**Date**: 2026-11-06T23:20:00+01:00
**Git Commit**: 148c1f38b161f1bfdf3a4b61d3c9e255730f748e (untracked: `context/changes/account-avatar/`)
**Branch**: development

## Research Question

The user sees the Account avatar (bank logo) almost only on the Dashboard. Scope, decided with the user: one Account avatar with the bank logo (`mark_url`, `public/marks/*`) on every view that shows a financial Account, with a fallback tile (short mark or icon) when there is no logo; the design-system page and kitchen sinks follow. The sidebar user avatar (ADR-0053) is out of scope. The question: which places show an Account without the avatar, what data and components are there, what prior decisions must be superseded, and what are the charges for the plan.

## Summary

- Three components show an Account, and none of them is the avatar the user means. `AccountIdentityBlock` renders the logo and is used only in `DashboardHoldings.vue:109` and `:181` (this inspected set of imports). `AccountMarkCompact` renders a text tile and takes no `mark_url` (props `type`, `bank`, `size`, `AccountMarkCompact.vue:5-45`). `CompactAccountIdentity` wraps `AccountMarkCompact`, so the `mark_url` it accepts is dropped (`CompactAccountIdentity.vue:1-22`).
- In the places listed under Charge 3, the Account (or Term Deposit) appears as name only. In the other inspected places (Accounts, Term Deposits, own-transfer and capital-movement rows, `AccountSelect`) it appears as a text tile.
- Almost every Account payload already carries `name`, `type`, `bank`, `mark_url` (`AccountIdentity::payload`, `app/Support/AccountIdentity.php:34-43`). Backend gaps are limited to import pair candidates, the Term Deposits page, and Term Deposit lists on Operations and import (Charge 4).
- The decision to show text tiles instead of logos is ADR-0047 (L5, L23), repeated in ADR-0045 L9, ADR-0051 L11 and `.ai/rules/js.md:21`. The new change needs ADR-0054 (next free number; highest existing is 0053).
- Only 6 of the 9 files in `public/marks/` look like real logos. `ing.svg`, `pekao.svg` and `santander.svg` are 300-byte rounded squares with the bank's short text ("ING", "PEK", "SAN") (`public/marks/ing.svg`, `pekao.svg`). Provenance and licence of the logos are not documented anywhere in `context/`, `app/`, `resources/` or README (subagent grep).

## Detailed Findings

### Components

| Component | Renders | Sizes / frame | Source |
| --- | --- | --- | --- |
| `AccountMarkCompact` | text tile: bank short mark, "zł" for cash, `Building2` for `other` or no bank; `aria-hidden` | `sm` = `size-5`, `md` = `size-7`; `bg-muted rounded-sm`; text style `mark-label` (mono 9 px) | `components/AccountMarkCompact.vue:5-45`; `resources/css/app.css:273` |
| `AccountIdentityBlock` | `<img :src="mark_url" :alt="name">` when `type === 'bank'` and `mark_url !== null`; `Coins` for cash; `Building2` for bank without URL; default slot for text | `sm` = `size-6 rounded-md`, `md` = `size-10 rounded-lg`; frame `border-border/60 bg-muted border`; `object-cover` | `components/AccountIdentityBlock.vue:1-62` |
| `CompactAccountIdentity` | `AccountMarkCompact` + name | `markSize` default `sm` | `components/CompactAccountIdentity.vue:1-22` |

- The two tiles have no shared size scale (24/40 px versus 20/28 px) and different radius and frame.
- `AccountIdentityBlock` has no `@error` handler and no fallback for an image that fails to load; the only fallback is the `mark_url === null` branch (`AccountIdentityBlock.vue:41-46`). It is the only `<img>` found in `resources/js` (subagent grep).
- Its `alt` is the account name, placed next to the name text. The earlier change `accounts-design-system-page` recorded that this makes a screen reader read the name twice (`context/archive/2026-11-06-accounts-design-system-page/change.md:27`).

### Places an Account is shown (inspected set)

| Place | Today | File:line |
| --- | --- | --- |
| Dashboard Accounts | logo (`AccountIdentityBlock` `sm`) | `components/DashboardHoldings.vue:109-123` |
| Dashboard Term Deposits | logo, `type="bank"` hardcoded | `components/DashboardHoldings.vue:181-195` |
| Dashboard notices (negative Account / Term Deposit) | name only; `noticePresentation` reduces the notice to `{type:'name', name}` | `components/DashboardNotices.vue:102-106`, `lib/dashboard-notice-presentation.ts:31-35` |
| Accounts table | text tile `md` | `pages/Accounts.vue:207-227` |
| Term Deposits table | text tile `md` | `pages/TermDeposits.vue:230-240` |
| Own transfer rows | text tile `md` via `CompactAccountIdentity` | `components/OwnTransferRow.vue:36-55` |
| Capital movement rows | text tile for the Account; Term Deposit name only | `components/CapitalMovementRow.vue:33-63` |
| Financial operation rows | name only, inside the `detail` string ("Zwrot · name" for a refund) | `components/FinancialOperationRow.vue:44-51` |
| Operations filters, Account select | native `<select>`, name only, "(zarchiwizowane)" suffix | `components/OperationsFilters.vue:368-374`, type at `:41-45` |
| Operations active filter chips | name only | `components/OperationsActiveFilterChips.vue:40-44`, `:133-138` |
| Account pickers in dialogs and import start | text tile via `AccountSelect` | `components/AccountSelect.vue:62-90` |
| Import preview, "other account" select | text tile via `AccountSelect` | `components/ImportPreviewRow.vue:425-436` |
| Import preview, pair candidate `<option>` | name in a native select, text only | `components/ImportPreviewRow.vue:240`, `:453-460` |
| Import preview summary sentence | name interpolated into a string | `pages/OperationsImportPreview.vue:250-257` |
| Bank picker (not an Account) | text tile `sm` | `components/AccountBankSelect.vue:56`, `:69` |

- No Account rendering was found in `MonthPlan*.vue`, `Budget*.vue`, `ImportRecognizedFields.vue` or the confirmation texts of Accounts and Term Deposits (subagent greps; the confirmation texts are static translation strings).
- A native `<option>` cannot hold a tile. The design system already says to use reka-ui `Select` "when an option shows an icon or mark" (`.ai/rules/js.md:57`, `design-system.md:16`), as `AccountBankSelect` and `AccountSelect` do.

### Data and payloads

- Full identity (`name`, `type`, `bank`, `mark_url`) is sent for: Accounts page, Dashboard accounts, Dashboard notice `negative_account`, Operations rows (`account_*`, `source_account_*`, `destination_account_*`), Operations `accounts` and `filterAccounts`, import start `accounts`, import preview `account` and `otherAccounts` (`AccountController.php:171`, `DashboardController.php:180`, `OperationsController.php:62,97,98,124,143`, `OperationsFilterOptions.php:75`, `CsvImportController.php:136,384,510`, `DashboardNotices.php:82`).
- `filterAccounts` arrives with full identity but its TS types `OperationsFilterAccount` and `ChipAccount` carry only `id`, `name`, `is_archived` (`OperationsFilters.vue:41-45`; `OperationsActiveFilterChips.vue` `ChipAccount`).
- `FinancialOperationRow` already types and receives `account_type`, `account_bank`, `account_mark_url` (`FinancialOperationRow.vue:23`); the template uses only `account_name` (`:49-50`). One subagent claimed the row already renders a mark; the template contradicts that.
- Import pair candidates carry `account_id` and `account_name` only (`ImportPairCandidateDetector.php:178-189`, `:202-223`). `otherAccounts` has the full identity of every other non-archived Account plus the archived ones that appear as candidates, and excludes the import Account (`CsvImportController.php:356-389`, `whereKeyNot` and `orWhereIn`). The import Account itself is sent separately as `account` (`CsvImportController.php:134-138`). Whether a candidate can name the import Account itself was not checked.
- Term Deposits have a `bank` but no `type`. The Dashboard sends `mark_url` for them (`DashboardController.php:206`, `DashboardNotices.php:98`). The Term Deposits page does not (`TermDepositController.php:165`, only `bank`), and that is asserted by `tests/Feature/TermDepositTest.php:133,138` (`->missing('termDeposits.N.mark_url')`). Operations `termDeposits`, `filterTermDeposits`, the import `termDeposits` list and `capitalMovements.*.term_deposit_name` carry no bank (`OperationsController.php:102,123,127-136`, `OperationsFilterOptions.php:84-95`, `CsvImportController.php:481-497`).
- The PHPDoc of `otherAccounts` is stale: it lists `{id, name, current_balance_minor_units}` while the payload also has type, bank and mark_url (`CsvImportController.php:358`).
- `Bank::markPath()` maps every bank except `other` to a file in `public/marks`; `other` and a null bank (cash) give `null` (`app/Enums/Bank.php:20-33`). `markUrl()` returns an absolute `url(...)` (`AccountIdentity.php:11-16`).
- Tests that assert these payloads and would change with a prop change: `OperationsIdentityTest.php`, `AccountPickerTest.php`, `AccountTest.php`, `DashboardTest.php`, `TermDepositTest.php`, `CsvImportTest.php`, `OwnTransferTest.php`, `CapitalMovementTest.php`, `OperationsFilterTest.php` (subagent anchors; `OperationsFilterTest.php:441-445` asserts `filterAccounts` name and `is_archived` and not `mark_url`).

### Logo assets (`public/marks`)

| File | Format | Observation |
| --- | --- | --- |
| `mbank.jpg`, `bnp-paribas.jpg` | JPEG 400x400 (12 KB, 14 KB) | square, opaque; background colour not sampled |
| `ing.svg`, `pekao.svg`, `santander.svg` | SVG 32x32, 300 bytes | rounded square with the short text ("ING", "PEK", "SAN"), `font-family: ui-sans-serif` |
| `alior.svg` | SVG ~85x85, 42 KB | square gradient background |
| `pko-bp.svg` | SVG 111.71x121.59 | not square |
| `revolut.svg` | SVG 24x24 | one path, no background |
| `lokaty-facto.svg` | SVG viewBox `130 310 175 245` | portrait, no background |

Implication: a square avatar needs a neutral container (`muted`), `contain` fit with padding for the transparent and non-square files, and a decision about the three placeholder-style SVGs (they are the short mark in a coloured tile, not a logo).

### Prior decisions (supersede / keep)

Supersede (new ADR-0054):
- ADR-0047 L5 ("instead of the logo") and L23 ("`AccountIdentityBlock` keeps the logo until its own view change"). L23 gives a scope reason, not a licence reason.
- ADR-0045 L9 and ADR-0051 L11 (short mark "instead of `AccountIdentityBlock`").
- ADR-0044 L9 and L11 are already superseded; the logo tile at 28 px was its original position.
- `.ai/rules/js.md:21`, and in `design-system.md` lines 3, 18, 26, 35, 86, 103, 105, 143 (subagent line list).

Keep:
- Tile is `aria-hidden` because the name stands next to it (ADR-0047 L21). With the logo this means `alt=""` or `aria-hidden`, not `alt=name`.
- Sizes 28 px and 20 px, `radius-sm`, `muted` background; the fallback keeps `mark-label`, at most 3 characters, "zł" for cash, `Building2` for `other`/no bank (ADR-0047 L19-21).
- Narrow props: `v-bind` of a whole account object leaked `id`, `name`, `mark_url` as HTML attributes (`context/archive/2026-11-06-accounts-design-system-page/plan.md:63`).
- Round shape belongs to the user avatar and `BrandMark` (ADR-0053); Account tile stays `radius-sm`.
- The glossary needs no entry: the mark is "a trait of appearance, not a Bank rule" (`context/archive/2026-11-06-accounts-design-system-page/plan.md:55`); `AGENTS.md:9` requires an ADR only for glossary or _Avoid_ changes, but this change supersedes ADR-0047, which needs one.
- Switch "Pokaż zarchiwizowane" rule and the interface of Accounts/Term Deposits tables (ADR-0044, 0045, 0047, 0051).

### Guards

- `scripts/check-ui-literals.mjs` has a hand-maintained `FILES` array (`:9`, override by CLI args `:139`). It lists `AccountMarkCompact.vue` (`:37`), `CompactAccountIdentity.vue` (`:103`), `DashboardHoldings.vue`. `AccountIdentityBlock.vue` is in neither that script nor any `tests/Unit/*HardcodedUiTest.php`; the docblock of `AccountsHardcodedUiTest.php:5` mentions it as guarded elsewhere (subagent grep over those files).
- `resources/js/ui-contract.test.ts` has no Account or mark checks.
- A new component is guarded only when someone adds its path to those lists.

### Kitchen sinks and fixtures

- Dashboard sink fixtures all set `mark_url: null` (`pages/dev/dashboard-fixtures.ts:85,95,106,277,450,459`), so the logo path is never rendered there. Real URLs appear only in `accounts-fixtures.ts` (mbank, ing, pekao, revolut, bnp) and `import-start-fixtures.ts`, whose consumers ignore them today.
- `AccountsKitchenSink.vue:113-137` has a "Znaki banków" section with `AccountMarkCompact` `md` and `sm` for every bank and cash.
- `lib/operations-fixtures.ts`, `import-preview-fixtures.ts`, `operation-dialogs-fixtures.ts` and `term-deposits-fixtures.ts` have `mark_url: null` or no such field.
- No test covers the three Account components directly; `lib/account-identity.test.ts` covers `accountShortMark` and `bankLabel` only.

## Charges

Format: file:line, user impact, category, plan phase.

1. **Missing shared component** (`AccountMarkCompact.vue:5-45`, `AccountIdentityBlock.vue:1-62`, `CompactAccountIdentity.vue:1-22`). Two unrelated tiles for one Account, different sizes (24/40 px versus 20/28 px), frame and radius, and `mark_url` accepted by `CompactAccountIdentity` but dropped. The user sees a logo on the Dashboard and a different mark on the same Account everywhere else. -> Phase "component + tokens": one `AccountAvatar` (logo, fallback tile) with a single size scale.
2. **Accidental architecture** (ADR-0047 L5, L23; `.ai/rules/js.md:21`; `design-system.md:18,86`). The rules tell the next agent to build text tiles and to keep the logo on the Dashboard only, so the split would survive any fix that does not change the rule. `AccountIdentityBlock` is also outside every literal guard. -> Phase "ADR-0054 + rule": new ADR, rewrite `js.md:21`, update `design-system.md`, add the component to `check-ui-literals.mjs` and the matching `*HardcodedUiTest.php`.
3. **Missing occurrences** (Account name with no avatar): `FinancialOperationRow.vue:44-51`, `OperationsFilters.vue:368-374` (native select), `OperationsActiveFilterChips.vue:133-138`, `ImportPreviewRow.vue:240,453-460` (native select), `OperationsImportPreview.vue:250-257`, `DashboardNotices.vue:102-106`, `CapitalMovementRow.vue` (Term Deposit name), Term Deposit pickers (`CapitalMovementFormDialog.vue:351-364`, `ImportPreviewRow.vue:497-502`). The user cannot recognise the Account by logo in the list where they work most (Operations) or in the import review. Native `<select>` cannot show a tile, so these need reka-ui `Select` or are deferred. -> Phase "views", one view first (Operations list), the rest listed individually or marked deferred.
4. **Data gaps behind the avatar**: pair candidates (`ImportPairCandidateDetector.php:178-189,202-223`), Term Deposits page without `mark_url` (`TermDepositController.php:165`, asserted missing in `TermDepositTest.php:133,138`), `OperationsFilterAccount`/`ChipAccount` types strip identity (`OperationsFilters.vue:41-45`), Term Deposit lists without `bank` (`OperationsController.php:127-136`, `OperationsFilterOptions.php:84-95`, `CsvImportController.php:481-497`, `OperationsController.php:123`). Each Inertia prop change goes in one commit with `defineProps<>` and an `assertInertia` test (AGENTS.md). -> Phase "data", before the views that need it; pair candidates can resolve identity by lookup in `otherAccounts`/`account` on the frontend without a PHP change.
5. **Missing states and evidence**: no `@error` fallback and `alt=name` (`AccountIdentityBlock.vue:41-46`); non-square and transparent logos; three placeholder-style SVGs; Dashboard sink never renders a logo (`dashboard-fixtures.ts`). -> Phase "states": 7-state matrix incl. image error, cash, `other`, archived, long name, both themes, with fixtures that set real `mark_url` in the Dashboard, Operations and import sinks.

Deferred candidates (decide in plan, keep here with reason): the Operations term-deposit filter select and the import Term Deposit `<option>` (a Term Deposit has `bank` but no `type`, and needs a payload change); `AccountFormDialog` (no Account tile in the title, nothing to show for a new Account).

## Code References

- `resources/js/components/AccountMarkCompact.vue:5-45` - text tile, no `mark_url`
- `resources/js/components/AccountIdentityBlock.vue:36-62` - logo `<img>`, no error handling
- `resources/js/components/CompactAccountIdentity.vue:1-22` - wraps the text tile
- `resources/js/lib/account-identity.ts` - types, `accountShortMark`, `bankLabel`
- `app/Support/AccountIdentity.php:11-43` - `markUrl`, `prefixedRowFields`, `payload`
- `app/Enums/Bank.php:20-33` - `markPath()`
- `resources/js/components/FinancialOperationRow.vue:23,44-51` - identity typed, name only rendered
- `resources/js/components/DashboardHoldings.vue:109,181` - only users of the logo block
- `scripts/check-ui-literals.mjs:9,139` - guard list
- `context/foundation/adr/0047-bank-short-marks-and-accounts-title-row.md` - decision to supersede

## Architecture Insights

- Identity is already normalised on the server (`AccountIdentity`) and typed on the client (`AccountIdentityFields`, `PrefixedAccountIdentityFields`); the split is only in the presentation components and the rules that describe them.
- The design system forbids new colours and literals in views; the avatar needs tokens only (`muted`, `radius-sm`, `mark-label`), so no new token is expected. If the container for logos needs a new surface meaning, it lands on the design-system page and in ADR-0054.

## Historical Context (from prior changes)

- `context/archive/2026-11-06-accounts-design-system-page/{change,plan-brief,plan}.md` - why the Accounts tile became text, the double `alt` reading, narrow props, glossary note.
- ADR-0043 L15/L23 (Dashboard rows with "bank mark"), ADR-0044, ADR-0045, ADR-0047, ADR-0051, ADR-0053 - summarised above. The design-system artifact on claude.ai was not accessible; what it says about marks is known only from quotes in ADR-0047 L27.

## Related Research

None for this topic under `context/changes/**/research.md` or `context/archive/**/research.md` (subagent grep; the `*-ui-contract` research files mention `mark_url` but were not read in full).

## Open Questions

1. The three placeholder-style SVGs (`ing`, `pekao`, `santander`): keep as "logos", replace with real files (needs a source and licence), or treat them as fallback tiles? Provenance and licence of every file in `public/marks` are undocumented.
2. Sizes: one scale for the avatar (proposal from the audit: 20 px for pickers and chips, 28 px for tables and rows, plus whether the Dashboard needs 24/40 px) - decide in the plan.
3. Native `<select>` in Operations filters and import pair candidates: replace with reka-ui `Select` (as `AccountSelect`) or keep name-only and mark deferred.
4. Whether Term Deposits get the same avatar everywhere (needs `mark_url` on the Term Deposits page and `bank` on Operations and import lists) or only where they already have `mark_url`.
5. JPEG background colour (white versus the `muted` tile in dark mode) was not sampled; check in the dark-mode kitchen-sink pass.
6. Whether a pair candidate can name the import Account itself (frontend lookup in `otherAccounts` would then miss it).
