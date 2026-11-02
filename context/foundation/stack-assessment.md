---
project: Household Treasurer
assessed_at: 2026-11-01T18:19:05+01:00
agent_readiness: ready-with-compensation
context_type: brownfield
stack_components:
    language: PHP 8.5 (host 8.4.1), TypeScript 5.9
    framework: Laravel 13.30, Inertia 3.7, Vue 3.5
    build_tool: Vite+ 0.3.0 (vp) on Vite 8.2
    test_runner: Pest 5.1 (PHPUnit 13.3)
    package_manager: Composer, npm
    ci_provider: GitHub Actions
    deployment_target: Docker Compose on a VPS
gates_passed: 12
gates_failed: 5
---

## Stack Components

Ocena opiera się na `composer.lock`, `package-lock.json` i plikach konfiguracyjnych z tego repozytorium. Dotyczy obszarów zmiany z `context/foundation/prd.md`: odczytów miesiąca, powiadomień Dashboardu, ostrzeżeń przy operacjach i Imporcie oraz snapshotów.

**PHP.** CI, Sail i obraz produkcyjny używają 8.5, a host ma 8.4.1. `composer.json` deklaruje `^8.3`, ale zablokowane pakiety wymagają `>=8.4.1` (Symfony, PHPUnit 13) oraz `^8.4` (Pest 5). Faktyczne minimum to 8.4.1. Analiza statyczna: Larastan 3.11 na PHPStan 2.2, poziom 7. Formatowanie: Pint z presetem `laravel`.

**TypeScript.** Wersja 5.9 z `strict: true`. Szablony Vue sprawdza `vue-tsc` 2.2. Kod frontendu nie zawiera plików `.js`.

**Laravel.** Wersja 13.30 jako monolit z Fortify. Kod ma stały układ: `app/Actions` dla operacji zapisu, `app/Support` dla odczytów i obliczeń (48 plików), `app/Http/Requests` dla walidacji, `app/Policies` dla autoryzacji.

**Inertia i Vue.** Inertia 3.7 oraz Vue 3.5 z Composition API. Warstwa UI to Tailwind 4.3 i shadcn-vue (`reka-ui`).

**Wayfinder.** `laravel/wayfinder` 0.1.21 generuje funkcje tras w TypeScript i jest używany w 50 plikach frontendu. Wygenerowane katalogi są ignorowane przez Git.

**Build.** Skrypty npm wołają `vp` z `vite-plus` 0.3.0, zablokowanego dokładnie na tej wersji, z Vite 8.2 pod spodem. `vp check` obsługuje formatowanie i lint z konfiguracją w `vite.config.ts`. Projekt nie ma konfiguracji ESLint, Prettier ani Vitest.

**Testy.** Pest 5.1 na PHPUnit 13.3: 66 plików PHP w `tests/`. Frontend nie ma żadnych testów.

**Pakiety.** Composer i npm (`package-lock.json`, `npm ci` w CI). `pnpm-workspace.yaml` jest pozostałością, bo projekt nie ma pliku blokady pnpm.

**CI i wdrożenie.** `.github/workflows/tests.yml` uruchamia migracje, build i `composer ci:check` na PostgreSQL 18. Wdrożenie jest ręczne przez `scripts/deploy.sh` i `docker-compose.prod.yml`.

**Pliki instrukcji.** `AGENTS.md`, `.ai/rules` (3 pliki reguł), `.docs/agents/`, `.cursor/skills`, `CONTEXT.md` i 29 plików w `.docs/adr`. Projekt nie ma `CLAUDE.md` ani `.mcp.json`.

## Quality Gate Assessment

| Komponent         | Typowany | Konwencje | Dane treningowe | Dokumentacja | Werdykt        |
| ----------------- | -------- | --------- | --------------- | ------------ | -------------- |
| PHP + Larastan    | ✓        | —         | —               | —            | pass           |
| TypeScript        | ✓        | —         | —               | —            | pass           |
| Granica PHP ↔ Vue | ~        | —         | —               | —            | pass-with-note |
| Laravel 13        | —        | ✓         | ✓               | ✓            | pass           |
| Inertia 3 + Vue 3 | —        | ✓         | ✓               | ✓            | pass           |
| Vite+ (`vp`)      | —        | ✓         | ✗               | ~            | fail           |
| Wayfinder         | ✓        | —         | ✗               | ~            | fail           |
| Pest 5            | —        | —         | ✓               | ✓            | pass           |

Legenda: ✓ zaliczone, ✗ niezaliczone, ~ częściowo, — nie dotyczy.

Wynik: 12 z 17 ocen zaliczonych, 3 częściowe, 2 niezaliczone. Pole `gates_failed` liczy oceny częściowe jako niezaliczone, stąd wartość 5.

### Gate Details

**Typowany**

- **PHP: zaliczone.** `phpstan.neon` ustawia poziom 7 na `app/`, `config/`, `database/`, `routes/` i `bootstrap/app.php`, bez baseline. Kod ma 0 wyciszeń `@phpstan-ignore` i 71 kształtów tablic w PHPDoc. Żaden plik nie ma `declare(strict_types=1)`, co jest zgodne z konwencją Laravela. `tests/` są poza analizą.
- **TypeScript: zaliczone.** `tsconfig.json` ma `strict: true`. Wszystkie 221 plików `.vue` używają `lang="ts"`. Kod ma 2 użycia `any` (`resources/js/composables/useCurrentUrl.ts:70-71`) i 0 `@ts-ignore`.
- **Granica PHP ↔ Vue: częściowo.** Kontrolery zwracają zwykłe tablice, a typy propsów są pisane ręcznie w `defineProps<>` (205 komponentów). Nic nie sprawdza zgodności obu stron, bo `vue-tsc` nie widzi PHP. Zabezpieczeniem są asercje `assertInertia` w 32 plikach testów, ale dotyczą tylko wskazanych propsów.
- **Wayfinder: zaliczone.** Trasy trafiają do TypeScriptu jako wygenerowane funkcje.

**Konwencje**

- **Laravel: zaliczone.** Silne konwencje frameworka. Projekt dokłada własne, stosowane konsekwentnie (Actions, Support, Requests, Policies).
- **Inertia + Vue: zaliczone.** Strony w `resources/js/pages`, komponenty według starter kitu, reguły UI w `.ai/rules/js.md`.
- **Vite+: zaliczone.** Jedna konfiguracja w `vite.config.ts`, a skrypty npm ukrywają szczegóły wywołań.

**Dane treningowe** (oceniane w obrębie rodziny języka)

- **Laravel, Vue, Pest: zaliczone.** To standardowe wybory w swoich ekosystemach. Inertia jest domyślną ścieżką starter kitu Laravel Vue.
- **Świeżość wersji: uwaga.** Laravel 13, Inertia 3, Pest 5 i Vite 8 są młode, więc agent może odruchowo pisać starsze API. Przykłady: `Inertia::lazy()` i Axios usunięte w Inertia 3. `AGENTS.md` to przewiduje, ale opiera ochronę na narzędziu `search-docs`, dostępnym tylko z Boost MCP.
- **Vite+: niezaliczone.** Publiczna alfa pojawiła się w marcu 2026, więc materiału treningowego jest mało. Odruch agenta to `eslint`, `prettier` i `vitest`, a żadnego z nich tu nie ma.
- **Wayfinder: niezaliczone.** Pakiet w wersji 0.1.x, wciąż w becie, z niewielką liczbą przykładów publicznych.

**Dokumentacja**

- **Laravel: zaliczone.** Wersjonowana dokumentacja pod `laravel.com/docs/13.x`.
- **Inertia: zaliczone.** Dokumentacja i przewodnik migracji pod `inertiajs.com/docs/v3`.
- **Pest: zaliczone.** Oficjalna dokumentacja i przewodnik migracji pod `pestphp.com/docs`.
- **Vite+: częściowo.** Oficjalny przewodnik istnieje pod `viteplus.dev`, ale wydano już wersję 1.0, a projekt używa 0.3.0. Strony główna i rozwojowa opisują konfigurację `vp check` odmiennie, więc dokumentacja może nie pasować do zainstalowanej wersji.
- **Wayfinder: częściowo.** Głównym źródłem jest README pakietu. Nie znaleziono strony dla Laravela 13, a API może się zmienić przed 1.0. Dobrym uzupełnieniem jest `.cursor/skills/wayfinder-development/SKILL.md`.

## Gaps & Compensation

Największa luka nie dotyczy stosu, tylko dostarczania instrukcji. Technologie są dobre, ale część wiedzy projektu nie dociera do agenta.

### Luki w ocenie stosu

1. **Vite+ jest mało znany agentom.** Skutek: agent uruchamia lub dodaje narzędzia, których projekt nie używa. Kompensacja: reguła „Frontend toolchain”.
2. **Wayfinder to beta o małym korpusie przykładów.** Skutek: agent może wymyślać API albo wpisywać adresy na sztywno. Kompensacja: reguła „Routes in TypeScript”.
3. **Propsy Inertii nie mają wspólnego kontraktu.** Skutek: zmiana po stronie PHP psuje widok bez błędu kompilacji. Kompensacja: reguła „Inertia props contract”.

### Luki w instrukcjach dla agentów

4. **`AGENTS.md` nie ma faktów o tym projekcie.** Zawiera wytyczne Boost i odnośniki, ale wyszukiwanie haseł minor units, Sail, PostgreSQL, `CONTEXT.md`, ADR i `tech-stack` nie daje żadnego trafienia. Reguła o groszach jest tylko dla frontendu (`.ai/rules/js.md`), a PRD wymaga dokładności do grosza. Kompensacja: reguły „Project facts” i „Money”.
5. **`AGENTS.md` wskazuje zasoby niedostępne poza Cursorem.** `boost.json` ma `"agents": ["cursor"]`, a konfiguracja MCP istnieje tylko w `.cursor/mcp.json` i `.ai/mcp/mcp.json`, z zaszytą ścieżką do głównego checkoutu i `wsl.exe`. Sesje Claude Code nie mają więc `search-docs`, `database-query` ani `record-rule`. Sekcja o katalogu `.ai/rules/boost` opisuje zasób, którego nie ma w repozytorium. Kompensacja: reguła „When Boost tools are not available”.
6. **Rozjechane wersje PHP.** `AGENTS.md` podaje 8.4, CI i produkcja używają 8.5, a `.docs/tech-stack.md` błędnie twierdzi, że Laravel 13 wymaga 8.5 (minimum to 8.3). Kompensacja: reguła „Project facts”.
7. **Lokalny zielony wynik nie dowodzi poprawności na PostgreSQL.** `phpunit.xml` domyślnie ustawia SQLite w pamięci, bez `force`, a CI nadpisuje to zmiennymi środowiskowymi na PostgreSQL. `.docs/tech-stack.md` obiecuje PostgreSQL także w testach. Commity `28e978a` i `bc40577` pokazują, że ta różnica już powodowała awarie w CI. Kompensacja: reguła „Test database”.
8. **Świeży worktree nie jest gotowy do pracy.** Brakuje `vendor/`, `node_modules/` i plików Wayfindera, które powstają przy buildzie. CI buduje assety przed sprawdzeniem typów, a `AGENTS.md` o tej kolejności nie wspomina. Kompensacja: reguła „Fresh checkout or worktree”.

### Ryzyka dla tej zmiany (PRD)

- **FR-014 dotyka zdublowanej logiki.** `app/Support/MonthRemainingCalculator.php` i `resources/js/lib/month-remaining-calculator.ts` mają te same metody i parametry, a `hand-entry-month-remaining-preview.ts` i `import-month-remaining-warnings.ts` budują na kopii TS (razem 749 linii). Testy ma tylko strona PHP. Dodanie „do wydania” bez decyzji o miejscu liczenia utworzy trzecią wersję reguły. Decyzja należy do `/10x-plan`. Reguła „Month logic lives twice” chroni do tego czasu. Metod nie porównywano linia po linii.
- **FR-012 ma warunek wstępny.** `.ai/rules/support.md` wymaga dopisania terminu i progów nowego powiadomienia typu `warning` do `CONTEXT.md` przed kodem. Wszystkie obecne powiadomienia mają poziom `exceeded`, a „tempo za szybkie” i „brak planu” mogą być pierwszymi typu `warning`.
- **Reguły nie obejmują `app/Support` poza `DashboardNotices.php`.** Tam trafi większość nowych obliczeń. Kompensacja: reguła „Money” z zakresem na cały backend.

### Recommended Instruction File Additions

Wklej poniższe bloki do `AGENTS.md`, poza blokiem `<laravel-boost-guidelines>`, bo ten blok jest generowany przez Boost. Polecenia nie zostały tu uruchomione, bo ten worktree nie ma `vendor/` ani działającego Sail. Komendę z sekcji „Test database” warto sprawdzić przy pierwszym użyciu.

```markdown
## Project facts

- Target runtime is PHP 8.5 (CI, Sail, production). Locked dependencies need at least PHP 8.4.1, and the host CLI is 8.4.1. Do not use PHP 8.5-only syntax or functions until the host matches.
- Run project commands through Sail: `./vendor/bin/sail artisan test --compact`, `./vendor/bin/sail composer types:check`, `./vendor/bin/sail npm run types:check`. If you use the host CLI instead, say so in your report.
- npm is the only JS package manager (`package-lock.json`, `npm ci`). Ignore `pnpm-workspace.yaml`.
- Docs describe more than the code. Before relying on a term from `CONTEXT.md`, `.docs/prd.md` or `.docs/specs/`, confirm that the code exists. Agenda, Cash Expectation, Reserve and Year Close are not implemented on `main`.

## Fresh checkout or worktree

`vendor/`, `node_modules/`, `.env` and the generated Wayfinder directories (`resources/js/actions`, `resources/js/routes`, `resources/js/wayfinder`) are not in Git. Before running checks, follow the CI order: `composer install`, copy `.env.example` to `.env`, `php artisan key:generate`, `npm ci`, `npm run build`. Do not edit generated Wayfinder files.

## When Boost tools are not available

Boost MCP is configured for Cursor only. If `search-docs`, `database-query`, `database-schema`, `get-absolute-url`, `browser-logs` or `record-rule` are not in your tool list:

- Package docs: use the versioned official docs: `laravel.com/docs/13.x`, `inertiajs.com/docs/v3`, `pestphp.com/docs`. Check the installed version first with `composer show <package>` or `package.json`.
- Package skills: read `.cursor/skills/<skill-name>/SKILL.md` directly.
- Schema: read `database/migrations/`.
- Durable rules: edit `.ai/rules/<area>.md` and add its row to `.ai/rules/index.md`.
- `.ai/rules/boost` does not exist. Skip that instruction until the directory is restored.

## Test database

`phpunit.xml` defaults to in-memory SQLite. CI and production use PostgreSQL, and CI overrides the default through environment variables. A green SQLite run does not prove a change works on PostgreSQL. For migrations, raw SQL, date or JSON functions, and behavior that depends on constraints, run the affected tests on PostgreSQL:

`docker compose exec -u sail -e DB_CONNECTION=pgsql -e DB_HOST=pgsql -e DB_DATABASE=testing laravel.test php artisan test --compact <path>`

## Money

Amounts are integers in minor units: `*_minor_units` columns (`bigint`) and `int` in PHP. Never use `float` for money. Parse user input with `App\Support\MinorUnits`. In Vue, follow `.ai/rules/js.md`. Add a test for rounding-sensitive calculations.

## Inertia props contract

Props are PHP arrays in controllers and hand-written TypeScript types in `defineProps<>`. Nothing checks that they agree, because `vue-tsc` cannot see PHP. When you add, rename or change a prop, change both sides in the same commit and assert the prop with `assertInertia` in a Feature test.

## Routes in TypeScript

Wayfinder (`laravel/wayfinder` 0.1.x, beta) generates route helpers at build time. Import them from `@/actions/` or `@/routes/` and never hard-code URLs. Copy the pattern from an existing page and read `.cursor/skills/wayfinder-development/SKILL.md` before using a new option. After changing routes, run `npm run build` before `npm run types:check`.

## Frontend toolchain

The toolchain is Vite+ (`vp`), pinned at 0.3.0. There is no ESLint, Prettier or Vitest setup, so do not run or add those tools.

- Format and lint: `npm run check`. Autofix: `npm run check:fix`. Warnings fail the run (`denyWarnings`).
- Vue template types: `npm run types:check`. CI runs both this and `npm run check`.
- Config lives in `vite.config.ts` under `lint` and `fmt`.
- Confirm options with `npx vp --help`, because online docs may describe a newer version.

## Month logic lives twice

`app/Support/MonthRemainingCalculator.php` and `resources/js/lib/month-remaining-calculator.ts` carry the same calculation. `hand-entry-month-remaining-preview.ts` and `import-month-remaining-warnings.ts` build on the TS copy. Only the PHP side has tests, and the frontend has no test runner configured. Change both copies together. Compute new month readings on the server and pass them as props; add a TS copy only when a preview must react before submit, and state the reason in the plan.
```

Aby udostępnić Boost w Claude Code, dodaj w katalogu projektu `.mcp.json`. Plik działa tylko tam, gdzie zainstalowano `vendor/`:

```json
{
    "mcpServers": {
        "laravel-boost": {
            "command": "php",
            "args": ["artisan", "boost:mcp"]
        }
    }
}
```

## Summary

**Werdykt: gotowy po kompensacji.** Stos dobrze wspiera pracę agentów, a kod ma mocne zabezpieczenia: PHPStan na poziomie 7 bez wyjątków, TypeScript strict, spójne konwencje Laravela i pełną kontrolę w CI. Zmiana stosu nie jest potrzebna.

**Mocne strony:** dyscyplina typów, rozbudowany słownik domeny, 29 ADR-ów i testy backendu na PostgreSQL w CI.

**Główne luki:** instrukcje nie mają faktów o projekcie i zależą od narzędzi skonfigurowanych tylko dla Cursora, a dwa narzędzia (Vite+, Wayfinder) są słabo znane agentom. Te luki da się zamknąć wklejeniem powyższych reguł.

**Do sprawdzenia w `/10x-health-check`:**

- `vite-plus` 0.3.0 względem wydanej wersji 1.0,
- `php: ^8.3` w `composer.json` przy faktycznym minimum 8.4.1,
- `laravel/chisel` w zależnościach produkcyjnych bez odwołań w `app`, `config`, `routes`, `bootstrap`, `scripts` i README,
- brak testów frontendu i testów architektury (`arch()`),
- `tests/` poza analizą PHPStan,
- brak automatycznego wdrożenia w CI,
- zaszyte ścieżki w `.cursor/mcp.json`,
- zgodność `pnpm-workspace.yaml` i `.docs/tech-stack.md` ze stanem faktycznym.

**Następny krok:** `/10x-health-check`.
