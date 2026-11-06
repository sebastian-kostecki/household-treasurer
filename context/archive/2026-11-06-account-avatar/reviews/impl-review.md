<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Awatar konta z logo banku wszędzie (ADR-0054)

- **Plan**: context/changes/account-avatar/plan.md
- **Scope**: Full plan (Phase 1–5 of 5)
- **Reviewed phases**: 1, 2, 3, 4, 5
- **Date**: 2026-11-07
- **Verdict**: APPROVED
- **Findings**: 0 critical, 0 warnings, 6 observations

## Verification run (Krok 3)

| Check | Result |
|---|---|
| `grep AccountMarkCompact\|AccountIdentityBlock` w `resources/js scripts tests .ai/rules design-system.md` | 0 trafień |
| `public/marks/{ing,pekao,santander}.svg` usunięte | OK |
| PostgreSQL (Sail): OperationsIdentity, TermDeposit, CsvImport, OperationsFilter, CapitalMovement, Dashboard, Account + 4× `*HardcodedUiTest` | 250 passed (3065 assertions) |
| `npx vitest run account-identity.test.ts dashboard-notice-presentation.test.ts` | 18 passed |
| `npm run types:check` (vue-tsc) | OK |
| `npm run check` (vp: format + lint) | OK, 0 warnings |
| `npm run check:ui` | OK |
| `vendor/bin/sloppy ci` (host) | 100/100, 0 findings |
| Nie uruchomiono | `composer ci:check` w całości, Playwright (4.4), przeglądu wizualnego sinków (3.5, 3.6, 4.5–4.8, 5.4–5.6: ręczne, brak dowodu w repo poza odhaczeniem) |

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | PASS |
| Scope Discipline | PASS |
| Safety & Quality | PASS |
| Architecture | PASS |
| Pattern Consistency | PASS |
| Success Criteria | WARNING |

## Findings

### F1 — Kontrast logo na `muted` w ciemnym motywie nie jest nigdzie zapisany

- **Severity**: 👁 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: context/foundation/adr/0054-account-avatar-with-bank-logo.md
- **Detail**: Punkt 5.5 planu: „Kontrast JPEG-ów i przezroczystego Revoluta na `muted` w trybie ciemnym zaakceptowany albo zapisany w ADR-0054”. Pole jest `[x]`, ale ADR nie ma wzmianki o kontraście ani o ograniczeniach (plan przewidywał zapis ograniczenia, jeśli jest). Nie ma więc śladu w repo, że sprawdzono (ręczny punkt, nie do zweryfikowania z diffu).
- **Fix**: Obejrzyj Revolut/mBank/BNP w `/dev/ui/accounts` w ciemnym motywie; jeśli OK, dopisz jedno zdanie „sprawdzone, bez znanych ograniczeń” do ADR-0054, jeśli nie, wpisz ograniczenie.
- **Decision**: Fixed via Fix now (ADR-0054: zdanie o kontraście, potwierdzone przez użytkownika)

### F2 — Brak testu enuma `Bank::markPath()` (null dla ING/Pekao/Santander, plik istnieje dla reszty)

- **Severity**: 👁 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Enums/Bank.php:17-32, tests/Unit (brak testu Bank)
- **Detail**: `grep markPath tests` = 0 trafień. Kontrakt jest sprawdzany pośrednio przez stronę Operacji (`OperationsIdentityTest`). Literówka w ścieżce albo usunięty plik w `public/marks` dałyby cichy 404, a awatar by się „uratował” przez `@error` → skrót, więc nikt by tego nie zauważył.
- **Fix**: Jeden test Unit: iteruje `Bank::cases()`, dla niepustego `markPath()` sprawdza `file_exists(public_path(...))`, dla ING/Pekao/Santander/Other oczekuje `null`.
- **Decision**: Fixed via Fix now (tests/Unit/BankMarkPathTest.php)

### F3 — Detektor par składa pola tożsamości ręcznie zamiast `AccountIdentity::prefixedRowFields`

- **Severity**: 👁 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: app/Support/CsvImport/ImportPairCandidateDetector.php:195-197, 235-237
- **Detail**: Plan (Faza 2.4) wskazywał `...AccountIdentity::prefixedRowFields($account, 'account')`. Kontrakt wyjściowy jest ten sam (`account_name/type/bank/mark_url`), ale logika `type->value`, `bank?->value`, `markUrl()` jest zduplikowana dwa razy tutaj i raz w `AccountIdentity`. Przy nowym polu tożsamości trzeba pamiętać o trzech miejscach.
- **Fix**: Zamień oba bloki na `...AccountIdentity::prefixedRowFields($account, 'account')` (zachowuje PHPDoc i testy).
- **Decision**: Fixed via Fix now (prefixedRowFields w ImportPairCandidateDetector)

### F4 — `DashboardNotices.vue`: `noticePresentation` liczone wielokrotnie, `noticeAvatar(notice)!` trzy razy

- **Severity**: 👁 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: resources/js/components/DashboardNotices.vue:110-113, 295-299
- **Detail**: `v-if="noticeAvatar(notice)"` plus trzy `noticeAvatar(notice)!.x` w propsach to wielokrotne wywołanie i asercja non-null. Nie jest to błąd, tylko szum i ryzyko rozjazdu przy zmianie funkcji.
- **Fix**: Użyj `<template v-if>` z lokalnym aliasem (`v-for`/`v-bind="..."` po wąskich propsach) albo wylicz awatar raz na ostrzeżenie.
- **Decision**: Fixed via Fix now (jeden alias awatara w DashboardNotices.vue)

### F5 — `hasMetaLine` w `OperationDescriptionCell` liczone raz w setupie

- **Severity**: 👁 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/components/OperationDescriptionCell.vue:37
- **Detail**: `const hasMetaLine = secondaryLine !== null || account !== null` jest wyliczane jednorazowo przy destrukturyzowanych propsach, tak samo jak istniejące `secondaryLine`. Dziś nieszkodliwe (wiersz jest kluczowany, `account` nie zmienia się na żywo), ale gdyby `account` zmieniał się w tej samej instancji, linia meta nie zareaguje. To dziedziczony wzorzec, nie regresja.
- **Fix**: Zamień na `computed` (razem z `secondaryLine`), albo zostaw świadomie.
- **Decision**: Fixed via Fix now (computed w OperationDescriptionCell.vue)

### F6 — Zmiany poza planem: `app.css` (uzasadnione) i szum formatowania w `UserInfo.vue` / `SidebarKitchenSink.vue`

- **Severity**: 👁 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Scope Discipline
- **Location**: resources/css/app.css:1322; resources/js/components/UserInfo.vue:49-51; resources/js/pages/dev/SidebarKitchenSink.vue:131-134
- **Detail**: `.dashboard-notice-identity` przeszło na flex, bo awatar stoi obok nazwy (uzasadnione, bez nowego tokenu). `UserInfo.vue` (na liście „NOT Doing”, ADR-0053) i `SidebarKitchenSink.vue` mają wyłącznie przeformatowanie z commita bramki p5, zero zmiany zachowania.
- **Fix**: Zaakceptuj `app.css`; formatowanie zostaw (już w historii), nic nie cofaj.
- **Decision**: SKIPPED (zaakceptowane jak jest)
