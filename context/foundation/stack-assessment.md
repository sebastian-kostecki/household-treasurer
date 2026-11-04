---
project: Household Treasurer
assessed_at: 2026-11-04T13:06:05+01:00
agent_readiness: ready-with-compensation
context_type: brownfield
stack_components:
    language: PHP 8.5 (host 8.4.1), TypeScript 5.9
    framework: Laravel 13.40, Inertia 3 (server 3.5, client 3.9), Vue 3.5
    build_tool: Vite+ 1.4.0 (vp)
    test_runner: Pest 5.3 (PHPUnit 13.4), Vitest 5 via vp test
    package_manager: Composer, npm
    ci_provider: GitHub Actions
    deployment_target: Docker Compose on a VPS
gates_passed: 15
gates_failed: 4
---

## Stack Components

Ocena opiera się na `composer.lock`, `package-lock.json`, plikach konfiguracyjnych i plikach instrukcji z tego repozytorium. Kontekst zmiany: `context/foundation/prd-v2.md` (raport okresu z wykresem i tabelą, trend kategorii, wydatki nieregularne w planie miesiąca, nowa wersja snapshotu). Ocena zastępuje wersję z 2026-11-01 (commit `d38659a`); większość jej zaleceń jest już w `AGENTS.md`.

**PHP.** CI, Sail i obraz produkcyjny używają 8.5, host ma 8.4.1. `composer.json` deklaruje teraz `^8.4.1`, zgodnie z faktycznym minimum zablokowanych pakietów. Analiza statyczna: Larastan 3.13 na PHPStan 2.3, poziom 7. Formatowanie: Pint. Dodatkowo Sloppy 1.8 w CI (heurystyki jakości, baseline 26 znalezisk).

**TypeScript.** Wersja 5.9 z `strict: true`. `vue-tsc` 3.3 sprawdza szablony. Wszystkie 243 pliki `.vue` mają `lang="ts"`, frontend nie ma plików `.js`.

**Laravel.** Wersja 13.40, monolit z Fortify. Stały układ: `app/Actions` (zapisy), `app/Support` (odczyty i obliczenia, 62 pliki), `app/Http/Requests`, `app/Policies`.

**Inertia i Vue.** `inertiajs/inertia-laravel` 3.5, `@inertiajs/vue3` 3.9, Vue 3.5 z Composition API. UI: Tailwind 4.3 i shadcn-vue (`reka-ui` 2.11, styl `new-york-v4`). Projekt nie ma biblioteki wykresów ani komponentu `chart` w `resources/js/components/ui`.

**Wayfinder.** `laravel/wayfinder` 0.1.23 (najnowsza, 2026-10-16) i `@laravel/vite-plugin-wayfinder` 0.1.10 (dostępna 0.1.11). Wygenerowane katalogi są poza Gitem.

**Build.** `vite-plus` 1.4.0 (`vp`) z aliasem `vite` → `@voidzero-dev/vite-plus-core` 1.4.0 i takim samym `overrides`. Dostępna jest 1.5.0. `vp check` robi lint (typowany, `denyWarnings`) i formatowanie; konfiguracja w `vite.config.ts`.

**Testy.** Pest 5.3 na PHPUnit 13.4: 93 pliki w `tests/`, w tym testy wydajności w `tests/Feature/Nfr`. Frontend: Vitest 5 uruchamiany przez `vp test`, 20 plików `*.test.ts` obok kodu. Brak testów architektury (`arch()`).

**Pakiety.** Composer i npm (`npm ci` w CI). `pnpm-workspace.yaml` usunięto.

**CI i wdrożenie.** `.github/workflows/tests.yml`: `composer audit`, Sloppy, migracje, build i `composer ci:check` (lint, typy, testy JS i PHP) na PostgreSQL 18 i PHP 8.5. Wdrożenie ręczne przez `scripts/deploy.sh` i `docker-compose.prod.yml`.

**Pliki instrukcji.** `AGENTS.md` (299 linii: fakty projektu i wytyczne Boost), `.ai/rules` (3 reguły), `CONTEXT.md`, 31 ADR w `.docs/adr`, `.mcp.json` z Boost MCP dla Claude Code (`boost.json`: `"agents": ["claude_code"]`). `CLAUDE.md` istnieje lokalnie, ale jest w `.gitignore` i zawiera tylko blok `@przeprogramowani/10x-cli`.

## Quality Gate Assessment

| Komponent            | Typowany | Konwencje | Dane treningowe | Dokumentacja | Werdykt        |
| -------------------- | -------- | --------- | --------------- | ------------ | -------------- |
| PHP + Larastan       | ✓        | —         | —               | —            | pass           |
| TypeScript           | ✓        | —         | —               | —            | pass           |
| Granica PHP ↔ Vue    | ~        | —         | —               | —            | pass-with-note |
| Laravel 13           | —        | ✓         | ✓               | ✓            | pass           |
| Inertia 3 + Vue 3    | —        | ✓         | ✓               | ✓            | pass           |
| Vite+ 1.4 (`vp`)     | —        | ✓         | ✗               | ✓            | fail           |
| Wayfinder 0.1        | ✓        | —         | ✗               | ~            | fail           |
| Pest 5               | —        | —         | ✓               | ✓            | pass           |
| Vitest (`vp test`)   | —        | —         | ✓               | ✓            | pass           |

Legenda: ✓ zaliczone, ✗ niezaliczone, ~ częściowo, — nie dotyczy.

Wynik: 15 z 19 ocen zaliczonych, 2 częściowe, 2 niezaliczone. Pole `gates_failed` liczy oceny częściowe jako niezaliczone, stąd 4. Zmiana względem 2026-11-01: dokumentacja Vite+ przeszła z częściowej na zaliczoną (projekt jest na linii 1.x), doszedł Vitest.

### Gate Details

**Typowany**

- **PHP: zaliczone.** `phpstan.neon` ustawia poziom 7 na `app/`, `bootstrap/app.php`, `config/`, `database/`, `routes/`, bez baseline. 0 wyciszeń `@phpstan-ignore`. `tests/` są poza analizą.
- **TypeScript: zaliczone.** `tsconfig.json` ma `strict: true`. 243/243 pliki `.vue` z `lang="ts"`, 2 użycia `any`, 0 `@ts-ignore`/`@ts-expect-error`.
- **Granica PHP ↔ Vue: częściowo.** Kontrolery zwracają tablice, propsy są typowane ręcznie w `defineProps<>`, a `vue-tsc` nie widzi PHP. Zabezpieczenie: `assertInertia` w 40 plikach testów (było 32) i reguła „Inertia props contract” w `AGENTS.md`.
- **Wayfinder: zaliczone.** Trasy trafiają do TypeScriptu jako wygenerowane funkcje.

**Konwencje**

- **Laravel: zaliczone.** Silne konwencje frameworka plus własne, stosowane konsekwentnie (Actions, Support, Requests, Policies).
- **Inertia + Vue: zaliczone.** Strony w `resources/js/pages`, komponenty według starter kitu, 70 linii reguł UI w `.ai/rules/js.md` i skaner literałów `scripts/check-ui-literals.mjs` w CI.
- **Vite+: zaliczone.** Jedna konfiguracja w `vite.config.ts` (`lint`, `fmt`, `test`), skrypty npm ukrywają wywołania `vp`.

**Dane treningowe** (w obrębie rodziny języka)

- **Laravel, Vue, Inertia, Pest, Vitest: zaliczone.** Standardowe wybory w swoich ekosystemach.
- **Świeżość wersji: uwaga.** Laravel 13, Inertia 3, Pest 5 i Vitest 5 są młode, więc agent może pisać starsze API. Ochroną jest `search-docs` z Boost MCP, ale tylko jeśli agent widzi wytyczne z `AGENTS.md` (patrz luka 1).
- **Vite+: niezaliczone.** Projekt z 2026 roku, mało materiału treningowego. Odruch agenta to `eslint`, `prettier`, osobny `vitest.config.ts` i importy z `vitest`; tu testy importują z `vite-plus/test`, a pluginy Vite i alias `@` są wyłączone w testach (`process.env.VITEST` w `vite.config.ts`).
- **Wayfinder: niezaliczone.** Wersja 0.1.x w becie, mało publicznych przykładów.

**Dokumentacja**

- **Laravel:** `laravel.com/docs/13.x`. **Inertia:** `inertiajs.com/docs/v3`. **Pest:** `pestphp.com/docs`. **Vitest:** `vitest.dev`. Wszystkie zaliczone.
- **Vite+: zaliczone.** Oficjalny przewodnik `viteplus.dev` opisuje linię 1.x, a projekt jest o jedną wersję minor za najnowszą (1.4.0 wobec 1.5.0). `AGENTS.md` każe potwierdzać opcje przez `npx vp --help`.
- **Wayfinder: częściowo.** Głównym źródłem jest README i skill Boost w `vendor/laravel/wayfinder/resources/boost/skills/wayfinder-development/SKILL.blade.php`. API może się zmienić przed 1.0.

## Gaps & Compensation

Stos jest dobry, a reguły kompensujące z poprzedniej oceny są już w `AGENTS.md`: Project facts, Money, Test database, Inertia props contract, Month logic lives twice (teraz cztery pary PHP/TS z testami po obu stronach), Wayfinder, Frontend toolchain, Fresh checkout, When Boost tools are not available. Główna luka przesunęła się z treści instrukcji na ich dostarczenie.

### Luki w dostarczaniu instrukcji

1. **Claude Code nie wczytuje `AGENTS.md`.** Claude Code czyta `CLAUDE.md`, a lokalny `CLAUDE.md` zawiera tylko blok 10x-cli, bez importu `@AGENTS.md`. Sesja Claude Code nie widzi więc faktów projektu, reguły o groszach, par kalkulatorów, wymogu PostgreSQL ani wytycznych Boost. Dowód: kontekst tej sesji zawierał wyłącznie `CLAUDE.md`. Dodatkowo `CLAUDE.md` jest w `.gitignore` (linia 35), więc poprawka zostaje lokalna i nie trafi do świeżego klona. Kompensacja: „Load AGENTS.md” poniżej.

### Luki w ocenie stosu (skompensowane)

2. **Vite+ słabo znany agentom.** Pokryte regułą „Frontend toolchain” w `AGENTS.md`, łącznie z importem z `vite-plus/test` i wspólną wersją `vite-plus`/`vite`/`overrides`. Nowego wpisu nie trzeba.
3. **Wayfinder w becie.** Pokryte regułą „Wayfinder”. Nowego wpisu nie trzeba.
4. **Propsy Inertii bez wspólnego kontraktu.** Pokryte regułą „Inertia props contract”. Raporty z prd-v2 dodadzą duże struktury propsów (kategorie × miesiące), więc reguła będzie tu szczególnie ważna.

### Ryzyka dla tej zmiany (prd-v2)

- **Wykresy nie mają jeszcze miejsca w stosie.** Brak biblioteki wykresów w `package.json` i komponentu `chart` w `components/ui`. Agent sięgnie odruchowo po Chart.js, ApexCharts albo ECharts. Wybór (np. komponent Chart z rejestru shadcn-vue, oparty na Unovis, albo własne SVG) to decyzja dla `/10x-plan` i ADR. Kolory muszą iść z tokenów (`.ai/rules/js.md`), a kwoty przez `formatMoney`. Kompensacja: reguła „Charts and reports”.
- **Kwoty raportu muszą być równe co do grosza istniejącym odczytom.** PRD wymaga zgodności z wykonaniem kategorii w budżecie rocznym i z Wynikiem miesiąca. Nowe zapytanie SQL obok `BudgetMonthResult`, `CategoryExecutionMetrics` i `MonthCategoryActuals` grozi rozjazdem (zwroty netto, transfery własne, ruchy kapitału). Wymóg 2 s przy 10 000 operacji ma już wzorzec w `tests/Feature/Nfr`. Kompensacja: reguła „Charts and reports”.
- **Wydatki nieregularne zmieniają regułę „do wydania”.** `ToSpendCalculator` ma kopię TS, która zasila podgląd planu oraz ostrzeżenia przy wpisie i w Imporcie. Reguła „Month logic lives twice” to pokrywa. PRD wymaga wpisu w `CONTEXT.md` i nowego ADR przed kodem; kolejny numer to 0035 (numer 0008 jest zdublowany, 0025–0028 nie istnieją).
- **Podbicie wersji snapshotu może zablokować przywracanie wersji 5.** `RestoreUserData` (linie ~77–97) akceptuje `[1, 2, 3, 4, BackupUserData::SNAPSHOT_VERSION]`, a warunki per wersja też odwołują się do stałej. Zmiana `SNAPSHOT_VERSION` z 5 na 6 bez dopisania `5` sprawi, że obecne backupy przestaną się wczytywać, wbrew FR-014. Kompensacja: reguła „Snapshot format”.
- **Kategoria przez lata stoi w sprzeczności ze słownikiem.** `CONTEXT.md` ma przy Category _Avoid_ „a Category identity that spans years”. FR-007–FR-009 (nice-to-have) wymagają zmiany słownika i ADR przed kodem.

### Recommended Instruction File Additions

**1. Do lokalnego `CLAUDE.md`** — dodaj jako pierwszą linię, poza znacznikami `BEGIN/END @przeprogramowani/10x-cli`, żeby aktualizacja 10x-cli jej nie nadpisała:

```markdown
@AGENTS.md
```

Ponieważ `CLAUDE.md` jest ignorowany przez Git, rozważ śledzenie go w repozytorium albo dopisanie tej linii przy każdym nowym klonie.

**2. Do `AGENTS.md`** — wklej poza blokiem `<laravel-boost-guidelines>` (blok jest generowany przez Boost), np. po sekcji „Month logic lives twice”:

```markdown
## Charts and reports

- There is no chart library yet. Do not add one (Chart.js, ApexCharts, ECharts or another) without a decision recorded in the change plan and an ADR. If shadcn-vue Chart is chosen, add it with the shadcn-vue CLI into `resources/js/components/ui` and follow `components.json`.
- Every chart has a table with the same numbers next to it (prd-v2), so the data is readable without the chart.
- Chart colours come from tokens in `resources/css/app.css` and category colours from `resources/js/lib/category-appearance.ts`. Amounts go through `formatMoney`; see `.ai/rules/js.md`.
- Compute report amounts on the server in `app/Support` and pass them as props in minor units. Reuse the existing readings (`BudgetMonthResult`, `CategoryExecutionMetrics`, `MonthCategoryActuals`) or prove equality with them in a test: a month's report result must equal Month Result, and a year's category total must equal the annual execution, to the grosz. Refunds count net; own transfers and capital movements are excluded.
- A year report must load within 2 seconds at 10,000 operations. Add a test next to `tests/Feature/Nfr/NfrPerf01Test.php` and avoid per-month or per-category queries in loops (Sloppy flags them).

## Snapshot format

`BackupUserData::SNAPSHOT_VERSION` is the version written by `user:backup`. `RestoreUserData` lists accepted versions explicitly and uses the constant for the newest one. When you bump the constant, add the previous number to every version list in `RestoreUserData`, give new fields defaults for older snapshots, and add a restore test for each older version, so existing backups still load.

## Domain changes come before code

A change to a rule in `CONTEXT.md` (for example To Spend) or to a term marked _Avoid_ needs the `CONTEXT.md` entry and a new ADR in `.docs/adr` before the code. Take the next free number after the highest existing ADR.
```

## Summary

**Werdykt: gotowy po kompensacji.** Stos dobrze wspiera agentów: PHPStan na poziomie 7 bez wyjątków, TypeScript strict, spójne konwencje Laravela, testy po obu stronach par kalkulatorów, pełna bramka w CI na PostgreSQL. Zmiana stosu nie jest potrzebna.

**Mocne strony:** dyscyplina typów, zgodne z kodem `AGENTS.md`, rozbudowany słownik i ADR-y, Vitest dodany przez `vp test`, Sloppy i audyt zależności w CI.

**Główne luki:** Claude Code nie wczytuje `AGENTS.md`, bo `CLAUDE.md` go nie importuje (jedna linia do dopisania). Dla prd-v2 brakuje decyzji o wykresach i zasady zgodności kwot raportu z istniejącymi odczytami, a podbicie wersji snapshotu łatwo zrobić tak, że stare backupy przestaną się wczytywać.

**Do sprawdzenia w `/10x-health-check`:**

- `CLAUDE.md` w `.gitignore` i brak importu `@AGENTS.md`,
- `vite-plus` 1.4.0 wobec 1.5.0 oraz `@laravel/vite-plugin-wayfinder` 0.1.10 wobec 0.1.11,
- `tests/` poza analizą PHPStan i brak testów architektury (`arch()`),
- `phpunit.xml` z domyślnym SQLite przy PostgreSQL w CI i na produkcji,
- brak automatycznego wdrożenia w CI,
- zdublowany numer ADR 0008.

**Następny krok:** `/10x-health-check`.
