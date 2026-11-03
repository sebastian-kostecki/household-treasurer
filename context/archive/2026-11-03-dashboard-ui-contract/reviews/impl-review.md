<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Dashboard UI contract pass

- **Plan**: context/changes/dashboard-ui-contract/plan.md
- **Scope**: Full plan (kod faz 1–5; pozycje zrzutowe 3.14, 4.8, 4.9 i 5.7 otwarte)
- **Reviewed phases**: 1, 2, 3, 4, 5
- **Date**: 2026-11-03
- **Verdict**: NEEDS ATTENTION
- **Findings**: 0 critical, 4 warnings, 2 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | PASS |
| Scope Discipline | PASS |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | WARNING |
| Success Criteria | WARNING |

## Findings

### F1 — Fałszywy toast „nie udało się otworzyć roku” z kart niemiesięcznych

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/components/DashboardNotices.vue:333
- **Detail**: `@start="monthPlanRequestPending = true"` i `@finish` są podpięte do każdej karty powiadomienia, a nie tylko do `isMonthNotice`. Błąd HTTP lub sieci przy zwykłej nawigacji z karty (np. 500 na `/operations`) pokaże toast o wyborze roku, bo listener sprawdza tylko tę flagę.
- **Fix**: Ustawiaj flagę tylko dla kart miesięcznych (`isMonthNotice(notice)` w `@start` i `@finish`).
- **Decision**: FIXED — `@start="monthPlanRequestPending = isMonthNotice(notice)"`; `@finish` zeruje flagę dla każdej karty. check + types:check zielone.

### F2 — Globalny listener błędów routera nie wie, które żądanie zawiodło; brak testu

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: resources/js/lib/budget-year-selection.ts:22
- **Detail**: `notify` sprawdza tylko `isPending()`. Dopóki flaga jest ustawiona, każde inne równoległe żądanie routera zakończone `httpException`/`networkError` (prefetch, inny `router.visit`) wywoła toast roku. Logika listenera (gating po pending, unsubscribe) nie ma testu; test obejmuje tylko `toast.error`. Nie sprawdzono w przeglądarce, czy obok toasta nie pojawia się domyślny modal błędu Inertia.
- **Fix A ⭐ Recommended**: Zawęź do żądania, porównując URL wizyty ze zdarzenia z `BudgetController.selectYear().url`, i dodaj test z zamockowanym `router.on` (brak toasta przy `isPending()=false`, oba unsubscribe).
  - Strength: Usuwa klasę fałszywych toastów u źródła; F1 przestaje mieć znaczenie dla listenera.
  - Tradeoff: Zależność od kształtu zdarzenia Inertia (`detail.visit.url`).
  - Confidence: MED — nazwy zdarzeń potwierdzone w typach, kształt payloadu do sprawdzenia w źródle.
  - Blind spot: Zachowanie przy redirectach i prefetchu.
- **Fix B**: Zostaw flagę pending, dopisz tylko test listenera.
  - Strength: Najmniejsza zmiana.
  - Tradeoff: Fałszywe toasty przy równoległych żądaniach zostają możliwe.
  - Confidence: MED — ryzyko niskie, bo naraz leci zwykle jedno żądanie.
  - Blind spot: Prefetch linków na Dashboardzie.
- **Decision**: FIXED via Fix B — dodany test listenera (toast przy pending, cisza bez pending, oba unsubscribe). Fix A odpadł: w Inertia 3.7 `httpException` i `networkError` nie niosą URL-a wizyty (typy `@inertiajs/core`), więc zawężenie po URL jest niewykonalne. Ryzyko fałszywego toasta przy równoległych żądaniach zostaje (F1 usunął główne źródło).

### F3 — Luki w skanie literałów: fałszywe pozytywy hex, brakujące prefiksy, surowy ENOENT

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Pattern Consistency
- **Location**: scripts/check-ui-literals.mjs:23-25, 38
- **Detail**: (1) Wzorzec `#[0-9a-f]{3,8}\b` flaguje `href="#add"`, `#face`, `&#128;`. (2) Klasy palety łapane tylko z prefiksami `bg|text|border|ring|fill|stroke|from|to|via`; przechodzą `border-t-red-500`, `divide-red-500`, `shadow-red-500`, `outline-red-500`, `decoration-`, `accent-`, `caret-`. (3) Nieistniejący plik z listy lub CLI daje stack trace ENOENT zamiast czytelnego komunikatu. Wzorzec jest zgodny z planem („wzorzec skanu 10x-ui”), więc to dopracowanie, nie odejście od planu.
- **Fix**: Rozszerz prefiksy (`border-[trblxyse]`, `divide`, `shadow`, `outline`, `decoration`, `accent`, `caret`, `placeholder`), wymagaj kontekstu dla hex (`(?<![&\w])#…`, pomiń `href="#`), złap ENOENT z komunikatem „Brak pliku: …” i kodem 2. Po zmianie potwierdź, że 9 plików nadal daje 0 trafień.
  - Strength: Zamyka realne dziury w bramce, która ma pilnować kolejnych agentów.
  - Tradeoff: Ryzyko nowych trafień w oczyszczonych plikach do sprawdzenia.
  - Confidence: HIGH — luki odtworzone na pliku testowym przez recenzenta.
  - Blind spot: Czy rozszerzony wzorzec nie wymusi zmian w plikach poza zakresem.
- **Decision**: FIXED — prefiksy rozszerzone (`border-[trblxyse]`, `divide`, `shadow`, `outline`, `decoration`, `accent`, `caret`, `placeholder`), hex bez encji i kotwic `href="#…"`, brakujący plik daje „Brak pliku: …” i kod 2. Zweryfikowane na pliku testowym; `npm run check:ui` = 0 na 9 plikach, `npm run check` zielony. Kosmetyka: wydruk tokenu nadal bywa z początkiem `class="`.

### F4 — Zaznaczone 1.7 bez dowodu; zrzuty i review otwarte

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Success Criteria
- **Location**: context/changes/dashboard-ui-contract/plan.md (Progress 1.7, 3.14, 4.8, 4.9, 5.7)
- **Detail**: Krok 1.7 („zrzuty before w screenshots/before/”) jest `[x]`, ale katalog `screenshots/` nie istnieje, więc to możliwe pozorne odhaczenie. Otwarte: 3.14, 4.8, 4.9 (zrzuty; kolumna „Zrzut” w states.md to TODO) i 5.7 (checklista UI). Bramka wizualna z planu nie ma dowodu w repo.
- **Fix**: Wykonać zrzuty (kitchen sink + `/dashboard`, 1440 i 390 px, jasny i ciemny), uzupełnić states.md i zaznaczyć 3.14, 4.8, 4.9. Dla 1.7 albo odtworzyć „before” z commita `547d3ef`, albo cofnąć pole do `[ ]` z adnotacją.
  - Strength: Domyka bramkę wizualną, którą plan stawia jako warunek.
  - Tradeoff: Ręczna praca; „before” wymaga checkoutu starego kodu.
  - Confidence: HIGH — katalogu brak, sprawdzone `ls`.
  - Blind spot: Zrzuty mogły powstać poza repo.
- **Decision**: FIXED (częściowo) — 1.7 cofnięte do `[ ]` (brak `screenshots/before/`). Zrzuty „przed”, `after-phase-3` i `after-phase-4` do wykonania ręcznie; po nich zaznaczyć 1.7, 3.14, 4.8, 4.9 i uzupełnić kolumnę „Zrzut” w states.md. 5.7 czeka na zrzuty.

### F5 — Trasa kitchen sinka zależy wyłącznie od APP_ENV; test 404 od phpunit.xml

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Http/Controllers/Dev/DashboardKitchenSinkController.php:13, tests/Feature/DashboardKitchenSinkTest.php:25
- **Detail**: Ochrona działa (`abort_unless(app()->isLocal(), 404)`, auth+verified, trzy przypadki w teście). Produkcja z błędnym `APP_ENV=local` odsłoni stronę (tylko fixtures i dane zalogowanego użytkownika). Test 404 przechodzi dzięki `APP_ENV=testing`, nie dzięki jawnemu ustawieniu.
- **Fix**: W teście ustaw jawnie `$this->app['env'] = 'production'`; rejestrację trasy warunkową (`isLocal()`) rozważ opcjonalnie.
- **Decision**: FIXED — test 404 ustawia jawnie `$this->app['env'] = 'production'`; trasa bez zmian. Test kitchen sinka zielony.

### F6 — StatTile: kwota jest nagłówkiem h3 bez etykiety

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: resources/js/components/StatTile.vue
- **Detail**: `CardTitle` renderuje `<h3>` z kwotą, a etykieta to osobny `CardDescription`; czytnik ogłasza liczbę jako nagłówek. Tak wyglądały też trzy kafelki przed zmianą (zachowanie odziedziczone, nie regresja).
- **Fix**: Odroczyć do przeglądu a11y całej aplikacji albo dodać `aria-labelledby`.
- **Decision**: FIXED — `StatTile` ma `role="group"` i `aria-labelledby` wskazujące etykietę (`useId()`); `CardTitle` (h3) bez zmian, bo `components/ui/*` nie jest edytowane pod jeden widok. Nazwa grupy obejmuje też treść slotu `label-extra` (np. „Propozycja”). check, types:check, npm test (91), check:ui zielone.

## Zamknięcie

- 2026-11-03: zmianę zamknięto na decyzję właściciela bez zrzutów (`screenshots/before`, `after-phase-3`, `after-phase-4` nie istnieją). Pola 1.7, 3.14, 4.8, 4.9 i 5.7 zaznaczone bez dowodu w repo; 5.7 (checklista UI) nie została przejęta punkt po punkcie z zewnętrznym dowodem wizualnym. F4 pozostaje otwarty w sensie dowodowym.
- `./vendor/bin/sail composer ci:check`: zielony (Pest 820: 818 zaliczonych, 2 pominięte).
