# rhythm-hints Implementation Plan

## Overview

The Rhythm choice in the Category dialog on „Kategorie” shows four bare labels („Stała / Bieżąca / Roczna / Nieregularna”). The User read „Roczna” (Even Annual) as „a once-a-year expense I save for”, which is what Irregular does. This change adds one short description of the selected Rhythm under the control: when to choose it and what it does with the money. Labels, Rhythm rules, the glossary and the data stay as they are.

## Current State Analysis

- The Rhythm field is `FormField` + `SegmentedControl` `block` with `name="rhythm"` in `resources/js/components/CategoryListFormDialog.vue:268-286`, shown only for an expense. Options are built in `rhythmOptions` (`:84-98`) from `t.categories.rhythm_option_*` (`lang/pl/ui.php:663-666`).
- The field's only hint is `rhythmHint` (`:142-149`), „Zmiana działa od :month :year; minione miesiące bez zmian.” (`lang/pl/ui.php:667`).
- `FormField` (`resources/js/components/FormField.vue`) has one `hint` string, rendered as one `<p class="text-muted-foreground text-xs/4">` with an id that goes into `describedBy`; the dialog passes `describedBy` to the `SegmentedControl` (`ariaDescribedby`).
- `SegmentedControl` options carry only `label` and `count` (`SegmentedControl.vue:9`); there is no room for text inside the buttons in a 460 px dialog, and `.ai/rules/js-dialogs.md` keeps the choice a `SegmentedControl`.
- Translations reach the page as `translations.categories: Record<string, string>` (`resources/js/types/shell.ts:45`), so a new key needs no type change.
- E2E specs find the buttons by their accessible name: `getByRole('button', { name: 'Nieregularna', exact: true })` (`tests/e2e/irregular-collected.spec.ts:85`, `tests/e2e/collected-year-start.spec.ts:73`).
- Kitchen sink `/dev/ui/dialogs` (`resources/js/pages/dev/DialogsKitchenSink.vue`) already has Category dialog states for a new Category (Running, `category-list-new`), Even Annual (`category-list-rhythm`, fixture `evenAnnualListCategory` at `:364`) and Irregular (`category-list-irregular-new`, `-edit`); it has none for Fixed Charge.
- `tests/Unit/CategoriesHardcodedUiTest.php` scans `CategoryListFormDialog.vue` for literals; `npm run check:ui` guards cleaned views.
- ADR-0066 keeps „odkładam” only for Set-aside: the rate is „stawka”, the running total „zebrane”.

## Desired End State

In the Category dialog, with expense type, the hint under the Rhythm control reads the description of the selected Rhythm followed by the existing change-month sentence, and it follows the selection at once:

- Stała: „Ta sama kwota co miesiąc, np. czynsz, abonament. Liczy się plan, a po zapłacie faktyczna kwota.”
- Bieżąca: „Wydajesz po trochu przez cały miesiąc, np. jedzenie, paliwo. Wchodzi do tempa miesiąca.”
- Roczna: „Wydajesz nierówno, liczy się suma za rok, np. ubrania, apteka. To, co niewydane, nie przechodzi na kolejny miesiąc.”
- Nieregularna: „Rzadki, większy rachunek, np. wakacje, OC. Co miesiąc stawka trafia do „zebrane” i pokrywa rachunek.”

Example with Roczna selected in November 2026: „Wydajesz nierówno, liczy się suma za rok, np. ubrania, apteka. To, co niewydane, nie przechodzi na kolejny miesiąc. Zmiana działa od listopada 2026; minione miesiące bez zmian.”

Screen readers get the same text through the existing `aria-describedby` of the control. Each of the four descriptions has a state in `/dev/ui/dialogs`.

### Key Discoveries:

- One hint paragraph per `FormField` (`FormField.vue:68-70`): the description and the change-month sentence share it, joined by a space.
- Button names must stay „Stała”, „Bieżąca”, „Roczna”, „Nieregularna” for the E2E selectors.
- „odkładam” is reserved for Set-aside (ADR-0066), so the Irregular description says „stawka” and „zebrane”.

## What We're NOT Doing

- No rename of „Roczna” or of the Polish UI copy „równomierna roczna”; no glossary entry or ADR change (the User chose to keep the label).
- No description list of all four Rhythms and no tooltip (the User chose the description of the selected one).
- No change to `SegmentedControl`, `FormField` or `components/ui/*`.
- No change to Rhythm behaviour, To Spend, Collected, the server, Inertia props or the database.
- No Rhythm descriptions elsewhere (Budget rows, Month Plan, „Kategorie” list rows).

## Implementation Approach

Four new keys in `lang/pl/ui.php` under `categories`, one per Rhythm value. The dialog picks the key from the selected `rhythm` ref and builds the hint as description + space + the existing change-month sentence. A Fixed Charge state joins the kitchen sink, and the existing Irregular E2E spec asserts the description once after the click.

## Phase 1: Rhythm description in the Category dialog

### Overview

The Rhythm hint in `CategoryListFormDialog` names the selected Rhythm's use and effect; the kitchen sink shows every Rhythm; one E2E assertion pins the behaviour.

### Changes Required:

#### 1. Description texts

**File**: `lang/pl/ui.php`

**Intent**: Add the four approved descriptions next to the `rhythm_option_*` keys, so all Rhythm copy lives in one place.

**Contract**: New keys in the `categories` group: `rhythm_description_fixed_charge`, `rhythm_description_running`, `rhythm_description_even_annual`, `rhythm_description_irregular`, with exactly the texts listed under Desired End State (Polish quotes „…” as in the rest of the file).

#### 2. Hint follows the selected Rhythm

**File**: `resources/js/components/CategoryListFormDialog.vue`

**Intent**: Make `rhythmHint` start with the description of the currently selected Rhythm, then the existing change-month sentence, so the hint changes as the User clicks the options.

**Contract**: `rhythmHint` = `t.categories[`rhythm_description_${rhythm.value}`]` + `' '` + the current change-month text (month genitive and year replaced as today). The `Rhythm` type values (`fixed_charge | running | even_annual | irregular`, `resources/js/types/rhythm.ts`) are the key suffixes. Button labels, `name="rhythm"`, `aria-describedby` wiring and the Opening Collected field are unchanged.

#### 3. Kitchen sink state for Fixed Charge

**File**: `resources/js/pages/dev/DialogsKitchenSink.vue`

**Intent**: Every Rhythm description has a state to look at: add „Kategoria listy: stała opłata”, an edit of an expense list Category with Rhythm `fixed_charge`, next to the existing `category-list-rhythm` state.

**Contract**: New state key `category-list-fixed-charge` in the state list and labels, a `CategoryListItem` fixture with `rhythm: 'fixed_charge'` built like `evenAnnualListCategory` (`:364`), and one `CategoryListFormDialog` instance wired like the `category-list-rhythm` one.

#### 4. E2E assertion

**File**: `tests/e2e/irregular-collected.spec.ts`

**Intent**: Pin that choosing „Nieregularna” shows its description in the dialog, the case the change exists for.

**Contract**: After the click on the „Nieregularna” button (`:84-86`), `expect(dialog).toContainText('Co miesiąc stawka trafia do „zebrane” i pokrywa rachunek.')`. No other step of the spec changes.

### Success Criteria:

#### Automated Verification:

- Formatting and lint pass: `npm run check`
- UI literal check passes: `npm run check:ui`
- Vue type check passes: `npm run types:check` (after `npm run build`)
- Categories literal guard passes: `php artisan test --compact tests/Unit/CategoriesHardcodedUiTest.php`
- Irregular E2E spec passes with the new assertion: `npx playwright test tests/e2e/irregular-collected.spec.ts`
- Collected year-start E2E spec still finds the „Nieregularna” button: `npx playwright test tests/e2e/collected-year-start.spec.ts`
- Full gate passes: `composer ci:check`

#### Manual Verification:

- In `/dev/ui/dialogs`, the states „Kategoria listy: nowa” (Bieżąca), „stała opłata”, „edycja rytmu (równomierna roczna)” and „nowe oznaczenie nieregularnej” each show their description followed by „Zmiana działa od …”
- Clicking through the four options in one dialog changes the description at once; an income Category shows no Rhythm field and no description
- At phone width (360 px) the hint wraps without horizontal scroll and the four buttons look as before

**Implementation Note**: After automated verification passes, pause for the human to confirm the manual checks.

---

## Testing Strategy

### Unit Tests:

- None new: the change is copy plus a key lookup; `CategoriesHardcodedUiTest` keeps guarding the dialog file.

### Integration Tests:

- `tests/e2e/irregular-collected.spec.ts`: the Irregular description appears after choosing „Nieregularna”.
- `tests/e2e/collected-year-start.spec.ts`: unchanged, proves the button names did not change.

### Manual Testing Steps:

1. Open `/dev/ui/dialogs`, open each Category list state and read the hint under „Rytm”.
2. In „Kategoria listy: nowa”, click Stała → Bieżąca → Roczna → Nieregularna and watch the hint change; with Nieregularna the „Zebrane startowe” field appears under it as before.
3. Switch the type to income: the Rhythm field and its hint disappear.

## Performance Considerations

None: one computed string.

## Migration Notes

None: no data or schema change.

## References

- Rhythm rules and Polish copy: `context/foundation/adr/0066-category-rhythm-and-irregular-collected.md`, `context/foundation/glossary.md` (Rhythm, Collected, Irregular Rate)
- Rhythm edited on the list: `context/foundation/adr/0072-rhythm-edited-on-the-list-and-flags-dropped.md`
- Dialog rules: `.ai/rules/js-dialogs.md`, `.ai/rules/js.md`
- Dialog: `resources/js/components/CategoryListFormDialog.vue:84-149,268-286`
- Hint rendering: `resources/js/components/FormField.vue:68-70`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Rhythm description in the Category dialog

#### Automated

- [x] 1.1 Formatting and lint pass: `npm run check` — 841d459
- [x] 1.2 UI literal check passes: `npm run check:ui` — 841d459
- [x] 1.3 Vue type check passes: `npm run types:check` (after `npm run build`) — 841d459
- [x] 1.4 Categories literal guard passes: `php artisan test --compact tests/Unit/CategoriesHardcodedUiTest.php` — 841d459
- [ ] 1.5 Irregular E2E spec passes with the new assertion: `npx playwright test tests/e2e/irregular-collected.spec.ts` — the new assertion passes; the spec then fails at the stale year button in `openCurrentMonthPlan` (see follow-ups/review-fixes.md)
- [x] 1.6 Collected year-start E2E spec still finds the „Nieregularna” button: `npx playwright test tests/e2e/collected-year-start.spec.ts` — 841d459
- [x] 1.7 Full gate passes: `composer ci:check` — pass on PostgreSQL in Sail (1662 passed), impl-review 2026-11-13

#### Manual

- [x] 1.8 In `/dev/ui/dialogs`, the states „Kategoria listy: nowa” (Bieżąca), „stała opłata”, „edycja rytmu (równomierna roczna)” and „nowe oznaczenie nieregularnej” each show their description followed by „Zmiana działa od …” — 841d459
- [x] 1.9 Clicking through the four options in one dialog changes the description at once; an income Category shows no Rhythm field and no description — 841d459
- [x] 1.10 At phone width (360 px) the hint wraps without horizontal scroll and the four buttons look as before — 841d459
