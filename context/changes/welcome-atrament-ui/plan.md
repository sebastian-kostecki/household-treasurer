# Strona powitalna według Atramentu — plan implementacji

## Overview

Strona przed logowaniem (`pages/Welcome.vue`) i jej dwa komponenty (`LandingAppPreview.vue`, `LandingFeatureCard.vue`) przechodzą na kartę PageWelcome z design systemu Atrament. Treść ma szerokość 1120 px, typografia idzie z nazwanych stylów `display`, `heading`, `lead` i `card-title`, a panele mają `radius-lg` z pełną ramką. Pas wezwania stoi na ciemnym panelu z przyciskiem `brand`, przyciski hero mają 36 px, a podgląd aplikacji jest złożony z klocków Dashboardu zamiast szkicu z kropek. Teksty dla gościa tracą żargon. Na końcu strona dostaje strażnika wzorców, wpis w `check:ui` i kitchen sink `/dev/ui/welcome`.

## Current State Analysis

Pełny audyt: `context/changes/welcome-atrament-ui/research.md` (zarzuty C1–C11). W skrócie:

- Szerokość treści to `max-w-6xl` (1152 px), powtórzona w sześciu miejscach (`Welcome.vue:103,148,214,239,276,314`). Tokenu na nią nie ma (C1).
- Nagłówki mają rozmiary z Tailwinda: h1 do `text-5xl` (`:157-158`), h2 `text-2xl sm:text-3xl` (`:217,242`), lead `text-base sm:text-lg` (`:162`), tytuł karty funkcji `text-base` (`LandingFeatureCard.vue:26`). Style `display`, `heading`, `lead` i `card-title` stoją w `app.css:259-273` bez konsumenta (C2).
- Panele mają `rounded-2xl`/`rounded-xl` i `border-border/60` (`Welcome.vue:100,152,212,238,255,258,275,278,312`; `LandingAppPreview.vue:38,74,86,100`) (C3).
- Przyciski hero i pasa to `size="lg"` (40 px), a przyciski w pasku `size="sm"` (26 px) (C4). W `ui/button` nie ma rozmiaru 36 px.
- Pas wezwania stoi na `bg-accent` (`Welcome.vue:278`), a powinien na ciemnym panelu (C5).
- Podgląd aplikacji to pięć kropek zamiast sidebaru i dwie miary w `text-success`, który znaczy przychód (`LandingAppPreview.vue:41-55,80,92`). Do tego `min-h-[22rem]`, jedyne trafienie skanu literałów (C6).
- Ręczne klocki: plakietka hero zamiast `Badge ok` (`:152`), kropka zamiast ikony Check (`:199`), cztery panele kroków zamiast jednego (`:255`), kafelek ikony 40 px `rounded-xl` (`LandingFeatureCard.vue:21`) (C7).
- `href="/register"` za `canRegister` (`:138,184`), bez trasy Wayfindera, bo rejestracja jest wyłączona (C8).
- Pod przyklejonym paskiem kotwice `#features` i `#how` nie mają `scroll-margin` (C9).
- Z trzech plików tylko `Welcome.vue` jest na liście `check:ui`. Nie ma strażnika wzorców ani kitchen sinka (C10).
- Teksty: `features_lead` z „bez zbędnego marketingu”, `feature_privacy_description` z „passkeys” i „w MVP”, a w trzech innych kluczach pauza, którą karta zastępuje przecinkiem (C11).

## Desired End State

- `/` wygląda jak karta PageWelcome przy 1280 px w obu motywach. Pasek ma 56 px na `card` z pełną ramką u dołu i przyciskami 32 px. Hero ma `Badge ok`, h1 w `display`, lead w `lead`, przyciski 36 px i punkty z ikoną Check w `brand-text`. Podgląd aplikacji pokazuje sidebar z `BrandMark` i sześcioma pozycjami, trzy miary, tabelę „Wydatki” z czterema kolumnami i stopką „Kwoty w zł”, a Zdrowie 113% ma Badge „przekroczono”. Funkcje to sześć paneli z kafelkiem ikony 32 px. Kroki to jeden panel z czterema komórkami „01”–„04”. Pas wezwania jest granatowy z przyciskiem `brand`, a stopka stoi na `card`.
- Przy 390 px strona jest w jednej kolumnie, bez poziomego przewijania, a podgląd mieści się w szerokości ekranu.
- Po kliknięciu „Funkcje” lub „Jak to działa” nagłówek sekcji jest widoczny pod paskiem.
- Link „Zarejestruj się” zostaje za `canRegister` z literalnym `href="/register"`. To jedyny dozwolony wyjątek od zakazu `href="/` w plikach landingu.
- `WelcomeHardcodedUiTest`, `check:ui` i `ui-contract.test.ts` pilnują trzech plików, a `/dev/ui/welcome` pokazuje stany gość, gość z rejestracją i zalogowany.

Weryfikacja: `composer ci:check`, `vendor/bin/sloppy ci`, `npm test`, `npm run check:ui` oraz zrzuty `/dev/ui/welcome` przy 1280 i 390 px w obu motywach.

### Key Discoveries:

- `resources/css/app.css:352` otwiera `@layer components`, a wysokość przycisku `h-control` to `@utility` (`:322-324`). Komentarz w `:1485` mówi, że klasa z `components` nie nadpisze utility. Dlatego 36 px musi być utility, a nie klasą wzorowaną na `.auth-submit` (`:455-457`).
- `--control-height` 32 px i `--control-height-sm` 26 px stoją w `:root` (`app.css:157-158`). Nowy `--control-height-lg` 36 px dołącza do tej rodziny.
- `display` (`app.css:259-261`) nie ma konsumenta (ADR-0056), więc odstęp liter z karty (−0,025em) można dopisać w samym stylu bez ryzyka dla innych widoków.
- `ExecutionMeter.vue` przyjmuje `percentComplete`, `categoryType`, `isOverLimit` i `yearElapsedPercent`. Badge przekroczenia to `Badge variant="destructive-soft"` z `t.budget.over_limit_badge`, jak w `CategoryMeterRow.vue:82-83`. `CategoryMeterRow` nie nadaje się do podglądu, bo jego korzeń to Inertia `Link` (fokusowalny w regionie `aria-hidden`).
- `StatPanel.vue` przyjmuje `metrics: StatPanelMetric[]` (2–4, pierwsza w `kpi-lg`) i nie korzysta z routera.
- `canRegister` pochodzi z `HandleInertiaRequests.php:47` (`Features::enabled(Features::registration())`). Propsy z `Inertia::render` nadpisują propsy współdzielone płytko, więc sink może podać `auth.user = null` i `canRegister = true` bez zmiany `Welcome`.
- Karta (`project/components/PageWelcome/preview.html`) ma w pasku i w hero przycisk rejestracji, tabelę o kolumnach `minmax(0,1fr) 74px 74px 104px` i kroki „01”–„04” w jednym panelu `ht-stats`.
- `DashboardHardcodedUiTest.php` zawiera listę plików, mapę zakazanych wzorców i dwa autotesty skanera. To wzór dla nowego strażnika.

## What We're NOT Doing

- Nie włączamy rejestracji, nie zmieniamy `config/fortify.php` i nie dodajemy trasy `register` (decyzja: link zostaje za `canRegister` z literalnym adresem).
- Nie edytujemy `components/ui/*` (`button`, `card`, `badge`, `sidebar`). 36 px to utility, a sidebar podglądu jest zbudowany z tokenów, bez `SidebarProvider`.
- Nie dodajemy własnego progu 860 px z karty. Nawigacja w pasku pojawia się od `md`, hero dzieli się na dwie kolumny od `lg`, tak jak dziś i jak w ADR-0056.
- Nie dodajemy kolumny „Pozostało” do podglądu i nie zmieniamy `.category-meter-row` Dashboardu.
- Nie importujemy `pages/dev/dashboard-fixtures.ts` do produkcyjnego podglądu.
- Nie dodajemy prefiksu `ht-` do nazw klas ani tokenów `--space-*`.
- Nie dodajemy e2e dla landingu. Bramką wizualną są zrzuty z sinka.
- Nie usuwamy ani nie przemianowujemy `LandingPageSourceTest` i `LandingPageTest`.

## Implementation Approach

Kolejność jak w `auth-atrament-ui`: najpierw ADR i dokumenty, potem wartości (tokeny, klasy, teksty), potem widok, na końcu sink i zrzuty. W Fazie 3 strażnik powstaje przed edycją widoku i ma najpierw nie przechodzić na obecnych plikach. Każda faza to jeden commit: Faza 1 `docs(welcome-atrament-ui): … (p1)`, kolejne `feat(welcome-atrament-ui): … (pN)`.

## Critical Implementation Details

- **36 px przez utility, sprawdzone w renderze.** `Button` składa klasy przez `cn` (tailwind-merge, `ui/button/Button.vue:27`). Zostaje więc albo `h-control` z rozmiaru `default`, albo `h-control-lg` z `class`, a tailwind-merge może nie uznać tych dwóch nazw za konflikt. Wynik sprawdź komputowaną wysokością (`getComputedStyle(el).height === '36px'`) w przeglądarce. Jeśli wygrywa 32 px, użyj `size="lg"` z klasą `h-9` (tailwind-merge zastępuje `h-10`) i zapisz to w ADR-0057 jako odstępstwo.
- **Podgląd jest `aria-hidden` i nie ma w nim nic fokusowalnego.** Żadnego `Link`, `a` ani `button`. Pozycje sidebaru i wiersze tabeli to `div`/`span`.

## Faza 1: Decyzje i dokumenty

### Overview

ADR-0057 i aktualizacja design systemu oraz reguł, zanim powstanie kod (AGENTS.md: nowy token trafia na stronę design systemu i do ADR).

### Changes Required:

#### 1. ADR-0057

**File**: `context/foundation/adr/0057-welcome-follows-page-welcome.md`

**Intent**: Zapisać decyzje tej zmiany, żeby kolejny agent nie wracał po wartości do karty artefaktu.

**Contract**: Format jak ADR-0056. Treść:
- strona idzie za kartą PageWelcome (status „Propozycja”, stan z 2026-11-07);
- mapowanie `ht-land*` na `.landing-*` (tabela);
- tokeny `--landing-width` 1120 px, `--landing-bar-height` 56 px i `--control-height-lg` 36 px z utility `h-control-lg`, bez rozmiaru w `ui/button`;
- `display` dostaje odstęp liter −0,025em, a h1 hero ma 30/36 poniżej `sm`;
- panele `radius-lg` z pełną ramką (ADR-0037);
- pas wezwania na `sidebar-background` z tekstem `sidebar-foreground`, nagłówkiem `sidebar-strong`, fokusem `sidebar-ring` i przyciskiem `Button variant="brand"`;
- podgląd aplikacji z klocków Dashboardu (ADR-0045): `StatPanel` z trzema miarami, siatka `.landing-preview-row` z czterema kolumnami zamiast pięciu, `ExecutionMeter`, `CategoryChip`, Badge „przekroczono” przy przekroczeniu (odstępstwo od karty, zgodne z ADR-0045);
- sidebar podglądu pokazuje `BrandMark` bez nazwy produktu (nazwa stoi w pasku strony). To zawęża zdanie ADR-0053 o „decorative tile” w `LandingAppPreview.vue`;
- link rejestracji zostaje za `canRegister` z literalnym `href="/register"`, bo Wayfinder nie ma trasy przy wyłączonej rejestracji. To wyjątek od ADR-0056 („bez ręcznego `href`”), ograniczony do `Welcome.vue`. Po włączeniu rejestracji literał zastępuje trasa Wayfindera;
- progi: nawigacja paska od `md`, hero w dwóch kolumnach od `lg`, kroki w rzędzie od `md`, bez 860 px;
- teksty: pięć kluczy `landing` w brzmieniu karty.

#### 2. Design system

**File**: `context/foundation/design-system.md`

**Intent**: Strona design systemu nadąża za ADR-0057.

**Contract**:
- indeks ADR w nagłówku (wiersz 3) z 0057;
- tokeny wymiarów (wiersz 22): `--landing-width`, `--landing-bar-height`, `--control-height-lg`;
- tabela typografii: konsument `display` (strona powitalna, z odstępem liter), `heading`, `lead`, `card-title`;
- „Gotowe elementy” (wiersz 91): klasy `.landing-*` i `h-control-lg`;
- tabela tokenów sidebaru (wiersz 131): zdanie o `LandingAppPreview.vue` poprawione na sidebar podglądu i pas wezwania;
- „Kitchen sink i zrzuty” (wiersz 152 i akapit obok `/dev/ui/auth`): `/dev/ui/welcome` z trzema stanami.

#### 3. Reguły

**Files**: `.ai/rules/css.md`, nowy `.ai/rules/js-landing.md`, `.ai/rules/index.md`

**Intent**: Reguły ładowane przy edycji plików landingu mówią, jak utrzymać stronę po zmianie.

**Contract**:
- `css.md`: `--landing-width`, `--landing-bar-height` i `--control-height-lg` na liście tokenów wymiarów;
- `js-landing.md` z `paths:` `resources/js/pages/Welcome.vue` i `resources/js/components/Landing*.vue`. Treść:
  - szerokość przez `.landing-wrap`;
  - typografia z nazwanych stylów;
  - przyciski hero i pasa w `h-control-lg`;
  - podgląd z klocków Dashboardu, `aria-hidden`, bez elementów fokusowalnych i bez `text-success`;
  - jedyny literał `href` to `/register` za `canRegister`;
  - nowy stan trafia do `/dev/ui/welcome`;
  - strażnik `WelcomeHardcodedUiTest`.

  Potem `php artisan boost:index-rules`.

### Success Criteria:

#### Automated Verification:

- ADR-0057 istnieje pod kolejnym wolnym numerem: `ls context/foundation/adr/0057-*.md`
- Indeks reguł zawiera `js-landing.md` po `php artisan boost:index-rules`: `grep js-landing .ai/rules/index.md`

#### Manual Verification:

- ADR-0057 i `design-system.md` zgadzają się z tabelą decyzji w `plan-brief.md` (link rejestracji, Badge „przekroczono”, 4 kolumny, 36 px jako utility, progi `md`/`lg`)

**Implementation Note**: Po fazie zatrzymaj się na ręczne potwierdzenie.

---

## Faza 2: Tokeny, klasy i teksty

### Overview

Wszystkie wartości, z których skorzysta widok: tokeny wymiarów, utility 36 px, odstęp liter w `display`, klasy `.landing-*` i teksty.

### Changes Required:

#### 1. Tokeny i utility

**File**: `resources/css/app.css`

**Intent**: Wymiary z karty mają jedno źródło.

**Contract**:
- w `:root` obok `--control-height-sm`: `--control-height-lg: 36px`, `--landing-width: 1120px`, `--landing-bar-height: 56px` (wymiary, bez `.dark`);
- `@utility h-control-lg { height: var(--control-height-lg); }` obok `h-control-sm`;
- `display`: dopisany `letter-spacing: -0.025em`.

#### 2. Klasy landingu

**File**: `resources/css/app.css` (`@layer components`, po bloku `.auth-*`, z komentarzem ADR-0057)

**Intent**: Nazwane klasy zastępują `ht-land*` i powtarzane utility w widoku.

**Contract**:
- `.landing-wrap`: `mx-auto`, `max-w-(--landing-width)`, poziomy margines `px-4 sm:px-6`;
- `.landing-bar`: `sticky top-0`, wysokość `--landing-bar-height`, `bg-card`, pełna ramka `border-b`, bez przezroczystości i `backdrop-blur`;
- `.landing-section`: ramka u góry, pionowy rytm sekcji, `scroll-margin-top: var(--landing-bar-height)`;
- `.landing-hero`, `.landing-hero-title` (`display` od `sm`, poniżej 30/36), `.landing-points` (ikona Check 16 px w `brand-text`);
- `.landing-grid` (funkcje: 1/2/3 kolumny), `.landing-feature` (panel `card`, `border`, `rounded-lg`), `.landing-icon` (32 px, `radius-md`, `accent`/`accent-foreground`);
- `.landing-steps`: jeden panel `card` z pełną ramką i `rounded-lg`, komórki w kolumnie z `border-t` poniżej `md`, w rzędzie z `border-l` od `md`; `.landing-step-no` (`label-caps` w `muted-foreground`);
- `.landing-cta`: `bg-sidebar`, `text-sidebar-foreground`, h2 `sidebar-strong` 18/24, `rounded-lg`, padding 28/32 (na skali Tailwinda), układ kolumna/rząd od `sm`, fokus w środku `sidebar-ring`;
- `.landing-foot`: `bg-card`, ramka u góry;
- podgląd: `.landing-preview` (panel `card`, `border`, `rounded-lg`, bez cienia i bez `min-h`), `.landing-preview-side` (`bg-sidebar`, `border-sidebar-border`, ukryty poniżej `sm`), `.landing-preview-nav-item` (aktywna pozycja `bg-sidebar-primary text-sidebar-primary-foreground`), `.landing-preview-main`, `.landing-preview-row` (siatka `minmax(0,1fr) 74px 74px 104px`; poniżej `sm` bez kolumny „Plan bieżący”, żeby przy 390 px nie było przewijania).

Wartości px w klasach są dozwolone (stoją w `app.css`, nie w widoku). Bierz je z `bundle.css` karty.

#### 3. Kontrast pasa

**File**: brak (pomiar)

**Intent**: Pas wezwania przechodzi WCAG w obu motywach.

**Contract**: Policz jednorazowym skryptem, którego nie commitujesz: `brand-foreground` na `brand` (≥ 4,5:1) i obrys `brand` na `sidebar-background` (≥ 3:1, element nietekstowy), w jasnym i ciemnym motywie. Wyniki trafiają do ADR-0057. Jeśli któraś para nie przejdzie, zatrzymaj się i zapytaj, zanim zmienisz token.

#### 4. Teksty

**File**: `lang/pl/ui.php` (tablica `landing`)

**Intent**: Gość nie widzi żargonu, a teksty odpowiadają karcie.

**Contract**:
- `features_lead` → „Operacyjny panel do planu, płynności i księgowania.”;
- `feature_privacy_description` → „Jedno konto użytkownika, weryfikacja e-mail, 2FA i klucze dostępu, bez współdzielenia gospodarstwa.”;
- `hero_lead`, `feature_liquidity_description`, `feature_month_description`: „ — ” → „, ” jak w karcie;
- nowe klucze podglądu: `preview_metric_to_spend` („Do wydania”), `preview_metric_liquidity` („Płynność”), `preview_category_housing` („Mieszkanie”), `preview_category_health` („Zdrowie”), `preview_year_progress` („Postęp roku :percent%”), jeśli w `dashboard`/`budget` nie ma już takiego klucza;
- `preview_category_salary` usunięty, jeśli po Fazie 3 nic go nie czyta (grep).

Etykiety sidebaru podglądu, „Łączne środki”, „Wydatki”, „Realizacja”, „przekroczono” i „Kwoty w zł” biorą istniejące klucze (`nav.*`, `dashboard.total_funds`, `dashboard.expenses`, `budget.phase_execution`, `budget.over_limit_badge`, `budget.amounts_in_zl`).

### Success Criteria:

#### Automated Verification:

- `npm run build` przechodzi z nowymi tokenami i klasami
- `npm run check` przechodzi bez ostrzeżeń
- Testy tekstów i landingu przechodzą: `php artisan test --compact tests/Unit/UiTranslationKeysTest.php tests/Feature/LandingPageTest.php`
- W tablicy `landing` nie ma „marketingu”, „passkeys” ani „w MVP”: `grep -c "marketingu\|passkeys\|w MVP" lang/pl/ui.php` zwraca 0 (dziś te słowa są tylko w `:799` i `:811`)

#### Manual Verification:

- Wyniki kontrastu pasa (obie pary, oba motywy) są zapisane w ADR-0057 i przechodzą progi

**Implementation Note**: Po fazie zatrzymaj się na ręczne potwierdzenie.

---

## Faza 3: Strażnik i widoki

### Overview

Najpierw strażnik, który nie przechodzi na obecnych plikach, potem trzy pliki widoku przepisane na klasy z Fazy 2, aż strażnik przejdzie.

### Changes Required:

#### 1. Strażnik wzorców (najpierw czerwony)

**Files**: `tests/Unit/WelcomeHardcodedUiTest.php` (nowy), `scripts/check-ui-literals.mjs`, `resources/js/ui-contract.test.ts`

**Intent**: Oczyszczone pliki nie wrócą do wzorców sprzed Atramentu (C10).

**Contract**:
- `WelcomeHardcodedUiTest.php` na wzór `DashboardHardcodedUiTest.php`: lista `Welcome.vue`, `LandingAppPreview.vue` i `LandingFeatureCard.vue`, zakazy z mapy Dashboardu (hex, funkcje kolorów, klasy palety, wartości arbitralne, `text-lg|xl|2xl|3xl`, `text-base`, `rounded-xl|2xl`, `border-border/N`, `opacity-N`) oraz `max-w-6xl`, `size="lg"`, `shadow-sm`, `tracking-tight`, `backdrop-blur`, `text-success`. Do tego dwa autotesty skanera (wzorzec wykryty i czysty plik). Wywołany przed Fazą 3.2 ma nie przechodzić;
- `check-ui-literals.mjs`: do `FILES` dochodzą `LandingAppPreview.vue` i `LandingFeatureCard.vue`;
- `ui-contract.test.ts`: nowy `it.each` po trzech plikach. Zakazuje `href="/` z wyjątkiem `href="/register"` (regex `/href="\/(?!register")/`) i `text-success`. W `LandingAppPreview.vue` zakazuje `<Link`, `<a ` i `<button`. Asercja `BrandMark` w `Welcome.vue` zostaje, a dochodzi taka sama dla `LandingAppPreview.vue`.

#### 2. `Welcome.vue`

**File**: `resources/js/pages/Welcome.vue`

**Intent**: Strona odpowiada karcie PageWelcome (C1–C5, C7–C9).

**Contract**:
- pasek: `header.landing-bar` z `.landing-wrap`, `BrandMark` z nazwą, nawigacja od `md`, przyciski w rozmiarze `default` (32 px): `outline` „Zaloguj się” i, za `canRegister`, „Zarejestruj się” z `href="/register"`; zalogowany widzi „Przejdź do dashboardu”;
- hero: `Badge variant="ok"` z `hero_badge`, `h1.landing-hero-title`, `p.lead` w `muted-foreground`, przyciski z klasą `h-control-lg` (login domyślny, rejestracja `outline` za `canRegister`), punkty w `ul.landing-points` z ikoną `Check`;
- sekcje `#features` i `#how` w `.landing-section`: `label-caps` z `nav_features`/`nav_how` nad `h2.heading`, `p.lead`;
- kroki: `ol.landing-steps` z czterema `li`, numer „01”–„04” w `.landing-step-no`, `h3.card-title`, opis `text-sm` w `muted-foreground`;
- pas: `.landing-cta` z `h2`, opisem i `Button variant="brand"` z `h-control-lg` (zalogowany: dashboard, gość: login);
- stopka: `footer.landing-foot` z `BrandMark`, nazwą, `footer_tagline`, linkiem logowania dla gościa i rokiem;
- bez `max-w-6xl`, `border-border/60`, `rounded-xl|2xl`, `size="lg"|"sm"`, `tracking-tight` i rozmiarów `text-*` z Tailwinda.

Wynik 36 px sprawdź w przeglądarce (Critical Implementation Details).

#### 3. `LandingFeatureCard.vue`

**File**: `resources/js/components/LandingFeatureCard.vue`

**Intent**: Karta funkcji jak `ht-land__feature` (C7).

**Contract**: Te same propsy (`icon`, `title`, `description`). `article.landing-feature`, kafelek `.landing-icon` z ikoną 16 px, `h3.card-title`, opis `text-sm` w `muted-foreground`. Bez `ui/card`.

#### 4. `LandingAppPreview.vue`

**File**: `resources/js/components/LandingAppPreview.vue`

**Intent**: Podgląd przypomina Dashboard, który obiecuje, i nie myli miar z przychodem (C6).

**Contract**:
- korzeń `div.landing-preview` z `aria-hidden="true"`;
- `.landing-preview-side`: `BrandMark` bez nazwy i sześć pozycji (Dashboard aktywny, Budżet, Plan miesiąca, Konta, Lokaty, Operacje) z ikonami jak w `AppSidebar` i etykietami z `t.nav.*`, jako `div`;
- `.landing-preview-main`: tytuł „Dashboard” w `section-title` z `Badge variant="brand"` „Realizacja”;
- `StatPanel` z trzema miarami (Do wydania 2 340,50 zł, Płynność 27 770,50 zł, Łączne środki 47 770,50 zł) przez `formatMoney`, bez `toneClass` (żadnej miary w `text-success`);
- panel „Wydatki :year” w `ListSurface flush` z meta „Postęp roku 84%”, nagłówkiem w `table-header`, czterema wierszami `.landing-preview-row` (Żywność 18 000,00 / 14 912,40 / 83%, Mieszkanie 30 000,00 / 25 000,00 / 83%, Transport 6 000,00 / 5 520,00 / 92%, Zdrowie 2 400,00 / 2 720,00 / 113% przekroczone). Komórki: `CategoryChip`, kwoty przez `formatMoneyAmount` w `amount`, `ExecutionMeter` z `yearElapsedPercent` 84, a przy Zdrowiu `Badge variant="destructive-soft"` z `t.budget.over_limit_badge`. Stopka „Kwoty w zł”;
- dane w lokalnej stałej typowanej `DashboardCategory` (`@/lib/dashboard-categories`), ikony i kolory kategorii z kluczy `@/lib/category-appearance`;
- bez `Link`, `a` i `button`, bez `min-h-[…]`, `shadow-sm` i `text-success`.

### Success Criteria:

#### Automated Verification:

- `WelcomeHardcodedUiTest` nie przechodził przed edycją widoku i przechodzi po niej (czerwone uruchomienie zapisane w opisie commitu): `php artisan test --compact tests/Unit/WelcomeHardcodedUiTest.php`
- `npm run check:ui` przechodzi z trzema plikami landingu
- `npm test` przechodzi (w tym nowe asercje `ui-contract.test.ts`)
- `npm run build && npm run types:check` przechodzą
- `php artisan test --compact tests/Feature/LandingPageTest.php tests/Unit/LandingPageSourceTest.php` przechodzi
- `composer ci:check` i `vendor/bin/sloppy ci` przechodzą

#### Manual Verification:

- Przy 1280 px strona odpowiada karcie PageWelcome w obu motywach
- Przyciski hero i pasa mają komputowaną wysokość 36 px, a przyciski paska 32 px
- Przy 390 px nie ma poziomego przewijania, a podgląd mieści się bez kolumny „Plan bieżący”
- Po kliknięciu „Funkcje” i „Jak to działa” nagłówek sekcji jest widoczny pod paskiem
- Tab przechodzi przez pasek, hero, pas i stopkę, nie zatrzymuje się w podglądzie, a fokus jest widoczny także na ciemnym pasie

**Implementation Note**: Po fazie zatrzymaj się na ręczne potwierdzenie.

---

## Faza 4: Kitchen sink i bramka wizualna

### Overview

`/dev/ui/welcome` pokazuje stronę w trzech stanach jako pełną stronę. Zrzuty zamykają zmianę.

### Changes Required:

#### 1. Kontroler, trasa i test

**Files**: `app/Http/Controllers/Dev/WelcomeKitchenSinkController.php` (nowy), `routes/web.php`, `tests/Feature/WelcomeKitchenSinkTest.php` (nowy)

**Intent**: Sink tylko w `local`, z prawdziwym `Welcome` i nadpisanymi propsami współdzielonymi.

**Contract**:
- `GET dev/ui/welcome` → `dev.ui.welcome` w grupie `auth`+`verified` obok innych sinków;
- `show(Request)`: `abort_unless(app()->isLocal(), 404)`; `state` z `guest` (domyślny: `auth.user` null, `canRegister` false), `guest-register` (`auth.user` null, `canRegister` true), `authenticated` (prawdziwy zalogowany użytkownik, `canRegister` false); zwraca `Inertia::render('Welcome', <propsy stanu>)`; nieznany `state` → 404. Stany jako stała jak `AuthKitchenSinkController::SCREENS`.

Test:
- gość → przekierowanie na login;
- poza `local` → 404;
- `local` bez `state` → `component('Welcome')`, `auth.user` null, `canRegister` false;
- `state=guest-register` → `canRegister` true;
- `state=authenticated` → `auth.user.id` zalogowanego;
- nieznany stan → 404.

`Welcome` nie dostaje nowych propsów strony, więc `defineProps` się nie zmienia; `auth` i `canRegister` to propsy współdzielone, a test potwierdza ich nadpisanie przez `assertInertia`.

#### 2. Zrzuty i bramka

**Files**: `context/changes/welcome-atrament-ui/screenshots/`. Jednorazowy skrypt Playwright, którego nie commitujesz (wzór z `design-system.md`, „Kitchen sink i zrzuty”).

**Intent**: Wizualny dowód zgodności z kartą.

**Contract**: Pełne zrzuty trzech stanów przy 1280 i 390 px w jasnym i ciemnym motywie, zrzut fokusa na przycisku pasa wezwania i zrzut po skoku do `#features`.

### Success Criteria:

#### Automated Verification:

- `php artisan test --compact tests/Feature/WelcomeKitchenSinkTest.php` przechodzi
- `npm run build && npm run types:check` przechodzą po nowej trasie
- `composer ci:check` i `vendor/bin/sloppy ci` przechodzą
- `vendor/bin/pint --dirty --format agent` bez zmian do zrobienia

#### Manual Verification:

- Zrzuty trzech stanów przy 1280 i 390 px w obu motywach są w `screenshots/` i odpowiadają karcie PageWelcome
- W stanie `guest-register` „Zarejestruj się” jest w pasku i w hero, a w `guest` go nie ma
- Pas wezwania i sidebar podglądu są czytelne w ciemnym motywie

**Implementation Note**: Po fazie zatrzymaj się na ręczne potwierdzenie.

---

## Testing Strategy

### Unit Tests:

- `WelcomeHardcodedUiTest`: zakazane wzorce w trzech plikach i dwa autotesty skanera (czerwony przed Fazą 3.2)
- `ui-contract.test.ts`: `href="/` tylko jako `/register`, brak `text-success`, w podglądzie brak elementów fokusowalnych, `BrandMark` w `Welcome` i w podglądzie
- `UiTranslationKeysTest`: nowe i zmienione klucze `landing`
- `LandingPageSourceTest`: bez zmian, ma przechodzić

### Integration Tests:

- `LandingPageTest`: gość i zalogowany bez zmian
- `WelcomeKitchenSinkTest`: dostęp, trzy stany, 404

### Manual Testing Steps:

1. Otwórz `/` jako gość przy 1280 px i porównaj z kartą PageWelcome w obu motywach.
2. Kliknij „Funkcje” i „Jak to działa”. Nagłówek sekcji ma być widoczny pod paskiem.
3. Przejdź Tabem przez całą stronę. Fokus ma być widoczny wszędzie i nie wchodzić do podglądu.
4. Zwęż okno do 390 px. Wszystko ma być w jednej kolumnie, bez poziomego przewijania.
5. Zaloguj się i wróć na `/`. Wszystkie wezwania mają prowadzić do dashboardu.

## Performance Considerations

Brak: podgląd to statyczna makieta bez żądań.

## Migration Notes

Bez migracji i zmian konfiguracji.

## References

- Research: `context/changes/welcome-atrament-ui/research.md`
- Karta PageWelcome (podgląd, nie źródło wartości): https://claude.ai/artifact/9gJSdtfpFgUAa8oEMgR4HA (`project/components/PageWelcome/preview.html`, klasy `.ht-land*` w `bundle.css`)
- Wzór poprzedniej zmiany: `context/archive/2026-11-07-auth-atrament-ui/plan.md`
- Wzór sinka: `app/Http/Controllers/Dev/AuthKitchenSinkController.php`
- Wzór strażnika: `tests/Unit/DashboardHardcodedUiTest.php`
- Badge przekroczenia: `resources/js/components/CategoryMeterRow.vue:82-83`
- ADR-0037, ADR-0045, ADR-0053, ADR-0056

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Decyzje i dokumenty

#### Automated

- [x] 1.1 ADR-0057 istnieje pod kolejnym wolnym numerem — 0295d9c
- [x] 1.2 Indeks reguł zawiera `js-landing.md` po `php artisan boost:index-rules` — 0295d9c

#### Manual

- [ ] 1.3 ADR-0057 i `design-system.md` zgadzają się z tabelą decyzji w `plan-brief.md`

### Phase 2: Tokeny, klasy i teksty

#### Automated

- [x] 2.1 `npm run build` przechodzi z nowymi tokenami i klasami
- [x] 2.2 `npm run check` przechodzi bez ostrzeżeń
- [x] 2.3 `UiTranslationKeysTest` i `LandingPageTest` przechodzą
- [x] 2.4 W tablicy `landing` nie ma „marketingu”, „passkeys” ani „w MVP”

#### Manual

- [ ] 2.5 Wyniki kontrastu pasa zapisane w ADR-0057 i przechodzą progi

### Phase 3: Strażnik i widoki

#### Automated

- [ ] 3.1 `WelcomeHardcodedUiTest` czerwony przed edycją widoku, zielony po niej
- [ ] 3.2 `npm run check:ui` przechodzi z trzema plikami landingu
- [ ] 3.3 `npm test` przechodzi (w tym nowe asercje `ui-contract.test.ts`)
- [ ] 3.4 `npm run build && npm run types:check` przechodzą
- [ ] 3.5 `LandingPageTest` i `LandingPageSourceTest` przechodzą
- [ ] 3.6 `composer ci:check` i `vendor/bin/sloppy ci` przechodzą

#### Manual

- [ ] 3.7 Przy 1280 px strona odpowiada karcie PageWelcome w obu motywach
- [ ] 3.8 Przyciski hero i pasa mają 36 px, przyciski paska 32 px
- [ ] 3.9 Przy 390 px bez poziomego przewijania, podgląd bez kolumny „Plan bieżący”
- [ ] 3.10 Nagłówki sekcji widoczne pod paskiem po skoku do kotwicy
- [ ] 3.11 Tab omija podgląd, fokus widoczny także na ciemnym pasie

### Phase 4: Kitchen sink i bramka wizualna

#### Automated

- [ ] 4.1 `WelcomeKitchenSinkTest` przechodzi
- [ ] 4.2 `npm run build && npm run types:check` przechodzą po nowej trasie
- [ ] 4.3 `composer ci:check` i `vendor/bin/sloppy ci` przechodzą
- [ ] 4.4 `vendor/bin/pint --dirty --format agent` bez zmian do zrobienia

#### Manual

- [ ] 4.5 Zrzuty trzech stanów przy 1280 i 390 px w obu motywach odpowiadają karcie
- [ ] 4.6 `guest-register` pokazuje „Zarejestruj się” w pasku i hero, `guest` nie
- [ ] 4.7 Pas wezwania i sidebar podglądu czytelne w ciemnym motywie
