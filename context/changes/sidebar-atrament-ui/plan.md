# Sidebar według komponentu Sidebar z Atramentu: plan implementacji

## Overview

Sidebar (`AppSidebar.vue` i sloty) dostaje kształt komponentu `Sidebar` z design systemu Atrament: szerokość 224 px z tokenu, etykiety sekcji `label-caps` („Rok budżetu” nad przełącznikiem, „Menu” nad listą), `aria-current="page"` na bieżącej sekcji, okrągły awatar 28 px z e-mailem, pozycje menu `radius-md` z aktywną w wadze 600 i znak „HT” zamiast tymczasowego `Vault`. Znak „HT” trafia też na ekrany logowania (`AuthCardLayout`) i do `Welcome`, a martwe layouty ze starter kitu znikają. Decyzje zapisuje ADR-0053. Zamyka zarzuty C1–C7 z `research.md` i odroczony C5a z `sidebar-ui-contract`.

## Current State Analysis

- Szerokość pochodzi z `ui/sidebar/utils.ts:6` (`SIDEBAR_WIDTH = "16rem"`), wpisywanej inline jako `--sidebar-width` na wrapperze (`SidebarProvider.vue:72-75`); `app.css` nie ma tokenu szerokości sidebara (tokeny układu: `app.css:157-165`). Kitchen sink ma tę szerokość zapisaną drugi raz jako `w-64` (`SidebarKitchenSink.vue:182`).
- Pozycje menu: baza cva `rounded-xl` i `data-[active=true]:font-medium` (`ui/sidebar/index.ts:39`); wzorzec chce `radius-md` i 600.
- Etykieta grupy: `SidebarGroupLabel.vue:19-20` (`text-sidebar-foreground/80 … text-xs font-medium`, w trybie ikon `-mt-8 opacity-0`); „Rok budżetu” jest podpisem w przycisku (`BudgetYearSwitcher.vue:124-126`), „Brak roku budżetu” tak samo (`:96`). `label-caps` (`app.css:262-265`) ma jedno użycie w kodzie (`StatPanel.vue:41`).
- `aria-current` nie ma w sidebarze; `NavMain.vue:28-33` ustawia tylko `is-active` z `isCurrentSection`, a `@inertiajs/vue3` 3.11.0 atrybutu nie dodaje. `SidebarMenuButton` przekazuje `$attrs` do `<a>` (`SidebarMenuButton.vue:9,27,33`).
- `UserInfo.vue:25-34`: kwadratowy awatar 32 px (`rounded-lg`), fallback na `bg-muted`, e-mail tylko z `showEmail`; konsumenci: `NavUser.vue:34` (sidebar) i `UserMenuContent.vue:32` (białe menu, `show-email`).
- Znak: `AppLogo.vue:10-14` (kafelek `sidebar-primary` 32 px z `AppLogoIcon` = `Vault`). `AppLogoIcon` importują też `AuthCardLayout.vue:29` (jedyny żywy layout auth, `layouts/AuthLayout.vue:2`; z literałami `text-black dark:text-white`), `Welcome.vue:109,324` (kafelki 32 i 28 px) oraz martwe `AuthSimpleLayout.vue`, `AuthSplitLayout.vue` (`bg-zinc-900`, `text-white`) i `AppHeader.vue` (używany tylko przez martwy `AppHeaderLayout.vue`).
- `AppSidebar.vue:101`: `<slot />` po `</Sidebar>`, komponent ma dwa korzenie.
- Kitchen sink `/dev/ui/sidebar`: hover, focus-visible i disabled to opisy „zrzuty z aplikacji” (`SidebarKitchenSink.vue:46-57`).
- Testy: żaden nie zależy od markupu sidebara poza linkiem „Operacje” w `tests/e2e/auth.setup.ts:32`. Projekt nie ma `@vue/test-utils`; kontrakt markupu pilnuje `resources/js/ui-contract.test.ts` (regexy na źródłach, CI). Playwright działa lokalnie (`context/foundation/test-stack.md`, „E2E”), CI go nie uruchamia.

## Desired End State

- Na desktopie sidebar ma 224 px (`--sidebar-panel-width`), w trybie ikon 3rem, na mobile 18rem jak dziś.
- Nad przełącznikiem roku stoi „ROK BUDŻETU”, nad listą „MENU”, obie w `label-caps` w kolorze `sidebar-foreground`; w trybie ikon etykiety znikają, tooltip roku zostaje. Przycisk roku pokazuje sam rok; w stanie pustym druga linia „Brak roku budżetu” ma styl `meta`.
- Link bieżącej sekcji (także na podstronach, np. `/operations/import` → „Operacje”) ma `aria-current="page"`, pozostałe linki menu nie mają tego atrybutu.
- Pozycje menu mają promień `radius-md`, aktywna wagę 600.
- Blok użytkownika w sidebarze: okrągły awatar 28 px na `sidebar-accent`, nazwa w `sidebar-strong`, e-mail pod nią w `sidebar-foreground`. W menu użytkownika awatar jest okrągły, reszta menu bez zmian.
- Znak „HT” (JetBrains Mono 11 px 600 na `brand`, 26 px, `radius-sm`) stoi w sidebarze, nad kartą logowania (znak + nazwa aplikacji, link ma nazwę dostępną) i w nagłówku oraz stopce `Welcome`. W `resources/js` nie ma `Vault` ani `AppLogoIcon`.
- `AuthSimpleLayout`, `AuthSplitLayout`, `AppHeader`, `AppHeaderLayout` i `AppLogoIcon` są usunięte.
- `/dev/ui/sidebar` pokazuje wszystkie 7 stanów (disabled statycznie, focus-visible w sekcji „Tab…”, hover z notatką) i ramkę `brand-mark` ze znakiem w trzech miejscach.
- Weryfikacja: `npm test` (z nowymi asercjami `ui-contract.test.ts`), `npm run check:ui`, `npm run types:check`, `npm run build`, `composer ci:check`, `vendor/bin/sloppy ci`, spec Playwright `tests/e2e/sidebar-current-section.spec.ts`, zrzuty 1280/390 px w obu motywach.

### Key Discoveries:

- Token o nazwie `--sidebar-width` w `:root` przegrałby ze stylem inline wrappera, a `SIDEBAR_WIDTH = "var(--sidebar-width)"` byłby samoodwołaniem; stąd `--sidebar-panel-width` (precedens: ADR-0040, `--dialog-shadow`). `research.md`, C1.
- Precedens edycji prymitywów sidebara jako całej powłoki: `design-tokens-atrament` zmienił cva `ui/sidebar/index.ts:39` (`context/archive/2026-11-05-design-tokens-atrament/plan.md:302,311`).
- `check:ui` odrzuca `text-[11px]`, `size-[26px]`, `size-[28px]`; rozmiary spoza skali idą przez nazwane utility w `app.css` (wzór `mark-label`, `app.css:271-273`), 28 px to `size-7`.
- `aria-current` ustawiany ręcznie na `Link`: `layouts/settings/Layout.vue:47-49` (ADR-0049).
- Kontrasty z `research.md`: `brand-foreground` na `brand` 8.56:1; `sidebar-foreground` na `sidebar-background` 7.50/6.86:1; inicjały `sidebar-accent-foreground` na `sidebar-accent` ok. 12.8/14.7:1.

## What We're NOT Doing

- Paleta ⌘K, skróty `1`–`6`/`N`/`/` i linijka „Skróty: …”: osobna zmiana `command-palette` (decyzja z `change.md`).
- Rok budżetu w segmentach: zostaje menu `BudgetYearSwitcher` (decyzja z `change.md`).
- Szerokość mobilna sidebara: zostaje 18rem, wzorzec jej nie określa.
- Drugi rozmiar znaku: jeden rozmiar 26 px wszędzie, bez propa rozmiaru.
- `LandingAppPreview.vue`: dekoracyjny kafelek makiety (`:44`) i literał `min-h-[22rem]` zostają; to ilustracja, nie znak produktu.
- Uruchamianie Playwright w CI: spec jest lokalny jak pozostałe w `tests/e2e/`.
- Zmiany propsów Inertii, PHP i tłumaczeń: istniejące klucze `t.nav.menu`, `t.shell.budget_year`, `t.shell.no_budget_year` wystarczą.
- Kolejność merge z `period-report-by-category` (dodaje „Raporty” do `mainNavItems`): rozstrzyga się przy merge, nie w tym planie.

## Implementation Approach

Najpierw decyzje (ADR-0053, design-system, reguła), potem czerwone testy kontraktu, potem trzy fazy kodu, z których każda zieleni swoją część asercji: powłoka (prymitywy `ui/sidebar` jako cała powłoka, C1, C6, C7), sloty domenowe (C2, C3, C4), znak (C5 i porządek w martwych plikach). Na końcu kitchen sink, zrzuty i pełna bramka. Edycje `ui/sidebar/*` są celowe i uzasadnione w ADR-0053: sidebar jest jedną powłoką aplikacji, nie jednym ekranem.

## Critical Implementation Details

- **`label-caps` w `SidebarGroupLabel`**: `cn` (tailwind-merge) nie wie, że własna utility `label-caps` koliduje z `text-xs` i `font-medium`, więc doklejenie klasy przez prop `class` zostawiłoby obie. Dlatego `label-caps` wchodzi do bazowych klas `SidebarGroupLabel.vue` zamiast `text-xs font-medium` i `/80`, a nie przez `class` u konsumenta.
- **Usuwanie `AppLogoIcon`**: w tym samym kroku znikają pliki, które go importują, i wpis `AppLogoIcon` z listy `scripts/check-ui-literals.mjs:21-31`, inaczej `vue-tsc` i `check:ui` padną na brakującym pliku.
- **E2E**: serwer `scripts/e2e-server.sh` buduje aplikację i odmawia startu przy istniejącym `public/hot`; w worktree najpierw `npm ci`, potem spec (`context/foundation/test-stack.md`, „E2E”).

## Faza 1: Decyzje i dokumenty

### Overview

Zapisuje decyzje tej zmiany, zanim ruszy kod: ADR-0053, strona design systemu i reguła sidebara.

### Changes Required:

#### 1. ADR-0053

**File**: `context/foundation/adr/0053-sidebar-follows-sidebar-component.md`

**Intent**: Utrwala, że sidebar i znak produktu idą za komponentem `Sidebar` z Atramentu, z powodami dla każdego odstępstwa i odrzuconych opcji.

**Contract**: Po angielsku, bez frontmattera, wzór ADR-0049–0052 (`research.md`, „ADR-0053: konwencje”). Tytuł-zdanie, akapit otwierający z linkiem do artefaktu i datą stanu (2026-11-06), akapity z etykietami:
- „Width.”: token `--sidebar-panel-width` 224 px, inna nazwa niż `--sidebar-width` (powód jak ADR-0040), mobile zostaje 18rem; liczby w ADR-0051 akapit 3 („16rem sidebar”) opisują starą szerokość, decyzja `xl` zostaje.
- „Shell primitives.”: edycje `ui/sidebar/utils.ts`, cva w `index.ts` (`rounded-md`, aktywna 600) i `SidebarGroupLabel.vue` (`label-caps`), bo sidebar jest całą powłoką (precedens `design-tokens-atrament`).
- „Section labels.”: „Rok budżetu” nad przełącznikiem, „Menu” nad listą, `label-caps` w `sidebar-foreground` (zgodne z ADR-0037 akapit 1).
- „Current page.”: `aria-current="page"` z tego samego `isCurrentSection` co stan aktywny.
- „User block.”: okrągły awatar 28 px na `sidebar-accent`, e-mail w `sidebar-foreground`; awatar okrągły także w menu użytkownika.
- „Brand mark.”: „HT” w JetBrains Mono na `brand`, 26 px, `radius-sm`, jeden rozmiar w sidebarze, nad kartą logowania (ze znakiem i nazwą) i w `Welcome`; zamyka C5a z `sidebar-ui-contract`; rozszerza zdanie ADR-0035 o mono „dla kwot” bez zastępowania (precedens ADR-0047, `mark-label`). Usunięte martwe layouty starter kitu.
- „We rejected …”: paleta ⌘K i skróty (osobna zmiana), segmenty roku, drugi rozmiar znaku, style na konsumentach `SidebarProvider`.
- Zdanie, że glosariusz i propsy Inertii się nie zmieniają.

#### 2. Design system

**File**: `context/foundation/design-system.md`

**Intent**: Strona opisuje nowy stan: token szerokości, znak „HT”, awatar, `aria-current` i stany sinka.

**Contract**: `:3` lista ADR: dopisać „i ADR-0053 (…)” po ADR-0052 i brakujący ADR-0048. Tabela typografii (`:33` okolice): wiersz `brand-mark` (JetBrains Mono 600, 11 px, kafelek 26 px), `:26` mono także dla znaku. Tabela kolorów `:44`: „kafelek logo” → „znak „HT””. Sekcja „Sidebar” (`:114-128`): szerokość `--sidebar-panel-width` 224 px; `sidebar-primary` bez „kafelek logo” i bez `Welcome.vue` (zostaje `LandingAppPreview.vue`); `sidebar-accent` + awatar użytkownika; etykiety sekcji `label-caps`; `aria-current="page"`. `:140`: sink sidebara pokazuje 7 stanów i ramkę `brand-mark`.

#### 3. Reguły

**File**: `.ai/rules/js-sidebar.md`, `.ai/rules/css.md`

**Intent**: Następny agent trzyma szerokość z tokenu, etykiety w `label-caps`, `aria-current` i znak przez `BrandMark`.

**Contract**: `js-sidebar.md`, sekcja „The active item comes from isCurrentSection”: to samo wyliczenie steruje `is-active` i `aria-current="page"`; etykiety sekcji to `SidebarGroupLabel` (`label-caps`); szerokość tylko z `--sidebar-panel-width`; znak produktu to `BrandMark`, bez ikon Lucide. `css.md`: `brand-mark` na liście nazwanych stylów, `--brand-mark-size` na liście rozmiarów. `paths:` bez zmian, więc `php artisan boost:index-rules` nie powinien zmienić `index.md`.

### Success Criteria:

#### Automated Verification:

- ADR-0053 istnieje i ma etykiety akapitów z listy powyżej: `ls context/foundation/adr/0053-*.md`
- `php artisan boost:index-rules` nie zmienia `.ai/rules/index.md`: `git diff --exit-code .ai/rules/index.md`

#### Manual Verification:

- ADR-0053, `design-system.md` i reguły są spójne z decyzjami z `plan-brief.md` (szerokość, prymitywy, znak w trzech miejscach, jeden rozmiar, awatar okrągły wszędzie)

**Implementation Note**: Po zielonych kontrolach automatycznych zatrzymaj się na ręczne potwierdzenie, zanim przejdziesz dalej.

---

## Faza 2: Straż kontraktu (czerwone testy)

### Overview

Zamienia zarzuty C1–C5 w asercje, które dziś nie przechodzą, a po fazach 3–5 przechodzą i zostają w zestawie.

### Changes Required:

#### 1. Asercje źródeł

**File**: `resources/js/ui-contract.test.ts`

**Intent**: CI łapie powrót dryfu sidebara i znaku.

**Contract**: Nowy `describe('sidebar follows the Atrament Sidebar component (ADR-0053)')`, czytający źródła jak istniejące bloki:
- `components/ui/sidebar/utils.ts`: `SIDEBAR_WIDTH` to `var(--sidebar-panel-width)`, nie `16rem`; `resources/css/app.css` ma `--sidebar-panel-width: 224px`.
- `components/NavMain.vue` zawiera `aria-current`.
- `components/ui/sidebar/SidebarGroupLabel.vue` zawiera `label-caps`; `BudgetYearSwitcher.vue`, `SidebarGroupLabel.vue` i `NavMain.vue` nie zawierają `text-sidebar-foreground/80`.
- cva w `components/ui/sidebar/index.ts`: `rounded-md` zamiast `rounded-xl`, `data-[active=true]:font-semibold`.
- Żaden plik w `resources/js` nie importuje `AppLogoIcon` ani `Vault`; `AppLogo.vue`, `layouts/auth/AuthCardLayout.vue` i `pages/Welcome.vue` używają `BrandMark`.
- `components/AppSidebar.vue` nie zawiera `<slot`.

#### 2. Spec Playwright

**File**: `tests/e2e/sidebar-current-section.spec.ts`

**Intent**: Dowodzi, że `aria-current` dociera do elementu `<a>` z Inertia `Link`, także na podstronie sekcji.

**Contract**: Na sesji z `auth.setup.ts`: na `/operations/import` link „Operacje” w sidebarze ma `aria-current="page"`, a link „Dashboard” go nie ma; na `/accounts` link „Konta” (`t.nav.accounts`) ma `aria-current="page"`. Selektory przez `getByRole('link', { name })` w obrębie sidebara (np. `[data-sidebar="sidebar"]`), żeby nie trafić w link logo.

### Success Criteria:

#### Automated Verification:

- Nowe asercje nie przechodzą przed fazą 3: `npm test -- resources/js/ui-contract.test.ts` (czerwone tylko w nowym `describe`)
- Spec nie przechodzi przed fazą 4: `npx playwright test tests/e2e/sidebar-current-section.spec.ts`
- Typy się zgadzają: `npm run types:check`

**Implementation Note**: Faza kończy się czerwonymi testami; nie poprawiaj asercji pod obecny kod.

---

## Faza 3: Powłoka (C1, C6, C7)

### Overview

Prymitywy sidebara dostają szerokość z tokenu i pozycje menu ze wzorca; `AppSidebar` ma jeden korzeń.

### Changes Required:

#### 1. Token szerokości

**File**: `resources/css/app.css`

**Intent**: Szerokość sidebara jest tokenem układu jak `--aside-width`.

**Contract**: `--sidebar-panel-width: 224px` w `:root` obok tokenów układu (`:157-165`), bez wariantu `.dark` (wymiar). Komentarz przy `.category-meter-table` (`:1182`): liczba „ok. 644 px” przeliczona dla 224 px.

#### 2. Prymitywy sidebara

**File**: `resources/js/components/ui/sidebar/utils.ts`, `resources/js/components/ui/sidebar/index.ts`

**Intent**: Cała powłoka (AppShell i ramki sinka) bierze szerokość i kształt pozycji z jednego miejsca.

**Contract**: `SIDEBAR_WIDTH = "var(--sidebar-panel-width)"`; `SIDEBAR_WIDTH_MOBILE` i `SIDEBAR_WIDTH_ICON` bez zmian. Baza cva `sidebarMenuButtonVariants`: `rounded-xl` → `rounded-md`, `data-[active=true]:font-medium` → `data-[active=true]:font-semibold`; reszta klas bez zmian.

#### 3. Jeden korzeń i szerokość w sinku

**File**: `resources/js/components/AppSidebar.vue`, `resources/js/pages/dev/SidebarKitchenSink.vue`

**Intent**: Usuwa nieużywany `<slot />` (C7) i drugą, zapisaną na sztywno szerokość.

**Contract**: `AppSidebar.vue:101` bez `<slot />`. Ramka `menu-states` (`SidebarKitchenSink.vue:182`): `w-64` → `w-(--sidebar-panel-width)`.

### Success Criteria:

#### Automated Verification:

- Asercje szerokości, cva i slotu przechodzą: `npm test -- resources/js/ui-contract.test.ts`
- Literały: `npm run check:ui`
- Typy: `npm run types:check`
- Build: `npm run build`

#### Manual Verification:

- W `/dev/ui/sidebar` i w aplikacji rozwinięty sidebar ma 224 px (DevTools), zwinięty 3rem, odstęp treści równy panelowi; mobilny Sheet 18rem
- Pozycja aktywna ma wagę 600, promień pozycji 8 px w obu motywach

**Implementation Note**: Po zielonych kontrolach automatycznych zatrzymaj się na ręczne potwierdzenie.

---

## Faza 4: Sloty sidebara (C2, C3, C4)

### Overview

Etykiety sekcji, bieżąca strona dla czytników ekranu i blok użytkownika według wzorca.

### Changes Required:

#### 1. Etykiety sekcji

**File**: `resources/js/components/ui/sidebar/SidebarGroupLabel.vue`, `resources/js/components/AppSidebar.vue`, `resources/js/components/BudgetYearSwitcher.vue`

**Intent**: Obie etykiety sekcji idą jednym przepisem `label-caps`, a „Rok budżetu” stoi nad przełącznikiem.

**Contract**: Baza `SidebarGroupLabel.vue:19-20`: `text-sidebar-foreground/80 … text-xs font-medium` → `label-caps text-sidebar-foreground`; wysokość, padding i zachowanie w trybie ikon (`-mt-8 opacity-0`) bez zmian. `AppSidebar.vue`: `SidebarGroupLabel` z `t.shell.budget_year` w `SidebarHeader` między logo a `BudgetYearSwitcher` (w obu stanach przełącznika). `BudgetYearSwitcher.vue`: przycisk roku bez podpisu „Rok budżetu” (`:124-126`), sam rok w `sidebar-strong` `tabular-nums`; stan pusty: linia „Brak roku budżetu” (`:96`) w `meta text-sidebar-foreground`; tooltip `${budget_year}: ${year}` i `aria-disabled`/`aria-busy` bez zmian.

#### 2. Bieżąca strona

**File**: `resources/js/components/NavMain.vue`

**Intent**: Czytnik ekranu ogłasza bieżącą sekcję.

**Contract**: Jedno wyliczenie `isCurrentSection(toUrl(item.href), currentUrl)` na pozycję steruje `:is-active` i `:aria-current` (`'page'` albo `undefined`), atrybut na `SidebarMenuButton`, który przekazuje go do `<a>` (bez edycji `ui/`).

#### 3. Blok użytkownika

**File**: `resources/js/components/UserInfo.vue`, `resources/js/components/NavUser.vue`

**Intent**: W sidebarze okrągły awatar 28 px na `sidebar-accent` z e-mailem; w menu użytkownika awatar też okrągły.

**Contract**: `UserInfo` dostaje prop `variant?: 'default' | 'sidebar'` (domyślnie `'default'`). Oba warianty: `Avatar` bez nadpisania `rounded-lg` (domyślny `rounded-full`). `'default'`: 32 px (`size-8`), fallback jak dziś bez `rounded-lg`, e-mail za `showEmail` w `text-muted-foreground`. `'sidebar'`: `size-7`, fallback `bg-sidebar-accent text-sidebar-accent-foreground` z inicjałami 11 px 600 (nazwana utility albo `meta` + `font-semibold`, bez nawiasów), e-mail zawsze, w `text-sidebar-foreground` (styl `meta`). `NavUser.vue:34`: `<UserInfo :user="user" variant="sidebar" />`. `UserMenuContent.vue` bez zmian.

### Success Criteria:

#### Automated Verification:

- Asercje etykiet i `aria-current` przechodzą: `npm test -- resources/js/ui-contract.test.ts`
- Spec przechodzi: `npx playwright test tests/e2e/sidebar-current-section.spec.ts`
- Istniejące E2E nadal przechodzą: `npx playwright test`
- Literały: `npm run check:ui`
- Typy: `npm run types:check`

#### Manual Verification:

- „ROK BUDŻETU” i „MENU” w `label-caps`, znikają w trybie ikon, widoczne w mobilnym Sheet; stan pusty przełącznika czytelny
- Blok użytkownika: okrągły awatar 28 px, nazwa i e-mail obcięte wielokropkiem przy długim adresie, tryb ikon mieści awatar; menu użytkownika z okrągłym awatarem, poza tym bez zmian
- Czytnik ekranu (lub drzewo dostępności DevTools) ogłasza bieżącą sekcję

**Implementation Note**: Po zielonych kontrolach automatycznych zatrzymaj się na ręczne potwierdzenie.

---

## Faza 5: Znak „HT” (C5)

### Overview

Jeden komponent znaku zastępuje `Vault` w sidebarze, na logowaniu i w `Welcome`; martwe pliki starter kitu znikają.

### Changes Required:

#### 1. Styl i komponent znaku

**File**: `resources/css/app.css`, `resources/js/components/BrandMark.vue` (nowy)

**Intent**: Znak produktu ma jeden przepis nazwany w `app.css` i jeden komponent.

**Contract**: Token `--brand-mark-size: 26px` w `:root` obok `--control-height-sm`. `@utility brand-mark`: `font-mono`, 11 px, `leading-none`, 600, kwadrat `--brand-mark-size`, wyśrodkowany tekst (wzór `mark-label`, `app.css:271-273`). `BrandMark.vue`: jeden korzeń `<span aria-hidden="true">` z `brand-mark bg-brand text-brand-foreground rounded-sm shrink-0` i tekstem „HT” (znak marki, nie tekst UI do tłumaczenia); bez propsów.

#### 2. Miejsca znaku

**File**: `resources/js/components/AppLogo.vue`, `resources/js/layouts/auth/AuthCardLayout.vue`, `resources/js/pages/Welcome.vue`

**Intent**: Znak „HT” stoi wszędzie tam, gdzie był `Vault`, zawsze z nazwą aplikacji obok.

**Contract**: `AppLogo.vue`: `BrandMark` zamiast kafelka z `AppLogoIcon`; nazwa w `text-sidebar-strong` bez zmian; w trybie ikon znak 26 px mieści się w przycisku 32×32. `AuthCardLayout.vue:24-31`: link `home()` z `BrandMark` i nazwą aplikacji (`usePage().props.name`) w `text-foreground font-semibold`, bez `text-black`/`dark:`; link ma nazwę dostępną z tekstu. `Welcome.vue:106-111` (nagłówek) i `:321-325` (stopka): `BrandMark` zamiast kafelków `sidebar-primary` 32 i 28 px; nazwa i tagline bez zmian.

#### 3. Usunięcie `Vault` i martwych plików

**File**: `resources/js/components/AppLogoIcon.vue`, `resources/js/layouts/auth/AuthSimpleLayout.vue`, `resources/js/layouts/auth/AuthSplitLayout.vue`, `resources/js/components/AppHeader.vue`, `resources/js/layouts/app/AppHeaderLayout.vue`

**Intent**: Usuwa tymczasowy glif i resztki starter kitu, których nikt nie importuje.

**Contract**: Pięć plików usuniętych; przed usunięciem `grep -rn` po nazwach w `resources/js` pokazuje tylko importy z samych usuwanych plików. `Breadcrumbs`, `AppContent`, `AppShell` i `ui/*` zostają (mają innych konsumentów albo są prymitywami).

#### 4. Straż literałów

**File**: `scripts/check-ui-literals.mjs`

**Intent**: `check:ui` pilnuje nowych miejsc znaku.

**Contract**: Lista `:21-31`: bez `AppLogoIcon.vue`; dochodzą `components/BrandMark.vue`, `layouts/auth/AuthCardLayout.vue`, `pages/Welcome.vue`.

### Success Criteria:

#### Automated Verification:

- Wszystkie asercje nowego `describe` przechodzą: `npm test -- resources/js/ui-contract.test.ts`
- Brak `Vault` i `AppLogoIcon`: `grep -rn "AppLogoIcon\|Vault" resources/js` zwraca 0 trafień
- Literały, w tym nowe pliki: `npm run check:ui`
- Typy: `npm run types:check`
- Build: `npm run build`
- Logowanie w E2E działa (`auth.setup.ts`): `npx playwright test`

#### Manual Verification:

- Znak „HT” w sidebarze (rozwinięty i zwinięty), nad kartą logowania i w nagłówku i stopce `Welcome`, w obu motywach; krój JetBrains Mono wczytany (nie fallback)
- Na logowaniu link ze znakiem i nazwą prowadzi na stronę główną, fokus widoczny

**Implementation Note**: Po zielonych kontrolach automatycznych zatrzymaj się na ręczne potwierdzenie.

---

## Faza 6: Kitchen sink i bramka wizualna

### Overview

Sink sidebara pokazuje pełną macierz 7 stanów i znak w trzech miejscach; zrzuty i pełna bramka zamykają zmianę.

### Changes Required:

#### 1. Macierz stanów

**File**: `resources/js/pages/dev/SidebarKitchenSink.vue`

**Intent**: hover, focus-visible i disabled przestają być „zrzutami z aplikacji”.

**Contract**: `stateMatrix` (`:25-58`) wskazuje nowe sekcje. Ramka `menu-states` dostaje trzecią pozycję disabled: `SidebarMenuButton` z `aria-disabled="true"` i `aria-busy="true"` (cva `aria-disabled:opacity-50`). Nowa sekcja `focus-visible` z prawdziwymi kontrolkami (pozycje menu i przycisk roku) i instrukcją „Tab…” (wzór `MonthPlanSink.vue:546-563`, `ImportPreviewSink.vue:156-163`); hover jako notatka przy `menu-states` (wzór `ImportPreviewSink.vue:147-154`). Ramka `empty` pokazuje etykietę „Rok budżetu” nad pustym stanem (wynika z fazy 4).

#### 2. Ramka znaku

**File**: `resources/js/pages/dev/SidebarKitchenSink.vue`

**Intent**: Znak ocenia się w jednym widoku i obu motywach we wszystkich miejscach użycia.

**Contract**: Sekcja `data-frame="brand-mark"`: `BrandMark` z nazwą na `bg-sidebar` (sidebar), na `bg-background` (nagłówek Welcome) i na `bg-muted` (tło logowania).

#### 3. Zrzuty i bramka

**File**: `context/changes/sidebar-atrament-ui/screenshots/`

**Intent**: Dowód wizualny przed `impl-review`.

**Contract**: Podgląd z worktree według `context/foundation/agents/worktree-preview.md`; jednorazowy skrypt Playwright (niecommitowany) robi pełne zrzuty `/dev/ui/sidebar`, `/dashboard`, `/operations/import`, `/login` i `/` przy 1280 i 390 px, w jasnym i ciemnym motywie.

### Success Criteria:

#### Automated Verification:

- Vitest: `npm test`
- Literały: `npm run check:ui`
- Typy: `npm run types:check`
- Build: `npm run build`
- Pest (sink nadal 404 na produkcji, AppShell): `artisan test --compact tests/Feature/SidebarKitchenSinkTest.php tests/Feature/AppShellTest.php` przez polecenie `docker run … sail-8.5/app` z `AGENTS.md` („PostgreSQL tests, worktree”)
- Pełna bramka na PostgreSQL w worktree: polecenie `ci:check` z `AGENTS.md` („PostgreSQL tests, worktree”)
- Sloppy na hoście: `vendor/bin/sloppy ci`
- E2E: `npx playwright test`

#### Manual Verification:

- `/dev/ui/sidebar` pokazuje 7 stanów i ramkę `brand-mark`; Tab przechodzi po kontrolkach z widocznym pierścieniem `sidebar-ring`
- Zrzuty 1280/390 px w obu motywach zapisane w `screenshots/` i przejrzane: sidebar 224 px, etykiety, awatar, znak; Dashboard przy 1024 i 1280 px bez regresji układu

**Implementation Note**: Po zielonych kontrolach automatycznych zatrzymaj się na ręczne potwierdzenie i przegląd zrzutów.

---

## Testing Strategy

### Unit Tests:

- `ui-contract.test.ts` (Vitest, CI): szerokość z tokenu, cva (`rounded-md`, aktywna `font-semibold`), `label-caps` w `SidebarGroupLabel`, `aria-current` w `NavMain`, brak `text-sidebar-foreground/80` w etykietach, brak `AppLogoIcon`/`Vault`, `BrandMark` w trzech miejscach, `AppSidebar` bez slotu.
- Istniejące: `lib/nav-active.test.ts` (logika `isCurrentSection` bez zmian), `lib/budget-year-selection.test.ts`, `DesignTokensTest` (nowe tokeny to wymiary, nie kolory), `UiTranslationKeysTest`.

### Integration Tests:

- `tests/e2e/sidebar-current-section.spec.ts`: `aria-current="page"` na „Operacje” przy `/operations/import`, brak na „Dashboard”, „Konta” przy `/accounts`.
- `tests/e2e/auth.setup.ts`: logowanie z nowym nagłówkiem karty.

### Manual Testing Steps:

1. `/dev/ui/sidebar`: cztery ramki, `menu-states` z disabled, sekcja Tab, ramka `brand-mark`, oba motywy.
2. Aplikacja przy 1280 i 1024 px: sidebar 224 px, zwinięcie Ctrl/⌘+B, tooltipy w trybie ikon.
3. Telefon (390 px): Sheet 18rem z etykietami i blokiem użytkownika.
4. `/login` i `/`: znak „HT” z nazwą w obu motywach.
5. Menu użytkownika: okrągły awatar, e-mail, akcje bez zmian.

## Performance Considerations

Brak: zmiany klas i markupu. JetBrains Mono jest już ładowany (`vite.config.ts:22-26`, `preload: false`); na `/login` i `/` dochodzi jego pobranie dla dwóch liter znaku.

## Migration Notes

Brak danych. Ciasteczko `sidebar_state` bez zmian. Konflikt w `AppSidebar.vue` z `period-report-by-category` (`mainNavItems`) rozwiązuje ta zmiana, która trafi do `development` jako druga.

## References

- Research: `context/changes/sidebar-atrament-ui/research.md`
- Zmiana: `context/changes/sidebar-atrament-ui/change.md`
- Poprzedni kontrakt sidebara: `context/archive/2026-11-03-sidebar-ui-contract/`
- Precedens edycji `ui/sidebar`: `context/archive/2026-11-05-design-tokens-atrament/plan.md:302,311`
- Precedens `aria-current`: `resources/js/layouts/settings/Layout.vue:47-49`
- Wzorzec: https://claude.ai/artifact/9gJSdtfpFgUAa8oEMgR4HA (`project/components/Sidebar/README.md`, `.ht-side*` w `project/components/bundle.css`), podgląd, nie źródło wartości

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Decyzje i dokumenty

#### Automated

- [x] 1.1 ADR-0053 istnieje i ma etykiety akapitów z listy powyżej — 80c308d
- [x] 1.2 `php artisan boost:index-rules` nie zmienia `.ai/rules/index.md` — 80c308d

#### Manual

- [x] 1.3 ADR-0053, `design-system.md` i reguły są spójne z decyzjami z `plan-brief.md` — 80c308d

### Phase 2: Straż kontraktu (czerwone testy)

#### Automated

- [x] 2.1 Nowe asercje nie przechodzą przed fazą 3
- [x] 2.2 Spec nie przechodzi przed fazą 4
- [x] 2.3 Typy się zgadzają

### Phase 3: Powłoka (C1, C6, C7)

#### Automated

- [ ] 3.1 Asercje szerokości, cva i slotu przechodzą
- [ ] 3.2 Literały: `npm run check:ui`
- [ ] 3.3 Typy: `npm run types:check`
- [ ] 3.4 Build: `npm run build`

#### Manual

- [ ] 3.5 Rozwinięty sidebar 224 px, zwinięty 3rem, mobilny Sheet 18rem
- [ ] 3.6 Pozycja aktywna 600, promień pozycji 8 px w obu motywach

### Phase 4: Sloty sidebara (C2, C3, C4)

#### Automated

- [ ] 4.1 Asercje etykiet i `aria-current` przechodzą
- [ ] 4.2 Spec przechodzi
- [ ] 4.3 Istniejące E2E nadal przechodzą
- [ ] 4.4 Literały: `npm run check:ui`
- [ ] 4.5 Typy: `npm run types:check`

#### Manual

- [ ] 4.6 Etykiety `label-caps`, tryb ikon i mobilny Sheet, stan pusty
- [ ] 4.7 Blok użytkownika i menu użytkownika z okrągłym awatarem
- [ ] 4.8 Czytnik ekranu ogłasza bieżącą sekcję

### Phase 5: Znak „HT” (C5)

#### Automated

- [ ] 5.1 Wszystkie asercje nowego `describe` przechodzą
- [ ] 5.2 Brak `Vault` i `AppLogoIcon` w `resources/js`
- [ ] 5.3 Literały, w tym nowe pliki: `npm run check:ui`
- [ ] 5.4 Typy: `npm run types:check`
- [ ] 5.5 Build: `npm run build`
- [ ] 5.6 Logowanie w E2E działa

#### Manual

- [ ] 5.7 Znak „HT” w sidebarze, na logowaniu i w `Welcome`, oba motywy, JetBrains Mono
- [ ] 5.8 Link znaku na logowaniu prowadzi na stronę główną, fokus widoczny

### Phase 6: Kitchen sink i bramka wizualna

#### Automated

- [ ] 6.1 Vitest: `npm test`
- [ ] 6.2 Literały: `npm run check:ui`
- [ ] 6.3 Typy: `npm run types:check`
- [ ] 6.4 Build: `npm run build`
- [ ] 6.5 Pest: `SidebarKitchenSinkTest` i `AppShellTest`
- [ ] 6.6 Pełna bramka na PostgreSQL w worktree
- [ ] 6.7 Sloppy na hoście: `vendor/bin/sloppy ci`
- [ ] 6.8 E2E: `npx playwright test`

#### Manual

- [ ] 6.9 `/dev/ui/sidebar` pokazuje 7 stanów i ramkę `brand-mark`, Tab z pierścieniem
- [ ] 6.10 Zrzuty 1280/390 px w obu motywach przejrzane, Dashboard bez regresji
