# Z raportu do operacji — Plan Brief

> Full plan: `context/changes/report-to-operations/plan.md`
> Research: `context/changes/report-to-operations/research.md`

## What & Why

Raport pokazuje kwotę kategorii w okresie, ale nie pokazuje, z jakich operacji się wzięła. Żeby to sprawdzić, trzeba dziś ręcznie ustawić filtr na liście Operacji. Ten wycinek (S-31, FR-018, nice-to-have) robi z nazwy kategorii w Raporcie link do jej operacji w okresie raportu.

## Starting Point

Lista Operacji filtruje już po kategorii listy (`category_id`) i po zakresie dat, a przy tym filtrze pokazuje dokładnie te wiersze, które liczy raport: zwroty netto, bez transferów własnych i ruchów kapitału. Pulpit ma gotowy wzorzec takiego linku (`categoryOperationsQuery`, cały rok). Żaden komponent Raportu nie linkuje dziś do Operacji.

## Desired End State

W „Raport: marzec 2026” kliknięcie „Jedzenie” otwiera Operacje z filtrem „Jedzenie” i „01.03.2026 – 31.03.2026”, a widoczne operacje sumują się do kwoty z raportu. W widoku roku i w Trendzie zakres to cały rok budżetu. W spoczynku link wygląda jak dzisiejszy chip. Po najechaniu nazwa jest podkreślona na tle `secondary`, przy fokusie widać obwódkę, a czytnik ekranu ogłasza „Operacje kategorii Jedzenie, marzec 2026”.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Wiersz grupy | Bez linku, filtr Operacji zostaje płaski | Grupa nie ma odpowiednika w filtrze, a nowy filtr wymagałby ADR zmieniającego 0069 | Research |
| Kolumna „rok wcześniej” | Bez linku | Link prowadzi tylko do bieżącego okresu | Research |
| Wiersz tylko z porównania („—”) | Bez linku | Link istnieje tylko tam, gdzie wiersz ma kwotę w bieżącym okresie | Research |
| Trend | Link tylko z nazwy kategorii, zakres = cały rok | Komórki miesięcy i „Razem” zostają tekstem | Research |
| Suma listy Operacji | Bez sumy, równość dowodzi test Feature | Widok Operacji się nie zmienia | Research |
| Wygląd linku | Nazwa jest linkiem: podkreślenie i tło `secondary` po najechaniu, obwódka `ring` przy fokusie, `aria-label` z okresem | Tabela w spoczynku bez zmian, a przypięta kolumna Trendu zachowuje szerokość | Plan |
| Gdzie powstaje link | Na kliencie: `reportCategoryOperationsQuery` w `lib` przez `operationsListQuery` | Wiersz ma już `id` kategorii listy, więc bez nowych propsów i bez zmian na serwerze | Plan |
| Filtr po samej dacie | Wystarcza, bez `year` w query | Na każdej ścieżce zapisu (ręcznej, importu, restore) rok daty operacji jest rokiem jej Budżetu | Plan |

## Scope

**In scope:**
- Funkcja celu linku z testem Vitest (miesiąc, rok, luty przestępny, `has_operations: false` → bez linku)
- Test Feature (PostgreSQL): kwota raportu = suma listy Operacji dla miesiąca i roku
- Link w `ReportCategoryRow` (rok i miesiąc) i w wierszach kategorii `ReportTrendTable`
- Klasa `.report-name-link`, klucz `reports.operations_link_label`, propsy roku i miesiąca, kitchen sink
- Strażnik `ui-contract.test.ts` i zdanie w `.ai/rules/reports.md`

**Out of scope:**
- Linki z grup, „rok wcześniej”, komórek miesięcy, „Razem”, wykresów i panelu miar
- Filtr po grupie, ADR-0079 i suma na liście Operacji
- Zmiany propsów Inertia i serwera, nowy test E2E

## Architecture / Approach

`Reports.vue` → `ReportEntryTable` / `ReportTrendTable` (rok, miesiąc) → wiersz kategorii → `reportCategoryOperationsQuery(category, year, month)` → `operations({ query })` z `@/routes` w `<Link class="report-name-link">` wokół `CategoryChip`. Gdy funkcja zwraca `null`, zostaje sam chip. Lista Operacji przyjmuje istniejące `category_id`, `date_from` i `date_to`.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Cel linku i dowód równości | Funkcja celu z testami i test Feature „raport = lista” | Ostatni dzień miesiąca przesunięty przez strefę czasową (liczony w UTC) |
| 2. Linki w widokach raportu | Klikalne nazwy w tabelach okresu i Trendu, kitchen sink, strażnik, reguła | Link w przypiętej komórce Trendu psuje jej tło albo szerokość |

**Prerequisites:** S-28 (Raporty) na `development`. Worktree zrebase'owany na `development`, stack Sail z PostgreSQL do testów.
**Estimated effort:** ~1 sesja, 2 fazy.

## Open Risks & Assumptions

- Zakładamy, że restore przyjmuje tylko spójne snapshoty (operacja w Budżecie roku swojej daty). Restore sprawdza budżet linii, ale nie rok daty.
- Wiersz Trendu istnieje tylko dla kategorii z operacjami w roku, dlatego funkcja celu traktuje brak `has_operations` jako „jest kwota”.
- Trend pokazuje pełne złote, a lista Operacji grosze. Suma się zgadza, ale na oko może się różnić o zaokrąglenie.

## Success Criteria (Summary)

- Z każdej kategorii z kwotą w Raporcie (rok, miesiąc, Trend) jedno kliknięcie prowadzi do jej operacji w tym okresie, a ich suma równa się kwocie z raportu.
- Grupy, „rok wcześniej” i wiersze z „—” nie są klikalne, a tabela w spoczynku wygląda jak dziś.
- Link działa z klawiatury i ma nazwę dostępną z kategorią i okresem.
