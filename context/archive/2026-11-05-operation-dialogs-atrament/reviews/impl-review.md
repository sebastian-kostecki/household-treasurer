<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Dialogi operacji według Atramentu: podgląd „przed → po” i kontrakt ADR-0040

- **Plan**: context/changes/operation-dialogs-atrament/plan.md
- **Scope**: Full plan
- **Reviewed phases**: 1, 2, 3, 4, 5
- **Date**: 2026-11-05
- **Verdict**: NEEDS ATTENTION
- **Findings**: 0 critical, 2 warnings, 5 observations

## Verification run

- PHP na PostgreSQL (obraz Sail, 4.1 + 5.1 + DesignTokensTest): 83 testów, 512 asercji, zielone.
- Front na hoście: `npm test` 25 plików / 239 testów zielone; `npm run check`, `npm run check:ui`, `npm run types:check` czyste.
- Nie uruchomiono `composer ci:check` (5.4) ani `vendor/bin/sloppy ci`: diff nie dotyka PHP poza `lang/pl/ui.php`.
- Payload (nazwy pól, hidden inputy, znak `amount_minor_units`) porównany z wersją sprzed zmiany (44e1630) dla 4 dialogów: bez zmian.

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | WARNING |
| Scope Discipline | WARNING |
| Safety & Quality | PASS |
| Architecture | PASS |
| Pattern Consistency | PASS |
| Success Criteria | WARNING |

## Findings

### F1 — Stan `capital-negative-deposit` nie pokazuje ujemnej lokaty

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: resources/js/pages/dev/DialogsKitchenSink.vue:134-139,463-469
- **Detail**: Plan (Faza 5, tabela stanów): „Lokata → konto ponad saldo lokaty”. Stan to edycja istniejącego ruchu 200 zł z lokaty 2 (saldo 250 zł) z `initial-amount-input="300,00"`. Po cofnięciu oryginału saldo „po” = 250 + 200 − 300 = 150 zł, więc ostrzeżenie `term_deposits.negative_balance_warning` się nie pojawia. Zrzut `screenshots/capital-negative-deposit-light-1280.png` pokazuje „250,00 zł → 150,00 zł” bez ostrzeżenia. Krok 5.7 jest odhaczony, a ścieżka ostrzeżenia lokaty nie jest wizualnie zweryfikowana w żadnym stanie. Komentarz w l. 133 („ponad saldo”) przeczy wynikowi. Stan stał się błędny po poprawce znaku (F3).
- **Fix**: Ustaw `initial-amount-input` na wartość > 450,00 zł (np. „500,00”) i odśwież 4 zrzuty `capital-negative-deposit-{light,dark}-{1280,390}.png`.
- **Decision**: FIXED — Fix now: kwota 500,00 w stanie, 4 zrzuty odnowione; ostrzeżenie „Saldo lokaty będzie ujemne.” widoczne

### F2 — Stan `expense-month-without-plan` nie pokazuje ostrzeżeń Month Remaining

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: resources/js/lib/operation-dialogs-fixtures.ts:179-186
- **Detail**: Plan: stan „ostrzeżenia Month Remaining”, a blok ma je pokazywać jako linie na końcu. Fixture `dialogHandEntryMonthRemaining` ustawia kategorię 1 jako `is_paced: false`, a `previewHandEntryMonthRemainingWarnings` zwraca wtedy od razu `false/false` (`hand-entry-month-remaining-preview.ts:192`). Zrzut `expense-month-without-plan-light-1280.png` ma tylko limit i saldo. Ścieżka „ostrzeżenia bez odczytu na końcu bloku” nie jest nigdzie obejrzana.
- **Fix**: Daj kategorii w fixture `is_paced: true` (sprawdź, czy kategoria 1 jest tą wybraną w stanie) i odśwież 4 zrzuty `expense-month-without-plan-*`. Sprawdź, że inne stany nie używają tego fixture'a.
- **Decision**: FIXED — Fix now, z korektą: samo `is_paced: true` nie wystarczyło, bo budżet w szkicu daje stałe plan/12; fixture ma też `is_draft: false`. 4 zrzuty odnowione, widać dwie linie Month Remaining na końcu bloku

### F3 — Nieplanowana poprawka znaku korekty w edycji ruchu kapitału

- **Severity**: 💡 OBSERVATION
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Scope Discipline
- **Location**: resources/js/lib/hand-entry-warning-preview.ts:269,276
- **Detail**: Korekta edytowanego rekordu dostaje teraz `-originalAccountSignedEffect` (i to samo dla lokaty). Rachunkowo poprawne: zapisane saldo zawiera już efekt oryginału, więc trzeba go cofnąć; stary kod liczył go podwójnie. Testy (`hand-entry-warning-preview.test.ts:340-410`) to pokrywają. Plan jednak mówił „wszystkie flagi zachowują dzisiejsze wartości”, a zmiana zmienia `showNegativeBalanceWarning` i `showTermDepositNegativeBalanceWarning` w edycji ruchu kapitału. Opisana tylko w commicie 84358bf, nie w planie ani ADR.
- **Fix A ⭐ Recommended**: Dopisać addendum do planu (Faza 5) i wymienić poprawkę w opisie PR
  - Strength: Zachowuje poprawną naprawę z testami; źródło prawdy zgodne z kodem.
  - Tradeoff: Plan staje się lekko ruchomym celem.
  - Confidence: HIGH — rachunek sprawdzony niezależnie przez dwóch recenzentów.
  - Blind spot: Czy użytkownik zna wcześniejsze fałszywe ostrzeżenia z edycji ruchu kapitału.
- **Fix B**: Wydzielić poprawkę do osobnego commita/issue i zostawić tu stary znak
  - Strength: Ścisła dyscyplina zakresu.
  - Tradeoff: Wraca znany błąd; stan `capital-edit` pokazywałby błędne salda.
  - Confidence: MEDIUM — nie sprawdzono, czy testy Fazy 2/5 zakładają nowy znak.
  - Blind spot: Zależności testów od nowego znaku.
- **Decision**: FIXED — Fix A: addendum (pkt 9) w Fazie 5 planu; poprawkę wymienić w opisie PR

### F4 — Reguła „zła kwota” wklejona w 4 dialogach

- **Severity**: 💡 OBSERVATION
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Architecture
- **Location**: ExpenseFormDialog.vue:144-154, IncomeFormDialog.vue:121-131, OwnTransferFormDialog.vue:84-94, CapitalMovementFormDialog.vue:109-119
- **Detail**: `amountInvalid`, `amountMinorUnits` i `showPreview` są skopiowane 4 razy. To reguła pieniężna z ADR-0041 (nieczytelna lub ujemna), a `CategoryFormDialog` ma już inną (tylko `null`), więc przy zmianie łatwo o rozjazd. Boilerplate `busy`/stopki jest powtórzony, ale zgadza się z `CategoryFormDialog` i jest ustalonym wzorcem.
- **Fix**: Wyciągnąć `useOperationAmountInput(amountInput)` zwracające `{ amountInvalid, amountMinorUnits, showPreview }`; stopkę zostawić.
  - Strength: Jedno miejsce na regułę ADR-0041.
  - Tradeoff: Nowy plik i dotknięcie 4 świeżo ukończonych dialogów.
  - Confidence: MEDIUM — kod identyczny w 4 miejscach, ale brak testów komponentowych, więc regresję złapią tylko `types:check` i ręczne sprawdzenie.
  - Blind spot: Czy projekt ma konwencję katalogu na composables.
- **Decision**: FIXED — Fix now: `composables/useOperationAmountInput.ts` i podmiana w 4 dialogach; wpis w `.ai/rules/js.md`

### F5 — Drobne braki dostępności w podglądzie i polach typu

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: FormPreview.vue:22-27; ExpenseFormDialog.vue:476-496,633; IncomeFormDialog.vue:364-385,522; CapitalMovementFormDialog.vue:281-295; OwnTransferFormDialog.vue:347
- **Detail**: (a) `FormPreview` nie ma `aria-live`, a wartość „przed” nie ma ukrytej etykiety (tylko „po zapisie”). (b) Błąd pod `SegmentedControl` (`convert_to_income`, `convert_to_expense_refund`, `direction`) nie trafia do `aria-describedby`, bo kontrolka nie ma takiego propsa; ten sam wzorzec jest w `CategoryFormDialog`, a błąd pojawia się tylko po odpowiedzi serwera. (c) `aria-labelledby` na `<p class="form-readonly-text">` jest ignorowany.
- **Fix**: `aria-live="polite"` na kontenerze ostrzeżeń i `sr-only` „przed” w `FormPreview`; usunąć `aria-labelledby` z `<p>`; powiązanie błędu z `SegmentedControl` odłożyć na zmianę dotykającą tego komponentu (dotyczy też Budżetu).
- **Decision**: FIXED — Fix now: (a) `sr-only` „przed zapisem” (`operations.preview_before`) i `role="status"` na ostrzeżeniach w `FormPreview`, (c) `aria-labelledby` usunięte z `<p>`; (b) odłożone na zmianę `SegmentedControl`

### F6 — Dokumenty nie wspominają wyjątku `name` w wyborze typu

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: .docs/design-system.md („Gotowe elementy”), .ai/rules/js.md (zdanie „A choice in a form is `SegmentedControl` `block` with a `name`”)
- **Detail**: ADR-0041 robi wyjątek (wydatek i dochód: flaga konwersji w ukrytym polu, bez `name`), ale te dwa miejsca nadal mówią „z `name`” bez odesłania do wyjątku. Agent czytający regułę nie dowie się, że `name` bywa pomijane.
- **Fix**: Dopisać w obu miejscach „(wyjątek: flaga konwersji, ADR-0041)”.
- **Decision**: FIXED — Fix now: odesłanie do wyjątku w `.docs/design-system.md` i `.ai/rules/js.md`

### F7 — Martwy kod w `CapitalMovementFormDialog`

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: resources/js/components/CapitalMovementFormDialog.vue:137-140,374
- **Detail**: `originalSignedAccountEffect` jest liczone ręcznie, choć biblioteka i tak bierze `Math.abs(signed_amount_minor_units)` oraz osobno `direction`; wystarczy przekazać `originalAmountMinorUnits`. W l. 374 ternarny zapis `invalid === 'true' ? 'true' : undefined` jest zbędny.
- **Fix**: Uprościć oba miejsca; sprawdzić `types:check` i testy.
- **Decision**: FIXED (częściowo) — Fix now: martwe `originalSignedAccountEffect` usunięte; ternarny zapis `aria-invalid` zostaje, bo `invalid` ma typ `string | undefined`, a `<select>` wymaga `Booleanish` (`types:check` to wykazał, więc część (b) ustalenia była błędna)

## Not findings (pre-existing, outside this diff)

- Domyślna data dialogów z `new Date().toISOString().slice(0, 10)` (UTC) daje wczorajszą datę między 00:00 a 01:00/02:00 czasu polskiego; na przełomie miesięcy nowy wiersz „Do wydania” pokaże zły miesiąc. Kod sprzed zmiany, warto zgłosić osobno.
- Dochód w dodawaniu nigdy nie ostrzega o ujemnym saldzie, a w edycji ostrzega także gdy operacja saldo poprawia (zachowanie sprzed zmiany, zgodne z planem).
- Edycja wydatku ze zmianą roku zeruje kategorię, więc wiersz Do wydania chwilowo znika (zachowanie sprzed zmiany).
- Brak testów komponentowych dialogów; payload sprawdzono ręcznie względem 44e1630.
