# Report Redesign Decisions — Plan Brief

> Full plan: `context/changes/report-redesign-decisions/plan.md`

## What & Why

F-07 z kamienia M-4: zanim powstanie kod widoków „Lata” (S-34) i rankingu z panelem (S-35), jeden ADR-0097 i słownik ustalają, jak liczyć lata do tego samego dnia, co znaczą „Pełne lata” i zmiana od pierwszego roku, gdzie działa ranking i jakie kolory mają wykresy. Bez wspólnej decyzji dwa równoległe plany rozstrzygnęłyby to inaczej, a AGENTS.md wymaga słownika i ADR przed kodem.

## Starting Point

Raporty mają cztery okresy (Rok, Miesiąc, 12 miesięcy, Trend), wiersze zawsze w kolejności listy (ADR-0070), wykres słupkowy (ADR-0071, ADR-0084), porównanie tylko z N−1 bez procentów i bez przełącznika (ADR-0074), pełne złote tylko w Trendzie (ADR-0077). Karty Atramentu `PageReportsYears` (A) i `PageReports` (C) pokazują docelowy układ w `brand-text`, którego aplikacja w wykresach nie używa; design system nie ma tokenów wykresu.

## Desired End State

ADR-0097 i akapity „Years”, „Ranking”, „Category Panel” w słowniku opisują wszystko, czego S-34 i S-35 potrzebują, z przykładami liczbowymi jako przyszłymi testami. Siedem starszych ADR-ów ma odsyłacz. `design-system.md` i artefakt Atrament mają `chart-1`/`chart-2`, a pytania 1–2 roadmapy i Unknown S-35 są zamknięte.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) |
| --- | --- | --- |
| Kolory wykresów | `chart-1` wydatek, `success` przychód, `foreground` rok bieżący/najwyższy, `track` pusty rok; `chart-1`/`chart-2` do Atramentu | Token ma jedno znaczenie i sprawdzony kontrast; kod i testy bez zmian |
| Nazwa odczytu | Akapit „Years” w Period Report, `?view=years`, „Lata”; terminy Same-day Cut, Full Years, Change since First Year | Jedna nazwa w UI, kodzie i słowniku, jak Trend |
| Zakres rankingu | Ranking w Roku, Miesiącu, 12 miesiącach; panel tylko w Roku | Jeden układ, wykres słupkowy znika, nic niezaprojektowanego |
| „Pełne lata” | Zmiana i statystyki do ostatniego pełnego roku („Zmiana 2020–2025”) | Niepełny rok nie zaniża wzrostu |
| Zakres lat | Budżety w realizacji od najwcześniejszego do bieżącego roku, niezależnie od sidebara | Jedna tabela; wybór roku jej nie rusza |
| Same-day Cut | Każdy rok od 1.01 do dnia D włącznie, 29.02 → 28.02 | Uogólnienie `subYearNoOverflow` z ADR-0074 |
| „—” vs 0 | „—” brak pozycji w budżecie roku, 0 pozycja bez kwoty | Tak mówi stopka karty |
| Statystyki | „Średnio rocznie” = średnie roczne tempo wzrostu Razem; „Najszybciej rośnie” z min. 4 latami | Odtwarza liczby karty (+11,3%) |
| Procent | Tylko w „Latach” i w podpisie Różnicy panelu | „Różnica” w rankingu zostaje kwotą (ADR-0074) |
| Remis w rankingu | Kolejność listy kategorii | Stabilna kolejność niezależna od id |

## Scope

**In scope:** ADR-0097; odsyłacze w ADR-0070/0071/0074/0077/0079/0081/0084; wpis Period Report w słowniku; „Wykresy” w `design-system.md`; tokeny i kolory kart w artefakcie Atrament; zamknięcie pytań roadmapy.

**Out of scope:** kod, testy, propsy, `.ai/rules/reports.md` (zmienia się z kodem w S-34/S-35), PRD, nowe tokeny w `app.css`, panel dla Miesiąca i 12 miesięcy, „Lata” dla Miesiąca i 12 miesięcy.

## Architecture / Approach

ADR-0097 jest źródłem decyzji; słownik i design system cytują jego frazy dosłownie, co sprawdzają grep-y. Stare ADR-y dostają tylko dopisaną linię na końcu. Artefakt Atrament jest czytany przed publikacją i publikowany w miejscu.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. ADR-0097 i odsyłacze | Pełna decyzja z przykładami, 7 odsyłaczy | Pominięta klauzula starego ADR, która dalej przeczy |
| 2. Słownik | Akapity Years, Ranking, Category Panel; poprawione `_Avoid_` | Rozjazd fraz ze słowem w ADR |
| 3. Design system i roadmapa | „Wykresy”, `chart-1`/`chart-2` w Atramencie, zamknięte pytania | Nadpisanie współdzielonego artefaktu |

**Prerequisites:** brak (roadmapa: F-07 bez warunków wstępnych).
**Estimated effort:** ~1 sesja, 3 fazy, tylko dokumenty.

## Open Risks & Assumptions

- Liczby „Pełnych lat” (42 000, 74 000) są przykładowe, nie z danych; reszta przykładów pochodzi z kart.
- `.ai/rules/reports.md` do czasu S-34/S-35 opisuje stary stan; ADR-0097 wymienia zdania do przepisania.
- Spójność lat 2020–2021 z arkusza (Unknown S-34) zostaje w S-34.

## Success Criteria (Summary)

- S-34 i S-35 planują się bez nowych decyzji domenowych.
- Każda liczba z kart (+89,7%, +11,3%, „od 2022”, „—” przy Kino) wynika z reguł ADR-0097.
- Karty Atramentu i aplikacja używają tych samych tokenów wykresu.
