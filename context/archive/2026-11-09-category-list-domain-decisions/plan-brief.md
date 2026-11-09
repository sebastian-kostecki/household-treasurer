# Category List Domain Decisions — Plan Brief

> Full plan: `context/changes/category-list-domain-decisions/plan.md`

## What & Why

F-03 z roadmapy M-3. Zanim powstanie kod jednej listy kategorii (S-18…S-23), słownik i ADR muszą przestać mówić, że kategoria należy do jednego budżetu, a lata łączy zapytanie po nazwie. Wymaga tego twarda reguła AGENTS.md: zmiana terminu _Avoid_ zaczyna się od słownika i ADR. Migracja S-18 jest nieodwracalna i trafi na dane produkcyjne, więc każdy jej przypadek brzegowy musi być rozstrzygnięty na papierze.

## Starting Point

Kategoria to rekord jednego budżetu z kwotami roku na samym rekordzie. Nowy budżet kopiuje kategorie (ADR-0014). Lata łączy dokładne porównanie nazwy i typu w pięciu miejscach: propozycja stycznia, filtr Operacji, chip filtra, Import, linki z Dashboardu. Nazwy nie są unikalne: na devie 2026 ma dwie „Rozrywki” i dwa „Odsetki”. Earmark, Reserve, Year Close i Cash Expectation są tylko w słowniku i też łączą po nazwie.

## Desired End State

ADR-0063 opisuje listę kategorii przez lata, pozycję budżetu z planem rocznym, grupę, archiwum, usuwanie i regułę migracji krok po kroku, z przykładem z deva. Sześć starszych ADR-ów odsyła do niego jedną linią. Słownik ma wpisy Category, Budget Line i Category Group i nie zawiera już „a Category identity that spans years”. S-18 może pisać migrację i test bez nowych decyzji.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) |
| --- | --- | --- |
| Plan roczny | Budget Line (kategoria × budżet); nowy budżet z aktywnych, szkic dostaje nowe kategorie, budżet w wykonaniu przy pierwszym użyciu z planem 0 | Tak dziś działa kategoria dodana w trakcie roku, a stare lata nie dostają obcych kategorii |
| Duplikaty w jednym budżecie | Numer kolejny według id; nr 1 → „Rozrywka”, nr 2 → „Rozrywka (2)” | Żadna pozycja nie zmienia kwot; porządki robi scalanie (S-21) |
| Dopasowanie i unikalność nazw | Przycięte spacje, bez wielkości liter, polskie znaki się liczą; (nazwa, typ) unikalne, także z archiwalnymi | Jedna nazwa = jedna kategoria, więc Import, filtr i porównanie lat są jednoznaczne |
| Archiwum i wygląd po migracji | Aktywna tylko z niezarchiwizowaną pozycją w najnowszym budżecie albo w najnowszym budżecie w wykonaniu; wygląd z najnowszego roku, który ją ma | Selektory pokazują to, czego używasz teraz, także kategorie bieżącego roku nieobecne w szkicu |
| Flagi w S-18 | Zostają na pozycji budżetu do czasu rytmu (F-05) | Przeniesienie zmieniłoby „do wydania” bieżącego miesiąca |
| Grupa | Atrybut kategorii listy, przez wszystkie lata | Porównanie lat po grupach porównuje te same kategorie |
| Usuwanie | „Usuń” w szkicu zdejmuje pozycję; z listy tylko nieużywaną (bez operacji, Cash Expectation i pozycji w budżecie w wykonaniu) | Pomyłki znikają, historii nie da się zgubić |
| Earmark, Reserve, Year Close, Cash Expectation | Przepisane minimalnie na kategorię listy i pozycję budżetu; ADR-0018 i ADR-0030 dostają „Amended by” | Słownik bez sprzeczności; przyszła Rezerwa nie wróci do łączenia po nazwie |
| Zakres dokumentów | Jeden ADR-0063 + linie odsyłające w ADR-0014/0016/0018/0030/0033/0034 + słownik | Wzór F-01; stare ADR-y bez edycji treści |

## Scope

**In scope:**
- ADR-0063
- jedna linia odsyłająca w sześciu ADR-ach
- `glossary.md`: dwa nowe wpisy, przepisany Category, dopiski w około 12 wpisach

**Out of scope:**
- kod, migracje, testy
- rytm i „zebrane” (F-05)
- reguły `.ai/rules` i kopia bazy (F-04)
- predykaty scalania (S-21)
- UI listy i Budżetu (S-18…S-20)
- PRD, specyfikacje i reguły opisujące stare łączenie (zmienią je wycinki razem z kodem)

## Architecture / Approach

```
Category (użytkownik, przez lata) ──< Budget Line (kategoria × budżet: plan pocz., plan akt., wykonanie, flagi)
   │ nazwa, typ, ikona, kolor, kolejność, opis, archiwum
   └─> Category Group (opcjonalna, ten sam typ, bez planu)
Financial Operation / Cash Expectation / pozycja Month Plan → Category w budżecie roku daty
```

Najpierw decyzja (ADR-0063 z odsyłaczami), potem słownik, który się na nią powołuje.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. ADR-0063 i odsyłacze | Decyzja z regułą migracji a–f, nowe brzmienie klauzul pięciu ADR-ów, odrzucone opcje | Niejednoznaczny krok migracji utrwali się w S-18 |
| 2. Słownik | Category, Budget Line, Category Group i dopiski bez śladu łączenia po nazwie | Rozjazd predykatów między ADR a słownikiem |

**Prerequisites:** brak (F-03 nie ma zależności).
**Estimated effort:** około 1 sesja, 2 commity dokumentacyjne.

## Open Risks & Assumptions

- Dane produkcyjne mogą mieć duplikaty lub warianty wielkości liter, których nie ma na devie. Reguła a–f obejmuje je deterministycznie, ale lista „(n)” do scalenia może być dłuższa.
- Kategoria tylko z 2025, której wciąż używasz, trafi do archiwum. Przywrócenie to jedno kliknięcie (S-19).
- F-05 może przenieść rytm na listę, a wtedy dopisek o flagach na Budget Line zmieni się w tamtym ADR.

## Success Criteria (Summary)

- Przykład z deva i przypadek „Domeny” dają się przeliczyć z samego słownika i ADR-0063, z tym samym wynikiem.
- `glossary.md` nie zawiera „identity that spans years” ani łączenia po nazwie i typie.
- Plan S-18 nie musi zadawać żadnego pytania domenowego o migrację kategorii.
