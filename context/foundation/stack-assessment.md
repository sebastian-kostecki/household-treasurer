---
project: Household Treasurer
assessed_at: 2026-11-08T14:00:18+01:00
agent_readiness: ready-with-compensation
context_type: brownfield
stack_components:
    language: PHP 8.5 (host 8.4.1), TypeScript 5.9
    framework: Laravel 13.40, Inertia 3 (server 3.7, client 3.11), Vue 3.5
    build_tool: Vite+ 1.5.0 (vp)
    test_runner: Pest 5.3 (PHPUnit 13.4), Vitest 5.1 via vp test, Playwright 1.65
    package_manager: Composer, npm
    ci_provider: GitHub Actions
    deployment_target: Docker Compose on a VPS
gates_passed: 17
gates_failed: 4
---

## Stack Components

Ocena opiera się na `composer.lock`, `package-lock.json`, plikach konfiguracyjnych i plikach instrukcji z tego repozytorium. Kontekst zmiany: `context/foundation/prd-v3.md` (zarządzanie kategoriami: jedna lista kategorii przez lata, grupy, rytm zamiast flag Paced/One-off i Month Item Kind, kategorie nieregularne z „zebranym”, scalanie kategorii, migracja istniejących danych, raporty z wykresem i tabelą, snapshot w nowym formacie). Ocena zastępuje wersję z 2026-11-04 pod prd-v2 (commit `b72ef6d`). Jej zalecenia są wdrożone: `CLAUDE.md` jest w repozytorium i importuje `@AGENTS.md`, a reguły „Charts and reports”, „Snapshot format” i „Domain changes come before code” są w `.ai/rules/reports.md`, `.ai/rules/snapshot.md` i w twardych regułach `AGENTS.md`.

**PHP.** CI, Sail i obraz produkcyjny używają 8.5, host 8.4.1, `composer.json` deklaruje `^8.4.1`. Analiza statyczna: Larastan 3.14 na PHPStan 2.3.5, poziom 7. Formatowanie: Pint 1.32. Sloppy 1.8.2 w CI z baseline (`.sloppy-baseline.json`).

**TypeScript.** Wersja 5.9.3 z `strict: true`, `vue-tsc` 3.3 sprawdza szablony. 255 z 256 plików `.vue` ma `lang="ts"`.

**Laravel.** Wersja 13.40, monolit z Fortify 1.41. Stały układ: `app/Actions` (zapisy, 21 plików), `app/Support` (odczyty i obliczenia, 40 wpisów), `app/Http/Requests`, `app/Policies`. Baza: 20 migracji, wszystkie schematowe, z `down()`.

**Inertia i Vue.** `inertiajs/inertia-laravel` 3.7, `@inertiajs/vue3` 3.11, Vue 3.5 z Composition API. UI: Tailwind 4.3 i shadcn-vue (`reka-ui` 2.11), design system Atrament (`context/foundation/design-system.md`). W `resources/js/components/ui` nadal nie ma komponentu `chart`, a w `package.json` biblioteki wykresów.

**Wayfinder.** `laravel/wayfinder` 0.1.23 i `@laravel/vite-plugin-wayfinder` 0.1.11 (od poprzedniej oceny podbity z 0.1.10). Wygenerowane katalogi są poza Gitem.

**Build.** `vite-plus` 1.5.0 (`vp`), alias `vite` i `overrides.vite` na `@voidzero-dev/vite-plus-core@1.5.0`, wszystkie trzy zgodne. `vp check` robi lint i formatowanie, `vp test` uruchamia Vitest.

**Testy.** Pest 5.3 na PHPUnit 13.4: 114 plików `*Test.php`, w tym testy wydajności w `tests/Feature/Nfr`; `assertInertia` w 47 plikach. Frontend: 40 plików `*.test.ts` obok kodu. Playwright 1.65: 8 specyfikacji E2E (`playwright.config.ts`, `scripts/e2e-server.sh`). Brak testów architektury (`arch()`). `phpunit.xml` domyślnie używa SQLite.

**Pakiety.** Composer i npm (`npm ci` w CI, `engines.node >= 24`).

**CI i wdrożenie.** `.github/workflows/tests.yml`: `composer audit --locked`, Sloppy, migracje, `npm ci`, build i `composer ci:check` (`vp check`, `check:ui`, `vue-tsc`, `vp test`, Pint, PHPStan, Pest) na PostgreSQL 18 i PHP 8.5. Wdrożenie ręczne: `scripts/deploy.sh` buduje obraz, uruchamia `docker-compose.prod.yml` i robi `php artisan migrate --force` bez wcześniejszej kopii bazy.

**Pliki instrukcji.** `CLAUDE.md` (śledzony, `@AGENTS.md` plus sekcja Claude Code), `AGENTS.md` (236 linii: twarde reguły, polecenia, układ, wytyczne Boost), `.ai/rules` (17 reguł z `paths:` i `index.md`, ładowane przez symlink `.claude/rules/project`), `context/foundation/glossary.md`, 62 ADR w `context/foundation/adr`, Boost MCP przez `.mcp.json`. `CLAUDE.local.md` jest ignorowany i trzyma materiały kursu.

## Quality Gate Assessment

| Komponent          | Typowany | Konwencje | Dane treningowe | Dokumentacja | Werdykt        |
| ------------------ | -------- | --------- | --------------- | ------------ | -------------- |
| PHP + Larastan     | ✓        | —         | —               | —            | pass           |
| TypeScript         | ✓        | —         | —               | —            | pass           |
| Granica PHP ↔ Vue  | ~        | —         | —               | —            | pass-with-note |
| Laravel 13         | —        | ✓         | ✓               | ✓            | pass           |
| Inertia 3 + Vue 3  | —        | ✓         | ✓               | ✓            | pass           |
| Vite+ 1.5 (`vp`)   | —        | ✓         | ✗               | ✓            | fail           |
| Wayfinder 0.1      | ✓        | —         | ✗               | ~            | fail           |
| Pest 5             | —        | —         | ✓               | ✓            | pass           |
| Vitest (`vp test`) | —        | —         | ✓               | ✓            | pass           |
| Playwright         | —        | —         | ✓               | ✓            | pass           |

Legenda: ✓ zaliczone, ✗ niezaliczone, ~ częściowo, — nie dotyczy.

Wynik: 17 z 21 ocen zaliczonych, 2 częściowe, 2 niezaliczone. Pole `gates_failed` liczy oceny częściowe jako niezaliczone, stąd 4. Zmiana względem 2026-11-04: doszedł Playwright (dwie oceny zaliczone), oceny pozostałych komponentów są bez zmian.

### Gate Details

**Typowany**

- **PHP: zaliczone.** `phpstan.neon` ustawia poziom 7 na `app/`, `bootstrap/app.php`, `config/`, `database/`, `routes/`, bez baseline. 0 wyciszeń `@phpstan-ignore`. `tests/` są poza analizą.
- **TypeScript: zaliczone.** `tsconfig.json` ma `strict: true`. 255/256 plików `.vue` z `lang="ts"`, 2 użycia `any`, 0 `@ts-ignore`/`@ts-expect-error`.
- **Granica PHP ↔ Vue: częściowo.** Kontrolery zwracają tablice, a propsy są typowane ręcznie w `defineProps<>`; `vue-tsc` nie widzi PHP. Zabezpieczenie: twarda reguła w `AGENTS.md` (prop zmienia się po obu stronach w jednym commicie z testem `assertInertia`), `.ai/rules/inertia-props.md` i `assertInertia` w 47 plikach testów.
- **Wayfinder: zaliczone.** Trasy trafiają do TypeScriptu jako wygenerowane funkcje.

**Konwencje**

- **Laravel: zaliczone.** Silne konwencje frameworka plus własne, stosowane konsekwentnie (Actions, Support, Requests, Policies), opisane w `AGENTS.md` → „Where things live”.
- **Inertia + Vue: zaliczone.** Strony w `resources/js/pages`, komponenty domenowe w `components`, logika w `lib` z testami obok. Reguły UI w `.ai/rules/js*.md`, skaner literałów `scripts/check-ui-literals.mjs` w CI.
- **Vite+: zaliczone.** Jedna konfiguracja w `vite.config.ts` (lint, fmt, test), skrypty npm ukrywają wywołania `vp`.

**Dane treningowe** (w obrębie rodziny języka)

- **Laravel, Vue, Inertia, Pest, Vitest, Playwright: zaliczone.** Standardowe wybory w swoich ekosystemach.
- **Świeżość wersji: uwaga.** Laravel 13, Inertia 3, Pest 5 i Vitest 5 są młode, więc agent może pisać starsze API. Ochroną jest `search-docs` z Boost MCP. W tej sesji Boost MCP się nie połączył, a fallback jest w `context/foundation/agents/without-boost.md`.
- **Vite+: niezaliczone.** Projekt z 2026 roku, mało materiału treningowego. Odruch agenta to `eslint`, `prettier`, osobny `vitest.config.ts` i importy z `vitest`.
- **Wayfinder: niezaliczone.** Wersja 0.1.x w becie, mało publicznych przykładów.

**Dokumentacja**

- **Laravel:** `laravel.com/docs/13.x`. **Inertia:** `inertiajs.com/docs/v3`. **Pest:** `pestphp.com/docs`. **Vitest:** `vitest.dev`. **Playwright:** `playwright.dev`. Wszystkie zaliczone.
- **Vite+: zaliczone.** Oficjalny przewodnik `viteplus.dev` opisuje linię 1.x, a `.ai/rules/frontend-toolchain.md` każe potwierdzać opcje przez `npx vp --help`.
- **Wayfinder: częściowo.** Głównym źródłem jest README i skill Boost w `vendor/laravel/wayfinder/resources/boost/skills/wayfinder-development/SKILL.blade.php`. API może się zmienić przed 1.0.

## Gaps & Compensation

### Luki stosu (skompensowane, bez nowych wpisów)

1. **Vite+ słabo znany agentom.** Pokryte przez `.ai/rules/frontend-toolchain.md` (import z `vite-plus/test`, wspólna wersja `vite-plus`/`vite`/`overrides`, tylko npm).
2. **Wayfinder w becie.** Pokryte przez `.ai/rules/wayfinder.md` (kopiuj wzorzec z istniejącej strony, `npm run build` przed `npm run types:check`). Nowy widok „Kategorie” i raporty dodadzą trasy, więc reguła zadziała od razu.
3. **Propsy Inertii bez wspólnego kontraktu.** Pokryte przez twardą regułę w `AGENTS.md` i `.ai/rules/inertia-props.md`. Raporty (grupy × kategorie × miesiące, porównanie rok do roku) dadzą duże struktury propsów, więc typ w `defineProps<>` warto trzymać w jednym pliku `lib/*` współdzielonym przez stronę i komponenty.

Instrukcje docierają do agenta: `CLAUDE.md` importuje `@AGENTS.md`, a reguły ścieżek ładują się same. Luka w dostarczaniu instrukcji z poprzedniej oceny jest zamknięta.

### Ryzyka dla tej zmiany (prd-v3)

Stos przechodzi ocenę, ale prd-v3 to pierwsza zmiana, która przebudowuje dane zamiast je dokładać. Trzy z poniższych ryzyk nie mają jeszcze żadnej reguły.

- **Pierwsza migracja danych w projekcie (bez reguły).** Wszystkie 20 migracji są schematowe, a FR-006 wymaga scalenia kategorii o tej samej nazwie i typie z różnych budżetów w jedną kategorię listy i przepięcia operacji, pozycji planu miesiąca i planów rocznych bez zmiany kwot. Agent ma tu trzy złe odruchy: modele Eloquent w migracji (zmienią się razem z kodem), test tylko na SQLite (domyślny w `phpunit.xml`) i `down()`, który nie potrafi rozdzielić scalonych kategorii. Kompensacja: reguła „Data migrations” poniżej.
- **Wdrożenie migruje bez kopii bazy (bez reguły).** `scripts/deploy.sh` (linia 36) robi `php artisan migrate --force` bez `pg_dump` ani `user:backup` przed migracją. Przy scalaniu kategorii błąd migracji oznacza utratę danych na produkcji. Kompensacja: krok kopii w `deploy.sh` (do sprawdzenia w `/10x-health-check`) i punkt w regule „Data migrations”.
- **Łączenie lat po nazwie i typie jest w kilku miejscach (bez reguły).** Po wprowadzeniu listy tożsamością kategorii jest jej identyfikator, a stare łączenia po nazwie muszą zniknąć: `app/Actions/CreateBudget.php` (kopiowanie kategorii, ADR-0014), `app/Support/MonthPlanProposal.php` (grudzień → styczeń), `app/Support/OperationsListQuery.php` i `app/Support/OperationsFilterOptions.php` (filtr Operacji, ADR-0016), `app/Support/CsvImport/ImportCategorySuggestionModel.php` i `ImportCategorySuggester.php` (podpowiedzi Importu po `(type, category name)`, ADR-0034). PRD wymienia też Earmark, ale Earmark nie istnieje w `app/` — to tylko dokumenty. Kompensacja: reguła „Category identity” poniżej.
- **Słownik i ADR przed kodem (pokryte).** prd-v3 wprost łamie wpis słownika Category (_Avoid_ „a Category identity that spans years”, „A Category on one Budget is not the same Category…”), zastępuje ADR-0014, zmienia ADR-0016, ADR-0034 i regułę To Spend (ADR-0033) przez kategorie nieregularne. Twarda reguła w `AGENTS.md` to wymusza. Kolejny wolny numer ADR to 0063; numer 0008 jest zdublowany.
- **Rytm zamiast dwóch flag ma duży zasięg (częściowo pokryte).** `is_paced` / `isPaced` występuje w 34 plikach, `month_item_kind` / `MonthItemKind` w 50 (PHP, TS, testy, migracje), w tym w parach kalkulatorów PHP/TS (`ToSpendCalculator`, `MonthPaceCalculator`, `MonthRemainingCalculator`, `MonthItemDeviation`). `.ai/rules/month-calculators.md` pilnuje zgodności par, ale nie kolejności przejścia. Usunięcie flag w jednym kroku zostawi czerwone CI na wiele godzin pracy. Kompensacja: punkt „expand, switch, contract” w regule „Category identity”.
- **Snapshot w nowym formacie (częściowo pokryte).** `.ai/rules/snapshot.md` wymaga dopisania `5` do list wersji w `RestoreUserData` (dziś `[1, 2, 3, 4, SNAPSHOT_VERSION]`, `SNAPSHOT_VERSION = 5`). Dla prd-v3 to za mało: snapshot w wersjach 1–5 ma kategorie per budżet, więc przywrócenie musi je scalić tak samo jak migracja (FR-022), a nie tylko dać domyślne wartości nowym polom. Kompensacja: dopisek do `.ai/rules/snapshot.md`.
- **Wykresy (pokryte).** `.ai/rules/reports.md` wymaga decyzji w planie i ADR przed dodaniem biblioteki, tabeli obok każdego wykresu, kolorów z tokenów i kwot zgodnych co do grosza z `BudgetMonthResult`, `CategoryExecutionMetrics` i `MonthCategoryActuals`. Nowych wpisów nie trzeba; decyzja o bibliotece należy do `/10x-plan`.

### Recommended Instruction File Additions

**1. Nowa reguła `.ai/rules/migrations.md`** (plus wiersz w `.ai/rules/index.md`: `database/migrations/**/*.php, tests/Feature/Migrations/**` → `.ai/rules/migrations.md`, potem `php artisan boost:index-rules`):

```markdown
---
paths:
    - 'database/migrations/**/*.php'
    - 'tests/Feature/Migrations/**'
---

# Migrations

## A data migration uses the query builder and is tested on PostgreSQL

Migrations that move or merge rows (for example one Category list from per-Budget categories) use `DB::table()` and plain arrays, never Eloquent models or `app/Actions`: models change with the code, the migration must not. Write a test in `tests/Feature/Migrations/` that seeds the old shape, runs the migration and asserts the invariants: every operation keeps its amount and points to a category of the same type, budget plan and execution totals per Budget are equal before and after, to the grosz. Run it on PostgreSQL (AGENTS.md → Commands); SQLite proves nothing here.

## Expand, then backfill, then drop

Add new tables and columns in one migration, fill them in a second, drop the old columns only after the code no longer reads them, in a later phase. A migration that merges rows cannot be reversed: its `down()` throws a `RuntimeException` naming the backup to restore instead of pretending to split the rows.

## Back up before a migration reaches production

`scripts/deploy.sh` runs `php artisan migrate --force`. A change with a data migration adds a database dump to the backup directory before that line and says in its plan how to restore it.
```

**2. Nowa reguła `.ai/rules/categories.md`** (wiersz w indeksie: `app/Actions/*Budget*.php, app/Actions/*Categor*.php, app/Support/MonthPlanProposal.php, app/Support/Operations*.php, app/Support/CsvImport/ImportCategory*.php, app/Models/Category*.php`):

```markdown
---
paths:
    - 'app/Actions/*Budget*.php'
    - 'app/Actions/*Categor*.php'
    - 'app/Models/Category*.php'
    - 'app/Support/MonthPlanProposal.php'
    - 'app/Support/Operations*.php'
    - 'app/Support/CsvImport/ImportCategory*.php'
---

# Categories

## A Category is identified by its id, never by its name

Since prd-v3 a Category spans years. Code that links years, proposes a plan, filters operations or suggests a category on Import joins by category id. Matching by `name` and `type` belongs only to the one-off data migration. Before closing the change, `grep -rn "'name'" app/Support app/Actions` shows no category lookup by name.

## Rhythm replaces the two flags in phases

`is_paced` and `month_item_kind` are read in about 50 files, including the PHP/TS calculator pairs (`.ai/rules/month-calculators.md`). Add the rhythm next to the flags, switch readers one area per phase with tests green, and drop the flags in the last phase. Each calculator pair switches in one commit, PHP and TS together.
```

**3. Dopisek do `.ai/rules/snapshot.md`** (nowa sekcja na końcu):

```markdown
## An older snapshot is converted, not just defaulted

When the data model changes shape (prd-v3: per-Budget categories become one Category list), restoring an older snapshot runs the same conversion as the data migration, reusing its rules, so a restored v1–v5 snapshot equals what the migration would produce from the same data. Test it in `tests/Feature/UserSnapshotCommandTest.php` with a v5 fixture that has the same category name in two Budgets.
```

## Summary

**Werdykt: gotowy po kompensacji.** Stos dobrze wspiera agentów: PHPStan na poziomie 7 bez wyjątków, TypeScript strict, spójne konwencje Laravela, testy po obu stronach par kalkulatorów, E2E w Playwright, pełna bramka w CI na PostgreSQL. Zmiana stosu nie jest potrzebna, a obie słabsze części (Vite+ i Wayfinder) mają już reguły.

**Mocne strony:** dyscyplina typów, instrukcje dostarczane automatycznie (`CLAUDE.md` → `AGENTS.md` → `.ai/rules` według ścieżek), rozbudowany słownik i 62 ADR-y, Sloppy i `composer audit` w CI.

**Główne luki dla prd-v3:** projekt nie ma jeszcze wzorca migracji danych, a ta zmiana zaczyna się od scalenia kategorii z kilku lat; wdrożenie migruje bez kopii bazy; łączenie kategorii po nazwie jest rozsiane po sześciu plikach; snapshot w starym formacie trzeba konwertować, nie tylko uzupełniać. Trzy wpisy powyżej to pokrywają.

**Do sprawdzenia w `/10x-health-check`:**

- `scripts/deploy.sh` bez kopii bazy przed `migrate --force`,
- `phpunit.xml` z domyślnym SQLite przy PostgreSQL w CI i na produkcji, wobec pierwszej migracji danych,
- `tests/` poza analizą PHPStan i brak testów architektury (`arch()`),
- brak automatycznego wdrożenia w CI,
- zdublowany numer ADR 0008,
- Earmark opisany w PRD i słowniku, nieobecny w kodzie.

**Następny krok:** `/10x-health-check`.
