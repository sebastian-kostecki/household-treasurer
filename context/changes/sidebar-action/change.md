---
change_id: sidebar-action
title: Funkcjonalny sidebar jak w mocku (paleta ⌘K, skróty, rok w segmentach) i powłoka bez górnej belki
status: preparing
created: 2026-11-06
updated: 2026-11-06
archived_at: null
---

## Notes

Zamiar użytkownika: „brakuje mi akcji i poprawy wyglądu sidebara jak według mocka w design system; ponadto jest jeszcze na każdej stronie górna belka z tytułem strony, której nie powinno być”.

- **Widok:** powłoka aplikacji: `resources/js/components/AppSidebar.vue` ze slotami (`NavMain`, `BudgetYearSwitcher`, `NavUser`, `BrandMark`) oraz `resources/js/layouts/app/AppSidebarLayout.vue` (belka `AppSidebarHeader`). Kitchen sink: `/dev/ui/sidebar` (`resources/js/pages/dev/SidebarKitchenSink.vue`).
- **Źródło tokenów:** `--sidebar-*` i `--sidebar-panel-width` w `resources/css/app.css` (role w `context/foundation/design-system.md`, „Sidebar”). Wzorzec: komponenty `Sidebar` i `CommandPalette` oraz strony `Page*` (bez belki, `main` zaczyna się od `ht-title`) w artefakcie https://claude.ai/artifact/9gJSdtfpFgUAa8oEMgR4HA (klasy `.ht-side*`, `.ht-kbd`, `.ht-yseg`, `.ht-palette*`, `.ht-app*` w `project/components/bundle.css`); to podgląd, nie źródło wartości.
- **Wariant kontraktu:** istniejący system projektowy (Tailwind v4 + shadcn-vue). Bez `shadcn init`; brakujący komponent (`command`) ścieżką shadcn-vue, bez edycji `components/ui/*` pod jeden ekran.
- **Poprzednie zmiany na tym widoku:** `context/archive/2026-11-06-sidebar-atrament-ui/` (ADR-0053; tam paleta ⌘K i segmenty były odroczone), `context/archive/2026-11-03-sidebar-ui-contract/`. Nowy ADR ma numer 0054.

### Decyzje użytkownika (2026-11-06)

- **Pełny, funkcjonalny sidebar jak w mocku:** „Szukaj i przejdź ⌘K” otwiera paletę z grupami „Widoki i akcje” oraz „Kategorie: operacje” (z realizacją); skróty `1`–`6` (z „Raportami” z `period-report-by-category` będzie 7), `N` nowa operacja, `/` szukaj w Operacjach, `Esc`; skróty przy pozycjach menu i linijka „Skróty: …” nad użytkownikiem.
- **Bez górnej belki z tytułem strony** na każdej stronie.
- **Rok budżetu w segmentach** jak w mocku („2026 | 2027 +”); odwraca decyzję z ADR-0053, więc ADR-0054.
- **Bez przełącznika sidebara:** użytkownik uważa go za zbędny. Założenie agenta: na desktopie sidebar nie zwija się (koniec trybu ikon 3rem, co usuwa też główny argument ADR-0053 przeciw segmentom); na telefonie zostaje minimalne wejście ☰, bo PRD wymaga obsługi telefonów (`prd-mvp.md:74`, NFR-UX-01) i bez niego menu jest nieosiągalne. Miejsce ☰ rozstrzyga plan.

### Zarzuty z wstępnego audytu (do `## Charges` w `research.md`)

Skan wartości zakodowanych na sztywno na 11 plikach powłoki: 0 trafień.

- **C1 (A) Belka nad każdą stroną:** `layouts/app/AppSidebarLayout.vue:28` renderuje `AppSidebarHeader` (64 px, `SidebarTrigger` + `Breadcrumbs`); strony mają własne `h1.page-title` (`Dashboard.vue:114`, `Operations.vue:158`), wzorzec zaczyna `main` od `ht-title`. Prop `breadcrumbs` w 10 stronach (np. `Dashboard.vue:98`) i `Breadcrumbs.vue` przestają być potrzebne. Wpływ: tytuł dwa razy, 64 px pionu stracone.
- **C2 (A) Przełącznik i telefon:** `AppSidebar.vue:76` `collapsible="icon"`, stan w ciasteczku `sidebar_state` (`HandleInertiaRequests.php:48`, `AppShell.vue`). Pułapka: `ui/sidebar/Sidebar.vue:25` przy `collapsible="none"` renderuje zwykły `div` bez gałęzi mobilnej (`Sheet`). Wpływ: bez wejścia ☰ na telefonie nie ma nawigacji.
- **C3 (K) Brak palety ⌘K:** brak `command` w `components/ui`; wzorzec `CommandPalette` (grupy, pierwsza pozycja zaznaczona, Enter/Esc, kolory sidebara). Kategorie z realizacją wymagają danych (nowy prop: PHP + `defineProps<>` + `assertInertia`, albo doczytanie na żądanie). Dostępność: `dialog`, `combobox`, pułapka fokusu. Wpływ: nie da się przejść do widoku ani akcji z klawiatury.
- **C4 (K) Brak skrótów i podpowiedzi:** `NavMain.vue` bez `ht-kbd`, `AppSidebar.vue` bez przycisku wyszukiwania i linijki skrótów; brak globalnej obsługi klawiszy (jedyna lokalna: `OperationsFilters.vue:399`). Skróty nie działają w polach; `N` musi otworzyć dialog żyjący dziś tylko w `Operations.vue`; `/` fokusuje szukajkę Operacji.
- **C5 (K) Rok w segmentach:** `BudgetYearSwitcher.vue:106-163` to `DropdownMenu`; wzorzec `.ht-yseg`, „+” tworzy brakujący rok. Zachować stan oczekiwania i błędu; rozwiązać wiele lat w 224 px.

### Macierz 7 stanów

Obejmuje też paletę: puste wyniki wyszukiwania, wczytywanie kategorii, wyłączona akcja.
