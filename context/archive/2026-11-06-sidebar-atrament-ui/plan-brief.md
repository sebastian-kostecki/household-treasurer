# Sidebar według komponentu Sidebar z Atramentu: brief planu

> Full plan: `context/changes/sidebar-atrament-ui/plan.md`
> Research: `context/changes/sidebar-atrament-ui/research.md`

## What & Why

Sidebar aplikacji odbiega od komponentu `Sidebar` design systemu Atrament w siedmiu miejscach (zarzuty C1–C7 z researchu): szerokość 16rem zamiast 224 px, etykiety sekcji złożone ręcznie zamiast `label-caps`, brak `aria-current="page"`, kwadratowy awatar na `muted` bez e-maila, tymczasowy glif `Vault` zamiast znaku „HT”, inne promienie pozycji menu i komponent z dwoma korzeniami. Plan doprowadza sidebar do wzorca, a znak „HT” wprowadza wszędzie, gdzie był `Vault`.

## Starting Point

Prymitywy `ui/sidebar/*` mają pełną obsługę trybu ikon i stanów; dryf siedzi w slotach domenowych (`AppLogo`, `BudgetYearSwitcher`, `NavMain`, `NavUser`, `UserInfo`), w braku tokenu szerokości i w cva pozycji menu. `Vault` importuje też jedyny żywy layout logowania (`AuthCardLayout`), `Welcome` i cztery martwe pliki ze starter kitu. Kitchen sink `/dev/ui/sidebar` pokazuje 4 z 7 stanów.

## Desired End State

Sidebar ma 224 px, etykiety „ROK BUDŻETU” i „MENU” w `label-caps`, bieżąca sekcja jest ogłaszana przez czytnik ekranu (także na podstronach), blok użytkownika ma okrągły awatar 28 px z e-mailem, a znak „HT” w JetBrains Mono stoi w sidebarze, nad kartą logowania (z nazwą aplikacji) i w `Welcome`. Martwe layouty są usunięte, sink pokazuje wszystkie stany i znak, a `ui-contract.test.ts` i spec Playwright pilnują kontraktu.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Paleta ⌘K i skróty | Poza zmianą (`command-palette`) | Nowy komponent, globalne klawisze i prop; przycisk bez palety byłby martwą kontrolką | change.md |
| Rok budżetu | Menu `BudgetYearSwitcher`, bez segmentów | Segmenty nie mieszczą się w pasku 3rem i mieszają wybór z tworzeniem | change.md |
| Szerokość i pozycje menu (C1, C6) | Edycja `ui/sidebar` (`utils.ts`, cva, `SidebarGroupLabel`) z tokenem `--sidebar-panel-width` | Sidebar to cała powłoka; jedno miejsce zamiast trzech, precedens `design-tokens-atrament` | Plan |
| Nazwa tokenu szerokości | `--sidebar-panel-width`, nie `--sidebar-width` | Styl inline wrappera przesłania `--sidebar-width` (precedens ADR-0040) | Research |
| Zasięg znaku „HT” | Sidebar, `AuthCardLayout` i `Welcome` | Jeden znak produktu od razu, bez okresu z dwoma znakami | Plan |
| Rozmiar znaku | Jeden, 26 px, zawsze z nazwą obok | Jedyna wartość z design systemu, bez propa rozmiaru | Plan |
| Martwe pliki | Usunąć `AuthSimpleLayout`, `AuthSplitLayout`, `AppHeader`, `AppHeaderLayout`, `AppLogoIcon` | Nikt ich nie importuje, a mają literały do sprzątania | Plan |
| Blok użytkownika (C4) | Prop `variant="sidebar"` w `UserInfo`, awatar okrągły wszędzie | Jeden komponent i jeden kształt awatara w produkcie | Plan |
| Kitchen sink | disabled statycznie, focus-visible w sekcji „Tab…”, hover z notatką, ramka `brand-mark` | Wzory z innych sinków; znak z trzech miejsc w jednym widoku | Plan |
| Testy | `ui-contract.test.ts` (CI) + spec Playwright `aria-current` (lokalnie) | CI łapie dryf źródeł, E2E dowodzi atrybutu na `<a>` z Inertia `Link` | Plan |
| ADR-0035 | Rozszerzony (mono także dla znaku), nie zastąpiony | Precedens ADR-0047 (`mark-label`) | Research |

## Scope

**In scope:**
- Zarzuty C1–C7 w `AppSidebar` i slotach oraz w prymitywach `ui/sidebar`
- `BrandMark` i utility `brand-mark` w sidebarze, `AuthCardLayout` i `Welcome`
- Usunięcie pięciu martwych plików, aktualizacja listy `check:ui`
- ADR-0053, `design-system.md`, reguły `js-sidebar.md` i `css.md`
- Macierz 7 stanów i ramka znaku w `/dev/ui/sidebar`, zrzuty 1280/390 px

**Out of scope:**
- Paleta ⌘K i skróty, rok w segmentach, szerokość mobilna (18rem zostaje)
- Drugi rozmiar znaku, makieta `LandingAppPreview`
- Playwright w CI, zmiany propsów Inertii, PHP i tłumaczeń

## Architecture / Approach

Zmiana idzie od decyzji do pikseli: ADR i dokumenty, potem czerwone asercje kontraktu, potem trzy warstwy kodu, z których każda zieleni swoją część testów. Prymitywy powłoki (`utils.ts`, cva, `SidebarGroupLabel`) dają szerokość, kształt pozycji i przepis etykiety całej aplikacji. Sloty domenowe dokładają `aria-current`, etykietę roku i wariant użytkownika. Nowy `BrandMark` (jeden korzeń, `aria-hidden`, obok zawsze nazwa) zastępuje `Vault`. Na końcu sink i bramka wizualna.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Decyzje i dokumenty | ADR-0053, `design-system.md`, reguły | Rozjazd ADR z planem przy późniejszej korekcie |
| 2. Straż kontraktu | Czerwone asercje `ui-contract.test.ts` + spec `aria-current` | Regexy zbyt luźne lub zbyt kruche wobec formatowania |
| 3. Powłoka (C1, C6, C7) | Sidebar 224 px, pozycje `radius-md`/600, jeden korzeń | Przesunięcie progów układu (Dashboard, Lokaty) o 32 px |
| 4. Sloty (C2, C3, C4) | `label-caps`, `aria-current`, awatar z e-mailem | `label-caps` musi zastąpić bazę `SidebarGroupLabel`, nie dokleić się przez `cn` |
| 5. Znak „HT” (C5) | `BrandMark` w trzech miejscach, usunięte martwe pliki | Brakujący import po usunięciu `AppLogoIcon` (łapie `vue-tsc`) |
| 6. Sink i bramka | 7 stanów, ramka znaku, zrzuty, pełna bramka | E2E wymaga lokalnego stosu Sail i bazy `household_treasurer_e2e` |

**Prerequisites:** worktree po `npm ci`, `composer install`, `.env` i `key:generate`; uruchomiony stos Sail (PostgreSQL, serwer E2E); użytkownik E2E w `household_treasurer_e2e` (`context/foundation/test-stack.md`).
**Estimated effort:** ok. 2–3 sesje, 6 faz; fazy 3–5 to głównie zmiany klas i markupu.

## Open Risks & Assumptions

- `period-report-by-category` (`plan_reviewed`) też edytuje `mainNavItems` w `AppSidebar.vue`; konflikt rozwiązuje zmiana, która trafi do `development` jako druga.
- Edycje `ui/sidebar/*` nadpisze przyszły update shadcn-vue; ADR-0053 nazywa te wiersze.
- Zakładamy, że węższy sidebar (−32 px) nie łamie progów `xl` Lokat i `.category-meter-table`; research ocenia skutek jako korzystny lub neutralny, zrzuty przy 1024/1280 px to sprawdzają.
- Spec Playwright nie biegnie w CI; jego ochrona jest lokalna, CI opiera się na `ui-contract.test.ts`.

## Success Criteria (Summary)

- Sidebar w aplikacji i w `/dev/ui/sidebar` wygląda jak komponent `Sidebar` z Atramentu w obu motywach, rozwinięty i zwinięty.
- Czytnik ekranu ogłasza bieżącą sekcję, także na podstronach.
- Znak „HT” jest jedynym znakiem produktu (sidebar, logowanie, `Welcome`), a `composer ci:check`, `check:ui`, Vitest i E2E przechodzą.
