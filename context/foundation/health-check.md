---
project: Household Treasurer
checked_at: 2026-11-01T18:49:10+01:00
health_status: critical-issues
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
    critical: 5
    high: 8
    moderate: 10
    low: 0
test_runner_detected: true
ci_provider: GitHub Actions
recommended_fixes: 12
---

Po tym raporcie wykonano wszystkie poprawki od 1 do 10 i włączono alerty Dependabota. Nadpisanie `tinypool` z poprawki 2 usunięto, bo zastąpiła je migracja `vite-plus` do 1.4.0. Stan audytu po nich: Composer 0 podatności, npm 0 podatności. Otwarte zostają tylko pozycje z kolejnych lekcji. Polecenie z poprawki 4 zastąpiono uruchomieniem obrazu Sail na worktree, bo daje PHP 8.5. Reszta raportu opisuje stan sprzed poprawek.

Sposób sprawdzenia: projekt nie został zmieniony. Testy, analiza i próby poprawek działały na tymczasowych kopiach worktree, uruchamianych przez host CLI (PHP 8.4.1, Node 24.21). Sail nie nadawał się do tego, bo montuje główny checkout, a ten jest na gałęzi `development`. Wyniki na PHP 8.5 i PostgreSQL pochodzą z CI (patrz sekcja CI/CD).

## Dependency Health

### Lockfile

Status: present (`composer.lock`, `package-lock.json`)
Package manager: composer, npm

`pnpm-workspace.yaml` jest pozostałością bez pliku blokady pnpm. Wpływa jednak na narzędzia (patrz poprawka 6).

### Security Audit

**Composer**

Tool: `composer audit --locked --format json`
Summary: 0 CRITICAL, 5 HIGH, 3 MODERATE, 0 LOW
Direct vs transitive: 4 bezpośrednie (`phpoffice/phpspreadsheet`), 4 przechodnie. Wszystkie w zależnościach produkcyjnych.

**npm**

Tool: `npm audit --json`
Summary: 5 CRITICAL, 3 HIGH, 7 MODERATE, 0 LOW
Direct vs transitive: 4 bezpośrednie (`concurrently`, `vite-plus`, `vite`, `vue-tsc`), 11 przechodnich.

Narzędzia liczą inaczej: Composer podaje liczbę advisory, a npm liczbę pakietów, także tych objętych tylko pośrednio. 5 CRITICAL w npm ma 2 źródła: `shell-quote` i `tinypool`. Tryb `--omit=dev` zgłasza 2 HIGH (`vite`, `source-map-js`), ale obraz produkcyjny kopiuje tylko `public/build`, bez `node_modules`. Podatności npm dotyczą więc dewelopmentu i builda, a podatności Composera działającej aplikacji.

#### CRITICAL findings

- **shell-quote** 1.9.0 (przez `concurrently` 10.0.5) — GHSA-pqg4-j6r4-53mv: wstrzyknięcie polecenia w `quote()`. Fix: `npm audit fix`.
- **tinypool** 2.1.0 (przez `oxfmt` i `vite-plus` 0.3.0) — GHSA-5gmw-xhrv-c9v3, GHSA-85c8-ppgw-ccpr: prototype pollution prowadzące do RCE. Fix: nadpisanie do 2.2.0 albo `vite-plus` 1.x.

#### HIGH findings

- **phpoffice/phpspreadsheet** 5.9.0 — CVE-2026-108357, CVE-2026-108358: obejście XmlScannera dające XXE i DoS. Osiągalne po zalogowaniu przez Import XLSX (`IOFactory::load` w `BnpParibasXlsxParser`). Fix: update to 5.10.0.
- **phpoffice/phpspreadsheet** 5.9.0 — CVE-2026-108359: DoS w czytniku ODS. Reguła `mimes:xlsx` ogranicza dostęp do tej ścieżki. Fix: update to 5.10.0.
- **symfony/http-foundation** 8.1.6 — CVE-2026-108082: ReDoS w `HeaderUtils::split()` przez nagłówek `Cache-Control`. Według advisory nie wymaga logowania. Fix: update to 8.1.10.
- **league/commonmark** 2.10.1 — GHSA-3q6v-r5mr-hxv8: DoS w rozszerzeniu tabel. W `app`, `routes` i `resources/views` nie ma wywołań Markdownu. Fix: update to 2.10.7.
- **vite** 8.2.2 — zestaw advisory serwera deweloperskiego i `launch-editor`, m.in. GHSA-9jrq-w75r-8gcw, GHSA-rq7h-c2jc-7f22. Ma znaczenie, bo `vite.config.ts` ustawia `server.host: '0.0.0.0'`. Fix: update to 8.3.5.
- **source-map-js** 1.2.1 — GHSA-68fv-2mgg-jv7q: DoS pętli zdarzeń. Fix: `npm audit fix`.
- **brace-expansion** 2.1.4 — GHSA-q2hr-2g5m-vwhr, GHSA-qhr7-859c-m2p7, GHSA-6j4f-fj2g-mc7p: DoS. Fix: `npm audit fix`.

#### MODERATE findings

- **phpoffice/phpspreadsheet** 5.9.0 — CVE-2026-108360: obejście listy dozwolonych adresów (SSRF) w `Drawing::setPath`.
- **symfony/mime** 8.1.6 — CVE-2026-45070: wstrzyknięcie nagłówka e-mail.
- **league/commonmark** 2.10.1 — GHSA-97jj-33gv-5xf9: obejście `DisallowedRawHtml`.
- **vitest**, **@vitest/mocker**, **@vitest/browser**, **@vitest/browser-preview**: wstrzyknięcie kodu przez mocki, tylko przez `vite-plus` 0.3.0.
- **vue-tsc**, **@vue/language-core**, **minimatch**: DoS w `minimatch`, usuwany przez `vue-tsc` 3.

### Outdated Dependencies

Packages with major version gaps: 5

- **@types/node**: 22.20.1 → 26.6.6 (4 major versions behind). Trzymaj się linii Node z CI, czyli 24.
- **@vueuse/core**: 12.8.2 → 15.0.0 (3 major versions behind)
- **typescript**: 5.9.3 → 7.0.2 (2 major versions behind)
- **vite-plus**: 0.3.0 → 1.4.0 (1 major version behind)
- **vue-tsc**: 2.2.12 → 3.3.14 (1 major version behind)

`vuedraggable` pomijam: `npm outdated` podaje 2.24.3 jako najnowszą wersję, ale to linia Vue 2. Composer nie ma luk wersji głównych. 12 bezpośrednich pakietów ma aktualizacje zgodne z semver, m.in. `laravel/framework` 13.30.1 → 13.39.0 i `pestphp/pest` 5.1.4 → 5.3.5.

## Test Suite

Test runner: Pest 5.1.4 (PHPUnit 13.3)
Tests found: 619 tests w 64 plikach
Test execution: passing (617 zaliczonych, 2 pominięte, 4984 asercje, około 30 s na SQLite)

Configuration: `phpunit.xml`, `phpunit.nfr.xml`
Framework: Pest 5.1 + `pest-plugin-laravel`

- Testy pomijają się warunkowo, gdy funkcja Fortify jest wyłączona (`skipUnlessFortifyHas`). Nie ustalono, które 2 testy były pominięte.
- PostgreSQL: `BudgetMonthRemainingTest` przechodzi z hosta na bazie `testing` (12 z 12).
- Grupa `nfr` jest wykluczona z domyślnego uruchomienia, więc CI nie sprawdza wymagań wydajnościowych.
- Pozostałe bramki z CI uruchomione lokalnie: Pint czysty, PHPStan bez błędów, `vue-tsc` bez błędów, `vp check` pada na formatowaniu jednego pliku (poprawka 3). Po tej poprawce formatowanie i lint przechodzą bez ostrzeżeń.
- Frontend nie ma testów, a `tests/` nie ma testów `arch()`. `vp test` jest dostępne w zainstalowanej wersji, ale nie jest skonfigurowane.

## CI/CD

Provider: GitHub Actions
Configuration: `.github/workflows/tests.yml`

| Stage      | Status | Notes                                                       |
| ---------- | ------ | ----------------------------------------------------------- |
| Lint       | ✓      | `vp check`, Pint przez `composer ci:check`                  |
| Test       | ✓      | Pest na PostgreSQL 18 i PHP 8.5                             |
| Build      | ✓      | `npm run build`                                             |
| Type check | ✓      | PHPStan poziom 7, `vue-tsc`                                 |
| Security   | ✗      | Brak audytu w CI. Dependabot obejmuje tylko GitHub Actions. |

Ostatni przebieg na `main` po pushu (`da18968`, 2026-09-24) zakończył się sukcesem. Od tego commita kod aplikacji, testy i pliki zależności się nie zmieniły, więc ten wynik dalej dotyczy tej gałęzi. Akcje są przypięte do SHA, a checkout ma `persist-credentials: false`.

API GitHuba zgłasza wyłączone alerty Dependabota. Nowe advisory od września nie trafiają więc do repozytorium żadną drogą.

## Configuration

Obecne i poprawne: `.editorconfig`, `.gitignore`, `.env.example`, `tsconfig.json` ze `strict: true`, Pint, formatowanie i lint przez `vp`. Git nie śledzi plików `.env` ani arkusza budżetu. Sprawdzono nazwy plików, a nie ich treść, więc to nie jest skan sekretów.

### High severity

- **`vite.config.ts` / `context/foundation/shape-notes.md`** — `vp check` obejmuje `context/` i odrzuca formatowanie tego pliku, a CI uruchamia ten krok jako pierwszy w `composer ci:check`. Fix: poprawka 3.
- **`AGENTS.md`, sekcje „Test database” i „Project facts”** — polecenia nie działają z worktree. Fix: poprawka 4.

### Medium severity

- **`.github/dependabot.yml`** — brak ekosystemów `composer` i `npm`. Fix: poprawka 5.
- **`pnpm-workspace.yaml`** — plik sprawia, że `vp migrate` przełącza projekt na pnpm. Fix: poprawka 6.
- **`composer.json`** — `php: ^8.3`, a zablokowane pakiety wymagają 8.4.1. Fix: poprawka 6.

### Low severity

- **`composer.json`, `package.json`** — `laravel/chisel` i `concurrently` nie mają żadnych odwołań w kodzie. Fix: poprawka 10.
- **`.docs/tech-stack.md`** — błędnie twierdzi, że Laravel 13 wymaga PHP 8.5 i że testy idą na PostgreSQL, a o Vite+ nie wspomina. Fix: poprawka 10.
- **`phpstan.neon`** — `tests/` poza analizą. Na razie bez zmian: uruchomienie na `tests/` daje 109 błędów, a widoczna próbka to same `TestCall::…()`, czyli brak typowania Pesta, nie błędy w testach.

## Stack Assessment Cross-Reference

Stack assessment: context/foundation/stack-assessment.md
Agent readiness (from stack-assess): ready-with-compensation

| Quality Gate Gap           | Health-Check Finding                                                                               | Status              |
| -------------------------- | -------------------------------------------------------------------------------------------------- | ------------------- |
| Vite+: dane treningowe     | `vite-plus` 0.3.0 ma 3 CRITICAL, a wersja 1.x wymaga migracji. Reguła „Frontend toolchain” obecna. | Reinforced          |
| Vite+: dokumentacja        | Wersja 1.4.0 ma inny formatter i wymaga `vp migrate`. Różnica wersji realnie kosztuje.             | Reinforced          |
| Wayfinder: dane treningowe | Brak podatności, reguła w `AGENTS.md`, wygenerowane pliki działają. Sam krok trwa 83 s na hoście.  | Mitigated           |
| Wayfinder: dokumentacja    | Reguła wskazuje wzorzec z istniejących stron i lokalny skill.                                      | Mitigated           |
| Granica PHP ↔ Vue          | `vue-tsc` czysty, reguła „Inertia props contract” obecna. Nadal nic nie sprawdza zgodności stron.  | Partially mitigated |

Wpisy kompensacyjne z oceny stosu są już w `AGENTS.md`. Świeży worktree przygotowany według sekcji „Fresh checkout or worktree” działa. Jedyny wyjątek to polecenia Sail, które ocena oznaczyła jako nieuruchomione: z worktree kończą się błędem (poprawka 4).

Pozycje przekazane do sprawdzenia:

- `vite-plus` względem 1.x: największy problem, poprawki 2 i 7.
- `php: ^8.3`, `laravel/chisel`, `pnpm-workspace.yaml`, `.docs/tech-stack.md`: potwierdzone, poprawki 6 i 10. `pnpm-workspace.yaml` szkodzi bardziej, niż zakładała ocena.
- Brak testów frontendu i `arch()`: potwierdzone, poprawka 8.
- `tests/` poza PHPStanem: potwierdzone, patrz sekcja Configuration.
- Brak automatycznego wdrożenia i zaszyte ścieżki MCP: potwierdzone, kolejne lekcje.

## Recommended Fixes

### Fix before agent work (Category A)

### 1. Zaktualizuj podatne pakiety w obrębie obecnych wersji

**Impact**: usuwa całe ryzyko w działającej aplikacji, w tym XXE w Imporcie, oraz wszystkie HIGH w narzędziach. Agent dotyka Importu w FR-014, więc ta ścieżka nie powinna zostać podatna.
**Severity**: critical
**Effort**: quick (< 5 min)
**Fix**:

```bash
composer update phpoffice/phpspreadsheet league/commonmark symfony/http-foundation symfony/mime --with-dependencies --no-interaction
npm audit fix
```

Sprawdzone na kopii: `composer audit` czysty, npm schodzi do 3 CRITICAL, 0 HIGH, 7 MODERATE, 619 testów bez zmian. Composer aktualizuje 20 pakietów.

### 2. Nadpisz `tinypool` do czasu migracji `vite-plus`

**Impact**: usuwa pozostałe 3 CRITICAL bez migracji narzędzi. To osłona tymczasowa, którą usuwa poprawka 7.
**Severity**: critical
**Effort**: quick (< 5 min)
**Fix**:

```bash
npm pkg set overrides.tinypool=2.2.0
npm install
```

Sprawdzone na kopii: 0 CRITICAL, 1 HIGH (`vite-plus`), 7 MODERATE, a formatter i lint działają. Pełnego builda z tym nadpisaniem nie uruchamiano.

### 3. Napraw czerwony `vp check`

**Impact**: następny PR z tej gałęzi nie przejdzie CI, bo krok pada przed testami. Agent zobaczy błąd niezwiązany ze swoją zmianą.
**Severity**: high
**Effort**: quick (< 5 min)
**Fix**:

Dodaj `'context/**'` do `fmt.ignorePatterns` w `vite.config.ts`. Kolejne artefakty w `context/` nie będą wtedy blokować CI. Jeśli te pliki mają być formatowane, uruchom zamiast tego `npm run check:fix`.

Sprawdzone na kopii: po dodaniu wzorca `npm run check` przechodzi w całości.

### 4. Popraw polecenia dla worktree w `AGENTS.md`

**Impact**: agenci pracują w worktree, a tam `docker compose exec` kończy się błędem `service "laravel.test" is not running`. `docker exec` do działającego kontenera przetestowałby kod głównego checkoutu, czyli gałęzi `development`, a nie zmianę agenta.
**Severity**: high
**Effort**: quick (< 5 min)
**Fix**:

Dopisz do sekcji „Test database”:

```markdown
In a worktree, the running Sail stack serves the main checkout, not your code. After the fresh-checkout steps, run tests from the host against Sail's PostgreSQL:

`DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=5432 DB_DATABASE=testing DB_USERNAME=sail DB_PASSWORD=password php artisan test --compact <path>`
```

Sprawdzone na kopii: `BudgetMonthRemainingTest`, 12 z 12 na PostgreSQL.

### 5. Włącz stały nadzór nad zależnościami

**Impact**: podatności pojawiły się w ciągu ostatnich tygodni, a nic ich nie zgłosiło. Bez tego kolejne wyjdą dopiero przy ręcznym audycie.
**Severity**: medium
**Effort**: moderate (15–30 min)
**Fix**:

Dodaj do `updates` w `.github/dependabot.yml`:

```yaml
- package-ecosystem: 'composer'
  directory: '/'
  schedule:
      interval: 'weekly'
  cooldown:
      default-days: 5
- package-ecosystem: 'npm'
  directory: '/'
  schedule:
      interval: 'weekly'
  cooldown:
      default-days: 5
```

Włącz alerty w Settings → Advanced Security → Dependabot alerts. Po poprawkach 1 i 2 dodaj krok CI `composer audit --locked`.

### 6. Usuń `pnpm-workspace.yaml` i popraw wymaganie PHP

**Impact**: narzędzia odczytują ten plik jako deklarację pnpm, choć projekt używa npm. `php: ^8.3` obiecuje wersję, na której zablokowane pakiety się nie zainstalują.
**Severity**: medium
**Effort**: quick (< 5 min)
**Fix**:

```bash
git rm pnpm-workspace.yaml
```

Zmień w `composer.json` wpis na `"php": "^8.4.1"`, a potem uruchom `composer update --lock`.

### 7. Zmigruj `vite-plus` do 1.x jako osobną zmianę

**Impact**: zamyka ostatni HIGH i wszystkie MODERATE (kopia: 0 podatności npm) oraz wyrównuje wersję z dokumentacją, z której korzysta agent.
**Severity**: high
**Effort**: significant (> 1 hour)
**Fix**:

Najpierw wykonaj poprawkę 6. Próba na kopii pokazała:

- `npm install -D --save-exact vite-plus@1.4.0` pada na konflikcie z opcjonalnym peerem `@vitest/browser-playwright`. Przechodzi z `--legacy-peer-deps`, a to samo trzeba by rozwiązać dla `npm ci` w CI.
- Build wymaga `npx vp migrate --no-interactive --no-agent --no-hooks`.
- Przy obecnym `pnpm-workspace.yaml` migracja przeszła na pnpm i wpisała `catalog:` do `package.json`. Build przeszedł, ale `vue-tsc` zgłosił błędy typu `page.props.auth is of type unknown`.
- Formatter 1.x oznacza też pliki w `.agents/` i `.cursor/skills/`.

Migracji bez `pnpm-workspace.yaml` nie sprawdzono.

### 8. Dodaj testy logiki miesiąca po stronie TypeScript

**Impact**: FR-014 zmienia ostrzeżenia oparte na kopii TS, która nie ma testów. Agent może rozjechać obie wersje reguły i nic tego nie wykryje.
**Severity**: medium
**Effort**: moderate (15–30 min)
**Fix**:

Decyzja należy do planu FR-014. Jeśli kopia TS zostaje:

```bash
npm pkg set scripts.test="vp test"
```

Dodaj `resources/js/lib/month-remaining-calculator.test.ts` z przypadkami z `tests/Feature/BudgetMonthRemainingTest.php`, dopisz `npm test` do `ci:check` i zaktualizuj regułę „Frontend toolchain”. Tego kroku nie sprawdzono na kopii.

### 9. Zaktualizuj `vue-tsc` do wersji 3

**Impact**: usuwa 3 MODERATE z jednego narzędzia, które agent uruchamia przy każdej zmianie frontendu.
**Severity**: low
**Effort**: quick (< 5 min)
**Fix**:

```bash
npm install -D vue-tsc@3
```

Sprawdzone na kopii: `npm run types:check` bez błędów.

### 10. Usuń pozostałości

**Impact**: nieużywane pakiety zwiększają powierzchnię audytu, a nieprawdziwe zdania w dokumentacji wprowadzają agenta w błąd.
**Severity**: low
**Effort**: quick (< 5 min)
**Fix**:

```bash
composer remove laravel/chisel
npm uninstall concurrently
```

W `.docs/tech-stack.md` popraw linię 8 (PHP 8.5 to wybór projektu, a nie wymóg Laravela), linię 39 (domyślne testy idą na SQLite, PostgreSQL w CI) i dopisz Vite+. Usunięcia pakietów nie sprawdzono na kopii.

### Addressed in upcoming lessons (Category B)

### Boost MCP tylko dla Cursora

**Lesson**: [Agent Onboarding: Agents.md, AI Rules i feedback loops (M1L4)](https://platforma.przeprogramowani.pl/external/10xdevs-3/m1-l4)
**What you'll do there**: uporządkujesz dostęp agentów do narzędzi, w tym brak `.mcp.json` oraz zaszyte ścieżki w `.cursor/mcp.json` i `.ai/mcp/mcp.json`. Do tego czasu sekcja „When Boost tools are not available” w `AGENTS.md` wystarcza.

### Ręczne wdrożenie

**Lesson**: [Sprint Zero z Agentem: infrastruktura, walking skeleton i pierwszy deploy (M1L5)](https://platforma.przeprogramowani.pl/external/10xdevs-3/m1-l5)
**What you'll do there**: zdecydujesz, czy `scripts/deploy.sh` ma zostać ręczny, czy przejść do potoku CI/CD. CI już dziś pokrywa lint, testy, build i typy.

## Summary

Health status: critical-issues

Status wynika z reguły: audyt zgłasza CRITICAL, więc raport nie może być łagodniejszy. Realne ryzyko jest mniejsze, niż sugeruje ta etykieta, bo wszystkie 5 CRITICAL to narzędzia dewelopera, a główny problem aplikacji to HIGH w PhpSpreadsheet na ścieżce Importu. Fundament jest mocny: 619 testów, PHPStan poziom 7, TypeScript strict i pełne CI. Poprawki 1 i 2 zajmują kilka minut i sprowadzają projekt do 0 CRITICAL oraz 1 HIGH, czyli do statusu `needs-attention`.

Next step: wykonaj poprawki 1–4, potem przejdź do onboardingu agentów (M1L4). Poprawkę 7 zaplanuj jako osobną zmianę.
