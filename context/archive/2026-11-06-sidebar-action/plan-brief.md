# Funkcjonalny sidebar i powłoka bez belki — Plan Brief

> Full plan: `context/changes/sidebar-action/plan.md`
> Research: `context/changes/sidebar-action/research.md`

## What & Why

Sidebar ma działać jak komponent `Sidebar` + `CommandPalette` z Atramentu: paleta „Szukaj i przejdź ⌘K”, skróty `1`–`n`/`N`/`/`/`Esc` z podpowiedziami i rok budżetu w segmentach. Jednocześnie znika górna belka z tytułem i okruszkami, która powtarza `h1` każdej strony i zabiera 64 px pionu. Użytkownik uznał przełącznik sidebara za zbędny.

## Starting Point

Belka `AppSidebarHeader` stoi nad każdą stroną (11 stron podaje okruszki), sidebar zwija się do paska ikon 3rem z ciasteczkiem `sidebar_state`, rok wybiera się z `DropdownMenu`, a palety, skrótów i wspólnej listy widoków nie ma. Dialogi operacji żyją tylko na stronie Operacji.

## Desired End State

Każda strona zaczyna się od własnego wiersza tytułu; na desktopie sidebar jest stały, na telefonie wąski pasek z ☰ otwiera arkusz. Rok to segmenty „2026 | 2027 +”. Pozycje menu mają numery skrótów, a nad użytkownikiem stoi linijka „Skróty: …”. ⌘K/Ctrl+K lub przycisk w sidebarze otwiera paletę z widokami, akcjami i kategoriami roku z realizacją.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Zakres | Pełny sidebar z mocka, bez belki, bez przełącznika | Decyzja użytkownika przy otwarciu zmiany | Research (`change.md`) |
| Desktop bez zwijania | Kontrolowany `open=true`, `collapsible="offcanvas"`, bez `sidebarOpen`/`sidebar_state` | `collapsible="none"` gubi arkusz mobilny (`Sidebar.vue:24`) | Research / Plan |
| Wejście na telefonie | Pasek z ☰ i „HT” tylko na telefonie, sterowany `isMobile` | Jedno miejsce w layoucie; `md:hidden` zostawiłby 768 px bez nawigacji | Plan |
| Dane kategorii | Endpoint JSON czytany `useHttp` przy każdym otwarciu | Zero kosztu na zwykłych żądaniach; `reload({ only })` przeliczyłby całą stronę | Plan |
| Zakres kategorii | Aktywne wydatki i dochody roku z sidebara, realizacja roczna | Zgodne z rokiem, który użytkownik ogląda, i z kwotą w mocku | Plan |
| Skrót `N` | Otwiera menu „Dodaj operację” (z innej strony po przejściu na Operacje) | Jeden skrót na wszystkie typy, z powodami wyłączenia | Plan |
| Skrót `/` | Fokus w szukajce Operacji (z innej strony po przejściu) | Zgodne z mockiem i różne od ⌘K | Plan |
| Segmenty roku | Wszystkie lata po 3 w wierszu + „<max+1> +”; pusty „<bieżący> +” | Każdy rok o jedno kliknięcie; odwraca ADR-0053 → ADR-0054 | Research / Plan |
| `N` i `/` między stronami | Jednorazowa intencja po stronie klienta, bez parametru adresu | Bez nowego kontraktu `/operations` i walidacji serwera | Plan |
| Numery skrótów | Z pozycji na wspólnej liście `useAppViews` | „Raporty” z `period-report-by-category` dostaną numer same | Plan |
| Komponent palety | shadcn-vue `command` bez nadpisania `ui/dialog`, `combobox` w `CommandPalette` | Ścieżka shadcn bez nowych pakietów i bez łamania dialogów Atramentu | Research / Plan |

## Scope

**In scope:** usunięcie belki, okruszków i trybu ikon; pasek ☰ na telefonie; segmenty roku; wspólna lista widoków, skróty, znaczniki `kbd`, linijka skrótów; intencja `N`/`/` dla Operacji; endpoint kategorii; paleta z sześcioma stanami w kitchen sinku; ADR-0054, design system, reguły `js-sidebar`/`js-import`/`js-operations`; testy Vitest, Feature, Playwright.

**Out of scope:** „Raporty” w menu; wylogowanie i motyw w palecie; zarchiwizowane kategorie i inne lata w palecie; konfigurowalne skróty; zmiany `ui/dialog`, `ui/sheet`, `SidebarProvider`; nowe pakiety npm; parametr adresu `/operations` dla skrótów.

## Architecture / Approach

Czysta logika (segmenty lat, predykat skrótu, pozycje palety, etykieta ⌘K/Ctrl+K) w `resources/js/lib` z Vitest; stan współdzielony (paleta, intencja Operacji, dialog nowego budżetu) w `composables`. Jeden nasłuch `keydown` w `AppSidebarLayout` → `resolveGlobalShortcut` → wizyta, intencja albo paleta. `Operations.vue` konsumuje intencję (`watch` z `immediate`). Paleta to prezentacyjny `CommandPalette` + `useCommandPalette` z `useHttp` GET `command-palette/categories` (jeden `SELECT` po `categories.execution_minor_units`).

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Powłoka bez belki i bez zwijania | Brak belki i okruszków, stały sidebar, ☰ na telefonie, ADR-0054 | Telefon bez nawigacji przy progu 768 px |
| 2. Rok budżetu w segmentach | Segmenty lat z „+” i stanami oczekiwania/błędu | Kontrast wybranego segmentu i wiele lat w 224 px |
| 3. Wspólna lista widoków i skróty | `1`–`n`, `N`, `/`, znaczniki, linijka skrótów, intencja Operacji | Skróty odpalające w polach lub dialogach; nazwy dostępne linków |
| 4. Paleta ⌘K | Endpoint kategorii, `ui/command`, `CommandPalette`, przycisk, ⌘K, E2E | CLI shadcn nadpisujące `ui/dialog` lub `package-lock.json` |

**Prerequisites:** Sail z PostgreSQL (`household-treasurer_sail`), baza `household_treasurer_e2e` z `demo:seed-household` dla Playwright, `node_modules` w worktree (`npm ci`).
**Estimated effort:** ~4 sesje, po jednej na fazę (faza 4 największa).

## Open Risks & Assumptions

- `period-report-by-category` zmienia te same pliki (`AppSidebar.vue` lista widoków, `BudgetYearSwitcher.vue` z `destination: 'reports'`): kto scala drugi, przenosi „Raporty” do `useAppViews` i logikę `destination` do segmentów.
- Programowe otwarcie `DropdownMenu` („Dodaj operację”) musi przenieść fokus do menu; sprawdzane w E2E i ręcznie.
- Ctrl+K w Firefoksie fokusuje wyszukiwarkę przeglądarki, jeśli strona nie zrobi `preventDefault` — predykat obsługuje skrót przed przeglądarką tylko przy fokusie na stronie.
- Zakładamy, że `CommandInput` z rejestru przekazuje atrybuty do `ListboxFilter`; jeśli nie, poprawka w `ui/command` jest nazwana w ADR-0054.

## Success Criteria (Summary)

- Na każdej stronie treść zaczyna się od tytułu, a na telefonie menu jest osiągalne przez ☰.
- Z klawiatury da się przejść do każdego widoku, otworzyć menu nowej operacji, szukajkę Operacji i paletę, a w polach skróty nie przeszkadzają.
- Rok budżetu zmienia się jednym kliknięciem, a brakujący rok tworzy się z segmentu „+”.
