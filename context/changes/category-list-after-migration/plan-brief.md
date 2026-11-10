# Category List After Migration — Plan Brief

> Full plan: `context/changes/category-list-after-migration/plan.md`

## What & Why

S-18, north star M-3: jedna lista kategorii przez lata, na którą migracja przepina budżety, operacje i plany bez zmiany kwot (US-01, FR-001, FR-006, FR-022). To pierwsza nieodwracalna migracja na danych produkcyjnych. Bez niej grupy, scalanie, rytm i raporty porównują lata po nazwie.

## Starting Point

Kategoria to rekord jednego budżetu z kwotami, flagami i zapisanym wykonaniem. Lata łączy dokładna nazwa i typ w sześciu miejscach w PHP i pięciu na froncie. Całą obsługę kategorii ma strona Budżet. Produkcja: 1 użytkownik, 2026 w wykonaniu i szkic 2027, 77 rekordów, 1 271 operacji, 38 pozycji planu. ADR-0063 rozstrzyga domenę, ale jego przykład z deva jest błędny: „Rozrywka (2)” to rekord innego użytkownika.

## Desired End State

Otwierasz „Kategorie” i widzisz jedną listę z latami i archiwum. Budżet 2026, plan miesiąca, Operacje, Import i Dashboard pokazują te same kwoty co przed wdrożeniem. Zmiana nazwy w Budżecie działa we wszystkich latach, a filtr Operacji idzie po kategorii przez lata. `user:backup` pisze v6, stare snapshoty się wczytują. Wdrożenie zatrzymuje się przy różnicy liczb kontrolnych, a migracja ma prawdziwy `down()`.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Model, reguła migracji, archiwum, nazwy | ADR-0063 kroki (a)–(f), bez zmian | Rozstrzygnięte w F-03 | F-03 |
| Czym operacja wskazuje kategorię | Przez Budget Line: `category_id` → `budget_line_id`, te same wartości | Migracja nie zmienia żadnego wiersza z 1 271 operacji, a powrót jest prosty | Plan |
| Wycofanie po wdrożeniu | Prawdziwy `down()` z testem; nazwy wracają z pisownią listy i „(k)” | Spełnia PRD „bez utraty operacji” bez martwych kolumn | Plan |
| Bramka liczb kontrolnych | `app:control-numbers` przed/po w `deploy.sh` + asercje sum w transakcji migracji + `user:backup` każdego użytkownika | Złe dane nie trafiają do bazy, a log przed/po jest jak w PRD | Plan |
| Strona Budżet po migracji | Jak dziś, zapis według ADR: opis → lista (wszystkie lata), plan i flagi → pozycja, archiwum globalne, dołączanie do szkiców | Bez regresu przed S-19 i z modelem spójnym od wdrożenia | Plan |
| Widok „Kategorie” | Przychody/Wydatki, lata pozycji, link do Operacji po id, zwijane archiwum; tylko odczyt | Widać wynik migracji i działa nowy filtr; edycja to S-19 | Plan |
| `category_id` na granicy HTTP | Zawsze id kategorii listy; serwer znajduje pozycję w budżecie roku | Stabilne id przez lata; S-19 tylko rozluźni wybór | Plan |
| Klucz nazwy | Kolumna `name_key` liczona w PHP, `unique(user_id, type, name_key)` | Jeden kod dla migracji, snapshotu i walidacji; `lower()` w PG zależy od collation | Plan |
| Kroki (a)–(f) w kodzie | Jedna zamrożona klasa na tablicach dla migracji i restore v1–v5 + test parytetu | Restore równa się migracji z definicji | Plan |
| Nowy budżet (2027 w grudniu) | Dzisiejszy zbiór pozycji, łączenie po id | Zasadę zmienia S-20; tworzenie musi działać już teraz | Plan |
| Import | Głosy per kategoria listy + test równoważności | Te same wyniki na danych bez wariantów nazwy; pomiar to S-23 | Plan |

## Scope

**In scope:**
- ADR-0065, migracje expand/backfill/drop z asercjami i `down()`, test migracji na PostgreSQL
- modele `Category` (lista) i `BudgetLine`, przeniesienie ok. 60 plików `app/` i 42 plików testów
- strona Budżet według ADR-0063; sześć łączeń po nazwie → id; filtr, linki, selektory i plan miesiąca po id listy
- snapshot v6 z konwersją v1–v5 i fixture v5
- widok „Kategorie” z kitchen sinkiem, nawigacją i E2E
- `app:control-numbers`, `deploy.sh`, README, reguły `.ai/rules`
- próba na kopii produkcji

**Out of scope:** grupy, opis, rytm (S-22, S-19, S-24); edycja i przywracanie w „Kategoriach” oraz selektor aktywnych kategorii listy (S-19); nowa zasada budżetu (S-20); scalanie (S-21); pomiar trafności Importu (S-23); mock 2025 (S-29); E2E w CI.

## Architecture / Approach

```
categories (lista: user_id, name, name_key, icon, color, sort_order, type, archived_at)
   └──< budget_lines (id = stare id kategorii; budget_id, plany, wykonanie, flagi, archived_at)
           ├──< financial_operations.budget_line_id   (wartości bez zmian)
           └──< month_plan_items.budget_line_id       (wartości bez zmian)
HTTP/propsy: category_id = id listy → BudgetLineResolver (budżet roku daty / strony)
```

Expand → backfill → przełączenie czytelników → drop, w jednej gałęzi i jednym wdrożeniu. Stare kolumny opisu żyją na `budget_lines` do fazy 3, żeby każda faza kończyła się zielonymi testami.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Schemat i migracja danych | ADR-0065, migracje z asercjami i `down()`, test PG, `BudgetLine` + `Category`, restore v1–v5 przez konwersję | Kroki (b)–(d) źle zakodowane utrwalą się na produkcji |
| 2. Snapshot v6 | Nowy format, fixture v5, parytet z migracją | Restore różni się od migracji |
| 3. Lista jako źródło prawdy | Strona Budżet według ADR, łączenia po id, usunięcie starych kolumn | Regres przycisków Budżetu; N+1 przy opisie kategorii |
| 4. Granica HTTP po id listy | Filtr, linki, selektory, Import i plan miesiąca po id | Rozjazd propsów PHP i TS |
| 5. Widok „Kategorie” | Strona, nawigacja, kitchen sink, E2E | Przesunięte skróty klawiszowe |
| 6. Zabezpieczenia wdrożenia | Komenda liczb, `deploy.sh`, README, reguły | Skrypt aktualizuje się sam: pierwsze wdrożenie przez `git pull` |
| 7. Próba na kopii produkcji | `migration-trial.md`: migracja, liczby, `down()`, F3 | Dane produkcji z niezgodnym `budget_id` zatrzymają migrację |

**Prerequisites:** F-03 i F-04 zamknięte (są); świeże archiwum produkcji do fazy 7.
**Estimated effort:** ok. 6–8 sesji w 7 fazach; fazy 1, 3 i 4 to większość pracy.

## Open Risks & Assumptions

- Produkcja może mieć warianty nazw lub niezgodny `budget_id` operacji, których nie ma na devie. Asercja zatrzyma migrację, a próba z fazy 7 ma to pokazać wcześniej.
- Kategorii zarchiwizowanej bez pozycji w widocznym budżecie nie da się przywrócić do czasu S-19.
- Po wycofaniu nazwy mają pisownię listy i sufiks „(k)”; snapshot v6 nie wczyta się w starym kodzie.
- „Kategorie” po „Budżecie” przesuwa skróty klawiszowe kolejnych widoków o jeden.

## Success Criteria (Summary)

- Po wdrożeniu wykonanie każdej pozycji budżetu 2026, salda i liczba operacji są co do grosza jak przed; żadna operacja nie zmieniła kwoty ani kategorii.
- „Kategorie” pokazuje jedną listę z archiwum, a Budżet, plan miesiąca, Operacje i Import działają bez ręcznej pracy.
- Stary snapshot się wczytuje, a `down()` i kroki przywracania przeszły na kopii produkcji.
