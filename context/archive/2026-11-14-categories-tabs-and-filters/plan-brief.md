# Kategorie w zakładkach z filtrami — Plan Brief

> Full plan: `context/changes/categories-tabs-and-filters/plan.md`

## What & Why

Wycinek S-37 (MS-04, M-4) wdraża kartę `PageCategories` (wariant B) z design systemu Atrament. Widok „Kategorie” dostaje:

- zakładki Wydatki | Przychody | Archiwum z licznikami;
- szukajkę po nazwie i opisie;
- filtr rytmu wydatków;
- kolumnę Rytm;
- lata w budżecie jako pasek komórek.

Od wczytania arkusza lista ma kategorie z lat 2020–2027, a tekst „2020 · 2021 · …” i dwa długie panele przestały być czytelne.

## Starting Point

Dziś „Kategorie” to dwa `ListSurface` jeden pod drugim (Przychody, Wydatki) i zwijana sekcja „Archiwum (n)”. Rytm stoi w podpisie pod nazwą, a lata są tekstem. Serwer już daje wszystko, czego trzeba: rytm z bieżącego miesiąca, opis, `budget_years`, a w `shell.budgetYears` lata Budżetów. Zapis kolejności przyjmuje tylko pełne drzewo typu (422 przy niepełnym).

## Desired End State

- `/categories?tab=income|archive` otwiera zakładkę, a Wstecz z Operacji do niej wraca.
- Szukajka jest wspólna dla zakładek („prad” znajduje „Prąd”), a liczniki zakładek pokazują trafienia.
- Chip „Roczna” zostawia grupę Dom jako „1 z 5 kategorii”. Przy filtrze nie ma uchwytów ani przesuwania grup.
- Archiwum ma podsekcje Wydatki i Przychody.
- Każdy wiersz ma pasek lat 2020–2027 z rokiem z pozycją na `accent`. Każdy stan ma scenariusz w `/dev/ui/categories`.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) |
| --- | --- | --- |
| Grupy przy filtrze | Tylko grupy z trafieniami, „k z n kategorii”; puste znikają | Zachowuje układ i kontekst grupy bez szumu pustych grup |
| Zasięg szukajki | Wspólna fraza dla zakładek, liczniki zakładek = trafienia | Od razu widać, w której zakładce jest „Zwrot” |
| Zakładka Archiwum | Podsekcje Wydatki i Przychody w kolejności listy; filtr rytmu działa na zarchiwizowane wydatki | Ten sam układ kolumn co w zakładkach typu, typ widać bez nowego znacznika |
| Stan w adresie | Tylko `?tab=` (Wydatki bez parametru, `replace`), fraza i rytm w stanie strony | Wstecz wraca do zakładki; wzorzec `?section=` z Operacji (ADR-0038) |
| Rytm w wierszu | Kolumna z `Badge` (krótkie etykiety), bez rytmu w podpisie; na telefonie druga linia: Rytm + pasek | Zgodne z kartą, rytm skanowalny obok filtra |
| Testy E2E | Przepisanie spec na zakładki + jeden nowy test (fraza → brak uchwytów, liczniki, fraza po zmianie zakładki, zapis po „Wyczyść filtry”) | Pilnuje w przeglądarce głównego ryzyka: zapisu kolejności z częściowej listy |
| Przeciąganie przy filtrze | Wyłączone, bez uchwytów i „W górę”/„W dół”; „Zmień grupę…” zostaje (własny endpoint) | Serwer przyjmuje tylko pełne drzewo typu (ADR-0069) |
| Chipy rytmu | `SegmentedControl` `neutral`, pojedynczy wybór z „Wszystkie rytmy” | Reguła `js.md`: wybór jednej wartości to `SegmentedControl` |
| Oś paska lat | `shell.budgetYears` (lata Budżetów, szkic włącznie) | Bez nowego propsa; pasek pokazuje lata, które użytkownik ma |
| Pusty wynik | Komunikat z frazą albo rytmem i przycisk „Wyczyść filtry” (czyści oba) | Jeden przycisk dla obu filtrów, jak w FilterBar |
| Dokumenty | ADR-0097 + przepisany akapit `design-system.md:120`; słownik bez zmian | Żaden ADR nie opisywał sekcji archiwum; reguły ADR-0067 się nie zmieniają |

## Scope

**In scope:**

- prop `tab` (enum, FormRequest, test);
- logika filtrów i paska w `lib` z Vitest;
- `CategoryYearsStrip` oraz zmiany w `CategoryListRow`, `CategoryGroupRow` i `Categories.vue`;
- usunięcie `CategoryArchiveSection`, klasy CSS, copy i strażnicy UI;
- sink, E2E, ADR-0097 i `design-system.md`.

**Out of scope:**

- zmiany zapisu kolejności i modelu danych;
- fraza i rytm w adresie;
- szukanie w nazwie grupy, skrót `/`;
- grupy w Archiwum, „Dodaj grupę” w Archiwum;
- aktualizacja karty w artefakcie claude.ai.

## Architecture / Approach

Serwer dokłada tylko walidowany prop `tab`. Całe filtrowanie odbywa się po stronie klienta w czystych funkcjach `lib`:

- `text-search.ts` składa polskie znaki;
- `category-list-filter.ts` daje drzewo z trafieniami, liczniki zakładek i rytmów oraz podsekcje archiwum;
- `category-list.ts` daje komórki i etykietę zakresów paska.

`Categories.vue` trzyma pełne drzewo `trees` jako jedyne źródło zapisu kolejności. Przy aktywnym filtrze renderuje wyliczone drzewo z wyłączonym przeciąganiem, którego nigdy nie zapisuje.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Decyzja i kontrakt widoku | ADR-0097, akapit design systemu | Rozjazd opisu z tym, co powstanie w fazie 4 |
| 2. Prop `tab` | Enum, FormRequest, prop, `assertInertia` | Zachowanie przy nieznanej wartości (iść za `section`) |
| 3. Logika w `lib` | Filtr drzewa, liczniki, `foldDiacritics`, komórki paska z Vitest | Licznik grupy „k z n” pomylony z pełnym `count` |
| 4. Widok | Zakładki, szukajka, rytm, archiwum, pasek, CSS, copy, strażnicy | Zapis kolejności z przefiltrowanego drzewa; potwierdzenie usunięcia grupy z liczbą po filtrze |
| 5. Sink i E2E | Scenariusze, macierz 7 stanów, spec na zakładkach + nowy test | E2E zależny od markupu (`data-tab`) |

**Prerequisites:** worktree z bazą `testing_categories_tabs_and_filters` (AGENTS.md, `parallel-changes.md`); `npm ci` i `npm run build` przed `types:check`.
**Estimated effort:** ~2–3 sesje w 5 fazach (faza 4 największa).

## Open Risks & Assumptions

- Rytm do filtra to rytm bieżącego miesiąca (już w propsie, ADR-0072), także dla archiwalnych wydatków.
- Oś lat to lata Budżetów użytkownika. Rok spoza osi w `budget_years` (nie powinien wystąpić) zostaje dodany do osi, a nie zgubiony.
- Przy wielu latach (> 10) pasek może się nie zmieścić w kolumnie. Szerokość kolumny trzeba sprawdzić na telefonie w fazie 4.
- E2E nie działa w CI. Uruchamia się go lokalnie wg `test-stack.md`.

## Success Criteria (Summary)

- Użytkownik znajduje kategorię frazą bez polskich znaków w dowolnej zakładce i widzi w licznikach, gdzie są trafienia.
- Filtr rytmu pokazuje np. wszystkie „Roczne” wydatki z kontekstem grup, a po wyczyszczeniu przeciąganie dalej poprawnie zapisuje kolejność.
- Lata w budżecie każdej kategorii widać na jeden rzut oka jako pasek 2020–2027.
