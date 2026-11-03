---
date: 2026-11-03T13:01:22+01:00
researcher: Sebastian Kostecki (z Claude)
git_commit: 547d3ef7358dad06a6e6c4217942f3256673179a
branch: month-plan-ui-contract
repository: household-treasurer
topic: "Dwukierunkowy audyt UI widoku /month-plan — zarzuty i decyzje kontraktu"
tags: [research, ui, design-system, month-plan, MonthPlan.vue, MonthPlanItemRow, app.css]
status: complete
last_updated: 2026-11-03
last_updated_by: Sebastian Kostecki (z Claude)
---

# Research: dwukierunkowy audyt UI widoku /month-plan

**Date**: 2026-11-03T13:01:22+01:00
**Researcher**: Sebastian Kostecki (z Claude)
**Git Commit**: 547d3ef7358dad06a6e6c4217942f3256673179a
**Branch**: month-plan-ui-contract
**Repository**: household-treasurer

## Research Question

Dwukierunkowy audyt widoku `/month-plan` (`MonthPlan.vue` + `MonthPlanItemRow`, `MonthPaceMeter`, `MonthSummaryCard`, `MonthYearSummary`, `BudgetMonthPicker`). Zweryfikować i rozwinąć 5 zarzutów z audytu wstępnego `/10x-ui` i zapisać je z plik:linia i wpływem pod `## Charges`. Ustalić:

1. shadcn-vue `toggle-group` przez CLI czy lokalny `SegmentedControl` na reka `ToggleGroup`;
2. który wzorzec `.*-grid` z `app.css` powielić;
3. czy `Heading` nadaje się na nagłówek z akcją;
4. gdzie postawić kitchen sink, skoro repo nie ma Playwrighta;
5. czy `vp check` może przyjąć skan wartości zakodowanych na sztywno ograniczony do tego widoku.

## Summary

Pięć zarzutów się potwierdziło. Dwa zostały zawężone przez dowody:

- **Zarzut 2:** rozmiar 11 px dla nagłówków kolumn jest zatwierdzony w `.docs/design-system.md:16`. Problemem nie jest więc sam rozmiar, tylko to, że zatwierdzona wartość nie ma nazwy i jest kopiowana jako `text-[11px]`.
- **Zarzut 3:** miejsce przełącznika rodzaju na stronie planu to świadoma decyzja (`context/archive/2026-11-02-month-plan-and-to-spend/plan.md:52,68,70`). Problemem jest prezentacja, czyli zakres zapisu, stan ładowania i położenie błędu, a nie lokalizacja.

Odpowiedzi na pytania:

1. **Lokalny `SegmentedControl.vue` na reka `ToggleGroupRoot`.** Bez CLI. Reka 2.11 daje roving focus i strzałki. Trzeba przechwycić odznaczenie w trybie `single`.
2. **Powielić `.category-meter-row` / `.category-meter-header-row`.** To jedyny wzorzec z klasą wiersza nagłówka i szablonem kolumn w CSS. Dla Month Plan wystarczy sam `grid-template-columns`, bez `grid-template-areas`.
3. **`Heading` się nie nadaje** (`h2`, `text-xl`, `mb-8`, brak slotów). Wzorzec do skopiowania to wiersz tytułu z `Budget.vue:195-210`.
4. **Kitchen sink jako uwierzytelniona trasa Inertia tylko dla `local`** + strona `resources/js/pages/dev/MonthPlanSink.vue` + fixture TS. Zrzuty robione ad hoc. Repo nie ma żadnego narzędzia do zrzutów.
5. **Nie przez `vp check`**, bo oxlint nie widzi klas w `<template>`. Pasuje test Pest skanujący źródło. Wzorzec już istnieje: `tests/Unit/LandingPageSourceTest.php`. Taki test biegnie w `composer ci:check` → `php artisan test`.

## Charges

Format: kategoria, kotwica, dowód, wpływ na użytkownika, kierunek poprawki. Status planu nadaje `/10x-plan`.

### C1 — Brakujący współdzielony komponent: przełącznik segmentowy (2 kopie w widoku)

- **Status:** planned → faza 2

- **Gdzie:** `resources/js/pages/MonthPlan.vue:595-620` (odkładam / dobieram) oraz `resources/js/components/MonthPlanItemRow.vue:143-160` (rachunek / bieżąca).
- **Dowód:** oba miejsca używają tego samego markupu. Kontener to `div role="group"` z klasami `border-border/60 bg-muted/40 inline-flex gap-1 rounded-xl border p-1`. W środku są `Button size="sm"`, wariant `default`/`ghost` przez `aria-pressed`, z nadpisaniem `h-7 rounded-lg px-2 text-xs`.
  - Nadpisanie omija rozmiary z `resources/js/components/ui/button/index.ts:28-35`, gdzie `sm` = `h-8 rounded-xl`.
  - W `resources/js/components/ui/` nie ma komponentu `toggle` ani `toggle-group` (sprawdzona lista katalogów).
- **Wpływ:** każda opcja to osobny przystanek Tab, więc użytkownik klawiatury przechodzi przez wszystkie przyciski, a strzałki nic nie robią. Poprawka fokusu lub rozmiaru musi trafić do dwóch plików i łatwo się rozjeżdża.
- **Kierunek:** `resources/js/components/SegmentedControl.vue` na `ToggleGroupRoot` / `ToggleGroupItem` z reka-ui (szczegóły w „Decyzja 1”), a potem migracja obu miejsc.

### C2 — Brakujące tokeny: siatka kolumn i typografia etykiet bez nazwy

- **Status:** planned → faza 3

- **Gdzie (siatka):** nagłówek kolumn w `MonthPlan.vue:697` i wiersz w `MonthPlanItemRow.vue:61` mają ten sam szablon `grid-cols-[1fr_5.5rem_5.5rem] sm:grid-cols-[1fr_9rem_9rem] lg:grid-cols-[1fr_9rem_9rem_9rem]`.
  - Czwarta kolumna (odchylenie) jest ukrywana w dwóch miejscach: `MonthPlan.vue:706` (`hidden … lg:block`) i `MonthPlanItemRow.vue:210`. Na małych ekranach jej kopia wisi pod kwotą faktyczną (`MonthPlanItemRow.vue:201`, `lg:hidden`).
  - Pierwsza kolumna to `1fr`, nie `minmax(0,1fr)`. Wzorce w `app.css` używają `minmax(0, …)` (`resources/css/app.css:427,437`).
- **Gdzie (typografia):**
  - **Nagłówek kolumn:** `MonthPlan.vue:697` ma `text-[11px] font-medium tracking-wider uppercase`. To jedyne trafienie skanu wartości zakodowanych na sztywno w plikach widoku. Ten sam ciąg występuje jeszcze w `Operations.vue:232,272,305`, `OperationsImportPreview.vue:301` i w `app.css:207` (`.list-surface-table thead th`).
  - **Etykieta sekcji:** `text-muted-foreground text-xs font-medium tracking-wider uppercase` powtarza się 8 razy: `MonthPlan.vue:520,556,586`, `MonthSummaryCard.vue:32,104`, `BudgetMonthStrip.vue:42,76`, `BudgetPlanSummaryStrip.vue:34`. Dla żadnej z tych dwóch ról nie ma wspólnej klasy ani komponentu. Jedyny wyjątek to reguła `thead th` w `app.css:206-208`.
- **Kontekst:** oba rozmiary są zatwierdzone. Nagłówki kolumn mają 11 px (`.docs/design-system.md:16`), a podpisy `text-xs` (`.docs/design-system.md:21`). Zarzut dotyczy braku nazwy dla zatwierdzonej wartości, nie samego rozmiaru.
- **Wpływ:** nagłówki „Plan / Faktycznie / Odchylenie” trzymają się kolumn kwot tylko dzięki dwóm skopiowanym ciągom w dwóch plikach. Zmiana szerokości w jednym z nich przesuwa nagłówki nad nie te liczby. Długi `CategoryChip` w kolumnie `1fr` może rozepchnąć wiersz.
- **Kierunek:** w `@layer components` w `app.css` dodać:
  - `.month-plan-grid`: trzy progi `grid-template-columns`, z pierwszą kolumną `minmax(0,1fr)`;
  - klasę nagłówka kolumn (np. `.column-header`) z zatwierdzonym 11 px;
  - klasę etykiety sekcji (np. `.section-label`).

  Widok ma przestać zawierać `text-[11px]` i arbitralne `grid-cols-[…]`. Pozostałe widoki z tymi ciągami są poza zakresem (patrz „Deferred”).

### C3 — Przypadkowa architektura: globalny zapis rodzaju pozycji wygląda jak lokalna kontrolka formularza

- **Status:** planned → faza 4

- **Gdzie:** kontrolka w `MonthPlanItemRow.vue:143-160` emituje zdarzenie do `MonthPlan.vue:343-361`. Tam `router.patch(CategoryController.updateMonthItemKind…)` z `preserveState`, które obsługuje `app/Http/Controllers/CategoryController.php:76-81`. Kontroler aktualizuje kolumnę kategorii `month_item_kind` (migracja `database/migrations/2026_11_02_090956_add_month_item_kind_to_categories_table.php:12`).
- **Dowód zakresu:**
  - Rodzaj jest atrybutem kategorii, nie pozycji planu (`context/archive/2026-11-02-month-plan-domain-decisions/plan.md:125`).
  - Odczyt minionego miesiąca używa dzisiejszego rodzaju (`context/archive/2026-11-02-month-plan-and-to-spend/plan.md:70`). Podsumowanie miesiąca też nie zapisuje rodzaju na stałe (`context/archive/2026-11-03-month-summary/plan.md:59`).
  - Zmiana w bieżącym miesiącu zmienia więc odczyt każdego miesiąca tej kategorii, także już zamkniętych.
- **Co jest świadomą decyzją (nie zmieniać):** przełącznik na stronie planu (`month-plan-and-to-spend/plan.md:52`). Do tego osobny endpoint z `back()`, żeby nie gubić wpisanych kwot (`:68`). Do tego dostępność tylko dla bieżącego i przyszłych miesięcy przez `can_change_kind` (`app/Http/Controllers/MonthPlanController.php:285`). Działanie w trybie zmiany potwierdza `context/archive/2026-11-02-approved-plan-changes/plan.md:228`.
- **Co jest problemem prezentacji:**
  1. Kontrolka jest wizualnie identyczna z przełącznikiem odkładam / dobieram (C1), który zmienia tylko stan formularza do czasu „Zatwierdź”. Leży wewnątrz `<Form>` (`MonthPlan.vue:577`) z przyciskami „Anuluj / Zapisz zmiany” (`:819-835`), a `stopChanging` (`:167-170`) nie cofa rodzaju.
  2. Błąd `kindError` jest renderowany raz, nad obiema listami (`MonthPlan.vue:689`), a nie przy wierszu, który go wywołał.
  3. W czasie żądania `router.patch` (`:350-360`) kontrolka nie pokazuje stanu ładowania ani wyłączenia. W kodzie nie ma flagi „w toku” dla tego żądania (sprawdzone: `MonthPlan.vue:341-361`, `MonthPlanItemRow.vue:139-163`).
- **Wpływ:** w trybie „Zmień” użytkownik przełącza „Czynsz” na „rachunek” i klika „Anuluj”, bo spodziewa się cofnięcia wszystkiego. Rodzaj jest już zapisany i zmienia też odczyt minionych miesięcy. Jeśli zapis się nie uda, komunikat pojawia się nad listą, często poza ekranem.
- **Kierunek (bez zmiany domeny):**
  - Kontrolka ma komunikować zakres, np. podpis lub podpowiedź „ustawienie kategorii, zapisuje się od razu, dotyczy wszystkich miesięcy”.
  - Ma mieć stan w toku: wyłączenie tego wiersza w czasie żądania, `onFinish`.
  - Błąd ma być pokazany w wierszu, `InputError` pod kontrolką.
  - Wizualnie ma się różnić od przełączników formularza. Wybór formy należy do planu.

### C4 — Przypadkowa architektura: kolejność strony odzwierciedla kolejność dodawania funkcji, akcje są na dole

- **Status:** planned → faza 3

- **Gdzie:**
  - wiersz tytułu bez akcji: `MonthPlan.vue:484-496` (tylko lewa połowa, brak `justify-between`);
  - wybór miesiąca jako osobna `Card`: `:498-508`;
  - „Do wydania” + tempo: `:517-570`;
  - podsumowanie: `:572-575`;
  - odkładanie: `:583-669`;
  - dwie listy: `:691-806`;
  - dopiero potem akcje „Zmień / Anuluj / Zapisz zmiany” (`:808-838`) i „Zatwierdź” (`:840-850`).
- **Dodatkowo:** `<InputError :message="errors.items" />` jest zduplikowany (`:809`, `:841`). Bloki wykluczają się wzajemnie, bo `can_approve` wymaga stanu `proposal` (`MonthPlanController.php:205-207`), a `can_change` planu zatwierdzonego (`approved-plan-changes/plan.md:51`). To duplikacja kodu, nie podwójny komunikat.
- **Wpływ:** na telefonie, przy liście kilkunastu kategorii, trzeba przewinąć obie listy, żeby zatwierdzić plan albo wejść w tryb „Zmień”. Podczas edycji kwota „Do wydania”, która reaguje na wpisywane kwoty, jest u góry poza ekranem, a „Zapisz zmiany” na dole poza ekranem. Główna akcja strony nie jest widoczna na pierwszym ekranie.
- **Kierunek:**
  - Wiersz tytułu wg `Budget.vue:195-210`: `flex flex-wrap items-center justify-between gap-3`, `h1` + `Badge` po lewej, główna akcja po prawej. Patrz „Decyzja 3”.
  - W trybie edycji zdecydować o przyklejonym pasku z „Do wydania” i „Anuluj / Zapisz”. W aplikacji nie ma takiego wzorca; najbliższy idiom to przyklejony `tfoot` (`app.css:282-295`).
  - Rozważyć włączenie wyboru miesiąca do wiersza nagłówka zamiast osobnej karty.
  - Jeden `InputError` dla `errors.items`.

### C5 — Stany error/disabled: zła kwota wyłącza przycisk bez wskazania wiersza

- **Status:** planned → faza 4

- **Gdzie:** pole kwoty `MonthPlanItemRow.vue:173-180` (`Input` bez `aria-invalid`, bez `InputError`) → walidacja `allAmountsValid` (`MonthPlan.vue:236-240`, na podstawie `parsedAmount`, `:186-201`) → `:disabled` przycisków (`:828-832`, `:845`).
- **Dowód:**
  - `Input` ma gotowe style `aria-invalid:border-destructive aria-invalid:ring-destructive/20` (`resources/js/components/ui/input/Input.vue`, trzeci argument `cn`), ale widok nigdy nie ustawia `aria-invalid`.
  - Jedyne „ujawnienie” złej kwoty to automatyczne rozwinięcie sekcji bezczynnych jednorazowych (`MonthPlan.vue:437-448`). Dla zwykłych wierszy nie ma żadnego sygnału.
  - Pole „odkładam” (`:621-627`) też nie ma `aria-invalid`. `InputError` przy nim (`:651-653`) pokazuje tylko błąd serwera.
- **Wpływ:** użytkownik wpisuje „12,3,4” w jednym z 20 wierszy. „Zatwierdź / Zapisz zmiany” szarzeje, a nic nie mówi, które pole jest złe ani dlaczego. Czytnik ekranu też tego nie ogłasza.
- **Kierunek:**
  - Wiersz dostaje stan niepoprawnej kwoty (np. prop `invalid`), który ustawia `aria-invalid` na `Input`. Pod polem pokazuje się `InputError` z krótkim komunikatem.
  - To samo dla pola „odkładam”.
  - Przy wyłączonym przycisku podpis z liczbą błędnych pól.

### Deferred (poza zakresem tej zmiany, z powodem)

_Status D1–D6: deferred._

- **D1 — Inne widoki z tymi samymi ciągami** (`Operations.vue:232,272,305`, `OperationsImportPreview.vue:301`, `BudgetMonthStrip.vue:42,76`, `BudgetPlanSummaryStrip.vue:34`, `DashboardCategorySection.vue:87` z dryfem `text-xs tracking-wide`). Powód: zasada „jeden widok na zmianę”. Nowe klasy z C2 będą gotowe do przyjęcia tam w osobnych zmianach.
- **D2 — `AppearanceTabs.vue:16-26`**: klasy palety `bg-neutral-100`, `bg-white`, `text-neutral-500`, `hover:text-black`, surowe `<button>` bez `aria-pressed`. To najlepszy trzeci konsument `SegmentedControl`, ale to inny widok (ustawienia). Do osobnej zmiany.
- **D3 — `.docs/design-system.md:21`** mówi „nagłówek strony `text-xl font-semibold`”, a wiersze tytułu stron domenowych używają `h1 text-lg font-semibold` (`Budget.vue:197`, `MonthPlan.vue:485`, wg agenta także Operations, Accounts, TermDeposits). Rozstrzygnąć dokumentem albo kodem dla wszystkich stron, nie w tym widoku.
- **D4 — `.docs/design-system.md:55`** wymienia `Heading` jako gotowy element do ponownego użycia, ale `Heading` pasuje tylko do sekcji ustawień (Decyzja 3). Poprawić opis przy okazji reguły „Utrwal to”.
- **D5 — `resources/css/app.css:86`** `border-color: var(--color-gray-200, currentColor)` w warstwie zgodności v3. Klasa palety w źródle tokenów, nadpisywana potem przez `* { @apply border-border }` (`app.css:185-187`). Poza widokiem.
- **D6 — Ekstrakcja wspólnego `PageHeader`** dla Budget/Operations/Accounts/TermDeposits. Szersza niż jeden widok.

## Decyzje dla planu

### Decyzja 1 — przełącznik: lokalny `SegmentedControl` na reka-ui, bez CLI shadcn-vue

- **Reka-ui 2.11.0** (`node_modules/reka-ui/package.json`) eksportuje `ToggleGroupRoot` / `ToggleGroupItem`.
  - Korzeń renderuje `role: "group"` (`dist/ToggleGroup/ToggleGroupRoot.js:96`).
  - Element deleguje do `Toggle`, który ustawia `aria-pressed` (`dist/Toggle/Toggle.js:71`).
  - Semantyka jest więc ta sama co dziś (`group` + `aria-pressed`, nie `radiogroup`). Dochodzi roving focus z pętlą: jeden przystanek Tab, strzałki, Home/End (`dist/RovingFocus/utils.js:9-18`, `RovingFocusItem.js:59-79,96-99`, wg agenta, kotwice z `node_modules` w głównym checkoucie).
- **Pułapka:** w trybie `single` kliknięcie aktywnej opcji ją odznacza i emituje `undefined` (`dist/shared/useSingleOrMultipleValue.js:43`, sprawdzone). Obie kontrolki muszą zawsze mieć wartość, więc wrapper ignoruje `undefined`.
- **Dlaczego nie CLI:**
  - `components.json:5` wskazuje `"config": "tailwind.config.js"`, którego nie ma (Tailwind v4). Zachowanie CLI w tej sytuacji jest niesprawdzone.
  - Wygenerowany `toggle-group` + `toggle` używałby `rounded-md` i `data-[state=on]:bg-accent` (wg agenta z pamięci, rejestru nie zweryfikowano lokalnie). Wymagałby przepisania na przepis `rounded-xl` / `rounded-lg` (`.ai/skills/openship-ui-development/SKILL.md` „Radius recipe”) i wygląd `bg-primary` stanu włączonego.
  - Reka jest już zależnością, więc nie dochodzą żadne pakiety.
- **Kształt:** `resources/js/components/SegmentedControl.vue`, czyli komponent domenowy obok innych z `.ai/rules/js.md`. Nie w `ui/`, bo zasady mówią „nie edytuj `ui/*` pod jeden ekran”.
  - Propsy: `modelValue`, `options: {value,label}[]`, `ariaLabel`, opcjonalnie `disabled`.
  - Chrome kontenera jak dziś; elementy `h-7 rounded-lg px-2 text-xs`.
  - Stan włączony przez `data-[state=on]:bg-primary data-[state=on]:text-primary-foreground`.
  - Fokus taki jak w `button/index.ts:7` (`focus-visible:ring-ring/50 focus-visible:ring-[3px]`).
- **Do sprawdzenia w implementacji:** czy klasa `ring-[3px]` w nowym komponencie łamie skan (wzorzec `-\[[0-9.]+(px|rem)\]` łapie `ring-[3px]`). Albo użyć `buttonVariants` z `ui/button`, albo dopuścić to w skanie jako wartość z kontraktu fokusu (`design-system.md:15` „pierścień 3 px”).

### Decyzja 2 — siatka: wzorzec `.category-meter-row` + `.category-meter-header-row`

- Przegląd wzorców w `@layer components` (`app.css:193+`):
  - `.list-surface-table` (`:198-290`) to prawdziwa `<table>`.
  - `.operations-grid` (`:297-355`) i `.import-preview-grid` (`:359-411`) używają `grid-template-areas`, układają komórki w stos na mobile, a nagłówek ukrywają poniżej progu klasami Tailwinda w Vue. Nie mają klasy nagłówka.
  - `.category-meter-row` / `.category-meter-header-row` (`:425-462`) to jedyny wzorzec, w którym CSS posiada szablon kolumn dla wiersza i nagłówka.
- Month Plan pokazuje nagłówek na każdej szerokości i ukrywa tylko czwartą kolumnę. Wystarczy więc `.month-plan-grid` z trzema progami `grid-template-columns` (`minmax(0,1fr) 5.5rem 5.5rem` → `sm` `minmax(0,1fr) 9rem 9rem` → `lg` `minmax(0,1fr) 9rem 9rem 9rem`), użyty przez nagłówek i wiersz. Elementy tylko dla wiersza (`items-center border-b py-3 last:border-b-0`) zostają na wierszu.
- `@variant lg` w `@layer components` działa tym samym mechanizmem co `@variant md` (`app.css:315`), ale nie było to uruchamiane.

### Decyzja 3 — `Heading` nie; wiersz tytułu wg `Budget.vue:195-210`

- **`Heading.vue`:**
  - propsy `title`, `description?`, `variant?` (`resources/js/components/Heading.vue:2-6`);
  - brak slotów;
  - renderuje `<header class="mb-8 …"><h2 class="text-xl …">`;
  - 7 konsumentów, wszyscy w sekcji ustawień (wg agenta).
- **Strony domenowe** budują wiersz tytułu ręcznie: `flex flex-wrap items-center justify-between gap-3`, `h1 text-lg font-semibold` + `Badge` po lewej, akcja po prawej (`Budget.vue:195-210`, sprawdzone).
- **Rekomendacja:** skopiować ten wzorzec w `MonthPlan.vue:484`. Wspólny `PageHeader` to D6.

### Decyzja 4 — kitchen sink: trasa Inertia tylko dla `local` + fixture TS, zrzuty ad hoc

- **Stan repo (wg agenta, sprawdzone `composer.json` / `package.json` / `tests/`):**
  - Brak `pestphp/pest-plugin-browser`, Duska i Playwrighta w zależnościach.
  - Brak testów zrzutów.
  - Brak wzorca trasy tylko dla dev (`routes/web.php:15-64` ma tylko `/` i grupę `auth` + `verified`).
- **Testy komponentów Vue nie są dziś możliwe:**
  - `vite.config.ts:11` wyłącza wtyczki pod `VITEST`, także `@vitejs/plugin-vue`;
  - `vite.config.ts:44-46` ustawia tylko `include` `*.test.ts`;
  - brak `@vue/test-utils` / `happy-dom`.
- **Rozwiązywanie stron Inertia** jest automatyczne z `resources/js/pages` (`config/inertia.php` „pages.paths”, `resources/js/app.ts:8-33`). Strona spoza `Welcome` / `auth/` / `settings/` dostaje `AppLayout`, który wymaga propsów `auth` / `shell` (`app/Http/Middleware/HandleInertiaRequests.php:44,49`). Trasa musi więc stać w grupie `auth`. SSR jest włączony.
- **Propsy:** typ `MonthPlanReading` jest lokalny w `MonthPlan.vue:49-65`, nie eksportowany. Typy wierszy są w `resources/js/types/month-plan.ts`. Fixture TS trzeba napisać. Testy `resources/js/lib/*.test.ts` mają tylko lokalne budowniczki wierszy. Wzorcowe wartości są w `tests/Feature/MonthPlanPageTest.php`.
- **Rekomendacja:**
  - `routes/web.php`: w grupie `auth` dodać `if (app()->environment('local')) { Route::inertia('dev/month-plan', 'dev/MonthPlanSink') }`. To pierwszy taki strażnik w repo. Do tego test Pest, że trasa daje 404 w `testing`.
  - Strona `resources/js/pages/dev/MonthPlanSink.vue` renderuje stany obok siebie.
  - `MonthPlanReading` przenieść do `types/month-plan.ts`; fixture np. w `resources/js/lib/month-plan-fixtures.ts`.
- **Kształt strony:** renderowanie całego `MonthPlan.vue` 7 razy jest niewygodne, bo wywołuje `setLayoutProps` (`:81`), `router.patch` i `<Form>`. Wiersze, przełączniki, pola i stan pusty lepiej pokazać na wydzielonych komponentach. Hover i focus wymagają interakcji albo wymuszonych klas.
- **Zrzuty:** na maszynie jest cache przeglądarek Playwrighta (`~/.cache/ms-playwright`, chromium-1248). Zrzut desktop + jedna szerokość mobilna można zrobić skryptem poza zależnościami repo. W CI nie będzie bramki zrzutów, chyba że uczestnik zdecyduje o nowej zależności (trzy warunki z `/10x-ui`).

### Decyzja 5 — bramka wartości zakodowanych na sztywno: test Pest skanujący źródło, nie `vp check`

- **`vp check` = format + oxlint** (`vite.config.ts:60-100`: `lint.ignorePatterns`, `options.denyWarnings` / `typeAware`, brak `rules` / `plugins` / `overrides`).
  - Oxlint analizuje `<script>` plików `.vue`, nie atrybuty `class` w `<template>`.
  - Reguła własna wymagałaby `jsPlugins` świadomego szablonów Vue, czyli nowej zależności.
  - Wersji oxlint w lockfile nie uruchamiano, bo worktree nie ma `node_modules`.
- **Precedens:** `tests/Unit/LandingPageSourceTest.php:1-15` i `tests/Unit/FontStackTest.php` czytają źródło przez `file_get_contents` i sprawdzają `not->toContain(...)`. Biegną w `php artisan test`, a to jest częścią `composer ci:check` (`composer.json:67-73,77-82`) wołanego w CI w `.github/workflows/tests.yml:90-91`.
- **Pre-commit:** brak hooków (husky / lefthook / captainhook / `.git/hooks`).
- **Rekomendacja:** `tests/Unit/MonthPlanHardcodedUiTest.php`.
  - Jawna lista plików: `pages/MonthPlan.vue`, `MonthPlanItemRow.vue`, `MonthPaceMeter.vue`, `MonthSummaryCard.vue`, `MonthYearSummary.vue`, `BudgetMonthPicker.vue`, nowy `SegmentedControl.vue`.
  - Regex skanu przeniesiony do PCRE.
  - Komunikat błędu z plik:linia.
  - Dodatkowo asercja zakazująca arbitralnego `grid-cols-[`, bo obecny regex go nie łapie.
- **Warunek wstępny:** po C2 widok nie może mieć `text-[11px]`. Rozstrzygnąć `ring-[3px]` (Decyzja 1).

## Code References

- `resources/js/pages/MonthPlan.vue:484-496` — wiersz tytułu bez akcji (C4)
- `resources/js/pages/MonthPlan.vue:498-508` — wybór miesiąca w osobnej `Card` (C4)
- `resources/js/pages/MonthPlan.vue:520,556,586` — etykiety sekcji bez klasy (C2)
- `resources/js/pages/MonthPlan.vue:595-620` — przełącznik odkładam / dobieram (C1)
- `resources/js/pages/MonthPlan.vue:621-627,651-653` — pole odkładania bez `aria-invalid` (C5)
- `resources/js/pages/MonthPlan.vue:236-240,186-201` — `allAmountsValid` / `parsedAmount` (C5)
- `resources/js/pages/MonthPlan.vue:343-361` — `changeKind` i `router.patch` bez stanu w toku (C3)
- `resources/js/pages/MonthPlan.vue:689` — `kindError` nad listami (C3)
- `resources/js/pages/MonthPlan.vue:697-708` — nagłówek kolumn: `text-[11px]` i arbitralna siatka (C2)
- `resources/js/pages/MonthPlan.vue:808-850` — akcje na końcu strony, zduplikowany `errors.items` (C4)
- `resources/js/components/MonthPlanItemRow.vue:61` — arbitralna siatka wiersza (C2)
- `resources/js/components/MonthPlanItemRow.vue:143-160` — przełącznik rodzaju (C1, C3)
- `resources/js/components/MonthPlanItemRow.vue:173-180` — `Input` kwoty bez stanu błędu (C5)
- `resources/js/components/MonthPlanItemRow.vue:201,210` — odchylenie: kopia mobilna i kolumna `lg` (C2)
- `app/Http/Controllers/CategoryController.php:76-81` — zapis `month_item_kind` na kategorii (C3)
- `app/Http/Controllers/MonthPlanController.php:205-208,285` — `can_approve`, `can_change`, `can_change_kind`
- `resources/css/app.css:206-208,425-462` — `thead th` 11 px; wzorzec `category-meter-*`
- `resources/js/components/ui/button/index.ts:7,28-35` — fokus i rozmiary `Button`
- `resources/js/components/Heading.vue:2-6` — propsy bez slotów
- `resources/js/pages/Budget.vue:195-210` — wzorzec wiersza tytułu z akcją
- `tests/Unit/LandingPageSourceTest.php:1-15` — wzorzec testu skanującego źródło
- `.docs/design-system.md:15,16,21,55` — fokus 3 px, nagłówki 11 px, skala typografii, lista gotowych elementów

## Architecture Insights

- Kontrakt systemu projektowego jest dojrzały:
  - tokeny ról w `:root` / `.dark` → `@theme inline` (`app.css:10-68,99-181`);
  - zasady dla agentów w `.ai/rules/js.md` i `css.md` (wg `.ai/rules/index.md`);
  - jednostronicowa dokumentacja `.docs/design-system.md`;
  - ADR-0001/0003/0023/0032.

  Widok nie łamie zasad kolorów (0 hex, 0 klas palety w 10 plikach widoku). Jego dług to układ i komponenty, nie paleta.
- Repo ma ustalony sposób na powtarzalny układ: klasy komponentowe w `@layer components` w `app.css`. Brakuje takich klas dla typografii etykiet (sekcja / nagłówek kolumny), mimo że obie role są opisane w `design-system.md`.
- Regułę „Utrwal to” należy dopisać do `.ai/rules/js.md`. To ten plik ładuje się dla `resources/js/**/*.vue`, a `CLAUDE.md` jest blokiem 10x CLI. Zapis przez `record-rule` z Laravel Boost (`design-system.md:65`). Do tego aktualizacja `design-system.md` o nowe klasy i kitchen sink.

## Historical Context (from prior changes)

- `context/archive/2026-11-02-month-plan-and-to-spend/plan.md:52` — rodzaj ustawia się w dialogu kategorii albo przełącznikiem na stronie planu. **Wspiera** obecną lokalizację (C3 nie podważa).
- `…/month-plan-and-to-spend/plan.md:68` — osobny endpoint z `back()`, żeby `preserveState` nie gubił kwot. **Wspiera.**
- `…/month-plan-and-to-spend/plan.md:70` — rodzaj jest cechą kategorii, miniony miesiąc czyta dzisiejszy rodzaj, przełącznik tylko dla bieżącego i przyszłych. **Wspiera.** Obecny kod: `MonthPlanController.php:285`.
- `context/archive/2026-11-02-approved-plan-changes/plan.md:51,228` — tryb zmiany przez `isChanging` w tym samym `<Form>`; przełącznik rodzaju działa w trybie zmiany. **Wspiera.** Decyzje nie mówią nic o tym, że „Anuluj” nie cofa rodzaju. To luka prezentacji, nie decyzja.
- `context/archive/2026-11-03-month-summary/plan.md:59` — podsumowanie bez zapisanego rodzaju; zmiana rodzaju zmienia odczyt minionego miesiąca. **Wspiera** wpływ opisany w C3.
- `context/archive/2026-11-02-month-items-plan-vs-actual/plan.md:233` — plakietki „Zapłacone / Wpłynęło” z tokenem `success`. Zgodne z obecnym kodem (`MonthPlanItemRow.vue`), poza zakresem zarzutów.

## Related Research

- `context/archive/2026-11-02-month-plan-and-to-spend/research.md`
- `context/archive/2026-11-02-month-items-plan-vs-actual/research.md`
- `context/archive/2026-11-02-approved-plan-changes/research.md`

## Open Questions

Wybory produktowe dla `/10x-plan` (nie luki dowodowe):

1. **C3:** jak komunikować zakres przełącznika rodzaju? Podpis lub podpowiedź w wierszu, inny wygląd niż przełączniki formularza, czy oba?
2. **C4:** czy w trybie edycji dodać przyklejony pasek „Do wydania + Anuluj / Zapisz”, czy wystarczy akcja w wierszu tytułu? Czy wybór miesiąca wchodzi do nagłówka?
3. **Decyzja 4:** czy bramka zrzutów zostaje ad hoc (cache Playwrighta poza repo), czy uczestnik chce nowej zależności (Pest browser / Playwright) w CI?
4. **Decyzja 1 / 5:** `ring-[3px]` w `SegmentedControl` — `buttonVariants` czy wyjątek w skanie?

Niesprawdzone (niska waga):

- zachowanie CLI shadcn-vue i dokładna treść rejestru `toggle-group`;
- uruchomienie `vp check` / oxlint w wersji z lockfile;
- `@variant lg` w `@layer components` w przeglądarce.
