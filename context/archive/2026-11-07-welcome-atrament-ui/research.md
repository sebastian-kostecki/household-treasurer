---
date: 2026-11-07T17:10:00+01:00
researcher: Claude Sonnet 5.5 (sesja użytkownika Sebastian Kostecki)
git_commit: 0f37fcd6967c4daf2af666aef81acc1c06cfce22
branch: development
repository: household-treasurer
topic: "Strona powitalna (Welcome.vue, LandingAppPreview.vue, LandingFeatureCard.vue) względem karty PageWelcome Atramentu: zarzuty, klocki do ponownego użycia, bramki"
tags: [research, codebase, ui, welcome, landing, atrament, design-system]
status: complete
last_updated: 2026-11-07
last_updated_by: Claude Sonnet 5.5 (sesja użytkownika Sebastian Kostecki)
---

# Research: Strona powitalna względem karty PageWelcome

**Date**: 2026-11-07T17:10:00+01:00
**Git Commit**: 0f37fcd6967c4daf2af666aef81acc1c06cfce22 (working tree: tylko nieśledzony `context/changes/welcome-atrament-ui/`)
**Branch**: development
**Repository**: household-treasurer

## Research Question

Które elementy `Welcome.vue`, `LandingAppPreview.vue` i `LandingFeatureCard.vue` odbiegają od karty `PageWelcome` (design system Atrament, https://claude.ai/artifact/9gJSdtfpFgUAa8oEMgR4HA) i od kontraktu Atramentu (tokeny, współdzielone komponenty, architektura)? Co z klas `.ht-land*` ma odpowiednik w repo, czego brakuje, które klocki Dashboardu nadają się na podgląd aplikacji dla gościa, jakie bramki trzeba rozszerzyć i jakie decyzje musi zapisać ADR-0057 przed planem?

## Summary

- Skan literałów (`scripts/check-ui-literals.mjs`) daje na tych trzech plikach dokładnie jedno trafienie: `LandingAppPreview.vue:40` (`min-h-[22rem]`). Zarzuty nie leżą w literałach, tylko we wzorcach sprzed Atramentu, których ten skan nie widzi: `rounded-2xl`, `border-border/60`, `max-w-6xl`, `size="lg"`, `tracking-tight`, rozmiary Tailwinda zamiast nazwanych stylów.
- Tokenów kolorów nie brakuje: wszystkie `--sidebar-*`, `brand`, `accent` istnieją. Brakuje trzech wymiarów (szerokość treści 1120 px, wysokość przycisku hero 36 px, wysokość paska 56 px) oraz klas `.landing-*`. Style `display`, `heading`, `lead`, `card-title` już są w `app.css` i czekają na konsumenta.
- Podgląd aplikacji da się złożyć z gotowych, bezpiecznych dla gościa klocków: `StatPanel`, `ExecutionMeter`, `CategoryChip`, `ListSurface`, `Badge`, `BrandMark`. `CategoryMeterRow`, `DashboardStatPanel`, `AppSidebar` i `ui/sidebar` nie nadają się wprost (Link, Inertia `Form`, `SidebarProvider`).
- Cztery ryzyka wymagają decyzji w ADR-0057 albo sprawdzenia w przeglądarce: zdanie z ADR-0053 o „decorative tile” w podglądzie, wysokość 36 px a kolejność warstw CSS, link `/register` bez trasy Wayfindera przy wyłączonej rejestracji oraz różnice tekstów karty względem `lang/pl/ui.php` większe niż trzy poprawki z README karty.
- Dowody pochodzą z czytania kodu i trzech równoległych przeglądów tylko do odczytu; nie uruchamiałem builda, testów ani przeglądarki.

## Detailed Findings

### Skan wartości i wzorce sprzed Atramentu (widok → źródło)

- Skan z `CLAUDE.local.md`/`10x-ui` na trzech plikach: 1 trafienie, `LandingAppPreview.vue:40` (`min-h-[22rem]`). Ten sam wynik daje `node scripts/check-ui-literals.mjs <3 pliki>`, exit 1 (uruchomione w tej sesji). Hexów i klas palety w tych plikach nie ma.
- `check-ui-literals.mjs` flaguje hex, funkcje kolorów, wartości arbitralne `-[Npx|Nrem]`, `/[.N]`, klasy palety i `dark:`; nie flaguje `rounded-xl`/`rounded-2xl`, `border-border/60`, `shadow-sm`, `tracking-tight`, `max-w-6xl`, `size="lg"` (czytany kod skryptu, wiersze 137-147 wg przeglądu). Te wzorce łapią tylko testy PHP typu `*HardcodedUiTest`.
- Wzorce sprzed Atramentu w `Welcome.vue` (ta sprawdzona wersja pliku): `max-w-6xl` w wierszach 103, 148, 214, 239, 276, 314; `border-border/60` w 100, 212, 238, 255, 275, 312; `rounded-2xl` w 255 i 278; `rounded-xl` w 152 i 258; `size="lg"` w 167, 173, 181, 295, 302; `size="sm"` w 126, 132, 137; `tracking-tight` w 157, 217, 242, 282.

### Układ, typografia i panele (widok → źródło)

- Szerokość treści: `max-w-6xl` to 72 rem (1152 px), karta ma 1120 px (`.ht-land__wrap`, `bundle.css:372`). W repo nie ma tokenu na tę szerokość; istniejące tokeny wymiarów (`--auth-aside-width`, `--form-panel-width` i inne) stoją tylko w bloku `:root` (`app.css:156-174`), bez wariantu `.dark`, i są czytane jako `max-w-(--token)`.
- Typografia: h1 hero to `text-3xl … lg:text-5xl` (`Welcome.vue:157-158`), czyli 48 px od `lg`, a karta ma `display` 36/40 z `letter-spacing: -0.025em` (na wąskim ekranie 30/36). `display` w repo (`app.css:259-261`, `text-4xl/10 font-semibold`) nie ma tego odstępu między literami, więc klasa landingu musi go dodać. h2 sekcji to `text-2xl … sm:text-3xl` (217, 242) wobec `heading` 24/30 (`app.css:263-265`); lead hero to `text-base sm:text-lg leading-relaxed` (162) wobec `lead` 15/22 (`app.css:267-269`); tytuł karty funkcji to `CardTitle class="text-base"` (`LandingFeatureCard.vue:26`) wobec `card-title` 14/20 (`app.css:271-273`).
- Panele: kroki „Jak zacząć” to cztery osobne `li` z `rounded-2xl border-border/60 p-5` (`Welcome.vue:255`); karta ma jeden panel z czterema komórkami. Najbliższy istniejący wzorzec to `.stat-panel` / `.stat-panel-metric` (`app.css:1646-1696`, od `md` rząd z `border-l`), ale `StatPanel.vue` wymaga `metrics` z etykietą i wartością, więc dla kroków trzeba albo klasy `.landing-steps` na tych samych tokenach, albo użyć samych klas `.stat-panel*` w innym markupie (decyzja do planu).
- `LandingFeatureCard.vue`: `Card` z `ui/card` ma `rounded-lg border` (`ui/card/Card.vue:15`), czyli promień już jest zgodny z ADR-0037; odstępstwa to kafelek ikony `rounded-xl` 40 px (`:21`) wobec karty: 32 px na `radius-md` w `accent`, oraz `text-base` w tytule (`:26`). Korekta wstępnego audytu: `Card` nie ma `rounded-xl`.
- Plakietka hero (`Welcome.vue:152`) to ręcznie zbudowany `<p>` z `bg-accent … rounded-xl`; `Badge` ma wariant `ok` (`ui/badge/index.ts:23`, `bg-accent text-accent-foreground`), którego karta używa (`ht-badge--ok`). Punkty hero mają kropkę `bg-primary` (`:199`), karta ikonę Check w `brand-text`.
- Nagłówek: `bg-background/80 backdrop-blur` z `border-border/60` (`:100`); karta daje pasek na `card` z pełną ramką u dołu, wysokość 56 px (`bundle.css:373-374`), czyli `h-14` jest już równe 56 px, a brakuje tylko braku przezroczystości. Przyciski w pasku są `size="sm"` (26 px, `ui/button/index.ts` rozmiar `sm`); karta nie zmienia wysokości przycisków w pasku (`.ht-land__cta .ht-btn` ustawia 36 px tylko w hero i w pasie wezwania, `bundle.css:384,411`), więc domyślny przycisk to 32 px (`h-control`).
- Stopka: `border-border/60` na tle strony (`:312`), karta: `card` z pełną ramką (`bundle.css:412`).

### Pas wezwania i przyciski 36 px

- Pas wezwania: `bg-accent … rounded-2xl` (`Welcome.vue:278`) → karta: `sidebar-background` z `color: sidebar-strong`, `radius-lg`, padding 28/32, h2 18/24, tekst `sidebar-foreground`, focus-visible `sidebar-ring` (`bundle.css:407-411`). Wszystkie te tokeny istnieją (`app.css:176-184` jasny, `:229-237` ciemny; panel jest ciemny granatowy w obu motywach). Precedens pasa na tokenach sidebaru bez providera: `.auth-aside` (`app.css:365-377`).
- Przycisk `variant="brand"` istnieje (`ui/button/index.ts:20-21`). Kontrast zmierzony w tej sesji (WCAG, skrypt jednorazowy): `sidebar-foreground` na `sidebar-background` 7,50:1 (jasny) i 6,86:1 (ciemny); `sidebar-strong` na `sidebar-background` 14,69:1 i 16,48:1. Pary `brand-foreground` na `brand` i `brand` jako grafika na ciemnym panelu nie były liczone (`brand` #5fd4c4, `brand-foreground` #062a26 w obu motywach, `app.css:124-125,196-197`).
- Wysokość 36 px: w `ui/button` nie ma takiego rozmiaru (`default` h-control 32 px, `sm` 26 px, `lg` `h-10` 40 px). Precedens obejścia: `.auth-submit` (`app.css:455-457`, `@apply h-control-field w-full justify-center`), ADR-0056 odrzuca wariant i rozmiar w `ui/button`. **Ryzyko niezweryfikowane w przeglądarce:** `.auth-submit` stoi w `@layer components` (`app.css:352`), a wysokość `h-control` z rozmiaru `default` to `@utility` (warstwa `utilities`, `app.css:322-324`); komentarz w `app.css:1485` mówi wprost, że „klasa z `@layer components` nie nadpisze utility z wariantem”. Czy `.auth-submit` faktycznie daje 34 px, działa tu przypuszczalnie dzięki `--control-h` wewnątrz `.form-field`, ale tego nie sprawdzałem; dla `.landing-cta` taka klasa w `components` może przegrać z `h-control`. Dwie sprawdzone drogi z kodu: `size="lg"` (`h-10`) z klasą `h-9`, gdzie `cn`/tailwind-merge zastępuje `h-10` (`ui/button/Button.vue:27`), albo własny `@utility` w warstwie `utilities`. Wybór i sprawdzenie renderu przypada fazie tokenów.

### Podgląd aplikacji (LandingAppPreview)

- Stan: szkic z pięciu kropek zamiast sidebaru, dwie miary w `text-success` (`LandingAppPreview.vue:80,92`), `rounded-2xl` w wierszach 38, 74, 86, 100, `shadow-sm` (38), `min-h-[22rem]` (40). `success` oznacza przychód (`.ai/rules/js.md`, reguła kolorów), a podgląd pokazuje „Płynność” i „Łączne środki”, które nie są przychodem. Użyty raz, `Welcome.vue:206`.
- Karta pokazuje: sidebar z markiem i sześcioma pozycjami (Dashboard aktywny), tytuł z `Badge brand` „Realizacja”, panel z trzema miarami i panel „Wydatki 2026” z czterema wierszami (Żywność 83%, Mieszkanie 83%, Transport 92%, Zdrowie 113% przekroczone), kreskę postępu roku 84%, stopkę „Kwoty w zł”, całość `aria-hidden`.
- Klocki bezpieczne dla gościa (przegląd importów i propsów): `StatPanel.vue` (props `metrics`, brak routera i Inertii), `ExecutionMeter.vue` (czysty; znacznik postępu roku wymaga `yearElapsedPercent`; tor ma stałe `w-14` w `.category-meter-cell-meter`, `app.css:1703`), `CategoryChip.vue`, `ListSurface.vue` (z `flush`, bez paginatora), `Badge`, `BrandMark.vue` (26 px bez propsu rozmiaru), `Card`, `formatMoney`/`formatMoneyAmount` (`@/lib/format`).
- Nie nadają się wprost: `CategoryMeterRow.vue` (korzeń to Inertia `Link` z trasą Wayfindera `operations()`, więc byłby fokusowalnym `<a>` w regionie `aria-hidden`; kopiowalny jest tylko markup komórek `.category-meter-cell-*`), `DashboardStatPanel.vue` (importuje `BudgetController.selectYear` i Inertia `Form`, renderuje POST „Plan miesiąca →”), `AppSidebar.vue` (wymaga zalogowanej powłoki), `ui/sidebar/Sidebar.vue` i `SidebarMenuButton.vue` (wywołują `useSidebar()` w `Sidebar.vue:20` i `SidebarMenuButton.vue:21`; `createContext` w `utils.ts:11`). `SidebarProvider` dokłada nasłuch klawiszy i cookie, więc nie jest narzędziem do ilustracji. Do sidebaru wystarczą tokeny `bg-sidebar`, `text-sidebar-foreground`, `text-sidebar-strong`, `bg-sidebar-primary`, `border-sidebar-border` oraz wyeksportowany `sidebarMenuButtonVariants` (`ui/sidebar/index.ts:38`) na zwykłych elementach.
- Tabela: `.category-meter-table` jest kontenerem (`app.css:1439`), pięć kolumn dopiero od `@container (min-width: 38rem)` (`:1443`), poniżej wiersz zwija się do kategorii, „Pozostało” i miernika. Karta nie ma kolumny „Pozostało”, a siatka `.category-meter-row` ma pięć kolumn na sztywno. Kolumny `StatPanel` przełączają się po oknie (`md`), tabela po własnej szerokości, więc w wąskiej ramce podglądu oba mogą się rozjechać. Skala „małej makiety” nie istnieje w repo: rozmiary `BrandMark`, `kpi`, `.category-meter-*` są stałe, więc albo podgląd dostaje pełne klocki w węższej kolumnie, albo nowa nazwana klasa skalująca (wartości arbitralne są zakazane).
- Fixtures: `pages/dev/dashboard-fixtures.ts` eksportuje tylko `dashboardScenarios`; import z produkcyjnej strony wciągnąłby kod dev, więc dla podglądu lepsza jest mała tablica lokalna typowana `DashboardCategory` (`@/lib/dashboard-categories.ts:3-27`).
- Teksty podglądu: istniejące klucze `dashboard.month_to_spend` („Do wydania · :month”, `lang/pl/ui.php:182`), `dashboard.spendable_liquidity` („Płynność dysponowalna”, `:164`), `dashboard.total_funds` („Łączne środki”, `:165`), `budget.phase_execution` („Realizacja”, `:218`), `budget.over_limit_badge` („przekroczono”, `:250`), `budget.amounts_in_zl` („Kwoty w zł”, `:248`). Karta ma krótsze etykiety („Do wydania”, „Płynność”) i kategorię „Zdrowie”/„Mieszkanie”, których klucze `landing.preview_*` nie zawierają (tam: `preview_year`, `preview_category_food/transport/salary`, `lang/pl/ui.php:825-828`).
- Dostępność: ADR-0045 wymaga słowa „przekroczono” obok procentu przekroczonej kategorii („kolor sam nie niesie statusu”), a karta pokazuje przekroczenie samym 113% i czerwonym paskiem; do rozstrzygnięcia, czy podgląd dostaje `Badge destructive-soft`.

### Zarzut architektoniczny: link rejestracji

- `Welcome.vue:138` i `:184` mają `<Link href="/register">`. Rejestracja jest wyłączona (`config/fortify.php:163-174` nie zawiera `Features::registration()`), `canRegister` jest więc `false` w aplikacji, a `resources/js/routes/index.ts` nie eksportuje `register` (grep w tej sesji: brak). Link jest martwy do czasu włączenia funkcji. Reguły `js-auth.md` i test auth (`ui-contract.test.ts`, blok z 9e015f1) zakazują `href="/` na ekranach wejścia; `Welcome` nie jest tym testem objęty. Karta rysuje „Zarejestruj się” w pasku i w hero.

### Kotwice pod przyklejonym paskiem

- Nawigacja wskazuje `#features` i `#how` (`Welcome.vue:112,118`), pasek jest `sticky top-0 h-14` (`:100,103`), a w pliku nie ma `scroll-mt-*` ani `scroll-margin` (grep: brak trafień). Po skoku nagłówek sekcji może schować się pod paskiem; w przeglądarce niesprawdzone.

### Teksty (`lang/pl/ui.php`, tablica `landing`, wiersze 785-829)

- Zmiany z polecenia: `features_lead` (`:799`) bez „— bez zbędnego marketingu”; `feature_privacy_description` (`:811`) z „klucze dostępu” zamiast „passkeys” i bez „w MVP”. Słowo „marketing” w całym pliku występuje tylko w `:799`.
- Karta zmienia więcej, niż mówi jej README (trzy poprawki): w `hero_lead`, `feature_liquidity_description`, `feature_month_description` i `feature_privacy_description` pauza „—” zastąpiona przecinkiem (porównanie `preview.html` z `lang/pl/ui.php:794,807,809,811`). Czy zmieniać także te trzy pozostałe pauzy, to decyzja do planu. `LandingPageTest` sprawdza tylko istnienie sześciu kluczy (`has`) plus `cta_dashboard`, nie ich treść; usuwać ani przemianowywać tych kluczy nie wolno bez zmiany testu.
- Etykiety nad h2 w karcie („Funkcje”, „Jak to działa” w `label-caps`) odpowiadają istniejącym `nav_features` i `nav_how`.

### Bramki, testy, kitchen sink (źródło → widoki)

- `npm run check:ui` wchodzi do `composer ci:check` (`composer.json` 67-73; `.github/workflows/tests.yml:102`). `Welcome.vue` jest na liście (`check-ui-literals.mjs:41`), `LandingAppPreview.vue` i `LandingFeatureCard.vue` nie; dodanie ich przed poprawką `:40` zaczerwieni `check:ui`.
- `ui-contract.test.ts:322-328` wymaga słowa `BrandMark` w `pages/Welcome.vue`; globalny skan blokuje `AppLogoIcon` i `Vault`. Wzorzec do skopiowania: blok z auth (zakazy `tabindex`, `text-success`, `href="/`, `'/dashboard'`), tu rozszerzony o `rounded-2xl`, `border-border/60`, `max-w-6xl`.
- `tests/Unit/LandingPageSourceTest.php` czyta `Welcome.vue` jako tekst i sprawdza tylko brak `rsms.me/inter` i `#f53003`. Model strażnika: `tests/Unit/DashboardHardcodedUiTest.php` (lista plików + mapa zakazanych wzorców + dwa autotesty skanera); wzorce muszą dopuszczać `display`/`heading`/`lead`/`card-title` i zależnie od wyboru `tracking-tight`.
- Kitchen sink: `/dev/ui/auth` to wzorzec (`routes/web.php:25-45`, kontroler z `abort_unless(app()->isLocal(), 404)`, `AuthKitchenSink.vue`, `tests/Feature/AuthKitchenSinkTest.php`). `Welcome` to `Route::inertia('/', 'Welcome')` bez propsów (`routes/web.php:25`), layout `null` (`app.ts:14`); propsy współdzielone (`auth`, `canRegister`) scala `HandleInertiaRequests` płytko, a propsy strony wygrywają, więc sink może pokazać gościa i `canRegister` true/false przez `Inertia::render('Welcome', [...])`. Stan „zalogowany” nie wymaga nadpisania. Nie ma e2e ani zrzutów dla landingu (`tests/e2e` ma jedną specyfikację, zalogowaną).
- Dokumentacja do aktualizacji przy nowych klasach i wymiarach: `.ai/rules/css.md:14` (lista tokenów wymiarów), `context/foundation/design-system.md` wiersze 3 (indeks ADR), 22 (tokeny wymiarów), 29-43 (tabela typografii, wiersze `display`, `heading`, `lead`, `card-title`), 91 („Gotowe elementy”), 126-134 (tabela tokenów sidebaru; pas wezwania je czyta), 131 (zdanie o `LandingAppPreview.vue`), 152 (lista kitchen sinków).

## Code References

- `resources/js/pages/Welcome.vue:100-103,148,214,239,276,314` — pasek i sekcje na `max-w-6xl` i `border-border/60`
- `resources/js/pages/Welcome.vue:138,184` — `href="/register"` bez Wayfindera
- `resources/js/pages/Welcome.vue:157-158,217,242,282` — rozmiary Tailwinda zamiast `display`/`heading`
- `resources/js/pages/Welcome.vue:255,278` — `rounded-2xl`; pas wezwania na `bg-accent`
- `resources/js/pages/Welcome.vue:167-181,295-302` — `size="lg"` (40 px)
- `resources/js/components/LandingAppPreview.vue:40,80,92` — `min-h-[22rem]`, `text-success`
- `resources/js/components/LandingFeatureCard.vue:21,26` — kafelek `rounded-xl`, `text-base`
- `resources/css/app.css:259-273` — `display`, `heading`, `lead`, `card-title` (bez konsumenta)
- `resources/css/app.css:352,365-377,455-457` — warstwa `components`, `.auth-aside`, `.auth-submit`
- `resources/css/app.css:1439-1455,1646-1710` — `.category-meter-table`, `.stat-panel*`, `.execution-meter*`
- `resources/js/components/ui/button/index.ts:20-21,33-40` — wariant `brand`, rozmiary
- `resources/js/components/ui/sidebar/Sidebar.vue:20`, `SidebarMenuButton.vue:21` — `useSidebar()` wymaga providera
- `scripts/check-ui-literals.mjs:41` — `Welcome.vue` na liście
- `resources/js/ui-contract.test.ts:322-328` — wymóg `BrandMark`
- `lang/pl/ui.php:785-829` — tablica `landing`
- `context/foundation/adr/0053-sidebar-follows-sidebar-component.md:15` — „`LandingAppPreview.vue` keeps its decorative tile”

## Architecture Insights

- Repo mapuje klasy `ht-*` z artefaktu na nazwy bez prefiksu (`.auth-*`, `.sidebar-*`) i opisuje każde odstępstwo w ADR (ADR-0056 ma tabelę mapowania). Dla landingu analogiczne byłyby `.landing-*` wstawione po bloku `.auth-*` (`app.css:487`) z komentarzem ADR-0057.
- W repo nie ma tokenów `--space-*`; karta używa ich jako zmiennych (`bundle.css`), a repo mapuje je na skalę Tailwinda (`px-4`, `px-5`, `gap-5`).
- Dark mode nie wymaga pracy w widoku: wszystkie wartości landingu idą przez tokeny, a pas wezwania jest granatowy w obu motywach.
- Mobile: media query karty to `@media (max-width: 860px)` (`bundle.css:416`: bez nawigacji w pasku, hero i siatka w jedną kolumnę, podgląd bez sidebaru, kroki jako kolumna z `border-top`). ADR-0056 odrzucił 860 px dla auth na rzecz `lg`; tu `md` (768 px) i `lg` (1024 px) trzeba wybrać po sprawdzeniu, jak 1120 px i podgląd zachowują się w drodze do 860 px.

## Historical Context (from prior changes)

- `context/archive/2026-11-07-auth-atrament-ui/` (change, research, plan) — ten sam tryb pracy: ADR-0056 najpierw, potem tokeny i klasy, widoki, kitchen sink z bramką wizualną; fazy 1-5 i commity `type(change-id): opis po polsku (pN)`.
- ADR-0056 (`adr/0056-auth-follows-page-login.md`, akapit „Text styles”) — zostawia `display` i `card-title` bez konsumenta „do zmiany, która przeniesie `Welcome`”; ta zmiana to domyka.
- ADR-0053 (`adr/0053-sidebar-follows-sidebar-component.md:15`) — „`LandingAppPreview.vue` keeps its decorative tile, because it is an illustration, not the product mark”. Przebudowa podglądu z klas Dashboardu zawęża to zdanie (sidebar w podglądzie ma pokazywać `BrandMark`), więc ADR-0057 powinien je wprost zawęzić. `design-system.md:131` mówi, że `LandingAppPreview.vue` czyta `sidebar-primary`; zdanie przestaje być prawdziwe, jeśli podgląd użyje `sidebarMenuButtonVariants`, albo pozostaje, jeśli użyje tokenu bezpośrednio.
- ADR-0045 i ADR-0050 — kształt Dashboardu (trzy miary, tabela Wydatki, stopka „Kwoty w zł”, tabela przełącza się po szerokości kontenera 38 rem), na którym ma się wzorować podgląd.
- `change.md` (ta zmiana) już zapisuje pięć decyzji do ADR-0057 i dwie zmiany tekstów; research niczego z nich nie podważa.

## Related Research

- `context/archive/2026-11-07-auth-atrament-ui/research.md` (sekcja `## Charges`) — wzorzec formatu zarzutów; decyzja Q2 o martwym `href="/register"` dotyczy tej samej tematyki, ale `Welcome` nie była jej zakresem.

## Charges

Format: plik:linia, wpływ na użytkownika, kategoria, propozycja fazy (dla `/10x-plan`: środowisko → tokeny → widok → stany).

**C1. Szerokość treści 1152 px zamiast 1120 px, bez tokenu** — `Welcome.vue:103,148,214,239,276,314` (`max-w-6xl`). Wpływ: układ jest o 32 px szerszy niż wzorzec, a każda sekcja powtarza tę samą klasę; zmiana szerokości wymaga sześciu edycji. Kategoria: brakujący token. → faza tokenów (`--landing-width` w `:root`, klasa `.landing-wrap`).

**C2. Typografia z rozmiarów Tailwinda, nie z nazwanych stylów** — `Welcome.vue:157-158` (h1 do 48 px od `lg`, `display` ma 36), `:217,242,282` (h2), `:162` (lead), `LandingFeatureCard.vue:26` (tytuł karty). Wpływ: strona powitalna jest pierwszym, co widzi nowy użytkownik, a jej nagłówki nie pasują skalą do reszty aplikacji i obchodzą style, które czekają na nią w `app.css`. Kategoria: brakujące tokeny/style. → faza tokenów (klasy landingu na `display`+odstęp liter, `heading`, `lead`, `card-title`) i faza widoku.

**C3. Panele z `rounded-2xl` i półprzezroczystym obramowaniem** — `Welcome.vue:255,278,100,212,238,275,312` (`border-border/60`), `LandingAppPreview.vue:38,74,86,100`. Wpływ: promień 16 px i obramowanie w 60% kryją krawędzie paneli na jasnym tle; strona wygląda jak inny produkt niż zalogowana aplikacja (ADR-0037: `radius-lg`, pełna ramka). Kategoria: brakujące tokeny. → faza widoku.

**C4. Przyciski hero i pasa 40 px zamiast 36 px; pasek 26 px** — `Welcome.vue:167-181,295-302` (`size="lg"`), `:126-137` (`size="sm"`). Wpływ: główne wezwanie do działania ma nietypową wysokość 40 px, a przyciski w pasku są 26 px (mniejsze niż wszędzie w aplikacji, 32 px). Kategoria: brakujący token/rozmiar (decyzja ADR-0057: 36 px bez edycji `ui/button`). → faza tokenów; wymaga sprawdzenia kolejności warstw (patrz Ryzyko 2).

**C5. Pas wezwania na `accent`, nie na ciemnym panelu** — `Welcome.vue:278` (`bg-accent … rounded-2xl`), `:292-306` (przyciski `size="lg"` na tle `accent`). Wpływ: wezwanie jest tej samej barwy co plakietki i ikony, więc nie wyróżnia się jako zamknięcie strony; wzorzec to ciemny panel z przyciskiem `brand`. Kategoria: brakujący współdzielony komponent/klasa (`.landing-cta` na tokenach sidebaru + `Button variant="brand"`). → faza widoku.

**C6. Podgląd aplikacji to szkic z kropek, z miarami w `text-success`** — `LandingAppPreview.vue:41-55` (kropki zamiast sidebaru), `:80,92` (`text-success`), `:40` (`min-h-[22rem]`, jedyny literał skanu), `:38` (`shadow-sm`). Wpływ: czytelnik widzi „Płynność” i „Łączne środki” w zieleni przychodu i bierze makietę za dane; podgląd nie przypomina Dashboardu, który obiecuje. Kategoria: brakujący współdzielony komponent. → faza widoku (z klas Dashboardu: `StatPanel`, `ExecutionMeter`, `CategoryChip`, `ListSurface`, `Badge`, sidebar z tokenów) oraz zawężenie zdania z ADR-0053.

**C7. Ręczne klocki zamiast komponentów** — `Welcome.vue:152` (plakietka z `bg-accent rounded-xl` zamiast `Badge variant="ok"`), `:199` (kropka `bg-primary` zamiast ikony Check w `brand-text`), `:255-260` (cztery panele zamiast jednego z komórkami), `LandingFeatureCard.vue:21` (kafelek ikony 40 px `rounded-xl` zamiast 32 px `radius-md` na `accent`). Wpływ: drobne rozjazdy kształtu i rozmiaru powtarzają się w kilku sekcjach. Kategoria: brakujący współdzielony komponent. → faza widoku.

**C8. Link rejestracji bez Wayfindera i bez trasy** — `Welcome.vue:138,184`. Wpływ: za `canRegister` (dziś zawsze `false`, rejestracja wyłączona), więc nikt go nie widzi; po włączeniu funkcji link trafi tam, gdzie nie ma trasy Wayfindera, a zasady wejścia zakazują `href="/`. Kategoria: przypadkowa architektura. → faza widoku (decyzja Q1).

**C9. Kotwice `#features` i `#how` pod przyklejonym paskiem bez `scroll-margin`** — `Welcome.vue:112,118,100,211,238`. Wpływ: po kliknięciu „Funkcje” nagłówek sekcji może wylądować pod 56-pikselowym paskiem; do potwierdzenia w przeglądarce. Kategoria: przypadkowa architektura (dostępność nawigacji). → faza stanów (sprawdzić w kitchen sinku).

**C10. Brak kitchen sinka i zabezpieczeń dla dwóch z trzech plików** — `scripts/check-ui-literals.mjs:41` (tylko `Welcome.vue`), `ui-contract.test.ts:322-328` (tylko `BrandMark`), brak `/dev/ui/welcome`, brak testu na wzorce sprzed Atramentu. Wpływ: kolejna zmiana wróci do `rounded-2xl` i `text-success` bez żadnego czerwonego testu; stany (gość z `canRegister` true/false, zalogowany) nie mają miejsca do przeglądu. Kategoria: przypadkowa architektura (brak bramki). → fazy guardu i kitchen sinka.

**C11. Teksty** — `lang/pl/ui.php:799,811` (zmiany z polecenia) i potencjalne pauzy zastąpione przecinkiem w `:794,807,809,811`. Wpływ: „passkeys” i „MVP” to żargon dla gościa; pauzy mają wpływ tylko stylistyczny. Kategoria: treść (poza trzema kategoriami skanu). → faza widoku.

Zarzuty C1-C11 mają przypisaną fazę; żaden nie jest odroczony.

## Open Questions

1. **Link „Zarejestruj się” (C8).** Zostawić go za `canRegister` z literalnym adresem i wyjątkiem w teście, usunąć z `Welcome` do czasu włączenia rejestracji, czy dodać trasę? Karta go rysuje. Decyzja produktowa, nie techniczna; do ADR-0057 albo planu.
2. **Wysokość 36 px a warstwy CSS.** Czy `.landing-cta` w `@layer components` nadpisze `h-control` z rozmiaru `default`? Niezweryfikowane w renderze. Warianty z kodu: `size="lg"` + `h-9` (tailwind-merge) albo `@utility`. Sprawdzić w fazie tokenów zrzutem lub testem komputowanej wysokości.
3. **Zakres tekstów.** Czy zmieniać także pauzy w `hero_lead` i trzech opisach funkcji, które karta zamienia na przecinki, czy tylko dwie zmiany z polecenia?
4. **Podgląd.** Czy ma pokazywać kolumnę „Pozostało” (siatka `.category-meter-row` ma ją na sztywno) i `Badge przekroczono` przy 113%? Czy skala makiety to węższa kolumna z pełnymi klockami, czy nowa klasa skalująca? Czy sidebar w podglądzie pokazuje nazwę produktu przy `BrandMark` (ADR-0053: „zawsze z nazwą”)?
5. **Próg układu.** 860 px karty, `md` czy `lg`? Do sprawdzenia przy 1120 px treści i podglądzie.
6. **Kontrast** `brand-foreground` na `brand` i `brand` jako grafika na ciemnym panelu nie był liczony; wyniki dla `sidebar-foreground` i `sidebar-strong` wyżej.
7. **Kroki jako jeden panel.** Użyć `.stat-panel*` w innym markupie czy własnej klasy `.landing-steps`? Wpływa na liczbę nowych nazwanych klas.
8. Niesprawdzone w tej sesji: `ADR-0003` i `ADR-0037` w pełnym brzmieniu (czytane przez cytaty w `design-system.md:14`), pełne `UiTranslationKeysTest.php`, `lang/pl.json`, to czy blok `@layer components` kończy się na końcu `app.css`.
