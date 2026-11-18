# Import wyciągu z przełomu przy szkicu roku — Plan Brief

> Full plan: `context/changes/import-across-draft-year/plan.md`

## What & Why

Wyciąg 29.12.2026–02.01.2027 przy szkicu Budżetu 2027 ma dać się zapisać dla wierszy grudnia, a przy wierszach stycznia użytkownik ma przeczytać, że trzeba rozpocząć realizację Budżetu 2027 (roadmapa S-42, MS-04). Dziś przy przełomie roku Import odmawia ogólnym „Uzupełnij klasyfikację” albo, przy zapisie, odrzuca cały zestaw komunikatem bez wskazania wiersza; próba przełomu (2026-11-18) zostawiła to jako `_known_gap` w `CsvImportTest`.

## Starting Point

Serwer nie sprawdza fazy roku w podglądzie, więc oba wiersze są „poprawne”; klient nie dostaje kategorii roku-szkicu, więc wiersz stycznia ma pusty wybór „Brak kategorii dla roku operacji” i blokuje przycisk jako niekompletny. Zapis robi jedna transakcja: wiersz w roku bez realizacji wycofuje wszystko. Klient nie odróżnia szkicu od braku Budżetu, a przelew własny i ruch kapitału są w roku-szkicu przyjmowane.

## Desired End State

Wiersz stycznia pokazuje notatkę „Budżet 2027 jest szkicem. Dochód, wydatek i zwrot z 2027 zaimportujesz po rozpoczęciu jego realizacji.” z odnośnikiem „Przejdź do Budżetu 2027”. Zaznaczony wydatek, dochód, zwrot albo ruch kapitału z odsetkami ze stycznia blokuje „Zatwierdź import” powodem z liczbą wierszy i rokiem; zaznaczony przelew własny nie blokuje. Po odznaczeniu stycznia zapisuje się grudzień, a po starcie 2027 styczeń wchodzi ponownym wgraniem pliku. Rok bez Budżetu ma wariant „Nie ma Budżetu 2028 …”. Serwer przy obejściu klienta dalej wycofuje całość, ale komunikat podaje datę wiersza i rok.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Wiersze stycznia przy szkicu | Odmowa w podglądzie, zestaw zostaje atomowy | Zapis częściowy łamie FR-IMP-20 i ryzyko #1 planu testów, a przyczynę lepiej widać przed zapisem | Plan (pytanie 1) |
| Co blokuje | Tylko dochód, wydatek, zwrot i odsetki z ruchu kapitału | Przelew własny i ruch kapitału są w roku-szkicu przyjmowane, a typ wybiera użytkownik w podglądzie | Plan (badanie kodu) |
| Wiersz roku bez realizacji | Notatka i powód blokady, nie nowy `ImportRowInvalidReason` | Wiersz niepoprawny zablokowałby też przelew własny | Plan |
| Zakres | Szkic i rok bez Budżetu | Ten sam mechanizm i prop obejmują oba przypadki z testów (`CsvImportTest`, `ImportIntegrityTest`) | Plan (pytanie 2) |
| Akcja w notatce | Odnośnik do Budżetu roku, start realizacji zostaje na stronie Budżetu | Nieodwracalny start przy widoku planu i listy kontrolnej, nie z ekranu Importu | Plan (pytanie 3) |
| Komunikat serwera | Import-specyficzny, z datą wiersza i rokiem; ręczny wpis bez zmian | Fallback przy obejściu klienta mówi, który wiersz i co zrobić | Plan |
| Testy | Feature na PostgreSQL, Vitest test-first, bez E2E | E2E w CI to dopiero S-44 | Plan |

## Scope

**In scope:**
- prop `budgetYearStates` (realizacja, szkic, brak) po obu stronach z `assertInertia`;
- odmowa zapisu z datą i rokiem w `FinancialOperationImportValidator`;
- funkcje `lib/import-*` i nowy powód `import_block_budget_year_not_in_execution`;
- notatka wiersza z odnośnikiem, teksty w `lang/pl/ui.php`, scenariusz przełomu w `/dev/import-preview`;
- `_known_gap` jako zwykły test oraz ADR-0103.

**Out of scope:**
- zapis częściowy i start realizacji z podglądu;
- rok wczytany z arkusza, treść `no_*_category_for_year` i dialogi ręcznego wpisu;
- powrót do tego samego podglądu po starcie i zbiorowe odznaczanie;
- przypomnienie w „Wymaga uwagi” (S-41) i spec E2E (S-44).

## Architecture / Approach

`ImportBudgetYearStates` (nowa klasa w `app/Support/CsvImport`) liczy jednym zapytaniem stan Budżetu dla lat z wierszy. Kontroler wysyła go jako prop podglądu, a walidator zapisu używa go przed `OperationCategoryChoice`. Po stronie klienta czyste funkcje w `import-preview-rows.ts` i `import-submit-block.ts` decydują zależnie od typu. `ImportPreviewRow.vue` pokazuje notatkę (`Alert variant="note"`, `Link`, nie zagnieżdżony formularz), a strona składa tekst powodu blokady.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Serwer: stan roku i odmowa z datą | Prop `budgetYearStates`, komunikat z datą i rokiem, testy zamiast `_known_gap`, ADR-0103 | Kontrola fazy w walidatorze nie może zablokować przelewu własnego ani zmienić ręcznego wpisu |
| 2. Klient: logika blokady | Funkcje stanu roku wiersza i nowy powód, Vitest najpierw | Kolejność powodów: nowy musi wyprzedzać „niekompletne” |
| 3. Klient: widok i kitchen sink | Notatka z odnośnikiem, teksty, scenariusz przełomu, check:ui, ręczny przebieg | Odnośnik wewnątrz formularza Importu nie może wysłać Importu |

**Prerequisites:** brak (S-42 nie czeka na F-08). Baza testowa `testing_import_across_draft_year` w kontenerze PostgreSQL; do ręcznego przebiegu podgląd worktree według `worktree-preview.md`.
**Estimated effort:** ~2 sesje w 3 fazach.

## Open Risks & Assumptions

- Zakładam, że rok wczytany z arkusza ma Budżet w realizacji i dalej odmawia przez `OperationCategoryChoice`; plan go nie dotyka.
- Po zapisie grudnia styczeń wymaga ponownego wgrania pliku (podgląd znika z sesji po sukcesie). To świadomy koszt odnośnika zamiast startu w podglądzie.
- Roadmapa M-5 jest tylko w niezacommitowanym `roadmap.md` głównego checkoutu; status S-42 aktualizuje się tam, nie w tej gałęzi.

## Success Criteria (Summary)

- Na wyciągu 29.12–02.01 przy szkicu 2027 zapisują się wiersze grudnia, a wiersze stycznia mówią, że trzeba rozpocząć realizację Budżetu 2027, i prowadzą do niego.
- Zestaw Importu zostaje atomowy: testy integralności przechodzą bez zmian, a odmowa serwera wskazuje wiersz i rok.
- `_known_gap` z `CsvImportTest` nie istnieje; jego scenariusz jest zwykłym testem nowego kontraktu.
