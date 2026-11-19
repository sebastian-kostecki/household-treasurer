<!-- PLAN-REVIEW-REPORT -->
# Plan Review: Tagi obrazu i szybkie wycofanie

- **Plan**: context/changes/image-rollback-tags/plan.md
- **Mode**: Deep
- **Date**: 2026-11-19
- **Verdict**: REVISE → SOUND po poprawkach
- **Findings**: 2 critical, 2 warnings, 3 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| End-State Alignment | WARNING |
| Lean Execution | PASS |
| Architectural Fitness | WARNING |
| Blind Spots | FAIL |
| Plan Completeness | WARNING |

## Grounding

9/9 paths ✓, 6/6 symbols ✓ (WORKDIR `/var/www/html`, wzorzec `to_regclass`, `production_report_failure`, `LABEL maintainer`, wolny ADR 0107, Progress↔Fazy), brief↔plan ✓. Eksperyment lokalny (Docker 28.3, Compose v2.39): przesunięcie tagu `:prod` + `up -d --no-build` odtwarza kontener, a bez zmiany obrazu go zostawia.

## Findings

### F1 — Sortowanie po .Created nie oddaje kolejności wdrożeń

- **Severity**: ❌ CRITICAL
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: End-State Alignment
- **Location**: Faza 1 §2 — `production_image_tags_to_remove`, `production_image_listing`
- **Detail**: BuildKit nadaje obrazowi, który różni się tylko końcowym `LABEL`, nowe ID, ale `Created` ostatniej warstwy z cache (trzy buildy z różnym REV, ten sam Created). `.dockerignore` wyklucza `context`, `scripts`, `tests`, `.ai`, `.github`, `AGENTS.md`, więc wdrożenie commita z samą dokumentacją remisuje, a przebudowa po `git revert` dostaje starą datę. „3 najnowsze” wybiera wtedy przypadkowo; testy z fikstur tego nie łapią.
- **Fix A ⭐ Recommended**: etykieta `household-treasurer.deployed-at` z `ARG DEPLOYED_AT` na końcu `runtime`; listing sortuje po niej, `.Created` tylko dla legacy.
  - Strength: tagi zostają `:<sha>`; LABEL nie psuje cache.
  - Tradeoff: druga etykieta w inspect, dodatkowy przypadek testowy.
  - Confidence: HIGH — eksperyment pokazał, że sam LABEL nie przebudowuje warstw.
  - Blind spot: None significant.
- **Fix B**: sortowalne tagi `<RRRRMMDDGGMMSS>-<sha>`.
  - Strength: kolejność widać w `docker image ls`.
  - Tradeoff: długie tagi, wyjątek dla `legacy-…`, zmiana kontraktu `:<sha>`.
  - Confidence: MED.
  - Blind spot: None significant.
- **Decision**: FIXED (Fix A)

### F2 — Blokada migracji działa w jedną stronę

- **Severity**: ❌ CRITICAL
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Faza 1 §2 i §4 (`production_unknown_migrations`, przypadek „obraz ma migrację spoza bazy: pusto”), Faza 2 §2 krok 3
- **Detail**: Obraz z migracjami, których baza nie ma, przechodzi blokadę, a `rollback.sh` nie migruje. Po `migrate:rollback` → `rollback.sh <stary>` → `rollback.sh <nowy sha>` nowy kod serwuje na starym schemacie, wbrew krokowi 3 `deploy.sh` w README.
- **Fix**: blokada w obie strony (`production_pending_migrations`), oczekujące migracje odsyłają do `scripts/deploy.sh`; odwrócony przypadek testowy.
- **Decision**: FIXED

### F3 — Ochrona przy sprzątaniu nie chroni obrazu, z którego wracano

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Critical Implementation Details — „Ochrona przy sprzątaniu”; Faza 1 §3
- **Detail**: Sprzątanie biegnie po przesunięciu `:prod` na nowy build, więc chroni tylko nowy obraz. Obraz, na który wcześniej wycofano, wypada z top 3, np. gdy po wycofaniu ktoś puści `deploy.sh` złego commita bez `git revert`.
- **Fix**: `PREVIOUS_PROD_IMAGE_ID` odczytany przed buildem i lista chronionych ID w `production_image_tags_to_remove`, z przypadkiem testowym.
- **Decision**: FIXED

### F4 — `docker image prune -f` sprząta cały współdzielony host

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Architectural Fitness
- **Location**: Faza 1 §2 — `production_remove_old_image_tags`
- **Detail**: halina175 dzielą landing, root-keeper, menu-maker, Vaultwarden i Backrest (infrastructure.md:59). Gołe `prune -f` usuwa ich wiszące obrazy; plan bez słowa pomija `until=168h` z infrastructure.md:121.
- **Fix**: `docker image prune -f --filter label=maintainer=household-treasurer` (Dockerfile.prod:79); ADR-0107 wymienia odrzucone warianty.
- **Decision**: FIXED

### F5 — Odmowa „ten sam obraz co :prod” blokuje ponowienie po awarii

- **Severity**: 🔍 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Faza 2 §2 kroki 2 i 4
- **Detail**: Po udanym `docker tag` i nieudanym `up -d` `:prod` wskazuje cel, a kontener stary obraz, więc ponowienie jest odrzucane. „Poprzedni tag” bywa pusty.
- **Fix**: porównanie z obrazem działającego kontenera; w pułapce ID obrazu, gdy nie ma tagu.
- **Decision**: FIXED

### F6 — Diff migracji jest w fazie 1, a kryterium 2.1 mówi, że w fazie 2

- **Severity**: 🔍 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Faza 1 §2 i §4 vs Faza 2, kryterium 2.1
- **Fix**: funkcje diffu i ich testy przeniesione do Fazy 2 §1.
- **Decision**: FIXED

### F7 — infrastructure.md:73-74 zostaną nieaktualne

- **Severity**: 🔍 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Faza 3 §3
- **Detail**: Devil's Advocate 1 i 2 („Brak szybkiego rollbacku”, „nie robi `docker image prune`”) przestaną być prawdą.
- **Fix**: kontrakt Fazy 3 §3 dopisuje adnotację przy tych punktach.
- **Decision**: FIXED
