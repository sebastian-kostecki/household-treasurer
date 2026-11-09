# Podpowiedź kategorii w podglądzie Importu — Plan Brief

> Full plan: `context/changes/import-category-suggestions/plan.md`
> Research: `context/changes/import-category-suggestions/research.md`

## What & Why

Przy Imporcie użytkownik wybiera kategorię dla każdego wiersza ręcznie, choć ten sam sklep klasyfikował już wiele razy. Wiersz podglądu dostanie wstępnie wybraną kategorię, oznaczoną „✦ podpowiedź”, liczoną z wag słów we wcześniejszych operacjach z Importu. Szum płatności kartą (imię, miasto, `POL`) waży ~0, a nazwa sklepu przy jednej kategorii waży ~1.

## Starting Point

Podgląd wzbogaca wiersze dwoma detektorami (duplikaty, pary) i zamraża je w sesji. Każdy wiersz startuje z pustą kategorią, a zmiana typu ją czyści. Kategorie należą do Budgetu jednego roku, bez tożsamości ponad latami. Prototyp algorytmu zmierzył na lokalnych danych 79% wierszy z podpowiedzią i 95% trafnych (sierpień–listopad).

## Desired End State

Wiersz ze znanym sklepem otwiera się z wybraną kategorią i wyciszonym „✦ podpowiedź” pod selektem. Nowy sklep albo BLIK bez nazwy ma puste pole jak dziś. Zmiana typu wstawia podpowiedź nowego typu, a ręczna zmiana kategorii ukrywa znacznik. Typ i zaznaczenie wiersza nie zmieniają się. Słownik, ADR-0034 i PRD opisują funkcję, a test NFR pilnuje czasu podglądu.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Algorytm | Wagi słów 1/k², kNN k=5, próg 0,25, bez Typesense i `pg_trgm` | Dopasowanie całego tekstu przegrywa z szumem płatności kartą | Research (`change.md`) |
| Cel mapowania | Jedyna aktywna kategoria o tej nazwie i typie w Budgecie w execution z **roku daty wiersza** | Tak waliduje zapis i filtruje frontend; korekta notatki „bieżący rok” | Research |
| Niejednoznaczność | Dwie kategorie o tej nazwie, archiwizacja, brak Budgetu albo remis głosów → brak podpowiedzi | Wzorzec ADR-0033; lepiej puste pole niż zgadywanie | Research / Plan |
| Źródło historii | Tylko operacje z Importu (`raw_statement_description` niepusty) | Dokładnie to zmierzono; ten sam styl tekstu co wiersze podglądu | Plan |
| Zmiana typu | Podpowiedź per rodzina: `income` oraz `expense` (zwrot używa `expense`) | Zwrot ze sklepu od razu dostaje kategorię sklepu; powrót do typu ją przywraca | Plan |
| Zakres wierszy | Wszystkie poprawne z datą, także duplikaty i kandydaci par; bez kategorii odsetek | Jedna reguła w serwisie; tekst lokaty opisuje kapitał, nie odsetki | Plan |
| Znacznik | Wyciszony tekst pod selektem z `aria-describedby`, widoczny tylko gdy wartość = podpowiedź | Znacznik mówi prawdę o polu; bez nowego wariantu `Badge` | Plan |
| Jakość | Testy zachowań w CI i ręczny pomiar progu skryptem na kodzie produkcyjnym | Dane bankowe nie trafiają do repo | Plan |
| Wydajność | Indeks odwrócony z kandydatami tylko z tokenów `w(t) ≥ 0,05` i test NFR-PERF-03 (10 000 operacji, plik 300 wierszy, < 2 s) | Prototyp był O(wiersze × historia); bez przycinania szum daje 28 s w benchmarku | Plan / plan review |

## Scope

**In scope:**
- `CONTEXT.md` (Import category suggestion), ADR-0034, PRD (FR-IMP-28, dopisek w FR-IMP-11, kryterium 11.6.19, NFR-PERF-03)
- Czysty model `ImportCategorySuggestionModel` i serwis `ImportCategorySuggester` wpięty w `preview()`
- Prop `rows[i].category_suggestion`, helpery TS, `onTypeChange`, znacznik, prop `ariaDescribedby` w `CategorySelect`
- Stan w kitchen sinku, reguła w `.ai/rules/js.md`, testy Unit/Feature/TS/NFR, skrypt `eval_service.php`

**Out of scope:**
- Wpisy ręczne jako historia, podpowiedź odsetek, formularz ręcznego wpisu
- Zmiana typu lub zaznaczenia, zapisywanie podpowiedzi, migracje
- Świeżość, uczenie z odrzuceń, telemetria, tożsamość Category ponad latami
- Próg trafności w CI, pierwszeństwo względem Settlement candidate

## Architecture / Approach

`POST preview` → detektor duplikatów → detektor par → **`ImportCategorySuggester`**: jedno zapytanie po historię użytkownika z Importu, z której powstaje `ImportCategorySuggestionModel` (wagi i indeks odwrócony). Dla każdego wiersza model daje nazwę dla `income` i `expense`, a `ImportCategoryOptions` (wspólne z listami opcji `showPreview`) mapuje ją na id w roku daty wiersza. Kandydatów generują tylko tokeny o `w(t) ≥ 0,05`. Wynik trafia do sesji. Frontend: `initialSelection` i `onTypeChange` biorą id przez `suggestedCategoryId` (z osłoną listy opcji), a `showsCategorySuggestion` steruje znacznikiem.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Słownik i decyzje domenowe | Hasło, ADR-0034, FR-IMP-28, 11.6.19, NFR-PERF-03 | Przegląd uzna funkcję za sprzeczną z ADR-0010 lub „funkcjami AI”, jeśli ADR ich nie odróżni |
| 2. Backend i kontrakt propsów | Model, serwis, prop `category_suggestion`, typ TS, testy | Fabryki tworzą niespójne `budget_id`, testy mapowania wymagają jawnych Budgetów |
| 3. Wstępny wybór i znacznik | Preselect, zmiana typu, „✦ podpowiedź”, kitchen sink, reguła UI | Kolejność w `onTypeChange` względem blokady `own_transfer` |
| 4. Wydajność i pomiar jakości | Test NFR, `eval_service.php`, ręczny próg | Częste tokeny (`ZAKUP`, `PLN`) mnożą kandydatów; produkcja może odbiec od prototypu |

**Prerequisites:** Sail z PostgreSQL; lokalne dane z Importu do ręcznego pomiaru.
**Estimated effort:** ~3–4 sesje w 4 fazach (faza 1 krótka, fazy 2 i 3 główne).

## Open Risks & Assumptions

- Implementacja różni się od prototypu: filtr użytkownika, głosy po nazwie i typie, remis daje `null`. Pokrycie może spaść nieznacznie. Przy niespełnionym progu implementujący zatrzymuje się i zgłasza wyniki, nie stroi stałych.
- Zmiana nazwy kategorii rozdziela historię (ADR-0016). Akceptujemy to.
- Podgląd w sesji sprzed wdrożenia nie ma klucza; helper TS traktuje go jak brak podpowiedzi.

## Success Criteria (Summary)

- Znany sklep startuje z poprawną kategorią i znacznikiem, a nowy sklep z pustym polem; typ i zaznaczenie się nie zmieniają.
- Na lokalnych danych od stycznia ≥ 78% wierszy ma podpowiedź i ≥ 92% z nich jest trafnych; w okresie sierpień–listopad ≥ 79% i ≥ 95%.
- Podgląd 300 wierszy przy 10 000 operacjach w historii trwa poniżej 2 s.
