# Month Plan Domain Decisions Implementation Plan

## Overview

Element przygotowawczy F-01 z `context/foundation/roadmap.md`. Zapisuje decyzje domenowe planu miesiąca **przed kodem**: nowy ADR-0033 zastępuje odrzucenie „a second monthly Budget” z ADR-0024, a `CONTEXT.md` dostaje nowe terminy, regułę To Spend i progi powiadomień Dashboardu o wadze `warning`, których ADR-0032 wymaga przed pierwszym takim powiadomieniem. Zmiana dotyczy wyłącznie dokumentów; odblokowuje S-01 (`month-plan-and-to-spend`) i S-07 (`month-plan-notices`).

## Current State Analysis

- `.docs/adr/0024-month-remaining-is-not-available-limit.md:5` wprost odrzuca „a second monthly Budget”. PRD (`context/foundation/prd.md:165`, `:197`) wybrał model „Plan miesiąca obok rocznego”, więc bez nowego ADR-a S-01 byłby sprzeczny z zapisaną decyzją.
- `.docs/adr/0032-dashboard-notice-severity.md:3`: każde powiadomienie Dashboardu ma `severity` (`exceeded` / `warning`); żaden rodzaj nie emituje jeszcze `warning`, a dodanie takiego rodzaju to decyzja domenowa (progi, które kategorie) zapisana w `CONTEXT.md` przed kodem.
- `app/Support/DashboardNotices.php:60-148`: dziś są cztery rodzaje (`negative_account`, `negative_term_deposit`, `paced_month_remaining`, `one_off_over_limit`), wszystkie `exceeded`.
- `CONTEXT.md` (161 wierszy) nie zna planu miesiąca. Wpisy, które staną się sprzeczne lub niepełne: **Category** (`:35-37`), **Paced Category** (`:39-41`), **One-off Category** (`:43-45`), **Month Remaining** (`:55-57`), **Month Result** (`:67-69`), **Spendable Liquidity** (`:123-125`). Analogie do odwzorowania: **Initial Plan / Current Plan** (`:27-33`), **Year Elapsed** (`:59-61`).
- PRD rozstrzyga już: wzór „do wydania” (`prd.md:191`), rodzaje pozycji „rachunek” / „bieżąca” (FR-006, FR-019), propozycję kwot (`prd.md:204`, FR-001, FR-015), „odkładam / dobieram” jako jedną kwotę (`prd.md:202`), próg tempa ≥ 10 pp (FR-007), „brak planu” od 3. dnia i „pozycja ponad plan” przy każdym przekroczeniu (FR-012), „miesiąc ma plan” obejmuje propozycję (`prd.md:206`), Month Remaining tylko dla minionych miesięcy bez planu (FR-013, `prd.md:207`), tempo bez kategorii jednorazowych (`prd.md:205`).
- Format (pliki `.agents/skills/domain-modeling/*-FORMAT.md` są od `ce0d94c` poza gitem, więc reguły wprost): ADR to `.docs/adr/NNNN-slug.md` z tytułem H1 i 1–3 akapitami (kontekst, decyzja, powód; opcjonalnie odrzucone opcje); wpis `CONTEXT.md` to `**Term**:`, definicja w 1–2 zdaniach (czym termin jest, nie co robi), polskie copy UI i wiersz `_Avoid_:`. Precedens częściowego zastąpienia: ADR-0023 zastąpił klauzulę ADR-0001 bez edycji ADR-0001.

## Desired End State

- `.docs/adr/0033-month-plan-beside-annual-budget.md` zapisuje model planu miesiąca, regułę To Spend z Month Item Kind, zawężenie Month Remaining, wagi powiadomień planu miesiąca i odrzucone opcje.
- `.docs/adr/0024-…` ma na końcu jedną linię odsyłającą do ADR-0033, tak że czytelnik samego ADR-0024 nie uzna planu miesiąca za odrzucony.
- `CONTEXT.md` ma wpisy: Month Plan, Approved Month Plan, Current Month Plan, Month Item Kind, To Spend, Set-aside, Month Pace, Month Elapsed, Month Summary — z progami powiadomień w definicjach — a sześć istniejących wpisów nie przeczy ADR-0033.
- S-01 może wdrażać wzór i propozycję, a S-07 cztery powiadomienia, bez nowych decyzji domenowych.

### Key Decisions:

Ustalone w tej sesji planowania (pozostałe pochodzą z PRD):

- **Wagi powiadomień planu miesiąca:** To Spend < 0 → `exceeded`; pozycja ponad plan → `exceeded`; Month Pace ≥ 10 pp przed Month Elapsed → `warning`; brak zatwierdzonego planu od 3. dnia → `warning`.
- **„Brak planu” = brak zatwierdzonego planu.** Propozycja istnieje zawsze dla bieżącego miesiąca budżetu w wykonaniu, więc dosłowne „brak planu” nigdy by nie zaszło.
- **One-off ponad plan:** powiadomienie przy każdym przekroczeniu, także przy planie miesiąca 0 (np. „Wakacje”: plan 0, wydatek 3000 → powiadomienie).
- **Month Pace:** jedna pula wszystkich pozycji Running bez kategorii One-off, jedno powiadomienie.
- **Month Elapsed:** dzień miesiąca / liczba dni miesiąca, z dzisiejszym dniem włącznie; daty odniesienia jak Year Elapsed.
- **Nazwy angielskie:** Month Plan (Proposal / Approved), Approved Month Plan, Current Month Plan, Month Item Kind (Bill / Running), To Spend, Set-aside (ujemny = Draw), Month Pace, Month Elapsed, Month Summary.
- **Zakres dokumentów:** ADR-0033 + `CONTEXT.md` + odsyłacz w ADR-0024; jeden ADR, nie dwa.
- **Średnia z rocznego (przegląd planu, F1):** floor(Current Plan / 12) tej Category w Minor units, grudzień bierze resztę (Current Plan − 11 × floor), jak odczyt wersji roboczej w `MonthRemainingCalculator.php:81-90`; źródło propozycji to Month Plan poprzedniego miesiąca kalendarzowego w dowolnym stanie.

## What We're NOT Doing

- Żadnego kodu, migracji, testów ani tłumaczeń w `lang/`.
- Bez zmian w dokumentach, które ADR-0033 nadpisuje dla miesięcy z Month Plan — zmienią je slice'y razem z kodem (AGENTS.md: dokumenty nie mają opisywać więcej niż kod): `.docs/prd.md:191` FR-TRX-08 (S-08), `.docs/prd.md:296` FR-REP-13 (S-06), `.docs/specs/dashboard-hierarchy.md` kafel Month Remaining (S-06) i powiadomienie `paced_month_remaining` (S-07), `.docs/adr/0031-…:5` zdanie o alertach Month Remaining (S-07), `.ai/rules/js.md:24` ostrzeżenie „over Month Remaining” (S-08).
- Bez reguły propozycji dla Set-aside (czy kopiuje się z poprzedniego miesiąca, czy startuje od 0) — rozstrzyga S-02. Do tego czasu S-01 traktuje Set-aside jako 0.
- Bez kolejności nowych rodzajów powiadomień w strefie uwagi, linków powiadomień i pomijania kategorii zarchiwizowanych — to szczegóły UI S-07 w specyfikacji Dashboardu.
- Bez sposobu przechowywania propozycji (wyliczana czy zapisywana) — szczegół S-01.
- Bez przebudowy niezwiązanych wpisów słownika (ryzyko F-01 z roadmapy).
- Bez zmiany numeracji ADR-ów na gałęzi `feat/backfill`.

## Implementation Approach

Najpierw decyzja (ADR-0033), potem słownik, który się na nią powołuje. Oba dokumenty po angielsku, jak reszta `.docs/adr/` i `CONTEXT.md`; polskie copy UI w definicjach jak w istniejących wpisach („Polish UI copy: …”). Każda reguła liczbowa jest zapisana jednoznacznie (granice, „włącznie”, znak), bo S-01 i S-07 mają ją wdrożyć dosłownie.

## Critical Implementation Details

- **Numer ADR:** 0033 — najwyższy na `development` to 0032. Lokalna, nieaktualna gałąź `feat/backfill` ma własne 0031–0033, które i tak kolidują z `development`; jej ewentualny merge przenumeruje własne pliki.
- **Spójność predykatów:** te same progi i znaki muszą brzmieć identycznie w ADR-0033 i `CONTEXT.md`, po angielsku dosłownie „10 percentage points” (≥, włącznie) i „3rd day” (od 3. dnia miesiąca włącznie); plan 0 też liczy się do „ponad plan”; dodatni Set-aside obniża To Spend. Powiadomienia nazywać dosłownie „a `warning` Dashboard notice” / „an `exceeded` Dashboard notice”. Weryfikacja 2.2 szuka tych fraz.

## Phase 1: ADR-0033 i odsyłacz w ADR-0024

### Overview

Zapisuje decyzję, która zastępuje odrzucenie „a second monthly Budget” i wyznacza granicę między planem miesiąca a budżetem rocznym.

### Changes Required:

#### 1. Nowy ADR

**File**: `.docs/adr/0033-month-plan-beside-annual-budget.md`

**Intent**: Zapisać, że Budget w wykonaniu ma obok rocznego planu Month Plan na bieżący i przyszłe miesiące; że To Spend zastępuje Month Remaining w miesiącach z planem; oraz dlaczego odrzucono alternatywy. Przyszły czytelnik musi zrozumieć, czemu istnieją dwa plany.

**Contract**: Tytuł (H1) plus akapity, gęstość jak ADR-0030. Musi stwierdzić:

- Supersedes wyłącznie klauzulę ADR-0024 odrzucającą „a second monthly Budget”; reszta ADR-0024 (roczny Available Limit, arytmetyka Month Remaining) obowiązuje.
- Month Plan: Budget w wykonaniu, jeden miesiąc kalendarzowy, pozycje to Categories tego Budget, kwoty w Minor units, plus jedna kwota Set-aside. Dostępny dla bieżącego i przyszłych miesięcy; minione miesiące zostają bez planu. Zatwierdzone kwoty nie zmieniają się po zmianie rocznego Current Plan; budżet roczny działa bez zmian; różnica między sumą planów miesięcy a rokiem może istnieć i ma być widoczna.
- Proposal: kwoty z Month Plan poprzedniego miesiąca kalendarzowego w dowolnym stanie (Approved → Current Month Plan, Proposal → kwoty tej Proposal); styczeń z grudnia poprzedniego Budget dla Category o tej samej nazwie i typie (zapytanie, nie wspólna tożsamość); bez źródła → średnia z rocznego = floor(Current Plan / 12) tej Category w Minor units, a w grudniu reszta (Current Plan − 11 × floor), jak odczyt wersji roboczej w `MonthRemainingCalculator.php:81-90` — to kwota propozycji, nie Month Remaining; One-off zawsze startuje od 0; Category dodana w trakcie roku dołącza z 0; zarchiwizowana zostaje w miesiącu, w którym ma plan albo wydatek, i znika z kolejnych propozycji.
- Reguła To Spend z Month Item Kind (dokładny predykat jak we wpisie To Spend w Fazie 2); „miesiąc ma plan” obejmuje Proposal.
- Month Remaining zostaje tylko dla minionych miesięcy bez Month Plan, więc powiadomienie `paced_month_remaining` przestaje dotyczyć Dashboardu, który zawsze pokazuje bieżący miesiąc (ADR-0031), gdy bieżący Budget jest w wykonaniu.
- Dokumenty nadpisane dla miesięcy z Month Plan, dopóki S-06/S-07/S-08 ich nie zaktualizują: FR-TRX-08 i FR-REP-13 z `.docs/prd.md`, kafel i powiadomienie Month Remaining z `.docs/specs/dashboard-hierarchy.md`, zdanie o alertach Month Remaining w ADR-0031, ostrzeżenie „over Month Remaining” w `.ai/rules/js.md`.
- Month Item Kind kopiuje się na nowy Budget jak flaga Paced / One-off; precedens to ADR-0024:3 i `app/Actions/CreateBudget.php:44` (kopiuje `is_paced`), nie ADR-0014, który tej flagi nie wymienia.
- Wagi czterech powiadomień planu miesiąca (Key Decisions) z uzasadnieniem według definicji ADR-0032: przekroczenie pozycji jest „już faktem”, więc `exceeded`, mimo że plan miesiąca jest miękki.
- Odrzucone: roczny z korektami (domyślne kwoty byłyby średnią, czyli założeniem), pełny plan miesięczny (przebudowa budżetu rocznego i migracja danych), przenoszenie reszty (łańcuch miesięcy, podwójne liczenie z Draw), zamykanie miesiąca, fundusz wydatków nieregularnych, tempo per Category (tygodniowe zakupy dają szum).

#### 2. Odsyłacz w ADR-0024

**File**: `.docs/adr/0024-month-remaining-is-not-available-limit.md`

**Intent**: Agent czytający sam ADR-0024 nie może uznać Month Plan za odrzucony.

**Contract**: Jedna nowa linia na końcu pliku, np. „Superseded in part by ADR-0033: …” — nazywa zastąpioną klauzulę (second monthly Budget) i to, że Month Remaining zostaje dla minionych miesięcy bez Month Plan. Istniejące akapity bez zmian.

### Success Criteria:

#### Automated Verification:

- ADR istnieje i numer 0033 jest jedyny: `ls .docs/adr | grep -c '^0033-'` zwraca `1`
- ADR-0033 powołuje się na ADR-0024 i ADR-0032: `grep -c 'ADR-0024\|ADR-0032' .docs/adr/0033-*.md` ≥ 2
- ADR-0024 odsyła do ADR-0033, a jego dotychczasowe akapity są nietknięte: `git diff development -- .docs/adr/0024-*.md | grep -c '^-[^-]'` zwraca `0`, a `grep -c 'ADR-0033' .docs/adr/0024-*.md` ≥ 1

#### Manual Verification:

- ADR-0033 zawiera każdy punkt z Contract (model, Proposal, To Spend, zawężenie Month Remaining, wagi, odrzucone opcje) i nie przeczy PRD (`context/foundation/prd.md`, „Rozstrzygnięcia domenowe”)
- Czytelnik samego ADR-0024 widzi, że klauzula o „second monthly Budget” jest zastąpiona

**Implementation Note**: Po zielonej weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przed Fazą 2.

---

## Phase 2: Terminy i progi w CONTEXT.md

### Overview

Dodaje słownik planu miesiąca z progami powiadomień i koryguje wpisy, które bez tego przeczyłyby ADR-0033.

### Changes Required:

#### 1. Nowe wpisy

**File**: `CONTEXT.md`

**Intent**: Dać S-01 i S-07 jednoznaczne terminy i predykaty. Wpisy wstawić po **Month Remaining** / **Year Elapsed**, w kolejności zależności. Format jak istniejące wpisy (zob. „Current State Analysis”): definicja, polskie copy UI, `_Avoid_:`.

**Contract**: Każdy wpis musi zawierać co najmniej:

- **Month Plan** — plan jednego miesiąca kalendarzowego Budget w wykonaniu, obok rocznego Current Plan; pozycje to Categories tego Budget plus jedna Set-aside; tylko bieżący i przyszłe miesiące. Stan Proposal (niezatwierdzony; kwoty z reguły propozycji, jak w ADR-0033) albo Approved. „Miesiąc ma plan” obejmuje Proposal. Próg: od 3. dnia bieżącego miesiąca włącznie Month Plan wciąż w stanie Proposal daje powiadomienie Dashboardu `warning`. Copy: „plan miesiąca”, „propozycja” / „niezatwierdzony”, „zatwierdzony”. _Avoid_: monthly Budget, second Budget, Month Remaining, Envelope.
- **Approved Month Plan** — kwoty zamrożone przy zatwierdzeniu, potem niezmienne (jak Initial Plan). Nie zmieniają się po zmianie rocznego Current Plan. Copy: „plan zatwierdzony”.
- **Current Month Plan** — żywe kwoty po zatwierdzeniu, edytowalne w trakcie miesiąca (jak Current Plan); na koniec miesiąca to plan końcowy. Copy: „plan miesiąca” w trakcie miesiąca, „plan końcowy” w Month Summary. _Avoid_: „plan bieżący” (to copy rocznego Current Plan, `lang/pl/ui.php:121,163`), Current Plan (bez „Month”).
- **Month Item Kind** — atrybut expense Category: Bill („rachunek”: po pierwszym zaksięgowanym w miesiącu wydatku — Financial Operation z kwotą dodatnią — na tej Category kwota faktyczna, ze zwrotami netto, zastępuje plan; sam zwrot tego nie robi) albo Running („bieżąca”: plan obowiązuje cały miesiąc, liczy się większa z planu i faktycznej). Domyślnie Running. Income Category go nie ma (dochód zawsze liczy się jak Bill). Niezależny od flagi Paced / One-off. Kopiowany na nowy Budget jak flaga Paced (ADR-0024:3; `CreateBudget.php:44`). „Rachunek” to tu rodzaj pozycji, nie Account („rachunek bankowy” w UI). _Avoid_: Paced (dla Running), One-off (dla Bill), Account (dla Bill), fixed / variable.
- **To Spend** — podpisany odczyt w Minor units, niezapisywany: przychody (plan, a po pierwszym wpływie w miesiącu kwota faktyczna) − pozycje Bill (plan, a po pierwszym wydatku z kwotą dodatnią w miesiącu kwota faktyczna) − pozycje Running (większa z: plan, kwota faktyczna) − Set-aside. Plan = Current Month Plan, a przed zatwierdzeniem Proposal. Kwota faktyczna = Execution tej Category z operacji datowanych w tym miesiącu (zwroty netto). Może być ujemny i niczego nie blokuje; Own Transfer i Capital Movement go nie zmieniają. Próg: poniżej zera → powiadomienie Dashboardu `exceeded`. Pozycja ponad plan: expense Category, której kwota faktyczna w miesiącu przekracza jej kwotę planu (każde przekroczenie, także One-off z planem 0, niezależnie od Month Item Kind) → powiadomienie `exceeded`. Copy: „do wydania”. _Avoid_: Spendable Liquidity, Month Remaining, Available Limit, Month Result, Pozostało.
- **Set-aside** — jedna podpisana kwota na Month Plan: dodatnia („odkładam”) obniża To Spend, ujemna to Draw („dobieram”) i go podnosi. Nie Category, nie przelew, nie zmienia Liquidity. Draw, którego wartość bezwzględna przekracza Spendable Liquidity (|Draw| > Spendable Liquidity), daje ostrzeżenie bez blokady. Copy: „odkładam / dobieram”. _Avoid_: Savings (jako Category lub Account), Reserve, Earmark, transfer.
- **Month Pace** — odsetek: suma kwot faktycznych (sama Execution w miesiącu, nie większa z planu i faktycznej) / suma kwot planu w puli pozycji Running, które nie są One-off Category. Brak odczytu, gdy suma planu puli wynosi 0. Próg: Month Pace − Month Elapsed ≥ 10 punktów procentowych → powiadomienie Dashboardu `warning`. Copy: „tempo”. _Avoid_: Year Elapsed, Year Pace, Month Remaining.
- **Month Elapsed** — dzień miesiąca daty odniesienia / liczba dni tego miesiąca. Data odniesienia: dziś dla bieżącego miesiąca, ostatni dzień dla minionego, pierwszy dzień dla przyszłego (jak Year Elapsed). Nie kwota. _Avoid_: Year Elapsed, Month Pace.
- **Month Summary** — odczyt miesiąca z Month Plan: Approved i Current (końcowy) Month Plan, Execution, odchylenia, Set-aside zamierzony oraz realne odkładanie (Month Result), dobrane kwoty (Draw) także w skali roku. Rozliczenie to tylko podsumowanie: bez przenoszenia i zamykania. Copy: „podsumowanie miesiąca”. _Avoid_: Month close, Year Close, carry-over.

#### 2. Korekty istniejących wpisów

**File**: `CONTEXT.md`

**Intent**: Usunąć sprzeczności z ADR-0033 minimalnymi dopiskami; bez przeredagowania wpisów.

**Contract**:

- **Category** — expense Category ma też Month Item Kind; pozycja Month Plan to Category.
- **Paced Category** / **One-off Category** — `_Avoid_` dostaje odpowiednio Running / Bill (flaga roczna ≠ Month Item Kind); One-off dopisuje, że w Month Plan startuje od 0 w każdej Proposal i nie wchodzi do Month Pace.
- **Month Remaining** — odczyt tylko dla minionych miesięcy bez Month Plan; bieżący i przyszłe miesiące (oraz minione z zatwierdzonym planem) pokazują To Spend (ADR-0033). `_Avoid_` dostaje To Spend.
- **Month Result** — `_Avoid_` dostaje To Spend; jedno zdanie: na koniec miesiąca to realne odkładanie w Month Summary.
- **Spendable Liquidity** — `_Avoid_` dostaje To Spend.

### Success Criteria:

#### Automated Verification:

- Wszystkie nowe terminy mają wpis: `for t in 'Month Plan' 'Approved Month Plan' 'Current Month Plan' 'Month Item Kind' 'To Spend' 'Set-aside' 'Month Pace' 'Month Elapsed' 'Month Summary'; do grep -q "^\*\*$t\*\*:" CONTEXT.md || echo "missing $t"; done` nic nie wypisuje
- Progi są zapisane dosłownie: ``grep -q '10 percentage points' CONTEXT.md && grep -q '3rd day' CONTEXT.md && grep -qF '`warning` Dashboard notice' CONTEXT.md && grep -qF '`exceeded` Dashboard notice' CONTEXT.md`` kończy się kodem 0 (samo „warning” już występuje w `CONTEXT.md:117`)
- Diff obejmuje tylko trzy dokumenty (plus artefakty zmiany i roadmapę): `git diff --name-only development -- . ':!context'` zwraca wyłącznie `.docs/adr/0024-…`, `.docs/adr/0033-…`, `CONTEXT.md`

#### Manual Verification:

- Przykład To Spend z wpisu daje 1700: przychód plan 6000 (pensja jeszcze nie wpłynęła); Bill czynsz plan 2000, zapłacone 1950; Running żywność plan 1500, wydane 400; Running paliwo plan 300, wydane 350; Set-aside +500 → 6000 − 1950 − 1500 − 350 − 500 = 1700; z Set-aside −300 (Draw) → 2500
- Przykład Month Pace z wpisu: pula Running bez One-off plan 1800 (1500 + 300), wydane 750 → 41,7%; 10. dzień 30-dniowego miesiąca (33,3%) → 8,3 pp, brak ostrzeżenia; 9. dzień (30,0%) → 11,7 pp → `warning`
- Żaden z sześciu poprawionych wpisów nie przeczy ADR-0033; progi i znaki brzmią tak samo w ADR-0033 i `CONTEXT.md`
- Słownik nie urósł poza dziewięć nowych terminów i dopiski w sześciu istniejących wpisach (ryzyko F-01)

**Implementation Note**: Po zielonej weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie.

---

## Testing Strategy

### Unit Tests:

- Brak — zmiana dokumentacyjna. Przykłady liczbowe z Fazy 2 (To Spend = 1700 / 2500; Month Pace 8,3 pp / 11,7 pp) są gotowymi przypadkami testowymi dla S-01 i S-07.

### Integration Tests:

- Brak.

### Manual Testing Steps:

1. Przeczytać ADR-0033 obok PRD, sekcja „Rozstrzygnięcia domenowe” — każda decyzja ma odpowiednik albo świadome pominięcie z „What We're NOT Doing”.
2. Przeliczyć oba przykłady z Fazy 2 według definicji z `CONTEXT.md`, bez zaglądania do planu.
3. Sprawdzić przypadki graniczne: 2. dzień miesiąca z Proposal (brak `warning`), 3. dzień (jest `warning`); One-off plan 0, wydatek 1 gr (`exceeded`); Bill z samym zwrotem −50 w miesiącu (zostaje plan); pierwsza propozycja przy Current Plan 12 000 zł → 1 000 zł, a przy 100 005 gr → 8 333 gr w listopadzie i 8 342 gr w grudniu.

## Performance Considerations

Nie dotyczy.

## Migration Notes

Nie dotyczy — brak zmian w danych. Wycofanie to revert commitów dokumentacyjnych.

## References

- Roadmapa: `context/foundation/roadmap.md` (F-01, S-01, S-07)
- PRD: `context/foundation/prd.md` (FR-001…FR-019, „Rozstrzygnięcia domenowe”, „Constraints & Compatibility”)
- ADR-y: `.docs/adr/0024-month-remaining-is-not-available-limit.md`, `.docs/adr/0032-dashboard-notice-severity.md`, `.docs/adr/0031-dashboard-always-shows-current-calendar-year.md`, `.docs/adr/0030-year-close-posts-year-result-into-reserve.md` (wzór gęstości)
- Formaty: reguły wprost w „Current State Analysis” (źródło lokalne, poza gitem: `.agents/skills/domain-modeling/`)
- Precedens kopiowania flagi: `app/Actions/CreateBudget.php:44`; średnia propozycji: `app/Support/MonthRemainingCalculator.php:81-90`
- Przegląd planu: `context/changes/month-plan-domain-decisions/reviews/plan-review.md`
- Powiadomienia dziś: `app/Support/DashboardNotices.php:60-148`, `.docs/specs/dashboard-hierarchy.md:49`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: ADR-0033 i odsyłacz w ADR-0024

#### Automated

- [x] 1.1 ADR istnieje i numer 0033 jest jedyny — 7bf6364
- [x] 1.2 ADR-0033 powołuje się na ADR-0024 i ADR-0032 — 7bf6364
- [x] 1.3 ADR-0024 odsyła do ADR-0033, a jego dotychczasowe akapity są nietknięte — 7bf6364

#### Manual

- [x] 1.4 ADR-0033 zawiera każdy punkt z Contract i nie przeczy PRD — 7bf6364
- [x] 1.5 Czytelnik samego ADR-0024 widzi, że klauzula o „second monthly Budget” jest zastąpiona — 7bf6364

### Phase 2: Terminy i progi w CONTEXT.md

#### Automated

- [x] 2.1 Wszystkie nowe terminy mają wpis — 72f7f5a
- [x] 2.2 Progi są zapisane dosłownie — 72f7f5a
- [x] 2.3 Diff obejmuje tylko trzy dokumenty — 72f7f5a

#### Manual

- [x] 2.4 Przykład To Spend z wpisu daje 1700 (z Draw 2500) — 72f7f5a
- [x] 2.5 Przykład Month Pace z wpisu (8,3 pp brak ostrzeżenia; 11,7 pp `warning`) — 72f7f5a
- [x] 2.6 Żaden z sześciu poprawionych wpisów nie przeczy ADR-0033; progi brzmią tak samo — 72f7f5a
- [x] 2.7 Słownik nie urósł poza dziewięć nowych terminów i dopiski w sześciu istniejących wpisach — 72f7f5a
