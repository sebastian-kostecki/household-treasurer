---
project: Household Treasurer
checked_at: 2026-11-04T13:26:40+01:00
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
recommended_fixes: 11
---

Raport zastępuje wersję z 2026-11-01 (commit `2e580a4`). Wszystkie poprawki tamtego raportu zostały wykonane, a audyty są czyste. Ta wersja ocenia stan projektu przed zmianą z `context/foundation/prd-v2.md`.

Jak sprawdzono: projekt nie został zmieniony. Audyty i kontrola nieaktualnych pakietów działały na hoście (PHP 8.4.1, Node 24.21). Pełna bramka `composer ci:check` działała w Sail (PHP 8.5) na PostgreSQL, w bazie `testing`, na gałęzi `development` (czysta, `b72ef6d`). Historia CI pochodzi z `gh run list`.

## Dependency Health

### Lockfile

Status: present (`composer.lock`, `package-lock.json`)
Package manager: composer, npm

`pnpm-workspace.yaml` już nie istnieje. W repozytorium jest jeden menedżer pakietów JS.

### Security Audit

Tool: `composer audit --format json`, `npm audit --json`
Summary: 0 CRITICAL, 0 HIGH, 0 MODERATE, 0 LOW
Direct vs transitive: brak ustaleń w obu drzewach

Composer: 0 podatności, 0 porzuconych pakietów. npm: 0 podatności. CI uruchamia `composer audit --locked`, a Dependabot (`.github/dependabot.yml`) co tydzień sprawdza Composer, npm i GitHub Actions z 5-dniowym `cooldown`.

### Outdated Dependencies

Packages with major version gaps: 4 (bezpośrednie, npm). Composer: brak luk w wersjach głównych.

- **typescript**: 5.9.3 → 7.0.2 (2 wersje główne). TypeScript 7 to natywny kompilator. Przed aktualizacją sprawdź, czy `vue-tsc` 3.3 go obsługuje. Do prd-v2 zostań na 5.9.
- **@vueuse/core**: 12.8.2 → 15.0.0 (3 wersje główne).
- **@types/node**: 22.20.1 → 26.6.6. Ważniejsze od luki jest to, że typy opisują Node 22, a CI i host używają Node 24. Wystarczy przejść na `^24`.
- **@laravel/passkeys**: 0.2.0 → 0.4.0. Pakiet 0.x, więc zmiana minor może łamać API.

Pomijam `vuedraggable` (`latest` w npm to linia 2.x dla Vue 2, projekt używa 4.1) oraz pakiety natywne `*-win32-*`, które dotyczą innej platformy.

Bez luki w wersji głównej, ale istotne dla tej zmiany:

- `@inertiajs/vue3` i `@inertiajs/vite` 3.9.1 → 3.11.0, `inertiajs/inertia-laravel` 3.5.2 → 3.7.0. Strona klienta i serwera rozjechały się o dwie wersje minor.
- `vite-plus` 1.4.0 → 1.5.0. Musi iść razem z aliasem `vite` i `overrides.vite` (reguła „Frontend toolchain”).
- `@laravel/vite-plugin-wayfinder` 0.1.10 → 0.1.11.
- Łatki: `vue` 3.5.44, `tailwindcss`/`@tailwindcss/vite` 4.3.4, `larastan` 3.14, `pest` 5.3.5, `sloppy` 1.8.2.

## Test Suite

Test runner: Pest 5.3 (PHPUnit 13.4) i Vitest 5 przez `vp test`
Tests found: 879 testów PHP (6713 asercji), 172 testy JS w 20 plikach
Test execution: failing (1 niestabilny test, reszta przechodzi)

Configuration: `phpunit.xml` (domyślnie SQLite w pamięci), `phpunit.nfr.xml` (PostgreSQL, grupa `nfr`), sekcja `test` w `vite.config.ts`
Framework: Pest 5.3.4, PHPUnit 13.4, Vitest 5 (`vite-plus` 1.4.0)

Wynik `composer ci:check` w Sail na PostgreSQL (3 min 39 s):

| Krok                      | Wynik                                          |
| ------------------------- | ---------------------------------------------- |
| `npm run check` (`vp`)    | ✓ format 262 plików, lint 200 plików, 0 uwag   |
| `npm run check:ui`        | ✓                                              |
| `npm run types:check`     | ✓ `vue-tsc`                                    |
| `npm test`                | ✓ 172/172                                      |
| Pint                      | ✓ 330 plików                                   |
| PHPStan (poziom 7)        | ✓ 0 błędów                                     |
| `php artisan test`        | ✗ 876 passed, 2 skipped, **1 failed** (147 s)  |

Pominięte testy dotyczą rejestracji, którą Fortify ma wyłączoną. Testy wydajności `tests/Feature/Nfr` przeszły, bo bramka działała na PostgreSQL.

**Niestabilny test.** `Tests\Feature\OwnTransferTest::test_own_transfer_does_not_change_category_execution` zgłosił „The transfer date cannot be earlier than either account opening date.” Przyczyna: `database/factories/AccountFactory.php:29` ustawia `'opening_date' => fake()->date()`, czyli losową datę od 1970 roku do dziś. Test tworzy dwa konta bez daty otwarcia i wysyła przelew z `2026-03-01`. Gdy Faker wylosuje datę późniejszą niż 2026-03-01, walidacja odrzuca przelew. Dziś zdarza się to w około 2,4% uruchomień i z każdym dniem częściej, bo przedział dat po 2026-03-01 rośnie. W tym samym pliku jest kilka podobnych testów, a sześć innych plików tworzy konta bez `opening_date`. W CI w ciągu ostatnich 60 przebiegów były 4 porażki. Jedna z nich to krok „Run CI checks” w merge'u PR #190 na `main`, choć PR przeszedł. Logi tego przebiegu nie są już dostępne, więc nie da się potwierdzić, że winny był ten sam test.

## CI/CD

Provider: GitHub Actions
Configuration: `.github/workflows/tests.yml` (+ `.github/dependabot.yml`)

| Stage      | Status | Notes                                                                    |
| ---------- | ------ | ------------------------------------------------------------------------ |
| Lint       | ✓      | `vp check` (lint + format), Pint, skaner literałów UI, Sloppy             |
| Test       | ✓      | Pest na PostgreSQL 18 i PHP 8.5, Vitest przez `vp test`                  |
| Build      | ✓      | `npm run build` (generuje też Wayfinder)                                  |
| Type check | ✓      | PHPStan poziom 7, `vue-tsc`                                              |
| Security   | ✓      | `composer audit --locked`, Dependabot; brak `npm audit` w potoku         |

Akcje są przypięte do SHA, `permissions: contents: read`, `persist-credentials: false`. Uwagi z przebiegów:

- **`cache: composer` nie działa.** `shivammathur/setup-php` zgłasza „Unexpected input(s) 'cache'”. Ta akcja nie ma takiego wejścia, więc zależności Composera instalują się bez cache przy każdym przebiegu.
- **Etykieta `ubuntu-latest`** przechodzi na Ubuntu 26 od 2026-10-19 (adnotacja w przebiegach). Obraz runnera może się zmienić bez zmiany w repozytorium.
- **Wyzwalacze:** `push` tylko na `main` i `pull_request`. Push na `development` bez otwartego PR nie uruchamia CI.
- **Wdrożenie** jest ręczne (`scripts/deploy.sh`), CI go nie wykonuje.

## Configuration

### High severity

Brak.

### Medium severity

- **`phpstan.neon` bez `tests/`** — PHPStan nie sprawdza testów, a w projekcie nie ma testów architektury (`arch()`). Agent może napisać test z błędnym typem albo złamać układ `Actions`/`Support` i żadna bramka tego nie złapie. Fix: patrz poprawka 2.

### Low severity

- **Brak przypiętej wersji Node** (`engines` w `package.json`, `.nvmrc`) — CI używa 24, ale nic tego nie wymusza lokalnie. Fix: poprawka 6.
- **`phpunit.xml` z domyślnym SQLite** — zielony przebieg na hoście nic nie mówi o PostgreSQL. Skompensowane regułą „Test database” w `AGENTS.md`, a CI działa na PostgreSQL.
- **Zdublowany numer ADR 0008** (`0008-cross-import-own-transfer-pair.md`, `0008-user-domain-snapshots-via-artisan.md`), luka 0025–0028. Fix: poprawka 9.

Obecne: `.editorconfig`, `.gitignore` (obejmuje `.env`, `.env.prod`, `.env.production`), `.env.example`, `.env.prod.example`, `tsconfig.json` z `strict: true`, `pint.json`, formatowanie i lint przez `vp` (zamiast Prettiera i ESLinta), `AGENTS.md`, `.ai/rules`, `context/foundation/glossary.md`.

## Stack Assessment Cross-Reference

Stack assessment: `context/foundation/stack-assessment.md` (2026-11-04)
Agent readiness (from stack-assess): ready-with-compensation

| Quality Gate Gap                                  | Health-Check Finding                                                                                      | Status            |
| ------------------------------------------------- | --------------------------------------------------------------------------------------------------------- | ----------------- |
| Vite+: dane treningowe ✗                          | Reguła „Frontend toolchain” obecna; `vp check` i `vp test` przechodzą; `vite-plus` 1.4.0 wobec 1.5.0       | Mitigated         |
| Wayfinder: dane treningowe ✗, dokumentacja ~      | Reguła „Wayfinder” obecna; build i `vue-tsc` przechodzą; wtyczka 0.1.10 wobec 0.1.11                       | Mitigated         |
| Granica PHP ↔ Vue ~                               | Reguła „Inertia props contract”; `assertInertia` w testach; Inertia klient 3.9 / serwer 3.5, obie za najnowszą | Mitigated      |
| Świeżość wersji (Laravel 13, Inertia 3, Pest 5)   | `CLAUDE.md` importuje `@AGENTS.md`, więc wytyczne Boost i `search-docs` są widoczne w sesji               | Mitigated         |
| Luka 1: Claude Code nie wczytuje `AGENTS.md`      | Lokalnie naprawione (`@AGENTS.md` w pierwszej linii `CLAUDE.md`), ale `CLAUDE.md` nadal jest w `.gitignore` | Partly mitigated |
| Zalecane wpisy w `AGENTS.md` (Charts and reports, Snapshot format, Domain changes come before code) | Wszystkie trzy sekcje są w `AGENTS.md`                         | Mitigated         |
| Ryzyko prd-v2: kwoty raportu równe co do grosza   | Bramka testów działa na PostgreSQL, testy Nfr przechodzą; niestabilna fabryka kont uderza w testy dat operacji, a raporty będą na nich polegać | Reinforced |
| Ryzyko prd-v2: wydajność raportu rocznego         | `NfrPerf01Test` i `NfrPerf03ImportPreviewTest` przechodzą na PostgreSQL, CI je uruchamia                   | Mitigated         |
| Kontrola do health-check: `tests/` poza PHPStan, brak `arch()` | Potwierdzone                                                                                 | Reinforced        |
| Kontrola do health-check: zdublowany ADR 0008     | Potwierdzone; następny wolny numer to 0035                                                                 | Open (low)        |

## Recommended Fixes

### Fix before agent work (Category A)

### 1. Niestabilny test przez losową datę otwarcia konta

**Impact**: Bramka `ci:check` czasem pada na teście, którego zmiana nie dotyczyła. Agent widzi czerwony wynik i zaczyna „naprawiać” kod, który działa, albo uczy się ignorować porażki. Raporty z prd-v2 będą tworzyć konta i operacje z ustalonymi datami, więc takich testów przybędzie.
**Severity**: high
**Effort**: quick (< 5 min)
**Fix**:

W `database/factories/AccountFactory.php` zastąp losową datę z całego przedziału datą sprzed dat używanych w testach:

```php
'opening_date' => fake()->dateTimeBetween('2000-01-01', '2020-12-31')->format('Y-m-d'),
```

Potem uruchom testy kont i przelewów na PostgreSQL kilka razy, np. `--repeat=20` dla `tests/Feature/OwnTransferTest.php`.

### 2. Testy poza PHPStan i brak testów architektury

**Impact**: Testy to połowa kodu, który agent pisze, a żadna bramka nie sprawdza w nich typów. Konwencje (`Actions` zapisują, `Support` czyta, brak `float` w kodzie kwot) są tylko w dokumentach, więc agent może je złamać bez czerwonego wyniku.
**Severity**: medium
**Effort**: significant (> 1 hour)
**Fix**:

Dodaj `tests/` do `paths` w `phpstan.neon` na niższym poziomie albo z baseline, np. w osobnym pliku `phpstan.tests.neon` z `level: 5`. Następnie dodaj `tests/Arch/ArchTest.php` z kilkoma regułami Pest, np.:

```php
arch()->preset()->php();
arch()->expect('App\Http\Requests')->toExtend('Illuminate\Foundation\Http\FormRequest');
arch()->expect('App\Policies')->toHaveSuffix('Policy');
```

Reguły dobierz do tego, co `AGENTS.md` już opisuje, i uruchom je na obecnym kodzie, zanim dodasz je do bramki. Gotowe presety, np. `preset()->laravel()`, mogą zgłosić istniejący kod.

### 3. Aktualizacje minor i łatki przed prd-v2

**Impact**: Inertia po stronie klienta (3.9) i serwera (3.5) jest o dwie wersje minor za najnowszą, a `search-docs` pokazuje dokumentację najnowszej. Agent może użyć API, którego zainstalowana wersja jeszcze nie ma.
**Severity**: medium
**Effort**: moderate (15–30 min)
**Fix**:

Najpierw połącz otwarte PR-y Dependabota, jeśli są (`gh pr list --author app/dependabot`). Pozostałe zaktualizuj ręcznie:

```bash
composer update inertiajs/inertia-laravel larastan/larastan pestphp/pest heyosseus/sloppy -W
npm install @inertiajs/vue3@^3.11 @inertiajs/vite@^3.11 @laravel/vite-plugin-wayfinder@^0.1.11 vue@^3.5.44 tailwindcss@^4.3.4 @tailwindcss/vite@^4.3.4
```

`vite-plus` 1.5.0 aktualizuj osobno, razem z aliasem `vite` i `overrides.vite`, i sprawdź `npx vp --help`. Po wszystkim uruchom pełną bramkę w Sail.

### 4. Pakiety z luką w wersji głównej

**Impact**: `@types/node` dla Node 22 przy Node 24 może przepuścić lub zablokować API, którego runtime nie ma. Pozostałe luki nie blokują agenta teraz, ale każda zmiana major to osobna praca z łamaniem API.
**Severity**: low
**Effort**: significant (> 1 hour)
**Fix**:

Teraz: `npm install -D @types/node@^24`. Później, każdą osobno: `@vueuse/core` 12 → 15 (sprawdź listę zmian, których composables używa projekt), `@laravel/passkeys` 0.2 → 0.4 (razem z `laravel/passkeys`, jeśli jest po stronie PHP). `typescript` 7 dopiero wtedy, gdy `vue-tsc` go oficjalnie obsługuje.

### 5. Cache Composera w CI nie działa

**Impact**: Każdy przebieg CI instaluje zależności od zera. Pętla informacji zwrotnej dla agenta przy PR trwa dłużej.
**Severity**: low
**Effort**: quick (< 5 min)
**Fix**:

W `.github/workflows/tests.yml` usuń `cache: composer` z kroku `Setup PHP` i dodaj przed `Install Composer dependencies` krok `actions/cache` przypięty do SHA, z kluczem `composer-${{ hashFiles('composer.lock') }}` i ścieżką z `composer config cache-files-dir`.

### 6. Wersja Node nie jest przypięta

**Impact**: Lokalnie albo w worktree może działać inny Node niż w CI, a różnice wyjdą dopiero w potoku.
**Severity**: low
**Effort**: quick (< 5 min)
**Fix**:

```bash
echo "24" > .nvmrc
npm pkg set engines.node=">=24"
```

W `tests.yml` możesz wtedy użyć `node-version-file: .nvmrc`.

### 7. Etykieta `ubuntu-latest` zmienia obraz runnera

**Impact**: Przejście na Ubuntu 26 może zmienić wynik CI bez żadnej zmiany w kodzie. Agent będzie szukał przyczyny w swojej zmianie.
**Severity**: low
**Effort**: quick (< 5 min)
**Fix**:

W `tests.yml` ustaw `runs-on: ubuntu-24.04` i podnieś ją świadomie, osobnym commitem.

### 8. Push na `development` nie uruchamia CI

**Impact**: Commity na `development` są sprawdzane dopiero po otwarciu PR. Do tego czasu agent ma tylko lokalną bramkę.
**Severity**: low
**Effort**: quick (< 5 min)
**Fix**:

Dodaj `development` do `on.push.branches` w `tests.yml` albo otwieraj PR od razu (wtedy `concurrency` anuluje starsze przebiegi).

### 9. Zdublowany numer ADR 0008

**Impact**: Reguła „Domain changes come before code” każe brać następny wolny numer. Duplikat i luka 0025–0028 sprawiają, że numer ADR nie identyfikuje jednej decyzji, a odwołanie „ADR 0008” jest niejednoznaczne.
**Severity**: low
**Effort**: moderate (15–30 min)
**Fix**:

Zmień numer jednego z plików 0008 na 0035 (`git mv`) i popraw odwołania (`grep -rn "0008" AGENTS.md context`). Nowe ADR z prd-v2 numeruj od 0036. Albo zostaw numery bez zmian i dopisz wyjaśnienie w `context/foundation/adr`.

### Addressed in upcoming lessons (Category B)

### `CLAUDE.md` jest poza Gitem

**Lesson**: [Agent Onboarding: Agents.md, AI Rules i feedback loops (M1L4)](https://platforma.przeprogramowani.pl/external/10xdevs-3/m1-l4)
**What you'll do there**: zdecydujesz, które pliki instrukcji są wspólne w repozytorium. Lokalny `CLAUDE.md` importuje już `@AGENTS.md`, ale świeży klon tej linii nie dostanie, dopóki plik jest w `.gitignore` (linia 35).

### Brak automatycznego wdrożenia

**Lesson**: [Sprint Zero z Agentem: infrastruktura, walking skeleton i pierwszy deploy (M1L5)](https://platforma.przeprogramowani.pl/external/10xdevs-3/m1-l5)
**What you'll do there**: połączysz CI z wdrożeniem, które dziś działa ręcznie przez `scripts/deploy.sh` i `docker-compose.prod.yml`.

## Summary

Health status: needs-attention

Projekt jest w dobrym stanie. Audyty Composera i npm są czyste, Dependabot pilnuje trzech ekosystemów, a CI sprawdza lint, typy, build, testy na PostgreSQL i zależności PHP. Lokalnie przechodzi wszystko poza jednym testem: PHPStan, `vue-tsc`, 172 testy JS i 876 z 879 testów PHP. Jedyne ustalenie, które realnie przeszkadza agentowi, to niestabilny test przez losową datę w `AccountFactory`. Bramka czasem pada bez związku ze zmianą, a raporty z prd-v2 dodadzą więcej testów opartych na datach. Pozostałe ustalenia są drobne: testy poza PHPStan, Inertia i Vite+ o wersję lub dwie za najnowszą, kilka szczegółów konfiguracji CI.

Next step: napraw poprawkę 1 (jedna linia w fabryce), rozważ 2 i 3 przed `/10x-plan` dla prd-v2, a potem przejdź do onboardingu agentów.
