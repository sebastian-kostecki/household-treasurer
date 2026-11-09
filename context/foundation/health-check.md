---
project: Household Treasurer
checked_at: 2026-11-08T14:20:00+01:00
health_status: needs-attention
context_type: brownfield
language_family: multi
stack_assessment_available: true
checks_run:
    - lockfile
    - dependency_audit
    - outdated_deps
    - test_runner
    - ci_cd
    - configuration
audit_findings:
    critical: 0
    high: 0
    moderate: 0
    low: 0
test_runner_detected: true
ci_provider: GitHub Actions
recommended_fixes: 10
---

Raport zastępuje wersję z 2026-11-04 pod prd-v2 (commit `932aa8d`). Ocenia stan projektu przed zmianą z `context/foundation/prd-v3.md` (zarządzanie kategoriami) i łączy ustalenia z `context/foundation/stack-assessment.md` z 2026-11-08.

Jak sprawdzono: kod projektu nie został zmieniony. W worktree `prd-categories` (`d998364`) wykonano `npm ci` i `npm run build`. Audyty i kontrola nieaktualnych pakietów działały na hoście (PHP 8.4.1, Node 24.21, Composer 2.8.12). Pełna bramka `composer ci:check` działała w obrazie `sail-8.5/app` (PHP 8.5) na tymczasowym PostgreSQL 18, bo stos Sail był wtedy wyłączony; kontener usunięto po przebiegu. Sloppy działał na hoście. Testy E2E działały na stosie Sail po jego uruchomieniu, z danymi logowania E2E z `.env` głównego checkoutu przekazanymi tylko w zmiennych środowiskowych. Historia CI pochodzi z `gh run list` i logów zadań.

## Dependency Health

### Lockfile

Status: present (`composer.lock`, `package-lock.json`)
Package manager: composer, npm

### Security Audit

Tool: `composer audit --format json --locked`, `npm audit --json`
Summary: 0 CRITICAL, 0 HIGH, 0 MODERATE, 0 LOW
Direct vs transitive: brak ustaleń w obu drzewach

Composer: 0 podatności, 0 porzuconych pakietów. npm: 0 podatności w 304 zależnościach. CI uruchamia `composer audit --locked` i ten krok działa: 2026-11-07 zatrzymał dwa przebiegi na `333e9b2` przez trzy porady dla `symfony/serializer` (m.in. CVE-2026-118515), a kolejne commity je usunęły. `npm audit` nie ma w potoku; npm pilnuje tylko Dependabot (`.github/dependabot.yml`, co tydzień, 5-dniowy `cooldown`).

### Outdated Dependencies

Packages with major version gaps: 3 (bezpośrednie, npm). Composer: brak luk w wersjach głównych.

- **typescript**: 5.9.3 → 7.0.2 (2 wersje główne). TypeScript 7 to natywny kompilator; `vue-tsc` 3.3.14 jest najnowszy i nie deklaruje jego obsługi. Do prd-v3 zostań na 5.9.
- **@vueuse/core**: 12.8.2 → 15.0.0 (3 wersje główne).
- **@laravel/passkeys**: 0.2.0 → 0.4.1. Pakiet 0.x, zmiana minor może łamać API.

`@types/node` 24.19 wobec 26.6 to świadomy wybór: typy odpowiadają Node 24 z `.nvmrc` i `engines`. Pomijam `vuedraggable` (`latest` w npm to linia 2.x dla Vue 2) i pakiety natywne `*-win32-*`.

Od poprzedniego raportu zaktualizowano Inertię (klient i serwer 3.11 / 3.7, znów zgodne), `vite-plus` 1.5.0 razem z aliasem `vite` i `overrides.vite`, `@laravel/vite-plugin-wayfinder` 0.1.11 i `@types/node` do `^24`. Do zrobienia zostały łatki: `vue` 3.5.45, `tailwindcss` 4.3.5, `@lucide/vue` 1.70, `tailwind-merge` 3.7, `laravel-vite-plugin` 3.2.1, `phpoffice/phpspreadsheet` 5.11, `spatie/laravel-backup` 10.4.

## Test Suite

Test runner: Pest 5.3 (PHPUnit 13.4), Vitest 5.1 przez `vp test`, Playwright 1.65
Tests found: 971 testów PHP (7318 asercji), 448 testów JS w 40 plikach, 24 testy E2E w 9 plikach
Test execution: passing

Configuration: `phpunit.xml` (domyślnie SQLite w pamięci), `phpunit.nfr.xml` (PostgreSQL, grupa `nfr`), sekcja `test` w `vite.config.ts`, `playwright.config.ts` z `scripts/e2e-server.sh`
Framework: Pest 5.3, PHPUnit 13.4, Vitest 5.1 (`vite-plus` 1.5.0), Playwright 1.65

Wynik `composer ci:check` w obrazie Sail na PostgreSQL 18:

| Krok                   | Wynik                                              |
| ---------------------- | -------------------------------------------------- |
| `npm run check` (`vp`) | ✓ format 299 plików, lint 266 plików, 0 uwag       |
| `npm run check:ui`     | ✓                                                  |
| `npm run types:check`  | ✓ `vue-tsc`                                        |
| `npm test`             | ✓ 448/448 w 40 plikach                             |
| Pint                   | ✓ 368 plików                                       |
| PHPStan (poziom 7)     | ✓ 0 błędów, 243 pliki                              |
| `php artisan test`     | ✓ 969 passed, 2 skipped (161 s)                    |
| `vendor/bin/sloppy ci` | ✓ 216 plików, 0 ustaleń (osobno, na hoście)        |
| `npx playwright test`  | ✓ 24/24 (1,2 min, na stosie Sail)                  |

Pominięte testy dotyczą rejestracji, którą Fortify ma wyłączoną. Niestabilny test z poprzedniego raportu (`OwnTransferTest`, losowa data otwarcia konta) jest naprawiony w `6080320`: `AccountFactory` losuje datę z lat 2000–2020. Jedyna porażka bramki testów w ostatnich 30 przebiegach CI (2026-11-04 09:20, `1d49286`) była właśnie tym testem i poprzedza poprawkę.

Dla prd-v3: nie ma jeszcze żadnego testu migracji (`tests/Feature/Migrations` nie istnieje), a `phpunit.xml` domyślnie używa SQLite. Pierwsza migracja danych nie ma wzorca, na którym agent mógłby się oprzeć (poprawka 3).

## CI/CD

Provider: GitHub Actions
Configuration: `.github/workflows/tests.yml` (+ `.github/dependabot.yml`)

| Stage      | Status | Notes                                                                        |
| ---------- | ------ | ---------------------------------------------------------------------------- |
| Lint       | ✓      | `vp check` (lint + format), Pint, skaner literałów UI, Sloppy                 |
| Test       | ✓      | Pest na PostgreSQL 18 i PHP 8.5, Vitest przez `vp test`; **bez Playwright**   |
| Build      | ✓      | `npm run build` (generuje też Wayfinder)                                      |
| Type check | ✓      | PHPStan poziom 7, `vue-tsc`                                                  |
| Security   | ✓      | `composer audit --locked`, Dependabot; brak `npm audit` w potoku             |

Wykonane poprawki z poprzedniego raportu: cache Composera przez `actions/cache`, `node-version-file: .nvmrc`, `runs-on: ubuntu-24.04`, push na `development` uruchamia CI. Akcje są przypięte do SHA, `permissions: contents: read`, `persist-credentials: false`.

Historia: w ostatnich 30 przebiegach 4 porażki, wszystkie z realnych powodów, żadna niestabilna: Sloppy (7 ustaleń, `fc80578`), dwa razy `composer audit` (`symfony/serializer`, `333e9b2`) i test `OwnTransferTest` sprzed poprawki fabryki. Ostatnie 15 przebiegów jest zielonych.

Wdrożenie jest ręczne (`scripts/deploy.sh`). Linia 36 robi `php artisan migrate --force` bez kopii bazy. Kopia `backup:run` (spatie/laravel-backup) działa z harmonogramu codziennie o 03:00 do `BACKUP_HOST_DIR`, więc przywrócenie po nieudanej migracji cofa do 24 godzin zmian (poprawka 1).

## Configuration

### High severity

- **`scripts/deploy.sh` bez kopii bazy przed migracją** — prd-v3 zaczyna się od scalenia kategorii z kilku budżetów, a takiej migracji nie da się odwrócić przez `down()`. Fix: poprawka 1.

### Medium severity

- **Brak wpisów kompensacyjnych z oceny stosu** — `.ai/rules/migrations.md` i `.ai/rules/categories.md` nie istnieją, a `.ai/rules/snapshot.md` nie ma sekcji o konwersji starszych snapshotów. Fix: poprawka 2.
- **`phpstan.neon` bez `tests/`, brak testów architektury** — bez zmian od poprzedniego raportu. Fix: poprawka 4.

### Low severity

- **Świeży worktree ma puste `E2E_USERNAME` / `E2E_PASSWORD`** — hook `EnterWorktree` kopiuje `.env` z `.env.example`, więc `npx playwright test` pada w setupie logowania. Fix: poprawka 6.
- **`phpunit.xml` z domyślnym SQLite** — skompensowane twardą regułą w `AGENTS.md`, ale bez wzorca testu migracji. Fix: poprawka 3.
- **Zdublowany numer ADR 0008, luka 0025–0028** — 57 plików z numerami do 0062. Ocena stosu podaje „62 ADR”, co nie zgadza się z liczbą plików. Fix: poprawka 9.
- **Earmark opisany w PRD i słowniku, nieobecny w `app/`** — twarda reguła „Docs describe more than the code” go nie wymienia. Fix: poprawka 10.

Obecne: `.editorconfig`, `.gitignore`, `.env.example`, `.env.prod.example`, `.nvmrc` (24) i `engines.node >= 24`, `tsconfig.json` z `strict: true`, `pint.json`, formatowanie i lint przez `vp`, `CLAUDE.md` (teraz w Gicie) z `@AGENTS.md`, `AGENTS.md`, 17 reguł w `.ai/rules`, `context/foundation/glossary.md`.

## Stack Assessment Cross-Reference

Stack assessment: `context/foundation/stack-assessment.md` (2026-11-08)
Agent readiness (from stack-assess): ready-with-compensation

| Quality Gate Gap / ryzyko                                         | Health-Check Finding                                                                                              | Status            |
| ----------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------- | ----------------- |
| Vite+: dane treningowe ✗                                          | `.ai/rules/frontend-toolchain.md` obecna; `vp check` i `vp test` przechodzą; `vite-plus`, `vite` i `overrides` na 1.5.0 | Mitigated     |
| Wayfinder: dane treningowe ✗, dokumentacja ~                      | `.ai/rules/wayfinder.md` obecna; build i `vue-tsc` przechodzą; wtyczka 0.1.11                                    | Mitigated         |
| Granica PHP ↔ Vue ~                                               | `.ai/rules/inertia-props.md` i twarda reguła; Inertia klient 3.11 / serwer 3.7, oba najnowsze                     | Mitigated         |
| Świeżość wersji (Laravel 13, Inertia 3, Pest 5)                   | `CLAUDE.md` w Gicie importuje `@AGENTS.md`; Boost MCP w tej sesji działał                                         | Mitigated         |
| Ryzyko: pierwsza migracja danych (bez reguły)                     | Brak `.ai/rules/migrations.md` i `tests/Feature/Migrations`; lokalnie domyślny SQLite                             | Open              |
| Ryzyko: wdrożenie migruje bez kopii bazy                          | Potwierdzone w `deploy.sh:36`; kopia tylko z harmonogramu o 03:00                                                 | Reinforced        |
| Ryzyko: łączenie lat po nazwie i typie                            | Brak `.ai/rules/categories.md`                                                                                    | Open              |
| Ryzyko: rytm zamiast dwóch flag (expand, switch, contract)        | Reguła z oceny stosu niewdrożona; pary kalkulatorów PHP/TS mają testy i przechodzą                                | Partly mitigated  |
| Ryzyko: snapshot w nowym formacie                                 | `.ai/rules/snapshot.md` bez sekcji o konwersji starszych wersji                                                   | Open              |
| Ryzyko: wykresy                                                   | `.ai/rules/reports.md` obecna; brak biblioteki wykresów, decyzja w `/10x-plan`                                     | Mitigated         |
| Do sprawdzenia: `tests/` poza PHPStan, brak `arch()`              | Potwierdzone                                                                                                     | Open (medium)     |
| Do sprawdzenia: brak automatycznego wdrożenia                     | Potwierdzone; CI nie wdraża                                                                                       | Category B        |
| Do sprawdzenia: zdublowany ADR 0008                               | Potwierdzone; następny wolny numer to 0063                                                                        | Open (low)        |
| Do sprawdzenia: Earmark w PRD, nieobecny w kodzie                 | Potwierdzone (`grep -rli earmark app` pusty)                                                                      | Open (low)        |

Ocena stosu wydała werdykt `ready-with-compensation`, ale żaden z trzech zalecanych wpisów nie jest jeszcze w repozytorium. To główna luka tego raportu: kompensacja istnieje tylko jako tekst w ocenie i agent jej nie zobaczy, bo reguły ładują się z `.ai/rules`, a nie z `stack-assessment.md`.

## Recommended Fixes

### Fix before agent work (Category A)

### 1. Kopia bazy przed migracją w `deploy.sh`

**Impact**: Scalenie kategorii z kilku budżetów to pierwsza nieodwracalna migracja w projekcie. Jeśli zepsuje dane na produkcji, jedyną drogą jest kopia z 03:00, więc znikną zmiany z całego dnia, a agent nie ma jak tego naprawić po fakcie.
**Severity**: high
**Effort**: quick (< 5 min)
**Fix**:

W `scripts/deploy.sh`, przed linią 36 (`Running migrations`), dodaj kopię samej bazy do katalogu kopii:

```bash
echo "==> Backing up the database before migrations..."
$COMPOSE exec -T app php artisan backup:run --only-db --disable-notifications
```

`set -euo pipefail` zatrzyma wdrożenie, gdy kopia się nie uda. W planie prd-v3 opisz przywrócenie z tego pliku (`BACKUP_HOST_DIR`) i sprawdź je raz na kopii lokalnej.

### 2. Wpisy kompensacyjne z oceny stosu

**Impact**: Bez reguł agent użyje modeli Eloquent w migracji, sprawdzi ją na SQLite, napisze `down()`, który udaje rozdzielenie kategorii, zostawi łączenie po nazwie w części z sześciu plików i usunie flagi `is_paced` / `month_item_kind` jednym krokiem.
**Severity**: medium
**Effort**: moderate (15–30 min)
**Fix**:

Skopiuj trzy wpisy z `context/foundation/stack-assessment.md` → „Recommended Instruction File Additions”: utwórz `.ai/rules/migrations.md` i `.ai/rules/categories.md`, dopisz sekcję „An older snapshot is converted, not just defaulted” na końcu `.ai/rules/snapshot.md`, dodaj dwa wiersze do `.ai/rules/index.md`, potem:

```bash
php artisan boost:index-rules
```

Sprawdź, że reguła się ładuje: otwórz w nowej sesji dowolny plik z `database/migrations/` i zobacz, czy treść `migrations.md` trafia do kontekstu.

### 3. Wzorzec testu migracji na PostgreSQL

**Impact**: `tests/Feature/Migrations` nie istnieje, a lokalnie `php artisan test` działa na SQLite. Agent może oddać zieloną migrację, która na PostgreSQL działa inaczej (agregaty, `DISTINCT ON`, ograniczenia), i dowie się o tym dopiero w CI albo na produkcji.
**Severity**: medium
**Effort**: moderate (15–30 min)
**Fix**:

Pierwszy test migracji w prd-v3 niech zapisze wzorzec: plik w `tests/Feature/Migrations/`, który odpala się tylko na PostgreSQL i mówi o tym wprost, np.:

```php
beforeEach(function () {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Data migrations are verified on PostgreSQL (AGENTS.md → Commands).');
    }
});
```

CI działa na PostgreSQL, więc tam test zawsze się uruchomi. Lokalnie uruchamiaj go poleceniem z `AGENTS.md` → Commands (worktree: `docker run ... sail-8.5/app artisan test --compact tests/Feature/Migrations`).

### 4. Testy poza PHPStan i brak testów architektury

**Impact**: prd-v3 przepisze wiele testów (rytm zamiast flag w około 50 plikach). Żadna bramka nie sprawdza w nich typów, a konwencje (`Actions` zapisują, `Support` czyta, brak `float` w kodzie kwot) są tylko w dokumentach.
**Severity**: medium
**Effort**: significant (> 1 hour)
**Fix**:

Dodaj osobny `phpstan.tests.neon` z `paths: [tests/]` na `level: 5` (lub z baseline) i krok w `composer.json` → `types:check`. Potem `tests/Arch/ArchTest.php` z regułami, które `AGENTS.md` już opisuje, np.:

```php
arch()->preset()->php();
arch()->expect('App\Http\Requests')->toExtend('Illuminate\Foundation\Http\FormRequest');
arch()->expect('App\Policies')->toHaveSuffix('Policy');
```

Uruchom je na obecnym kodzie, zanim dodasz je do bramki; gotowe presety, np. `preset()->laravel()`, mogą zgłosić istniejący kod.

### 5. E2E poza CI

**Impact**: 24 testy Playwright przechodzą lokalnie, ale CI ich nie uruchamia. prd-v3 dodaje widok „Kategorie” i raporty, a zmiana kategorii dotyka Operacji, Importu i planu miesiąca. Agent dowie się o złamanym przepływie w przeglądarce tylko wtedy, gdy sam uruchomi E2E.
**Severity**: low
**Effort**: significant (> 1 hour)
**Fix**:

Na teraz: w planie prd-v3 dopisz `npx playwright test` do weryfikacji każdej fazy, która zmienia widoki. Później: osobny job w `tests.yml`, który uruchamia `php artisan serve` na bazie usługi `pgsql` (`scripts/e2e-server.sh` wymaga sieci Sail, więc w CI potrzebny jest inny `webServer.command`, np. przez zmienną `CI`), tworzy użytkownika `artisan user:create` i przekazuje `E2E_USERNAME` / `E2E_PASSWORD` z sekretów.

### 6. Dane logowania E2E w świeżym worktree

**Impact**: Każdy worktree startuje z `.env` z `.env.example`, więc `npx playwright test` pada w `auth.setup.ts`. Agent widzi czerwony E2E bez związku ze swoją zmianą.
**Severity**: low
**Effort**: quick (< 5 min)
**Fix**:

W skrypcie hooka `EnterWorktree` (`.claude/settings.json`) po skopiowaniu `.env` przepisz wiersze `E2E_USERNAME` i `E2E_PASSWORD` z `.env` głównego checkoutu, np. `grep -E '^E2E_(USERNAME|PASSWORD)=' "$MAIN/.env" >> .env` (z usunięciem pustych wierszy z `.env.example`). Albo dopisz ten krok do `context/foundation/agents/worktree-preview.md`.

### 7. `npm audit` poza potokiem

**Impact**: Podatność w zależności npm zatrzyma dziś tylko Dependabot, raz w tygodniu i z 5-dniowym opóźnieniem. Composer ma blokadę w CI, npm nie.
**Severity**: low
**Effort**: quick (< 5 min)
**Fix**:

W `.github/workflows/tests.yml` po kroku `Install Node dependencies` dodaj:

```yaml
- name: Audit Node dependencies
  run: npm audit --audit-level=high
```

### 8. Łatki i pakiety z luką w wersji głównej

**Impact**: Łatki nie blokują agenta. Luki major (`@vueuse/core` 12 → 15, `@laravel/passkeys` 0.2 → 0.4) to osobna praca z łamaniem API; lepiej nie mieszać ich z prd-v3.
**Severity**: low
**Effort**: quick (< 5 min) dla łatek, significant (> 1 hour) dla wersji głównych
**Fix**:

Teraz połącz otwarte PR-y Dependabota (`gh pr list --author app/dependabot`) albo:

```bash
npm update vue tailwindcss @tailwindcss/vite @lucide/vue tailwind-merge laravel-vite-plugin
composer update phpoffice/phpspreadsheet spatie/laravel-backup
```

Wersje główne po prd-v3, każdą osobno. `typescript` 7 dopiero wtedy, gdy `vue-tsc` go oficjalnie obsługuje.

### 9. Zdublowany numer ADR 0008

**Impact**: prd-v3 doda kilka ADR-ów (zastąpienie ADR-0014, zmiany ADR-0016, ADR-0033, ADR-0034). Odwołanie „ADR 0008” jest niejednoznaczne, a luka 0025–0028 sprawia, że liczba plików nie mówi, jaki numer jest wolny.
**Severity**: low
**Effort**: moderate (15–30 min)
**Fix**:

Zmień numer jednego z plików 0008 (`0008-cross-import-own-transfer-pair.md`, `0008-user-domain-snapshots-via-artisan.md`) na 0063 przez `git mv` i popraw odwołania (`grep -rn "0008" AGENTS.md .ai context`). Nowe ADR z prd-v3 numeruj od 0064. Albo zostaw numery i dopisz wyjaśnienie w `context/foundation/adr`. Przy okazji popraw w `stack-assessment.md` liczbę ADR (57 plików, nie 62).

### 10. Earmark poza listą terminów bez kodu

**Impact**: PRD i słownik opisują Earmark, a w `app/` go nie ma. Twarda reguła „Docs describe more than the code” wymienia Agendę, Cash Expectation, Reserve i Year Close, ale nie Earmark, więc agent może budować na nieistniejącym module.
**Severity**: low
**Effort**: quick (< 5 min)
**Fix**:

W `AGENTS.md` (linia 10) dopisz Earmark do listy: „Agenda, Cash Expectation, Earmark, Reserve and Year Close are not on `main`.”

### Addressed in upcoming lessons (Category B)

### Brak automatycznego wdrożenia

**Lesson**: [Sprint Zero z Agentem: infrastruktura, walking skeleton i pierwszy deploy (M1L5)](https://platforma.przeprogramowani.pl/external/10xdevs-3/m1-l5)
**What you'll do there**: połączysz CI z wdrożeniem, które dziś działa ręcznie przez `scripts/deploy.sh` i `docker-compose.prod.yml`; kopia przed migracją (poprawka 1) przejdzie wtedy do potoku wdrożenia.

`CLAUDE.md` z poprzedniego raportu jest już w Gicie i importuje `@AGENTS.md`, więc ten punkt jest zamknięty.

## Summary

Health status: needs-attention

Projekt jest w bardzo dobrym stanie technicznym. Audyty Composera i npm są czyste, wszystkie bramki przechodzą na PostgreSQL i PHP 8.5 (969 testów PHP, 448 testów JS, 24 testy E2E, PHPStan i Sloppy bez uwag), a wszystkie poprawki CI z poprzedniego raportu są wykonane. Porażki CI z ostatnich dni to realne problemy wykryte przez bramki, a nie niestabilne testy. Werdykt nie wynika ze stanu kodu, tylko z gotowości na prd-v3. Wdrożenie migruje bez świeżej kopii bazy, a kompensacja z oceny stosu (reguły migracji danych, tożsamości kategorii i konwersji snapshotu) jest jeszcze tylko tekstem w `stack-assessment.md`, więc agent jej nie zobaczy.

Next step: wykonaj poprawki 1 i 2 (razem około pół godziny), poprawkę 3 zostaw pierwszej fazie planu prd-v3, potem przejdź do onboardingu agentów i `/10x-plan`.
