# Widok Operacji w systemie „C · Atrament i morski” — Plan Brief

> Full plan: `context/changes/ui-operations-atrament/plan.md`
> Research: `context/changes/ui-operations-atrament/research.md`

## What & Why

`/operations` przechodzi na wybrany design system: zakładki sekcji, pasek filtrów w panelu, lista pogrupowana po dniach z sumą dnia, kwoty w JetBrains Mono bez „zł” w komórkach. Wydatek przestaje być czerwony (pkt 7 README systemu), bo czerwień ma znaczyć przekroczenie, a lista z przewagą wydatków dziś świeci na czerwono. Wiersze dnia (pkt 8) odpowiadają na pytanie „ile wydałem danego dnia” bez liczenia w głowie.

## Starting Point

Trzy panele jeden pod drugim (operacje, transfery, ruchy kapitału), pięć przycisków w tytule z „Import operacji” jako primary, płaska lista z datą w każdym wierszu, wydatek `destructive`, kafelek typu 40 px, ręczny stary przepis panelu w filtrach. Układ wynika z kryteriów zamkniętych issue #19/#35/#80, nie z ADR. Tokeny (ADR-0035) i style/kontrolki (ADR-0037) są już w `app.css`.

## Desired End State

Tytuł z zarysowanym „Importuj” i primary „Dodaj operację” (menu: Dochód, Wydatek, Transfer własny, Ruch kapitału). Pod nim zakładki z licznikami, wybór w `?section=`. Jeden panel: pasek filtrów z „X z Y”, wiersze dnia („środa, 4 listopada” i suma w kolumnie kwot), wiersze Opis | Kategoria | Data | Kwota, stopka „Kwoty w zł”. Stany: brak rekordów, pusty wynik, sekcja wyciszona filtrem, sekcja bez rekordów, ładowanie.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Sekcje | Zakładki `SegmentedControl` neutral z licznikiem, stan w `?section=` | Link i odświeżenie trzymają zakładkę | /10x-ui |
| Akcje w tytule | Primary „Dodaj operację” z menu, „Importuj” zarysowany, dialogi bez zmian | Jedna akcja główna bez scalania dialogów | /10x-ui |
| Kolumny operacji | Opis (kafelek, konto w 2. linii) \| Kategoria \| Data \| Kwota \| akcje | Jak mockup | /10x-ui |
| Suma dnia | Serwer, cały dzień, te same filtry, jedno GROUP BY | Ta sama liczba na obu stronach przy dniu na granicy strony | /10x-ui |
| Kolor kwot | Dochód i zwrot `+` `success`, wydatek `−` `foreground`; suma dnia tak samo | Czerwień tylko dla przekroczeń | Research |
| Wyciszona zakładka | Zostaje wybrana, licznik 0, notka i „Wyczyść filtry” | Użytkownik widzi, dlaczego pusto | Research |
| Filtry | W panelu nad wierszami, jeden pasek dla wszystkich zakładek | Jak mockup | Research |
| Zły `section` | Błąd walidacji jak inne filtry | Spójne z `IndexOperationsRequest` | Research |
| Transfery i ruchy kapitału | Dotychczasowe kolumny w nowym stylu + stopka z objaśnieniem | Zachowuje opis i akcje | Plan |
| ADR | ADR-0038 (0036 zajęty przez raport okresu) | Odwraca kryteria issue, których nie trzyma żaden ADR | Research |

## Scope

**In scope:**
- ADR-0038, dopisek w ADR-0035, `.docs/design-system.md`, `.ai/rules/js.md`
- Formatery (kwota operacji, suma dnia, data słowami), kafelek typu, licznik w `SegmentedControl`, siatka i wiersz dnia
- Prop `section`, `operationDaySums`, `sectionTotals`; klasy `OperationsDaySums`, `OperationsSectionTotals`; testy Feature
- Widok, pasek filtrów, menu akcji, puste stany, sink 7 stanów, straż literałów, zrzuty

**Out of scope:**
- Ukrywanie transferów przy każdym filtrze (domena), ⌘K i skróty, jeden dialog z wyborem typu
- Kolumny transferów z mockupu, wiersze dnia w transferach, klikalna pigułka kategorii
- Przepis wiersza `text-sm` wspólny z Budżetem, wpis „suma dnia” w `CONTEXT.md`

## Architecture / Approach

Najpierw dokumenty, potem elementy współdzielone sprawdzone na imporcie i Planie miesiąca, potem kontrakt propsów po obu stronach w jednym commicie, na końcu widok. Sumę dnia liczy `OperationsDaySums` jednym zapytaniem na filtrach `OperationsListQuery::apply()` (dni bieżącej strony, cały dzień), front tylko grupuje wiersze i czyta mapę sum. Query listy buduje jeden helper TS, żeby filtry i zakładki nie gubiły `section`.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Decyzje i dokumenty | ADR-0038 i zgodne dokumenty | Konflikt z `period-report-by-category` w tych samych plikach |
| 2. Warstwa współdzielona | Formatery z testami, kafelek, licznik zakładek, siatka, straż | Regresja w podglądzie importu i Planie miesiąca |
| 3. Kontrakt serwera | `section`, sumy dni, liczby sekcji, testy SQLite + PostgreSQL | Daty na SQLite; nowe SL102 w Sloppy |
| 4. Widok i stany | Zakładki, filtry w panelu, menu, wiersze dnia, sink, zrzuty | Szerokość kolumn przy 390 px; filtry gubiące `section` |

**Prerequisites:** Sail z PostgreSQL działa; ADR-0035 i ADR-0037 są na `development`.
**Estimated effort:** ~2–3 sesje w 4 fazach.

## Open Risks & Assumptions

- Dzień przecięty granicą strony pokazuje sumę całego dnia, choć część jego wierszy jest na sąsiedniej stronie (świadoma decyzja).
- Reka pomija wyłączone pozycje menu przy nawigacji klawiaturą; powód musi być widoczny w pozycji.
- `period-report-by-category` też edytuje `.docs/design-system.md` i `.ai/rules/js.md`; przy scalaniu możliwy konflikt tekstowy.

## Success Criteria (Summary)

- `/operations` odpowiada mockupowi w obu motywach przy 1280 i 390 px; wydatki bez czerwieni, suma dnia w kolumnie kwot
- Zakładka przetrwa paginację, filtry i odświeżenie; puste i wyciszone stany mówią, dlaczego pusto
- `composer ci:check`, `sloppy ci`, `npm run check`, `check:ui`, `types:check`, `npm test` zielone
