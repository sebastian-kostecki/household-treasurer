# Sidebar UI contract pass — Plan Brief

> Full plan: `context/changes/sidebar-ui-contract/plan.md`
> Research: `context/changes/sidebar-ui-contract/research.md`

## What & Why

Sidebar jest zbudowany z prymitywów shadcn-vue, ale komponenty domenowe dołożone do jego slotów nie znają trybu ikon. Po zwinięciu sidebara przełącznik roku rozsypuje się, a „Dodaj rok” wychodzi na treść. Podczas importu CSV menu nie pokazuje sekcji. Logo i awatar mają literały `dark:`, blok `--sidebar-*` ma martwy token i nie jest opisany, a w nagłówku stoi znak startera Laravel. Celem jest powłoka zbudowana z tokenów i komponentów repo oraz zabezpieczenie, które utrzyma kolejnego agenta przy kontrakcie.

## Starting Point

Tailwind v4 i shadcn-vue: wartości w `resources/css/app.css`, prymitywy w `components/ui/sidebar/*`. Research zapisał 5 zarzutów (C1–C5) ze zrzutami „przed”. Gałąź odchodzi od `547d3ef`, a na `development` są już fazy 1–4 `dashboard-ui-contract`: wzorzec kitchen sinka i helper toasta dla wyboru roku. Skan `check:ui` (faza 5 dashboardu) jeszcze nie istnieje.

## Desired End State

Przełącznik roku to jeden przycisk z menu lat i „Dodaj rok”. W zwiniętym pasie zostaje ikoną z podpowiedzią, a na mobile działa w Sheet. „Operacje” są aktywne na podstronach importu. Logo ma jasny glif z tokenu w obu motywach, znak Laravela zastępuje tymczasowy `Vault`, a `NavFooter.vue` znika. Nieudany wybór roku pokazuje toast. `/dev/ui/sidebar` pokazuje cztery stany powłoki, `states.md` macierz 7 stanów, a `npm run check:ui` obejmuje pliki sidebara.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Przełącznik w trybie ikon (C1) | Jeden `SidebarMenuButton size="lg"` + `DropdownMenu` (lata, „Dodaj rok”), jak `NavUser` | Prymitywy same obsługują tryb ikon, podpowiedź, fokus i Sheet, a US-10 „at all times” zostaje spełnione. | Plan |
| Stan bez lat | Przycisk „Brak roku budżetu / Dodaj rok” otwiera dialog bezpośrednio | Jedno kliknięcie jak dziś, US-11 zachowane. | Research / Plan |
| Aktywna pozycja (C2) | Helper `isCurrentSection` dopasowujący po segmencie ścieżki, z testem | Surowe `startsWith` zapaliłoby `/budget` na `/budgets/…`. | Research / Plan |
| Glif logo w trybie ciemnym (C3) | Usunąć literał, biały glif z tokenu, bez zmiany tokenów | Glif jest grafiką (WCAG 1.4.11, 3:1), więc 3,3:1 wystarcza, a landing zostaje bez zmian. | Plan |
| Znak produktu (C5) | Tymczasowy `Vault` z `@lucide/vue` w `AppLogoIcon`; `NavFooter.vue` usunięty | Znika branding startera bez pracy nad grafiką; docelowe logo odroczone. | Plan |
| Martwy token (C4) | Usunąć `--sidebar`, opisać `--sidebar-*` w `design-system.md` | Kolejna edycja „tła sidebara” ma trafić w działający token. | Research |
| Bramka wizualna | `/dev/ui/sidebar` (404 poza `local`) z 4 ramkami + zrzuty z aplikacji | Wszystkie stany przełącznika naraz, ten sam wzorzec co dashboard. | Plan |
| Zależność od dashboardu | Merge `development` przed fazami 1, 4 i 5; helper toasta reużyty; `check:ui` rozszerzony albo utworzony wg kontraktu dashboardu | Sidebar nie blokuje się na drugiej zmianie, a wspólny kontrakt upraszcza merge. | Plan |
| Integracja gałęzi | `git merge`, nie rebase | Gałąź jest na `origin`, a force-push jest zakazany. | Plan |

## Scope

**In scope:**

- `BudgetYearSwitcher.vue`, `AppSidebar.vue`, `AppSidebarLayout.vue`, `NavMain.vue`, `AppLogo.vue`, `UserInfo.vue`, `AppLogoIcon.vue` (plus zdjęcie `fill-current` u wywołujących), usunięcie `NavFooter.vue`
- Nowe: `lib/nav-active.ts` (+ test), kitchen sink (kontroler, trasa, strona, test Feature), `states.md`, zrzuty
- `app.css` (martwy token, komentarze), `lang/pl/ui.php` + typ `shell` (`select_year_failed`)
- `check:ui`, `.ai/rules/js.md`, `.docs/design-system.md`, spec powłoki, `SKILL.md:55`, deferred w `research.md`

**Out of scope:**

- Docelowe logo, kontrast tekstu na ciemnym `--primary` (globalnie), zmiany tokenów `--sidebar-primary*`
- Literały na landingu i w layoutach auth, prymitywy `components/ui/*`, `useCurrentUrl`
- Rok w URL i cel przekierowania, błąd 500 na `/dashboard` w dev, Playwright / `toHaveScreenshot`

## Architecture / Approach

Kolejność według 10x-ui: środowisko i biblioteka → tokeny → jeden widok → stany → zabezpieczenie. Dane lat płyną z `AppSidebarLayout` przez propsy `AppSidebar` do `BudgetYearSwitcher`, więc kitchen sink może postawić cztery sidebary z fixture'ami, każdy we własnym `SidebarProvider` i w ramce z `transform-gpu` (sidebar jest `fixed`). Faza 1 nie zmienia wyglądu, faza 3 wymienia widok, a fazy 4–5 zaczynają się od merge'a `development`.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Integracja i kitchen sink | Merge `development`, propsy lat, `isCurrentSection`, `/dev/ui/sidebar`, zrzuty „przed” | Ramki z `fixed` sidebarem nachodzą na siebie bez `transform-gpu` |
| 2. Wartości tokenów (C4) | Bez martwego `--sidebar`, sekcja „Sidebar” w `design-system.md` | Brak (zmiana bez efektu wizualnego) |
| 3. Jeden widok (C1–C3, C5) | Nowy przełącznik, aktywna sekcja, bez literałów, `Vault`, bez `NavFooter` | Dialog otwierany z menu (fokus, `pointer-events`) |
| 4. Stany | Toast błędu, `aria-busy`, macierz 7 stanów, `states.md`, zrzuty | Konflikty merge'a w `app.css` / `design-system.md` |
| 5. Zabezpieczenie | `check:ui` z plikami sidebara, reguła, dokumenty, deferred | Równoległe utworzenie skryptu przez dashboard (suma list) |

**Prerequisites:** kroki „Fresh checkout or worktree” z `AGENTS.md`, działający Sail (obraz `sail-8.5/app`, sieć `household-treasurer_sail`), podgląd worktree na :8090 z osobną bazą (`AGENTS.md`, „Preview from a worktree”), przeglądarka do zrzutów 1440 px i 390 px.
**Estimated effort:** ~2–3 sesje w 5 fazach. Fazy 1 i 3 są największe.

## Open Risks & Assumptions

- Glif w trybie ciemnym zmienia się z czarnego na biały (6,2 → 3,3:1). To zamierzone i zgodne z WCAG dla grafiki.
- Rozwinięty przełącznik wygląda inaczej niż dziś: zamiast natywnego selecta jest przycisk z menu, a „Dodaj rok” przechodzi do menu.
- Kitchen sink pokazuje stany tylko na desktopie; mobile dokumentują zrzuty z aplikacji.
- Jeśli faza 5 dashboardu wyląduje równolegle, przy merge'u trzeba scalić listy plików `check-ui-literals.mjs`.

## Success Criteria (Summary)

- Po zwinięciu sidebara można zmienić i dodać rok budżetu, a nic nie wychodzi na treść strony; na mobile działa to samo.
- Podczas importu CSV „Operacje” są aktywne, w obu motywach nie ma literałów `dark:` ani znaku Laravela, a nieudany wybór roku kończy się komunikatem.
- `composer ci:check` przechodzi z `npm run check:ui`, skan widoku daje 0 trafień, a `states.md` ma 7 wypełnionych wierszy.
