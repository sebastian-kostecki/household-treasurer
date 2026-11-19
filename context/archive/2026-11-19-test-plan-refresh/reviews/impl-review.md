<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Odświeżenie planu testów po M-4 i próbie przełomu roku

- **Plan**: context/changes/test-plan-refresh/plan.md
- **Scope**: Full plan
- **Reviewed phases**: 1, 2, 3
- **Date**: 2026-11-19
- **Verdict**: NEEDS ATTENTION
- **Findings**: 0 critical, 6 warnings, 3 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | PASS |
| Scope Discipline | PASS |
| Safety & Quality | WARNING |
| Architecture | WARNING |
| Pattern Consistency | WARNING |
| Success Criteria | FAIL |

## Grounding

Diff `6212a54..HEAD`: `context/foundation/test-plan.md` (+111/−59), `plan.md` (SHA w Progress), `change.md` (status). Brak plików poza planem. §7 bajt w bajt jak w `2141188`. Liczby hot-spotów (372 / CsvImport 52 / Support 347 / lib 425 / components 550 / pages 340), profil §4 (133+43 PHP, 63 `*.test.ts`, 15 speców, 44 pliki z `travelTo`) i wersje (Pest 5.3.5, PHPUnit 13.4.5, Larastan 3.15.0, Vitest 5.1.0, vite-plus 1.6.0, Playwright 1.65.0) zgodne z repo i lockfile'ami. Wszystkie cytowane ścieżki repo i komendy artisan (`app:rehearsal-clock`, `demo:seed-household`, `demo:seed-previous-year`) istnieją. Kontrole automatyczne: 1.1–1.5, 2.2–2.4, 3.2–3.6 przechodzą; 2.1 i 3.1 dosłownie nie (F4).

## Findings

### F1 — Testy `_known_gap` nie „zielenieją bez zmiany asercji”

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: context/foundation/test-plan.md:169 (też :69, guidance #9)
- **Detail**: Oba testy utrwalają dzisiejsze błędne zachowanie i są dziś zielone: `tests/Feature/SelectedBudgetYearTest.php:84` asertuje rok 2026 w styczniu 2027, `tests/Feature/CsvImportTest.php:688` asertuje odrzucenie całego wyciągu. Poprawka w S-40/S-42 je zaczerwieni i musi odwrócić ich asercje. Zdanie „zielenieją z S-40 i S-42 bez zmiany asercji wymagań” pochodzi wprost z planu (kontrakt §6.2), więc to wada planu przeniesiona do dokumentu. Planista S-40/S-42 albo utknie, albo zostawi asercję błędu.
- **Fix**: Przepisać punkt: testy `_known_gap` przypinają obserwowany błąd; S-40/S-42 przepisują je na wymagane zachowanie (2027; grudzień zapisany + komunikat dla stycznia lub decyzja S-42) i zdejmują sufiks — jako zmiana wymagania, nie strojenie asercji. To samo w guidance #9 :69.
  - Strength: Zgodne z faktycznym kodem testów i z twardą zasadą „napraw przyczynę, nie test” (zmiana wymagania jest jawna).
  - Tradeoff: Dwie linie tekstu; plan zmiany zostaje z błędnym sformułowaniem (historyczny).
  - Confidence: HIGH — asercje przeczytane w kodzie.
  - Blind spot: Docelowe zachowanie S-42 (zapis częściowy czy komunikat) nie jest jeszcze ustalone — patrz F5.
- **Decision**: FIXED

### F2 — Specy-bramki nie spełniają poprzeczki z §6.3

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: context/foundation/test-plan.md:128, :177
- **Detail**: §6.3 i guidance #7 wymagają, żeby spec-bramka padał na błąd JS i brał rok budżetu przez `tests/e2e/support/budget-year.ts`. Tymczasem `month-plan-groups.spec.ts:59`, `irregular-collected.spec.ts:58` i `import-other-format-without-header.spec.ts:16` biorą rok z `new Date().getFullYear()` (zegar runnera — dokładnie klasa ryzyka #9), a `month-plan-groups` i `irregular-collected` nie słuchają `pageerror`. `budget-year.ts` eksportuje tylko pomocniki menu (`yearTrigger`, `nextBudgetYear`, `selectBudgetYear`…), nic dla „bieżącego roku”. S-44 może podpiąć te specy jako bramki bez zmian i dostać bramkę niestabilną właśnie na przełomie roku.
- **Fix**: Do wiersza §5 (:128) albo celu etapu 2 dopisać, że S-44 doprowadza każdy spec-bramkę do poprzeczki §6.3 (`pageerror`, rok z aplikacji, nie z zegara runnera) przed oznaczeniem go jako required; w §6.3 :177 poprawić zdanie o `budget-year.ts` (rok z menu aplikacji przez jego pomocniki, nie z zegara).
  - Strength: S-44 dostaje listę braków zamiast odkrywać je przy pierwszej czerwonej bramce w styczniu.
  - Tradeoff: Rozszerza zakres S-44 o poprawki trzech speców.
  - Confidence: HIGH — linie speców i eksporty `budget-year.ts` sprawdzone.
  - Blind spot: Nie sprawdzono, czy pozostałe kryteria (sprzątanie, unikalne nazwy) też odstają.
- **Decision**: FIXED

### F3 — Wzorzec przełomu roku i „dziś” w Vitest nieprecyzyjny dla S-39

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: context/foundation/test-plan.md:147, :167–171 (też :69)
- **Detail**: Cztery nieścisłości w przepisie, z którego S-39 ma startować „bez szukania”: (a) „31.12 22:59:59/23:00 UTC, a latem 21:59:59/22:00 UTC” — 31.12 nigdy nie jest latem; brak daty letniej (ADR-0103 podaje 30.06). (b) §6.1 :147 każe przekazywać „dziś” jako `YYYY-MM-DD`, a wskazane referencje tego nie robią: `budget-year-menu.test.ts` podaje numer roku, `format.test.ts:149` podaje `new Date('2026-11-05T12:00:00')`. (c) :168 „Przeglądarka bierze dzień tylko z propsów serwera” w czasie teraźniejszym, a `shell.today` jeszcze nie istnieje (`app/Support/ShellState.php` bez `today`) — sprzeczne z zasadą „Docs describe more than the code”. (d) Strażnik zakazuje `getFullYear()` w „domenowym JS” bez zakresu, a `getFullYear()` na przekazanej dacie jest poprawne (`resources/js/lib/format.ts`). Punkty (b) i (c) pochodzą z kontraktu planu.
- **Fix**: Doprecyzować blok: przełom roku 31.12 22:59:59/23:00:00 UTC, przełom miesiąca latem 30.06 21:59:59/22:00:00 UTC; „od S-39 (ADR-0103) przeglądarka bierze dzień z `shell.today`”; kontrakt Vitest = dzień aplikacji jako string `YYYY-MM-DD` z `shell.today` albo rok z niego wyprowadzony, z referencjami, które to pokazują (albo bez referencji do czasu S-39); strażnik = brak odczytu zegara (`new Date()` bez argumentu, `Date.now()`) w nazwanych katalogach, metody na przekazanej dacie dozwolone.
  - Strength: Usuwa cztery miejsca, w których planista S-39 zbudowałby zły test albo zbyt szeroki strażnik.
  - Tradeoff: Kilka zdań; część decyzji (zakres strażnika) przesuwa się z S-39 do planu testów.
  - Confidence: HIGH — wszystkie cztery punkty sprawdzone w kodzie i ADR.
  - Blind spot: Dokładny zakres katalogów strażnika to decyzja S-39; tu wystarczy zasada.
- **Decision**: FIXED

### F4 — Statusy §3 w backtickach; kontrole 2.1 i 3.1 dosłownie nie przechodzą

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: context/foundation/test-plan.md:79–84
- **Detail**: Komórki Status to `` `complete` `` / `` `not started` ``, a schemat (`test-plan-schema.md:164`, „parser literals”) i wersja sprzed zmiany używają gołych literałów. Kontrola 2.1 z planu zwraca 0 zamiast 6, a grep 3.1 nic nie znajduje; obie są `[x]` w Progress (wariant z backtickami daje 6 i 1). Źródło: tabela kontraktu w planie (plan.md:216–221) sama ma backticki, więc plan był wewnętrznie sprzeczny.
- **Fix**: Zdjąć backticki ze statusów w §3 :79–84 (zgodnie ze schematem); kontrole 2.1 i 3.1 przejdą wtedy w brzmieniu z planu.
- **Decision**: FIXED

### F5 — Decyzja S-42 o zapisie częściowym nie jest skierowana do ryzyka #1

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: context/foundation/test-plan.md:61, :157
- **Detail**: Roadmapa S-42 (`roadmap.md:167–168`) ma otwarte pytanie „Zapis częściowy… czy tylko lepszy komunikat” i ryzyko, że zapis częściowy „podważa zasadę z fazy 1 planu testów”. Plan testów tego nie wspomina: guidance #1 i §6.2 mówią „błąd jednego wiersza wycofuje cały zestaw” jako regułę nietykalną.
- **Fix**: Dopisać jedno zdanie do §6.2 (blok atomowości) lub guidance #1: decyzja S-42 o zapisie częściowym to zmiana reguły #1 i jawnie aktualizuje wzorzec atomowości oraz jego testy.
- **Decision**: FIXED

### F6 — Etap 2 i S-44 śledzą tę samą zmianę bez ustalonego id

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Architecture
- **Location**: context/foundation/test-plan.md:80, :91
- **Detail**: Etap 2 ma `not started` i `—`, a cel wskazuje zmianę `e2e-in-ci` (S-44). Orkiestrator `/10x-test-plan` zaproponuje własne id (`testing-…`), a S-44 pewnie powstanie przez `/10x-roadmap` → `/10x-new e2e-in-ci`, co §3 nie aktualizuje. Wynik: zdublowana zmiana albo nieaktualny etap 2. Podobna, już nazwana luka śledzenia dotyczy bramki przełomu roku (:91).
- **Fix A ⭐ Recommended**: Dopisać w akapicie kolejności, że etap 2 realizuje zmiana `e2e-in-ci` (S-44): `/10x-new` używa tego id, a orkiestrator przy następnym wywołaniu przestawia wiersz na `change opened` z tym folderem.
  - Strength: Nie wpisuje folderu, którego jeszcze nie ma (słownik statusów zostaje prawdziwy: `not started` = brak folderu).
  - Tradeoff: Zależy od tego, że planista S-44 przeczyta akapit.
  - Confidence: MED — zachowanie orkiestratora przy obcym id wywnioskowane ze schematu, nie przetestowane.
  - Blind spot: Nie sprawdzono, czy orkiestrator rozpoznaje folder po id z komórki, czy po prefiksie.
- **Fix B**: Wpisać `context/changes/e2e-in-ci/` w komórkę Change folder już teraz, przy statusie `not started`.
  - Strength: Id jest w miejscu, które orkiestrator czyta.
  - Tradeoff: Łamie znaczenie `not started` („No change folder yet”) — komórka wskazuje nieistniejący folder.
  - Confidence: MED — zgodność z parserem niepewna.
  - Blind spot: Jak orkiestrator traktuje nieistniejący folder przy `not started`.
- **Decision**: FIXED (Fix A)

### F7 — Stałe zdanie §2 „ordered by risk” już nie jest prawdziwe

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: context/foundation/test-plan.md:39–40
- **Detail**: Po zmianie ocen #3 (High/Medium) stoi nad #5 (High/High), #6 (High/Low) nad #7, a #9 (High/High) na końcu. Numery są stałe celowo (schemat), ale czytelnik wywnioskuje priorytet z kolejności wierszy.
- **Fix**: Pod tabelą dopisać: „Numery są stałe; priorytet wyznacza kolejność §3.”
- **Decision**: FIXED

### F8 — Nieaktualne lub nieprecyzyjne opisy warstwy e2e (#7)

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: context/foundation/test-plan.md:67; context/foundation/test-stack.md:16
- **Detail**: Guidance #7 mówi „konto testowe poza `.env`”, a `test-stack.md:14` bierze dane logowania z `E2E_USERNAME`/`E2E_PASSWORD` w `.env`; poza nim jest tylko rekord użytkownika w bazie `household_treasurer_e2e`. `test-stack.md:16` opisuje #7 w starym brzmieniu („stops working although HTTP and Vitest tests pass”), a §4 i §6.3 kierują tam po szczegóły.
- **Fix**: W :67 napisać „użytkownik testowy tylko w lokalnej bazie e2e”; aktualizację `test-stack.md:16` dopisać do celu S-44.
- **Decision**: FIXED

### F9 — Kontrole manualne 1.6, 2.5, 3.7 otwarte przy statusie `implemented`

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: context/changes/test-plan-refresh/plan.md:388, :401, :416
- **Detail**: Wszystkie trzy potwierdzenia użytkownika są `[ ]`. 2.5 (etap 2 i bramka E2E wystarczą do planu S-44) i 3.7 (S-39/S-44 mogą startować z §6) są wprost dotknięte przez F2, F3 i F6 — warto odhaczyć je dopiero po triage.
- **Fix**: Po triage F1–F6 przeczytać §2, §3/§5 i §6.2/§6.3 i odhaczyć 1.6, 2.5, 3.7.
- **Decision**: PENDING — potwierdzenia manualne 1.6, 2.5, 3.7 należą do użytkownika
