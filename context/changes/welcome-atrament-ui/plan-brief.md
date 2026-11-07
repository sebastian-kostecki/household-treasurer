# Strona powitalna według Atramentu — Plan Brief

> Full plan: `context/changes/welcome-atrament-ui/plan.md`
> Research: `context/changes/welcome-atrament-ui/research.md`

## What & Why

Strona przed logowaniem jest pierwszym, co widzi gość, a wygląda jak inny produkt niż zalogowana aplikacja. Ma szerszą treść, nagłówki z rozmiarów Tailwinda, panele `rounded-2xl` z półprzezroczystą ramką, pas wezwania na `accent` i podgląd aplikacji w postaci szkicu z kropek z miarami w zieleni przychodu. Zmiana przenosi `Welcome.vue`, `LandingAppPreview.vue` i `LandingFeatureCard.vue` na kartę PageWelcome z Atramentu i zamyka je bramkami.

## Starting Point

Tokeny kolorów i style `display`, `heading`, `lead` oraz `card-title` już są w `app.css`, a ten ostatni zestaw czeka na tę zmianę (ADR-0056). Brakuje wymiarów (1120 px, 36 px), klas `.landing-*`, strażnika wzorców i kitchen sinka. Na liście `check:ui` jest tylko `Welcome.vue`.

## Desired End State

`/` przy 1280 px odpowiada karcie PageWelcome w obu motywach, a przy 390 px mieści się w jednej kolumnie bez przewijania w bok. Podgląd aplikacji wygląda jak Dashboard: sidebar, trzy miary, tabela „Wydatki” z Badge „przekroczono”. Pas wezwania jest granatowy z przyciskiem `brand`. Teksty dla gościa nie mają żargonu. `WelcomeHardcodedUiTest`, `check:ui` i `ui-contract` pilnują trzech plików, a `/dev/ui/welcome` pokazuje trzy stany.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Link „Zarejestruj się” | Zostaje za `canRegister` z literalnym `href="/register"`, jedyny wyjątek w teście | Zgodne z kartą; po włączeniu rejestracji link działa od razu, a literał zastąpi trasa Wayfindera | Plan |
| Zakres tekstów | Pięć kluczy w brzmieniu karty (dwa z polecenia + trzy pauzy na przecinki) | Strona i karta mówią to samo | Plan |
| Przekroczenie w podglądzie | Badge `destructive-soft` „przekroczono” obok 113% | ADR-0045: kolor sam nie niesie statusu; podgląd uczy kodu produktu | Plan |
| Tabela podglądu | 4 kolumny w nowej `.landing-preview-row`, bez „Pozostało” | Mieści się w kolumnie hero bez przełączania 38rem i odpowiada karcie | Plan |
| Przyciski 36 px | Token `--control-height-lg` + `@utility h-control-lg`, sprawdzony w renderze; zapasowo `size="lg"` + `h-9` | Klasa z `@layer components` przegrywa z utility `h-control` | Research / Plan |
| Pas wezwania | `sidebar-background` + `Button variant="brand"`, `radius-lg` | Wzorzec karty, tokeny już są (precedens `.auth-aside`) | change.md |
| Podgląd | Klocki Dashboardu (`StatPanel`, `ExecutionMeter`, `CategoryChip`, `ListSurface`, `Badge`), sidebar z tokenów, `BrandMark` bez nazwy, `aria-hidden` bez elementów fokusowalnych | `CategoryMeterRow` i `ui/sidebar` wymagają routera lub providera | Research / Plan |
| Kroki | Jeden panel `.landing-steps` z czterema komórkami | `.stat-panel` to miary, nie kroki | Plan |
| Progi | Nawigacja od `md`, hero w dwóch kolumnach od `lg`, bez 860 px | Jak dziś i jak w ADR-0056 | Plan |
| `display` | Odstęp liter −0,025em w samym stylu | Styl nie ma innego konsumenta | Research |
| Kotwice | `scroll-margin-top: var(--landing-bar-height)` w `.landing-section` | Nagłówek sekcji nie chowa się pod paskiem 56 px | Research |
| Sink | `/dev/ui/welcome?state=guest\|guest-register\|authenticated` renderuje prawdziwy `Welcome` | Propsy współdzielone nadpisane płytko, `Welcome` bez nowych propsów | Research / Plan |
| ADR | ADR-0057 przed kodem, zawęża zdanie ADR-0053 o podglądzie i ADR-0056 o `href` | Reguła AGENTS.md | change.md |

## Scope

**In scope:**
- ADR-0057, `design-system.md`, `css.md`, nowa reguła `js-landing.md`
- tokeny `--landing-width`, `--landing-bar-height`, `--control-height-lg`, utility `h-control-lg`, klasy `.landing-*`
- trzy pliki widoku i pięć zmienionych kluczy `landing` oraz nowe klucze podglądu
- `WelcomeHardcodedUiTest`, `check:ui`, `ui-contract.test.ts`
- `/dev/ui/welcome` z testem i zrzuty 1280/390 w obu motywach

**Out of scope:**
- włączanie rejestracji i trasa `register`
- edycja `components/ui/*`
- kolumna „Pozostało” i zmiany `.category-meter-row` Dashboardu
- próg 860 px, prefiks `ht-`, e2e landingu

## Architecture / Approach

Kolejność jak w `auth-atrament-ui`: decyzje → wartości → widok → sink. Wartości z karty trafiają do `app.css` jako tokeny `:root` i klasy w `@layer components`; widok składa się z tych klas i istniejących komponentów współdzielonych. Strażnik powstaje przed edycją widoku i ma najpierw nie przechodzić.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Decyzje i dokumenty | ADR-0057, design system, reguła `js-landing.md` | Rozjazd ADR z tabelą decyzji |
| 2. Tokeny, klasy i teksty | Wymiary, `h-control-lg`, `.landing-*`, teksty, pomiar kontrastu pasa | Para `brand` na granatowym panelu poniżej progu |
| 3. Strażnik i widoki | Strażnik czerwony → zielony, trzy przepisane pliki | 36 px przegrywa z `h-control`; podgląd przewija się przy 390 px |
| 4. Kitchen sink i bramka wizualna | `/dev/ui/welcome`, test, zrzuty | Nadpisanie `auth` w sinku pod zalogowaną sesją |

**Prerequisites:** gałąź robocza z `development`, działający Sail (PostgreSQL do `ci:check`), Playwright do zrzutów.
**Estimated effort:** ~2–3 sesje w 4 fazach.

## Open Risks & Assumptions

- tailwind-merge może zostawić obie klasy, `h-control` i `h-control-lg`. Wtedy wysokość zależy od kolejności w CSS, dlatego jest sprawdzana w renderze, a zapasem jest `size="lg"` + `h-9`.
- Kontrast `brand` na `sidebar-background` nie był liczony. Jeśli nie przejdzie, faza 2 zatrzymuje się przed zmianą tokenu.
- Sidebar podglądu pokazuje `BrandMark` bez nazwy, co jest odstępstwem od ADR-0053 („zawsze z nazwą”), zapisanym w ADR-0057.

## Success Criteria (Summary)

- Gość widzi stronę, która przy 1280 i 390 px wygląda jak karta PageWelcome i jak reszta aplikacji.
- Podgląd nie myli miar z przychodem i oznacza przekroczenie słowem.
- Powrót do `rounded-2xl`, `text-success` czy `max-w-6xl` w trzech plikach zaczerwieni CI.
