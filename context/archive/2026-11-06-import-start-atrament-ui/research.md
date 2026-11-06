---
date: 2026-11-06T15:35:30+01:00
researcher: Claude Code (claude-opus-5-5)
git_commit: 8ff6c14fd357daa9b69942dfab9b92f26fe7ce55
branch: worktree-import-start-atrament-ui
repository: household-treasurer
topic: "Import operacji, krok 1 (OperationsImport.vue) względem karty PageImportStart design systemu Atrament"
tags:
    [
        research,
        ui,
        atrament,
        import,
        OperationsImport,
        FormField,
        SegmentedControl,
        checkbox,
        translations,
    ]
status: complete
last_updated: 2026-11-06
last_updated_by: Claude Code (claude-opus-5-5)
---

# Research: Import operacji, krok 1 w Atramencie

**Date**: 2026-11-06T15:35:30+01:00
**Researcher**: Claude Code (claude-opus-5-5)
**Git Commit**: 8ff6c14fd357daa9b69942dfab9b92f26fe7ce55
**Branch**: worktree-import-start-atrament-ui
**Repository**: household-treasurer

## Research Question

Potwierdzić lub odrzucić pięć zarzutów wstępnego audytu `/10x-ui` z `change.md` dla `resources/js/pages/OperationsImport.vue` (wzorzec: karta `PageImportStart` w https://claude.ai/artifact/9gJSdtfpFgUAa8oEMgR4HA, nowy ADR-0048, brakujące klucze `import_optional_column` i `import_no_column`) oraz zebrać, co z repo można użyć ponownie, a czego brakuje.

## Summary

Wszystkie pięć zarzutów się potwierdza; zarzut 2 jest poważniejszy niż w audycie — to błąd danych, nie kosmetyka:

- **Format „Inny” nie działa dla plików `.csv`.** Wybór pliku `.csv` ustawia format `mbank` i wychodzi przed policzeniem kolumn (`OperationsImport.vue:98-99`, `:104-105`); przełączenie na „Inny” czyści etykiety (`:142`), a „przeładowanie” pliku przez `null` → ten sam `File` w jednym ticku (`:147-149`, `:154-156`) nie wywołuje watchera (Vue porównuje wartość w chwili flush: stara = nowa). Zestaw „Kolumny” (`:369-370`) pojawia się więc tylko dla plików bez rozszerzenia `.csv`/`.xlsx`.
- **Checkbox nagłówka jest martwy** (`:351-357`): reka 2.11 nie zna `checked` / `update:checked`, pole startuje odznaczone, `hasHeader` zostaje `true` na zawsze. Nietknięte pole nie wysyła `has_header` → walidacja `required_if:import_format,other` (`PreviewCsvImportRequest.php:54`) odrzuca formularz, a strona nie ma `InputError` dla `has_header` ani `amount_layout` — wysyłka „nic nie robi”. Wartości `0` nie da się wysłać, więc plik bez nagłówka traci pierwszą operację (`CsvImportParser.php:51`).
- **Dwa brakujące klucze** to jedyne braki `t.<grupa>.<klucz>` w repo według jednorazowego skanu (dolne ograniczenie, patrz C1); typ `operations: Record<string, string>` (`resources/js/types/shell.ts:45`) ich nie wyłapie.
- Komponenty do przebudowy istnieją (`FormField`, `SegmentedControl` z `name`, `IndexEmptyState`, `.form-preview`, `AccountSelect` ze znakiem banku). Brakuje: panelu formularza strony ze stopką akcji, tokenu szerokości ~640 px, klasy listy pól, przepisu checkboxa/`fieldset` w `FormField` — to decyzje do ADR-0048.
- Krok 1 nie ma żadnego strażnika (`check:ui`, `*HardcodedUiTest`, kitchen sink) ani testu stanu „brak aktywnych kont”.

## Charges

Każdy zarzut: plik:linia, wpływ na użytkownika, status po researchu.

### C1. Brakujące tłumaczenia — **potwierdzony**

- `OperationsImport.vue:428` (`import_optional_column`) i `:439` (`import_no_column`); w `lang/pl/ui.php` (jedyny locale, sąsiedzi `:607-611`) brak obu kluczy.
- Wpływ: w formacie „Inny” etykieta brzmi „Nadawca / odbiorca ()” (`'counterparty' => 'Nadawca / odbiorca'`, `lang/pl/ui.php:655`), a pierwsza opcja listy jest pusta — użytkownik nie wie, że kolumnę można pominąć.
- Dlaczego nikt nie zauważył: tłumaczenia idą przez `HandleInertiaRequests.php:50` → `UiTranslations::all()` (`app/Support/UiTranslations.php:10-20`) → `useTranslations` (cast, `resources/js/composables/useTranslations.ts:5-9`); grupy poza `nav` i `shell` są `Record<string, string>` (`resources/js/types/shell.ts:21-48`). W `tests/` i `resources/js/**/*.test.ts` nie znaleziono testu porównującego użyte klucze z `lang/pl/ui.php`; testy Feature sprawdzają pojedyncze klucze przez `assertInertia` (np. `tests/Feature/MonthPlanPageTest.php:49-54`).
- Skan jednorazowy (724 klucze-liście vs 589 różnych `t.<g>.<k>` w `resources/js`): brakuje dokładnie tych dwóch. Regex pomija dostęp przez nawiasy, klucze dynamiczne, łamane linie i aliasy inne niż `t` — to dolne ograniczenie.
- Czerwony test do wyboru w planie: test Pest, który `require`-uje `lang/pl/ui.php` i porównuje z kluczami `t.<g>.<k>` wyciągniętymi z `resources/js/**/*.{vue,ts}`.
- Copy: wzorzec nie podaje tekstu; propozycja „opcjonalnie” i „Brak kolumny” (decyzja planu).

### C2. Martwy checkbox nagłówka i martwe przeliczanie kolumn — **potwierdzony, rozszerzony (błąd danych)**

- `OperationsImport.vue:351-357`: `:checked="hasHeader"` i `@update:checked` — wrapper `resources/js/components/ui/checkbox/Checkbox.vue:9-10` przekazuje tylko propsy reka 2.11.0 (`modelValue`, `update:modelValue`), więc oba spadają do atrybutów `<button>`. Render SSR (worker, `node -e`) dał `aria-checked="false" data-state="unchecked"` przy `hasHeader = true`.
- reka renderuje w `<form>` ukryty `<input type="checkbox" name="has_header" value="1">`; Inertia `<Form>` buduje `FormData` z elementu formularza (`@inertiajs/vue3` dist `index.js:1122`). Skutek na ścieżce „format Inny”:
    - pole nietknięte → `has_header` nie jest wysyłane (ukryte `has_header=0` z `:362-367` renderuje się tylko przy `!hasHeader`) → błąd walidacji `required_if` (`PreviewCsvImportRequest.php:54`), na stronie niewidoczny (brak `InputError` dla `has_header`);
    - pole kliknięte raz → `has_header=1`; „bez nagłówka” (`0`) nie da się wysłać, więc dla pliku bez nagłówka parser pomija pierwszą operację bez ostrzeżenia (`CsvImportParser.php:51`). Odwrotny przypadek (nagłówek jako dane) kończy się odrzuceniem wiersza bez daty (`CsvImportController.php:82-84`, `:472-481`).
    - Niepotwierdzone w przeglądarce: wartość DOM ukrytego inputu po kliknięciu (`"1"` vs `"true"`) — wniosek z kodu Vue (`patchDOMProp`).
- `hasHeader` nigdy się nie zmienia, więc watcher `:152-158` nie rusza. Niezależnie od checkboxa przeładowanie pliku w obu watcherach (`:147-149`, `:154-156`) jest no-opem (worker potwierdził na vue 3.5.44: 0 wywołań). Skutek: dla `.csv` + „Inny” zestaw „Kolumny” się nie pojawia; indeksy kolumn z wcześniejszego pliku nie są zerowane, więc `canSubmit` (`:53-69`) może być `true` przy ukrytym zestawie.
- `amount_layout` (`:454-482`) też nie ma `InputError`, a wysyła się tylko przy widocznym zestawie (`:369-370`; walidacja `:57`).
- Strażnik: `resources/js/ui-contract.test.ts:48-52` trzyma `['pages/OperationsImport.vue']` jako jedyny wyjątek; `:115-140` pada zarówno na nowe naruszenie, jak i na wyjątek, który już nie pasuje — poprawka i usunięcie wyjątku muszą wejść w jednym commicie. Issue #186 (OPEN, `needs-triage`): Accounts i TermDeposits już naprawione, to ostatnie miejsce — poprawka zamyka issue.
- Pokrycie testami: wszystkie testy „Inny” wysyłają `has_header => true` (`tests/Feature/CsvImportTest.php`), brak przypadku `has_header = false/"0"`; brak testu JS/E2E strony (`tests/e2e/` ma tylko account-edit, auth, seed).
- Kierunek dla planu: `v-model` na `Checkbox` (wzorzec `CategoryFormDialog.vue`, `js.md`), jawne ukryte `has_header` z wartością `hasHeader ? 1 : 0` zamiast polegania na wewnętrznym inpucie reka (do decyzji), przeliczanie etykiet kolumn bez sztuczki z `null` (np. funkcja w `resources/js/lib/` z testem Vitest: tekst pliku + `hasHeader` → etykiety i domyślne indeksy), `InputError` dla `has_header` i `amount_layout`, test Feature dla `has_header = 0`.

### C3. Tytuł, panel i pola poza Atramentem — **potwierdzony**

- `:199` `h1.text-lg font-semibold` i `:202` `text-muted-foreground text-sm` zamiast `page-title` / `meta` (`resources/css/app.css:246`, `:264`). Wzorce wiersza tytułu się różnią: `Accounts.vue:128-138` (opis `text-sm`) vs `Operations.vue:156-171` (opis `meta`); wspólnego komponentu brak (`Heading.vue` to stary styl ustawień).
- `:206-210` „Wróć do operacji” bez ikony; gotowy wzorzec z `ArrowLeft` (`@lucide/vue`) w `OperationsImportPreview.vue:238-245`, ikona z `aria-hidden` jak `OperationsListPanel.vue:226`. ADR-0045:11: `ghost` dla „Anuluj”, `outline` dla akcji drugorzędnych, które nie są wyjściem — „Wróć do operacji” jest wyjściem, ADR nie rozstrzyga (decyzja ADR-0048).
- `:213-233` `Card` + `CardTitle class="text-base"` (`text-base` zakazane w `tests/Unit/AccountsHardcodedUiTest.php:17-31`). Wzorzec: panel ~640 px, nagłówek h2 + `meta`, akcja w stopce. Nagłówek do skopiowania: `space-y-1` > `h2.section-title` + `p.meta` (`MonthSummaryCard.vue:88-98`).
- `:565-572` „Pokaż podgląd” w treści. W sprawdzonych panelach stron (`ListSurface`, `DashboardExecutionPanel`, `MonthPlanTable`, `MonthSummaryCard`, `DashboardHoldings`) nie ma stopki z przyciskiem: `ListSurface` `#footer` jest bez `border-t` (`ListSurface.vue:39-41`), `DialogFooter` zależy od tokenów paddingu dialogu (`ui/dialog/DialogFooter.vue:18`), `CardFooter` nieużywany poza `components/ui`. Najbliższy wzorzec akcji z powodem blokady: `ImportPreviewSubmitBar.vue` (`aria-describedby`).
- Osiem bloków `div.grid gap-2` + `Label` + `InputError` (`:240`, `:264`, `:279`, `:377`, `:399`, `:423`, `:484` w gałęzi signed, `:510`, `:536`). `FormField` (`resources/js/components/FormField.vue:6-13` propsy `label`, `id`, `hint`, `error`, `group`, `money`; slot `id`, `labelId`, `describedBy`, `invalid`, `controlClass`, `:45-63`) działa z `.native-select` (wzorzec `CreateBudgetDialog.vue:158-189`), z `AccountSelect` (`id`, `ariaDescribedby`, `ariaInvalid`, `AccountSelect.vue:13-31`; wzorzec `ExpenseFormDialog.vue:563-576`) i — bez istniejącego przykładu — z `Input type="file"` (`ui/input/Input.vue:27`). `.form-field` ustawia 34 px (`app.css:324-327`, ADR-0040:5).
- Uwaga: `required` na `AccountSelect` (`:289`) nic nie robi — ukryty input nie podlega walidacji ograniczeń (`AccountSelect.vue:82-88`).

### C4. Ręczne bloki zamiast przepisów — **potwierdzony**

- „Rozpoznawane pola” dwa razy: `:294-314` (mBank) i `:316-347` (BNP Paribas) jako `rounded-lg border p-4 text-sm` + `font-medium` + `list-inside list-disc`. Kontener `.form-preview` (`bg-background … rounded-md px-3 py-2.5`, `app.css:337-357`) pasuje do wzorca; `FormPreview.vue` jest tylko dla „przed → po” kwot (`:20-64`) — nie pasuje. Klasy listy w `app.css` brak.
- Układ kwoty: surowe radio `:454-482` bez fokusu z tokenów. `SegmentedControl` (`resources/js/components/SegmentedControl.vue`) z `name` renderuje `<input type="hidden" :name :value>` (`:63`) — wysyła się z `<Form>` (wzorzec `AccountFormDialog.vue:191-207`: `FormField group` + `SegmentedControl block tone="primary" name=…`). ADR-0040:7 tego wymaga dla wyboru jednej z kilku opcji w formularzu. A11y: reka `ToggleGroupRoot` → `role="group"` + przyciski `aria-pressed`, strzałki przenoszą fokus (nie wybór), pierścień `focus-visible:outline-ring` (`app.css:529-531`).
- Zestaw „Kolumny” `:369-375` (`fieldset` + `legend.text-sm font-medium`) i wiersz checkboxa `:350-361`: brak przepisu w `FormField` (wariant inline checkbox, `fieldset`/`legend`) — do decyzji (klasa w `@layer components` lub `FormField group`).

### C5. Przypadkowa architektura — **potwierdzony**

- Brak aktywnych kont: `CsvImportController::accounts()` (`:502-516`) zwraca niezarchiwizowane konta użytkownika bez filtra typu (gotówka wchodzi, lokaty to osobny model). Pusta lista → `accountId = ''` (`OperationsImport.vue:37`) → `canSubmit` zawsze `false` (`:54`), przycisk wyłączony bez powodu, `AccountSelect` z samym placeholderem. Wejścia nie blokują: „Importuj” w `OperationsPageActions.vue:87-92` (ma `accountsCount`, ale używa go tylko dla transferu i ruchu kapitału, `:43-57`), stan pusty `OperationsListPanel.vue:226-231`; polityka `FinancialOperationPolicy::create` zwraca `true` (`app/Policies/FinancialOperationPolicy.php:20-23`). Gotowy wzorzec stanu: `Card` + `IndexEmptyState` + link do Kont (`DashboardHoldings.vue:66-79`).
- Brak strażników kroku 1: `OperationsImport.vue` nie jest w `scripts/check-ui-literals.mjs` (pliki podglądu są, `:57-62`, od `7da06b0`); brak `*HardcodedUiTest` dla importu; jedyny test Inertia strony to `tests/Feature/AccountPickerTest.php:48-70`.
- Brak kitchen sinka kroku 1: `routes/web.php:48` ma tylko `dev/import-preview` (starszy wzorzec `Route::inertia` w bloku `local`). Bieżący wzorzec: `/dev/ui/*` z kontrolerem `abort_unless(app()->isLocal(), 404)` (`app/Http/Controllers/Dev/AccountsKitchenSinkController.php:13`), strona renderuje prawdziwy widok z fiksur (`resources/js/pages/dev/AccountsKitchenSink.vue:8`, `:99-111`, macierz stanów `:12-41`), test Feature z trzema przypadkami (`tests/Feature/AccountsKitchenSinkTest.php:54-72`). Typ propsów strony jest lokalny (`OperationsImport.vue:22-30`) — fiksury potrzebują go wyeksportowanego.
- Reguła `.ai/rules/js-import.md` nosi tytuł „Import preview”, a jej glob obejmuje `OperationsImport*.vue` (`:3`); jedyny sink w niej to `/dev/import-preview` (`:18`).

### Bez zarzutu

- Wejście bez logowania: przekierowanie (`tests/Feature/CsvImportTest.php:28-34`); konto obce lub zarchiwizowane przy wysyłce: `Rule::exists(...)->whereNull('archived_at')` (`PreviewCsvImportRequest.php:47-53`) i `firstOrFail` (`CsvImportController.php:56-60`).
- Skan literałów: 0 trafień w `OperationsImport.vue`.
- Pusta kolumna nadawcy (`counterparty_column = ''`): `ConvertEmptyStringsToNull` nie jest wyłączony w `bootstrap/` ani `app/` (grep), reguła `nullable` (`PreviewCsvImportRequest.php:61`); brak testu tej ścieżki (wniosek z kodu).

## Code References

- `resources/js/pages/OperationsImport.vue:37,53-69,98-105,140-158,351-367,369-375,428,439,454-482,565-572` — miejsca zarzutów
- `resources/js/components/ui/checkbox/Checkbox.vue:9-10` — wrapper reka v2 (brak `checked`)
- `app/Http/Requests/PreviewCsvImportRequest.php:47-61` — walidacja kroku 1
- `app/Support/CsvImport/CsvImportParser.php:51` — pominięcie pierwszej linii przy `has_header`
- `app/Http/Controllers/CsvImportController.php:36-43,502-516` — `create` i lista kont
- `resources/js/ui-contract.test.ts:48-52,115-140` — wyjątek #186
- `resources/js/components/FormField.vue:6-67`, `SegmentedControl.vue:4-63`, `IndexEmptyState.vue`, `ListSurface.vue:30-66`
- `resources/css/app.css:162-163` (jedyne tokeny szerokości: `--dialog-width` 460, `--aside-width` 300), `:246-264` (style typografii), `:319-367` (`.native-select`, `.form-field*`, `.form-preview*`, `.form-field-row`), `:516-555` (segmented)
- `scripts/check-ui-literals.mjs:9-109`, `tests/Unit/AccountsHardcodedUiTest.php:10-98`
- `lang/pl/ui.php:581-614,655`, `resources/js/types/shell.ts:21-48`

## Architecture Insights

- Dwie ścieżki stanu w jednym formularzu: wartości wysyłane z DOM (`FormData` elementu formularza) i stan Vue (`canSubmit`, etykiety). Gdzie się rozjeżdżają (checkbox, ukryty `has_header`, `required` na ukrytym inpucie), serwer dostaje co innego niż widać. Jawne ukryte inputy sterowane stanem Vue (jak `SegmentedControl name`) usuwają ten rozjazd.
- Logika etykiet kolumn (`parseCsvLine`, wykrywanie separatora, domyślne indeksy, `:108-185`) siedzi w stronie bez testu; `resources/js/lib/` z `*.test.ts` to miejsce na nią (AGENTS.md „Where things live”).
- `FormField` i `SegmentedControl` są dziś używane tylko w dialogach; ADR-0040 nie ogranicza ich do dialogów, a `.form-field` ustawia wysokość przez zmienną kontekstu, więc działają też na stronie.
- Strażnicy `*HardcodedUiTest` są kopiowani per widok; `accounts-atrament-ui/follow-ups/review-fixes.md` (F6) kolejkuje wspólny helper — czwarta kopia zwiększa ten dług (decyzja planu: kopia z przemianowanymi globalnymi nazwami Pest albo lista w `check:ui` tylko).

## Historical Context (from prior changes)

- `context/archive/2026-11-04-import-preview-ui-contract/research.md:111` — „D3 [deferred] — Ekran wgrywania pliku (`OperationsImport.vue`, w tym issue #186)”; tak samo `plan.md:53`, `change.md:14`. Wspierane: krok 1 nigdy nie był projektowany.
- `context/archive/2026-11-03-budget-view-ui/research.md:114` — `:checked="hasHeader"` jako „możliwy błąd danych”, zalecana osobna zmiana o wyższym priorytecie. Wspierane i rozszerzone: błąd danych potwierdzony z kodu (C2), plus no-op przeładowania pliku, którego tam nie opisano.
- `context/archive/2026-11-03-operations-ui-contract/research.md:37` — `h1 text-lg` w `OperationsImport.vue:199` odroczony. Nadal aktualne.
- ADR-0002:3 — formaty nazwane plus „Inny” z ręcznym mapowaniem; ADR-0004:3 — formatu nie wybieramy z banku konta (zgadywanie z rozszerzenia pliku `:98-102` to osobna ścieżka, ADR jej nie dotyczy).
- ADR-0037:3, ADR-0040:5,7, ADR-0041:7, ADR-0044:3, ADR-0045:11 — ograniczenia powierzchni, pól, przycisków i kolejności strony opisane w C3/C4.

## Related Research

- `context/archive/2026-11-04-import-preview-ui-contract/research.md` — krok 2 (podgląd), wzorzec strażników i sinka `/dev/import-preview`
- `context/archive/2026-11-06-accounts-atrament-ui/` — wzorzec `/dev/ui/accounts`, `AccountsHardcodedUiTest`, follow-up F6
- `context/changes/term-deposits-atrament-ui/` (main checkout, niezacommitowane) — równoległa zmiana UI, rezerwuje ADR-0046

## Open Questions

Decyzje dla `/10x-plan` (nie luki dowodowe):

1. **Zakres poprawki C2:** czy zgadywanie formatu z rozszerzenia (`.csv` → mBank, `.xlsx` → BNP Paribas, `:98-102`) zostaje — jeśli tak, przełączenie na „Inny” musi przeliczyć kolumny z tego samego pliku. Czy `has_header` idzie jawnym ukrytym inputem.
2. **ADR-0048:** panel formularza strony (szerokość — nowy token zamiast `max-w-xl` 576 / `max-w-2xl` 672 — i stopka z akcją: nowa klasa w `@layer components` czy `ListSurface flush` + własna stopka), wariant „Wróć do operacji” (`outline` z `ArrowLeft` jak w kroku 2 czy `ghost` jak „Anuluj”), opis w wierszu tytułu (`meta`), blok „Rozpoznawane pola” (`.form-preview` + nowa klasa listy), wiersz checkboxa i `fieldset` „Kolumny”.
3. **Copy** kluczy `import_optional_column` / `import_no_column`.
4. **Strażnicy:** `check:ui` + czwarty `ImportHardcodedUiTest` czy helper z F6; test brakujących kluczy tłumaczeń (Pest) jako trwały strażnik dla całego repo.
5. **Sink:** `/dev/ui/import` według wzorca kontrolera (`dev.ui.import`, test Feature z trzema przypadkami) i eksport typu propsów strony; nieweryfikowane: kilka instancji strony z `setLayoutProps` i stałymi `id` (`import-format`, `import-file`, …) na jednej stronie sinka — duplikaty `id` są realnym ryzykiem dla `FormField`/`Label for`.
6. **Pusty stan:** czy „Importuj” w `OperationsPageActions.vue:87-92` ma się wyłączać bez kont (poza zakresem jednego widoku — raczej odroczyć i pokazać stan pusty na stronie importu).

Ryzyko między zmianami: `accounts-design-system-page` (main checkout, niezacommitowane, `change.md:26`) planuje zmienić `AccountMarkCompact`, używany w `AccountSelect` — pole „Konto” w kroku 1.

Niepotwierdzone w przeglądarce (wnioski z kodu): wartość ukrytego inputu reka po kliknięciu; zachowanie watchera przy realnym wyborze pliku (potwierdzone tylko izolowanym `node -e` na vue 3.5.44).
