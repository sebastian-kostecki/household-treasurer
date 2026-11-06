<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Sidebar według komponentu Sidebar z Atramentu

- **Plan**: context/changes/sidebar-atrament-ui/plan.md
- **Scope**: Full plan
- **Reviewed phases**: 1, 2, 3, 4, 5, 6
- **Date**: 2026-11-06
- **Verdict**: NEEDS ATTENTION
- **Findings**: 0 critical, 4 warnings, 2 observations

## Verification run (this review)

| Command | Result |
|---------|--------|
| `npm test` | 33 files, 332 tests passed |
| `npm run check:ui` | exit 0 |
| `npm run types:check` | exit 0 |
| `npm run build` | exit 0 |
| `vendor/bin/sloppy ci` | 100/100, 0 findings |
| Pest `SidebarKitchenSinkTest`, `AppShellTest` (PostgreSQL, worktree) | 12 passed, 165 assertions |
| `grep -rn "AppLogoIcon\|Vault" resources/js` | 5 hits: 2 in `lib/category-appearance.ts` (Lucide category icon), 3 in `ui-contract.test.ts` (see F5) |

Not run in this review: Playwright (`npx playwright test`), full `composer ci:check` on PostgreSQL, `php artisan boost:index-rules` (`.ai/rules/index.md` is not in the diff `e889272..HEAD`). Progress marks 4.2, 4.3, 5.6, 6.6 and 6.8 rest on the commit-time evidence, not on a re-run here.

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | WARNING |
| Scope Discipline | PASS |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | PASS |
| Success Criteria | WARNING |

## Findings

### F1 — Przycisk roku budżetu traci nazwę dostępną „Rok budżetu”

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/components/BudgetYearSwitcher.vue:110-133, resources/js/components/AppSidebar.vue:87
- **Detail**: Przed zmianą przycisk zawierał tekst „Rok budżetu” nad rokiem, więc jego nazwa dostępna brzmiała „Rok budżetu 2026”. Plan (faza 4, pkt 1) wynosi etykietę do osobnego `SidebarGroupLabel` (div) i zostawia w przycisku sam rok. Teraz przycisk z `aria-haspopup` ma nazwę „2026”, a etykieta nie jest z nim powiązana (w trybie ikon ma `opacity-0`). `tooltip` nie jest nazwą dostępną.
- **Fix**: Dodać na `SidebarMenuButton` `:aria-label="`${t.shell.budget_year}: ${budgetYear}`"` (ten sam tekst co tooltip, zawiera widoczny tekst). Dopisać asercję do `ui-contract.test.ts` albo do specu E2E.
- **Decision**: FIXED — Fix now: `:aria-label` na przycisku roku (BudgetYearSwitcher.vue:112) + asercja w `ui-contract.test.ts` (najpierw czerwona, potem zielona; `npm test` 333/333)

### F2 — Znak „HT” i awatar nie są wyśrodkowane w trybie ikon

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/components/AppLogo.vue:9, resources/js/components/UserInfo.vue:27-29, resources/js/components/ui/sidebar/index.ts (size `lg`: `group-data-[collapsible=icon]:p-0!`)
- **Detail**: Przycisk `lg` w trybie ikon ma 32 px bez paddingu, a `BrandMark` (26 px) i awatar `size-7` (28 px) są `shrink-0` bez centrowania. Recenzent zmierzył to na `sink-1280-light.png`: znak x=773–798 (środek 785,5), ikony menu x=782–795 (środek 788,5), czyli ok. 3 px w lewo; awatar o ok. 2 px. Zrzut potwierdza to wizualnie (znak „HT” lekko na lewo od kolumny ikon). Plan wymagał tylko, żeby znak „mieścił się” w przycisku 32×32, i tak jest, ale kolumna ikon jest przez to krzywa.
- **Fix**: Wyśrodkować w trybie ikon (np. `group-data-[collapsible=icon]:mx-auto` na `BrandMark` w `AppLogo` i na `Avatar` w wariancie `sidebar`) i sprawdzić w ramce `collapsed-*` sinka.
- **Decision**: FIXED — Fix now: `mx-auto` na `BrandMark` (AppLogo) i na awatarze wariantu `sidebar` (UserInfo), tekst obok w trybie ikon `sr-only` (nie `hidden`, żeby link logo i przycisk użytkownika zachowały nazwę dostępną), szewron w NavUser `hidden` w trybie ikon; straż w `ui-contract.test.ts`. Pomiar w przeglądarce (`/dev/ui/sidebar`, 1280 px): środek x znaku, ikon menu i awatara = 789 w obu zwiniętych ramkach (wcześniej znak ok. 3 px w lewo).

### F3 — Ramki sinka nie pokazują bloku użytkownika (NavUser)

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Success Criteria
- **Location**: resources/js/pages/dev/SidebarKitchenSink.vue (ramki `expanded-*`, `collapsed-*`, `h-160`)
- **Detail**: `Sidebar` ma `fixed inset-y-0 h-svh`, a ramka 640 px; stopka z `NavUser` zostaje poza ramką. Na `sink-1280-light.png` blok użytkownika widać tylko w prawdziwym sidebarze po lewej, rozwinięty. Zwinięty `NavUser` (nowy awatar 28 px, F2) nie jest nigdzie pokazany, mimo że opis strony i `design-system.md` obiecują wszystkie warianty, a kroki ręczne 4.7 i 6.9 zaznaczono jako ukończone.
- **Fix A ⭐ Recommended**: Dać ramkom wysokość zgodną z `h-svh`, żeby stopka mieściła się w każdej z czterech ramek.
  - Strength: Zero nowych elementów; wszystkie cztery warianty pokazują blok użytkownika w obu trybach.
  - Tradeoff: Ramki stają się wyższe (cała wysokość okna), strona sinka dłuższa.
  - Confidence: MED — wygląda na wynik `h-svh` z `Sidebar.vue:76`, ale nie sprawdzałem, czy `fixed` wewnątrz ramki zachowuje się tak samo.
  - Blind spot: Nie uruchamiałem sinka w przeglądarce po takiej zmianie.
- **Fix B**: Dodać osobną ramkę `user-block` z `NavUser` rozwiniętym (z długim e-mailem) i zwiniętym.
  - Strength: Pokazuje też przypadek długiego e-maila z planu (krok 4.7).
  - Tradeoff: Kolejne ramki do utrzymania.
  - Confidence: HIGH — wzór ramek `data-frame` już jest w pliku.
  - Blind spot: `NavUser` zależy od `SidebarProvider` i `usePage`; trzeba sprawdzić, czy da się go osadzić poza `AppSidebar`.
- **Decision**: FIXED via Fix A (rekomendowana): ramki `expanded-*`/`collapsed-*` mają `h-svh` zamiast `h-160`. Pomiar: wysokość ramki 900 px, przycisk użytkownika widoczny w ramce rozwiniętej (207×48) i zwiniętej (32×32). Zrzuty `sink-{1280,390}-{light,dark}.png` odświeżone.

### F4 — Słabe asercje w nowym `describe` kontraktu sidebara

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: resources/js/ui-contract.test.ts:216-218, 228-232
- **Detail**: `toContain('aria-current')` przechodzi także przy komentarzu, przy `aria-current="false"` i przy zepsutym warunku; plan mówi „zawiera `aria-current`”, ale intencją jest ochrona wartości `page` sterowanej tym samym wyliczeniem co `is-active`. Jedyną realną ochroną jest spec E2E, którego CI nie uruchamia. Druga asercja: `NavMain.vue` nigdy nie miał wzorca `text-sidebar-foreground/80`, więc ta pozycja `it.each` jest wydmuszką, a `UserInfo`, `NavUser`, `AppLogo` nie są objęte.
- **Fix**: Zaostrzyć do `/:aria-current="isActive\(item\) \? 'page' : undefined"/` oraz sprawdzić, że `:is-active` używa tego samego `isActive(item)`; listę plików z `/80` rozszerzyć o pliki sidebara albo zostawić ten przypadek `check:ui`.
- **Decision**: FIXED — Fix now: asercja `aria-current` sprawdza `:aria-current="isActive(item) ? 'page' : undefined"` i `:is-active="isActive(item)"`; lista plików bez `text-sidebar-foreground/80` rozszerzona do `AppSidebar`, `AppLogo`, `BudgetYearSwitcher`, `NavMain`, `NavUser`, `UserInfo`, `SidebarGroupLabel` (`npm test` 339/339).

### F5 — Kryterium 5.2 planu („0 trafień `Vault`”) nie jest literalnie spełnione

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: resources/js/lib/category-appearance.ts:128, 269; resources/js/ui-contract.test.ts:256-283
- **Detail**: Plan i Progress 5.2 mówią, że `grep -rn "AppLogoIcon\|Vault" resources/js` zwraca 0 trafień. Zwraca 5: dwa to Lucide `Vault` jako wybieralna ikona kategorii (istniała przed zmianą, nie jest w diffie), trzy to sam test. Test wprost wyłącza ten plik komentarzem. Intencja planu (znak produktu) jest spełniona; plan nie przewidział ikony kategorii.
- **Fix**: Dopisać do planu (Progress 5.2 lub notatka) wyjątek `lib/category-appearance.ts`; kod bez zmian.
- **Decision**: FIXED — Fix now: wyjątek dopisany w `plan.md` (Phase 5, Automated Verification, brak `Vault` i `AppLogoIcon`); tytuły kroków w Progress bez zmian.

### F6 — Sekcja `focus-visible` w sinku nie używa prawdziwego przycisku roku; „7 stanów” a macierz ma 8 wierszy

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: resources/js/pages/dev/SidebarKitchenSink.vue (sekcja `focus-visible`, trzecia pozycja; `stateMatrix`)
- **Detail**: Plan (faza 6, pkt 1) mówi o „prawdziwych kontrolkach (pozycje menu i przycisk roku)”. Trzecia pozycja to zwykły `SidebarMenuButton` z napisem „2026” (na zrzucie bez ikony i szewronu), a opis sekcji mówi „przycisk roku”. Macierz ma 8 wierszy (default, empty, loading N/A, error, active, hover, focus-visible, disabled), a ADR i `design-system.md` mówią „siedem stanów”; kosmetyka.
- **Fix**: Użyć w sekcji `focus-visible` prawdziwego `BudgetYearSwitcher` albo poprawić opis; ujednolicić liczbę stanów w tekstach.
- **Decision**: FIXED — Fix now: sekcja `focus-visible` renderuje prawdziwy `BudgetYearSwitcher` (potwierdzone: przycisk „Rok budżetu: 2026” w sekcji); wstęp strony wyjaśnia, że macierz to standardowe siedem stanów plus pozycja aktywna. `disabled` zostaje statyczny, jak w planie.

## Post-fix verification (2026-11-06)

| Check | Result |
|-------|--------|
| `npm test` | 33 files, 339 tests passed |
| `npm run check:ui`, `npm run types:check` | exit 0 |
| Pest `tests/Unit` + `SidebarKitchenSinkTest` + `AppShellTest` (PostgreSQL, worktree) | 185 passed, 635 assertions |
| `npx playwright test --workers=1` | 6 passed (w tym `sidebar-current-section`) |
| `npx playwright test` (równolegle) | 5 passed, 1 failed: `import-other-format-without-header` (krok 1 importu nie przechodzi do podglądu po „Pokaż podgląd”; osobno i na 1 workerze przechodzi) |

Uwaga: padający równolegle spec dotyczy importu, nie sidebara; nie sprawdzałem, czy pada też na `e889272` (baseline), więc nie twierdzę, że to stan sprzed zmiany. Podejrzenie: interakcja ze specem `account-edit-keeps-bank` na wspólnej bazie E2E (zrzut pokazuje toast „Account created.”). Do osobnego zgłoszenia.
