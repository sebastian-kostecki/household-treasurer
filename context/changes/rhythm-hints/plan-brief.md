# rhythm-hints — Plan Brief

> Full plan: `context/changes/rhythm-hints/plan.md`

## What & Why

The Rhythm choice in the Category dialog shows only „Stała / Bieżąca / Roczna / Nieregularna”. The User took „Roczna” (Even Annual) for „a once-a-year expense I save for” — a holiday of 3 000 zł — which is what Irregular does: Even Annual never carries unspent money forward. A one-sentence description of the selected Rhythm under the control removes that guess.

## Starting Point

`CategoryListFormDialog.vue` renders the Rhythm as a `SegmentedControl` in a `FormField` whose only hint is „Zmiana działa od listopada 2026; minione miesiące bez zmian.” Nothing in the UI says what a Rhythm does.

## Desired End State

Under the four buttons the hint reads the selected Rhythm's description, then the change-month sentence, and changes as the User clicks. For Roczna: „Wydajesz nierówno, liczy się suma za rok, np. ubrania, apteka. Niewydane nie przechodzi na kolejny miesiąc. Zmiana działa od listopada 2026; minione miesiące bez zmian.” Every Rhythm has a kitchen sink state.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) |
| --- | --- | --- |
| Where the description stands | One description of the selected Rhythm, in the field hint | Short, no change to shared components, read by screen readers through `aria-describedby`. |
| Label „Roczna” | Stays | The description fixes the confusion; renaming would change glossary copy and need an ADR. |
| Content | When to choose + what it does with money | States outright what misled the User: Roczna does not carry over, Nieregularna collects. |
| Irregular wording | „stawka” / „zebrane”, not „odkładasz” | ADR-0066 reserves „odkładam” for Set-aside. |
| Layout of two sentences | Description and change-month sentence in one hint paragraph | `FormField` has one hint; it is not changed for one dialog. |
| Tests | One E2E assertion, no new unit test | Copy plus a key lookup; the E2E pins the Irregular case that caused the change. |

## Scope

**In scope:**
- Four `rhythm_description_*` texts in `lang/pl/ui.php`
- Hint built from the selected Rhythm in `CategoryListFormDialog.vue`
- Kitchen sink state „Kategoria listy: stała opłata”
- Assertion in `tests/e2e/irregular-collected.spec.ts`

**Out of scope:**
- Renaming „Roczna” / „równomierna roczna”, glossary or ADR changes
- A list of all four descriptions or tooltips
- Changes to `SegmentedControl`, `FormField`, `components/ui/*`
- Rhythm behaviour, server, props, data

## Architecture / Approach

Text lives in `ui.php` under `categories`; the dialog's `rhythmHint` computed reads `t.categories['rhythm_description_' + rhythm]` and appends the existing change-month sentence. Nothing else moves.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Rhythm description in the Category dialog | Texts, dynamic hint, kitchen sink state, E2E assertion, gate | E2E selectors by button name — names stay unchanged |

**Prerequisites:** Sail stack running for `composer ci:check`; Playwright setup for the E2E specs.
**Estimated effort:** one short session.

## Open Risks & Assumptions

- The hint grows to 3–4 lines of `text-xs`; accepted in place of a line break the mock-up showed.
- Texts are approved as written; a later wording change touches only `ui.php` and the E2E assertion.

## Success Criteria (Summary)

- Choosing a Rhythm tells the User, in the dialog, when it fits and whether unspent money carries over.
- „Nieregularna” says that the rate goes to „zebrane” and covers the bill.
- Gate and both Irregular E2E specs pass.
