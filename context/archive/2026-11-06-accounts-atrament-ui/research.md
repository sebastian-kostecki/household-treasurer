---
date: 2026-11-06T13:47:53+01:00
researcher: Claude (Opus 5.5)
git_commit: cd476e8
branch: worktree-accounts-atrament-ui
repository: household-treasurer
topic: "Audyt UI widoku Konta (Accounts.vue) względem karty PageAccounts systemu Atrament"
tags: [research, ui, atrament, accounts, stat-panel, data-table, switch, adr-0037]
status: complete
last_updated: 2026-11-06
last_updated_by: Claude (Opus 5.5)
---

# Research: Audyt UI widoku Konta względem karty PageAccounts

**Date**: 2026-11-06T13:47:53+01:00
**Researcher**: Claude (Opus 5.5)
**Git Commit**: cd476e8
**Branch**: worktree-accounts-atrament-ui
**Repository**: household-treasurer

## Research Question

Audyt UI `resources/js/pages/Accounts.vue` względem karty `PageAccounts` design systemu Atrament (https://claude.ai/artifact/9gJSdtfpFgUAa8oEMgR4HA): potwierdzić pięć zarzutów z `change.md` (StatPanel + Total funds, siatka DataTable, wiersz zarchiwizowany, Switch, `page-title` / znak banku / „Anuluj” `outline`), rozstrzygnąć „zł w komórkach” wobec ADR-0037 i ustalić wpływ na Lokaty i Dashboard.

## Summary

Wszystkie pięć zarzutów się potwierdza. Trzy z nich mają już w repo gotową decyzję, a karta tylko ich nie wdrożyła w Kontach:

- `Switch` dla „Pokaż zarchiwizowane”: `.ai/rules/js.md:29`, ADR-0037.
- Wiersz zarchiwizowany w `muted-foreground` bez `opacity`: `design-system.md:55`, `js.md:44`, dług F6 z `term-deposits-ui-contract`.
- „Anuluj” `outline`: ADR-0040:5, odroczone dwa razy dla `AccountFormDialog`.

Dwa zarzuty wymagają nowych rzeczy w kodzie:

- Druga miara „Łączne środki” potrzebuje propsa. Dashboard liczy ją inline (`DashboardController.php:56-57,128`), a suma lokat jest skopiowana w `TermDepositController.php:40`.
- Siatka tabeli potrzebuje nowej klasy. Obecna `.holdings-list-row` jest wspólna z Lokatami.

**„zł w komórkach”**: domyślna reguła ADR-0037 (`formatMoneyAmount` + stopka „Kwoty w zł”) obejmuje Konta, bo jedyny zapisany wyjątek to lista Operacji (ADR-0039:5, `design-system.md:63`). Karta `PageAccounts` (zł w każdej komórce, bez stopki) byłaby trzecim wariantem i wymagałaby ADR-0044. **Rekomendacja: zostać przy ADR-0037.** Artefakt jest podglądem, a nie źródłem wartości (`AGENTS.md:11`), i tabela Kont nie ma powodu z ADR-0039 (mieszanych typów kwot w jednej liście).

**Wpływ na Lokaty i Dashboard**: tylko przez współdzielone elementy. `.holdings-list-row` (Konta i Lokaty) oraz `AccountIdentityBlock` (Konta, Lokaty, Dashboard ×2) nie powinny być zmieniane w tej zmianie. Konta dostają własną klasę siatki i znak `AccountMarkCompact size="md"` (28 px). Po zmianie `StatTile`, `IconAction` w starym układzie i `Checkbox` „Pokaż zarchiwizowane” zostają tylko na Lokatach.

## Charges

Każdy zarzut ma plik, linię i wpływ na użytkownika. Kategorie: **T** brakujące tokeny, **K** brakujący współdzielony komponent, **A** przypadkowa architektura.

### C1 (K) Jedna karta `StatTile` zamiast `StatPanel` z dwiema miarami: potwierdzony

- **Gdzie:** `Accounts.vue:99-102` renderuje `StatTile`, a `StatTile.vue:29` ma `text-3xl` zamiast `kpi-lg`. Kontroler wysyła tylko `liquidity_total_minor_units` (`AccountController.php:39`).
- **Wpływ:** użytkownik nie widzi na Kontach środków razem z lokatami. Miara wygląda inaczej niż na Dashboardzie, w Budżecie i w Planie miesiąca (wszystkie mają `StatPanel`, `design-system.md:82`).
- **Dowody:**
  - API `StatPanel.vue:6-24` przyjmuje `metrics` z `{id?, label, value, caption?, toneClass?}` i 2–4 miary.
  - CSS `app.css:1148-1188` układa miary w stos z górną linią na telefonie, a od `md` w równe kolumny. Dwie miary dadzą więc 50/50. Szerszą pierwszą kolumnę 1.5fr ma tylko Dashboard (`app.css:1168-1172`).
  - „Total funds” jest w glosariuszu (`glossary.md:167-169`), więc nie trzeba zmieniać glosariusza. Spendable Liquidity ma przy sobie „_Avoid_: Łączne środki” (`glossary.md:163-165`), dlatego etykiety muszą być dwie i różne.

### C2 (K/A) Lista na `flex-wrap` zamiast tabeli z nagłówkami kolumn: potwierdzony

- **Gdzie:** `Accounts.vue:151-252` to `ListSurface` z wierszami `.holdings-list-row` (`app.css:811-814`: `flex flex-wrap`, `border-border/60`). Typ konta jest podpisem pod nazwą (`:171-173`), a akcje na wąskim ekranie przechodzą do osobnej linii (`:186` `w-full`). Saldo idzie przez `formatMoney` z „zł” (`:182`), wbrew `design-system.md:63`.
- **Wpływ:** salda nie stoją w jednej kolumnie, a bez nagłówków nie wiadomo, co jest czym. Tabele Budżetu, Planu miesiąca i realizacji na Dashboardzie mają wiersz nagłówka w `table-header` (`design-system.md:18`: jedyna receptura podpisu kolumny); bez nagłówka jest z zapisaną decyzją tylko lista Operacji (ADR-0039).
- **Wzorce w repo:**
  - siatki z `grid-template-areas` i ukrytym na telefonie wierszem nagłówka: `.budget-category-grid` (`app.css:628-706`), `.month-plan-grid` (`:433-494`), `.category-meter-row` (`:1005-1080`);
  - reguła „jeden markup na każdą szerokość” (`js.md`, sekcja „Responsive lists”).
- **Etykiety kolumn:** `accounts.name` „Nazwa” (`ui.php:443`), `accounts.type` „Typ” (`:444`) i `accounts.current_balance` „Saldo bieżące” (`:440`) istnieją. Brakuje `accounts.actions`; jest `budget.actions` (`:314`), ale lekcja F1 z `accounts-ui-contract` (`reviews/impl-review.md:26-38`) mówi, żeby nie pożyczać kluczy z innej przestrzeni. Brakuje też `accounts.amounts_in_zl`; jest `budget.amounts_in_zl` (`:206`).

### C3 (T) Wiersz zarchiwizowany: przezroczystość i ton salda: potwierdzony, wymaga jednej decyzji

- **Gdzie:** `Accounts.vue:156` ma `opacity-60`. Saldo zarchiwizowanego konta nadal ma `amountToneClass` (`:177-181`), a Badge `secondary` (`:167`).
- **Wpływ:** `opacity-60` obniża kontrast całego wiersza, także ikon. Ikona „Przywróć” wygląda na wyłączoną, choć działa.
- **Reguła:**
  - `design-system.md:55` i `js.md:44` mówią: cały wiersz `muted-foreground`, bez `opacity` i bez tonu, z wyjątkiem ujemnego salda lokaty (FR-TD-08).
  - Badge `neutral` (`ui/badge/index.ts:27`) mają zarchiwizowane wiersze Budżetu (`BudgetCategoryRow.vue:90`) i Planu miesiąca (`MonthPlanItemRow.vue:87`).
  - To dokładnie dług F6 (`context/archive/2026-11-03-term-deposits-ui-contract/follow-ups/review-fixes.md:5-9`).
- **Decyzja do podjęcia:** archiwizacja nie wymaga zerowego salda (`AccountController.php:107` ustawia tylko `archived_at`). FR-ACC-10 (`prd-mvp.md:143`) każe jednoznacznie oznaczyć ujemne saldo konta, a Spendable Liquidity liczy konta zarchiwizowane (`SpendableLiquidity.php:12-15`; test `ArchiveAccountTest.php:118-136`). **Rekomendacja:** rozszerzyć wyjątek FR-TD-08 na ujemne saldo zarchiwizowanego konta (`text-destructive`) i zapisać to w `design-system.md:17,55` oraz `js.md:44`, jak przewiduje F6. Alternatywa: zgasić ton zawsze.

### C4 (K) `Checkbox` zamiast `Switch` dla „Pokaż zarchiwizowane”: potwierdzony

- **Gdzie:** `Accounts.vue:104-119` (`Checkbox` + `Label`, `:disabled="isTogglingArchived"`, `v-if` na liczbie zarchiwizowanych).
- **Wpływ:** ustawienie, które zapisuje się od razu, wygląda jak pole formularza. W Budżecie to samo ustawienie jest przełącznikiem.
- **Reguła:** `js.md:29` („A setting saved at once … is a `Switch` … `v-model`”) i ADR-0037:5. Wzorzec jest w `Budget.vue:248-256` (`Switch` + `Label`, osobny wiersz pod `StatPanel`). Komponent `ui/switch/Switch.vue` przyjmuje `disabled`, więc obecną blokadę na czas żądania można zachować.
- **Selektory:** żaden test E2E ani jednostkowy nie zależy od `#show-archived-accounts` (`tests/e2e` ma tylko `auth.setup.ts` i `seed.spec.ts`).
- **Umiejscowienie (decyzja):** karta stawia przełącznik w wierszu tytułu. Żaden widok tak nie robi; `design-system.md:84` każe wierszom tytułu iść za `Budget.vue`, czyli tytuł po lewej i jedna akcja `primary` po prawej. **Rekomendacja:** osobny wiersz jak w Budżecie.

### C5 (T/K/A) Tytuł, znak banku, dialog: potwierdzony w trzech częściach

- **Tytuł:** `Accounts.vue:93` ma `text-lg font-semibold` zamiast `page-title` (`app.css:246`). Opis „Rachunki bankowe i konta gotówkowe.” jest w podpisie `ListSurface` (`:151`), a nie pod tytułem. Wzorzec to `Budget.vue:172-186` i `Dashboard.vue:114-129` (`page-title` + `<p class="text-muted-foreground text-sm">`). Wpływ: nagłówek ma inną wagę niż w Dashboardzie, Budżecie, Operacjach i Planie miesiąca (wszystkie `page-title`; Lokaty `TermDeposits.vue:104` mają ten sam `text-lg`).
- **Znak banku:**
  - `AccountIdentityBlock.vue:23-25,38` rysuje kafelek 40 px z `border-border/60`. Karta chce 28 px.
  - `AccountMarkCompact size="md"` ma już 28 px (`AccountMarkCompact.vue:17-23`), ale też `border-border/60` (`:42`).
  - Skrótu banku nie ma w żadnym źródle (`lib/account-identity.ts`, `Bank.php`, `ui.php:448-457` mają tylko pełne nazwy). Każdy nazwany bank ma logo (`Bank.php:18-31`), więc skrót dotyczyłby wyłącznie banku „Inny”.
  - **Rekomendacja:** zostać przy logo, `Coins` dla gotówki i `Building2` dla „Inny”. Nie wprowadzać skrótów ani tekstu „zł” w kafelku. To wartości z podglądu, a nie z repo.
- **Dialog:** „Anuluj” ma `variant="secondary"` (`AccountFormDialog.vue:231`), a ADR-0040:5 wymaga `outline`. `ConfirmActionDialog.vue:67` już ma `outline`. Reszta ADR-0040 (`FormField` 34 px, spinner zapisu, blokada zamykania w trakcie) została odroczona dla `AccountFormDialog` w `category-dialog-atrament/plan.md:38` i `operation-dialogs-atrament/change.md:19` (F7 w `review-fixes.md:13`). Wpływ: „Anuluj” wygląda inaczej niż w dialogach kategorii i operacji (`outline` w `CategoryFormDialog.vue:352-366`, `ExpenseFormDialog.vue:634` i in.; `secondary` zostaje też w `TermDepositFormDialog.vue:258`), a dialog można zamknąć w trakcie zapisu (F7).

### Odroczone (poza tą zmianą)

- **Lokaty (`TermDeposits.vue`)**: ten sam zestaw (`StatTile` `:110-136`, `Checkbox` `:138-153`, `.holdings-list-row` `:189`, Badge `secondary` `:201`, `text-lg` `:104`). Powód: jeden widok na zmianę. Lokaty dostaną własne `/10x-ui` i mogą użyć siatki Kont.
- **`TermDepositFormDialog`**: „Anuluj” `secondary` (`:258`). Powód: dialog Lokat.
- **`AccountIdentityBlock` i `AccountMarkCompact`**: `border-border/60`. Powód: zmiana dotyka Lokat, Dashboardu (2×), `AccountSelect` i `AccountBankSelect`, a `AccountIdentityBlock` nie jest pod żadnym zabezpieczeniem (`check-ui-literals.mjs` ani `DashboardHardcodedUiTest`). Jeśli plan zmieni `AccountMarkCompact`, musi sprawdzić te konsumenty.
- **Wspólny composable `useShowArchivedToggle`** (F7, trzy kopie: Konta, Lokaty, Budżet). Powód: refaktor, nie UI.
- **Rozbieżność stopek Dashboardu**: ADR-0043:23 („bold footer without the "Kwoty w zł" line”) nie jest wymieniona jako wyjątek w `design-system.md:63` ani w `js.md:16`. Powód: to dokumentacja Dashboardu; warto ją dopisać przy ADR-0044, jeśli powstanie.

## Detailed Findings

### Dane i props

- `AccountController::index()` renderuje `Accounts` z czterema propsami (`AccountController.php:37-44`): `accounts`, `liquidity_total_minor_units`, `show_archived`, `archived_accounts_count`. Typ TS jest w `resources/js/types/accounts.ts:22-27`.
- Total funds na Dashboardzie (`DashboardController.php:56-57,128`) to `SpendableLiquidity::forUser($user) + (int) $user->termDeposits()->sum('current_balance_minor_units')`. Suma lokat obejmuje zarchiwizowane i ujemne (`DashboardTest.php:170-191` przypina przypadek zarchiwizowany). Ta sama suma jest w `TermDepositController.php:40`.
- Klasa w `app/Support` dla Total funds nie istnieje. Konta byłyby trzecim miejscem tej sumy, więc plan powinien wydzielić odczyt (np. `App\Support\TotalFunds::forUser`) i użyć go w obu kontrolerach (`AGENTS.md`: „`app/Support` readings and calculations”).
- Nowy props (`total_funds_minor_units`) zmienia PHP i `AccountsPageProps` w jednym commicie z `assertInertia` (`.ai/rules/inertia-props.md`). Naturalne miejsce asercji to `tests/Feature/AccountTest.php:178-205` i przypadek zarchiwizowanych w `ArchiveAccountTest.php:118-136`.
- Wymagany props wymusza dopisanie wartości do wszystkich 6 scenariuszy `resources/js/pages/dev/accounts-fixtures.ts:84-153`, bo inaczej `vue-tsc` nie przejdzie.
- Etykiety miar: `dashboard.total_funds` „Łączne środki” (`ui.php:114`) i `dashboard.total_funds_caption` „z lokatami” (`ui.php:136`, podpis bez kwoty). Klucz `accounts.liquidity_total` „Łączna płynność” (`ui.php:438`) jest używany przez `DashboardHoldings.vue:149` i ma znacznik _Avoid_ (`glossary.md:165`), więc się go nie rusza. Konta potrzebują własnych kluczy `accounts.total_funds` (+ ewentualnie podpisu), jak w lekcji F1.

### Kitchen sink i zabezpieczenia

- `/dev/ui/accounts` (`routes/web.php:27`, `Dev/AccountsKitchenSinkController.php:13` z `abort_unless(isLocal)`) renderuje prawdziwą stronę (`AccountsKitchenSink.vue:8,110`). Jest w nim 6 scenariuszy (`default`, `with-archived`, `zero-total`, `negative-total`, `empty-new-user`, `all-archived`) i samodzielny przycisk dialogu (`:87-97`). Hover, focus-visible, disabled, loading i błąd serwera są tylko opisane w tabeli macierzy (`:12-41`). Brakuje scenariusza „zarchiwizowane konto z ujemnym saldem” (C3).
- `scripts/check-ui-literals.mjs:32-37` już pilnuje `Accounts.vue`, `AccountFormDialog.vue`, `AccountMarkCompact.vue`, `ConfirmActionDialog.vue` i sinka Kont. Nie zabrania jednak `text-lg`, `opacity-60` ani `border-border/60`.
- Te style sprzed Atramentu łapie `tests/Unit/DashboardHardcodedUiTest.php:23-37`, ale tylko dla plików Dashboardu (`:9-21`). Jego docblock (`:3-8`) wyłącza `StatTile.vue`, „because Accounts and Term Deposits still use it”. Kandydat na „Utrwal to”: rozszerzyć ten wzorzec na pliki Kont, osobnym testem jak `MonthPlanHardcodedUiTest.php`.
- `ui-contract.test.ts:50` nie ma Kont na liście wyjątków `Checkbox`, bo Konta już używają `v-model`.

## Code References

- `resources/js/pages/Accounts.vue:93` — `h1` `text-lg font-semibold` (C5)
- `resources/js/pages/Accounts.vue:99-102` — `StatTile` (C1)
- `resources/js/pages/Accounts.vue:104-119` — `Checkbox` „Pokaż zarchiwizowane” (C4)
- `resources/js/pages/Accounts.vue:151-252` — `ListSurface` + `.holdings-list-row` (C2)
- `resources/js/pages/Accounts.vue:156,167,177-182` — `opacity-60`, Badge `secondary`, ton i `formatMoney` w zarchiwizowanym wierszu (C2, C3)
- `resources/js/components/AccountFormDialog.vue:231` — „Anuluj” `secondary` (C5)
- `resources/js/components/AccountIdentityBlock.vue:23-25,38` — kafelek 40 px, `border-border/60`
- `resources/js/components/AccountMarkCompact.vue:17-23,42` — `md` = 28 px
- `resources/js/components/StatPanel.vue:6-24` — API miar
- `resources/js/pages/Budget.vue:172-186,248-256` — wiersz tytułu i `Switch`
- `resources/css/app.css:246,255,811-814,1148-1188` — `page-title`, `table-header`, `.holdings-list-row`, `.stat-panel`
- `app/Http/Controllers/AccountController.php:37-44,107` — props, archiwizacja bez warunku salda
- `app/Http/Controllers/DashboardController.php:56-57,128` — Total funds inline
- `app/Http/Controllers/TermDepositController.php:40` — kopia sumy lokat
- `resources/js/pages/dev/accounts-fixtures.ts:84-153` — 6 scenariuszy sinka
- `tests/Feature/AccountTest.php:178-205` — `assertInertia` strony Kont
- `tests/Unit/DashboardHardcodedUiTest.php:3-37` — wzorzec stylów sprzed Atramentu

## Architecture Insights

- Atrament w tym repo to tokeny i `@utility` w `app.css` oraz nazwane klasy siatek w `@layer components`, po jednej na widok (`js.md`, „Views take values from app.css”). Konta dostaną `.accounts-grid` z nagłówkiem zamiast zmieniać `.holdings-list-row`.
- Rozstrzyganie rozbieżności między artefaktem a repo: decyzja zapisana w ADR lub `design-system.md` wygrywa z podglądem (`AGENTS.md:11`). Nowe znaczenie trafia do ADR (poprzednio ADR-0039 dla Operacji, ADR-0043 dla Dashboardu). Następny wolny numer to **0044** (najwyższy: `0043-dashboard-layout-follows-atrament.md`).
- Komponenty domenowe dzielone między widokami (`AccountIdentityBlock`, `.holdings-list-row`, `StatTile`) są zmieniane tylko w zmianie właściwej dla ostatniego konsumenta. W poprzednich zmianach Atramentu (budget, dashboard) były wpisywane do „What We're NOT Doing”.

## Historical Context (from prior changes)

- `context/archive/2026-11-05-budget-atrament-ui/plan.md:49` — migracja `StatTile` w Kontach i Lokatach odroczona do własnego `/10x-ui` (nadal aktualne: ta zmiana).
- `context/archive/2026-11-05-dashboard-atrament-ui/research.md:149` i `plan.md:56` — `StatTile` zostaje dla Kont i Lokat; Dashboard używa `AccountIdentityBlock size="sm"` i własnej `.dashboard-holdings-row` (potwierdzone w kodzie, `app.css:787`).
- `context/archive/2026-11-03-accounts-ui-contract/plan.md:240-252` — `StatTile`, `ListSurface` z opisem i `h1` `text-lg` były świadomym stanem przed Atramentem (zastąpione przez ten audyt). Lekcja F1 (`reviews/impl-review.md:26-38`) mówi, by nie zmieniać kluczy współdzielonych z Dashboardem (nadal obowiązuje).
- `context/archive/2026-11-03-term-deposits-ui-contract/follow-ups/review-fixes.md:5-9` (F6) — zarchiwizowane wiersze Kont łamią regułę (potwierdzone: C3). `:13` (F7) — `AccountFormDialog` bez spinnera i blokady zamykania (potwierdzone pośrednio: C5).
- `context/archive/2026-11-05-category-dialog-atrament/plan.md:38` i `2026-11-05-operation-dialogs-atrament/change.md:19` — pełne ADR-0040 dla `AccountFormDialog` odroczone (nadal aktualne).

## Related Research

- `context/archive/2026-11-05-dashboard-atrament-ui/research.md` — ten sam przebieg `/10x-ui` dla Dashboardu.
- `context/archive/2026-11-03-accounts-ui-contract/research.md` — poprzedni kontrakt UI Kont (C3: cztery kopie kafelka znaku banku).

## Open Questions

Decyzje produktowe dla `/10x-plan`, każda z rekomendacją:

1. **Jednostka w tabeli:** ADR-0037 (`formatMoneyAmount` + stopka „Kwoty w zł”, rekomendowane) czy karta (zł w komórkach, bez stopki, nowe ADR-0044)?
2. **Ujemne saldo zarchiwizowanego konta:** zostaje `destructive` (rozszerzenie FR-TD-08 o konta, rekomendowane z uwagi na FR-ACC-10) czy zawsze bez tonu?
3. **Miejsce `Switch`:** osobny wiersz jak w Budżecie (rekomendowane) czy wiersz tytułu jak w karcie?
4. **Podpis miary Łączne środki:** statyczny „z lokatami” jak na Dashboardzie (rekomendowane, bez nowego propsa) czy „z lokatami X zł” (potrzebna suma lokat albo różnica w widoku)?
5. **Zakres dialogu:** tylko „Anuluj” `outline` czy pełne ADR-0040 dla `AccountFormDialog` (`FormField`, spinner, blokada zamykania), co zamyka odroczenie z dwóch poprzednich zmian?
6. **Zabezpieczenie:** nowy `AccountsHardcodedUiTest` na wzór `DashboardHardcodedUiTest` (rekomendowane) czy tylko wpis w `js.md`?
