<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: S-04: Pozycje miesiąca — plan i wykonanie

- **Plan**: context/changes/month-items-plan-vs-actual/plan.md
- **Scope**: Full plan
- **Reviewed phases**: 1, 2
- **Date**: 2026-11-02
- **Verdict**: APPROVED
- **Findings**: 0 critical, 2 warnings, 4 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | PASS |
| Scope Discipline | PASS |
| Safety & Quality | PASS |
| Architecture | PASS |
| Pattern Consistency | WARNING |
| Success Criteria | PASS |

## Verification run

- `tests/Unit/MonthItemDeviationTest.php` + `tests/Feature/MonthPlanPageTest.php` na PostgreSQL (obraz Sail): 35 passed, 369 assertions.
- `npm test`: 32 passed (4 pliki). `npm run check`: bez ostrzeżeń. `npm run types:check`: bez błędów.
- `composer ci:check` na obrazie Sail: 707 passed, 2 skipped, 0 failed. Dwa wcześniejsze przebiegi padły (22 i 6 błędów „relation/column does not exist”), bo inna sesja jednocześnie uruchamiała testy na tej samej bazie `testing`. Po jej zakończeniu przebieg jest zielony, więc to kolizja środowiska, nie regresja.
- Ręczne 2.5–2.9: odhaczone w Progress, niezweryfikowane przeze mnie (brak obserwowalnych dowodów w diffie, to z natury kontrole w przeglądarce).

## Findings

### F1 — Komórki odchylenia bez tekstu dla czytników ekranu

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: resources/js/components/MonthPlanItemRow.vue:167-180
- **Detail**: `aria-label` na zwykłym `<span>` (wąski układ) jest ignorowany przez większość czytników, a komórka w układzie `lg` nie ma żadnej etykiety. Siatka to `div`-y bez semantyki tabeli, więc nagłówek „Odchylenie” nie jest powiązany z komórkami. „—” nie niesie znaczenia.
- **Fix**: Zastąpić `aria-label` ukrytym tekstem `<span class="sr-only">{{ t.month_plan.deviation }}: </span>` w obu komórkach.
- **Decision**: FIXED — `sr-only` zamiast `aria-label` w obu komórkach odchylenia

### F2 — Komunikat „Brak pozycji” nad zwiniętą sekcją jednorazowych

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Plan Adherence
- **Location**: resources/js/pages/MonthPlan.vue:397-401
- **Detail**: Gdy jedyne wydatki miesiąca to bezczynne jednorazowe, strona pokazuje „Brak pozycji w tym miesiącu” tuż nad „Jednorazowe bez planu i wydatków (n)”. Kod robi dokładnie to, co plan nakazuje (Phase 2 pkt 5: „lista pokazuje komunikat, a sekcja zwinięta pod nim”), więc to wada projektu planu, nie implementacji. Komunikat jest mylący, bo są tam pozycje do wypełnienia.
- **Fix A ⭐ Recommended**: Pokazywać komunikat tylko gdy `rows.length === 0 && idleRows.length === 0`, zaktualizować zdanie w planie.
  - Strength: Usuwa sprzeczny komunikat; zmiana jednej linii.
  - Tradeoff: Odchodzi od dosłownego brzmienia planu (trzeba dopisać addendum).
  - Confidence: HIGH — warunek wynika wprost z kodu.
  - Blind spot: Nie sprawdzałem tego wizualnie; sekcja zwinięta bez listy nad sobą może wyglądać pusto.
- **Fix B**: Zostawić i zmienić tekst komunikatu na „Brak pozycji z planem lub operacjami”.
  - Strength: Zgodne z planem co do struktury.
  - Tradeoff: Zmiana tłumaczenia używanego też przy zwykłej pustej liście.
  - Confidence: MEDIUM — `empty_list` jest współdzielony z sekcją dochodów.
  - Blind spot: Nie sprawdzałem, gdzie jeszcze używany jest klucz.
- **Decision**: FIXED via Fix A — komunikat tylko gdy brak wierszy na liście i brak bezczynnych jednorazowych (odstępstwo od brzmienia planu, Phase 2 pkt 5)

### F3 — Predykat „rozliczone” poza parą PHP/TS i bez pełnych testów

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Architecture
- **Location**: app/Http/Controllers/MonthPlanController.php:192,215; app/Support/MonthItemDeviation.php:26
- **Detail**: Reguła „dochód albo Rachunek” jest zapisana dwa razy (`$isSettledKind` w kontrolerze i w `MonthItemDeviation`). Dziś zgodne, ale mogą się rozjechać. Test Feature nie asertuje dochodu z wpływem (rozliczone, dodatnie odchylenie) ani Rachunku z samym zwrotem (nierozliczony, odchylenie `null`).
- **Fix**: Dodać `MonthItemDeviation::isSettled(...)`, użyć w kontrolerze i w `deviationMinorUnits`, dopisać przypadki do testu jednostkowego i dwie asercje do `test_rows_expose_deviation_over_plan_and_settled_flags`. Kopia TS niepotrzebna (brak podglądu na żywo).
- **Decision**: FIXED — `MonthItemDeviation::isSettled`, kolumna `settled` w teście jednostkowym, nowy test Feature `test_settled_flag_covers_income_inflow_and_a_bill_with_only_a_refund`

### F4 — Błędna kwota w zwiniętej sekcji jest niewidoczna

- **Severity**: 💡 OBSERVATION
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: resources/js/pages/MonthPlan.vue:414-448
- **Detail**: Użytkownik może wpisać niepoprawną kwotę w bezczynnej jednorazowej, zwinąć sekcję i zobaczyć wyłączony przycisk „Zatwierdź” bez widocznej przyczyny. Działanie jest poprawne (ukryte pola idą z `planRows`), problemem jest tylko UX.
- **Fix**: Otwierać sekcję automatycznie, gdy którykolwiek wiersz w niej ma niepoprawną kwotę.
  - Strength: Mały, lokalny kod; przyczyna blokady staje się widoczna.
  - Tradeoff: Sekcja otwiera się „sama”, co przeczy zasadzie „domyślnie zamknięta” tylko w stanie błędu.
  - Confidence: MEDIUM — nie sprawdzałem, czy pole pokazuje stan błędu.
  - Blind spot: Czy wiersz ma własny wskaźnik niepoprawnej kwoty.
- **Decision**: FIXED — `watch` na `hasInvalidIdleAmount` otwiera sekcję (bez testu: brak testów komponentów Vue w projekcie)

### F5 — Nieużywany import `CategoryChip`

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Scope Discipline
- **Location**: resources/js/pages/MonthPlan.vue:8
- **Detail**: Pozostałość po przeniesieniu markupu wiersza do komponentu. Szablon strony już go nie używa (`grep` zwraca tylko linię importu).
- **Fix**: Usunąć import.
- **Decision**: FIXED — import usunięty

### F6 — Wąska pierwsza kolumna na telefonie

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: resources/js/components/MonthPlanItemRow.vue:56; resources/js/pages/MonthPlan.vue:381
- **Detail**: Poniżej `sm` siatka to `1fr_7rem_7rem` (224 px stałych kolumn). Przy ~375 px na kolumnę kategorii zostaje szacunkowo 60–70 px, a mieści ona chip, plakietki, limit i przełącznik rodzaju. Oszacowanie z klas, bez pomiaru; krok 2.9 jest odhaczony na 360 px (brak przewijania), ale ciasnota nie była kryterium.
- **Fix**: Sprawdzić wizualnie przy 375 px. Jeśli ciasno, zwęzić kolumny mobilne (np. 5,5 rem).
- **Decision**: FIXED — kolumny mobilne 5,5 rem w wierszu i nagłówku (bez pomiaru wizualnego; sprawdzić przy 375 px, zwłaszcza pole kwoty w trybie edycji)
