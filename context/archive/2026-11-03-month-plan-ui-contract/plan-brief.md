# Kontrakt UI widoku Plan miesiąca — Plan Brief

> Full plan: `context/changes/month-plan-ui-contract/plan.md`
> Research: `context/changes/month-plan-ui-contract/research.md`

## What & Why

Widok `/month-plan` był budowany funkcja po funkcji. Ma dwa ręcznie złożone przełączniki, arbitralne siatki i etykiety bez nazwy, akcje pod obiema listami, przełącznik rodzaju, który zapisuje ustawienie kategorii, a wygląda jak pole formularza, oraz złą kwotę, która tylko wyłącza przycisk. Zmiana przenosi widok na kontrakt istniejącego systemu projektowego i zostawia bramkę, żeby nie wrócił do literałów.

## Starting Point

Kolory już są na tokenach: 0 hex i 0 klas palety. Dług to układ i komponenty, czyli zarzuty C1–C5 w `research.md` z plik:linia. Repo ma klasy komponentowe w `app.css`, `ListSurface`, `Spinner`, reka-ui 2.11 i precedens testu skanującego źródło. Nie ma narzędzia do zrzutów ani testów komponentów Vue.

## Desired End State

Przy kilkunastu pozycjach na telefonie „Do wydania” i przycisk zapisu są stale u dołu ekranu. Błędne pole jest czerwone, ma komunikat i jest ogłaszane przez czytnik ekranu. Przełącznik rodzaju mówi, że zapisuje się od razu dla wszystkich miesięcy, i pokazuje zapis w toku. Każdy przełącznik to jeden przystanek Tab. Kitchen sink `/dev/month-plan` pokazuje 7 stanów, a test w CI blokuje wartości arbitralne w plikach widoku.

## Key Decisions Made

| Decision                    | Choice                                                                                       | Why (1 sentence)                                                                                 | Source   |
| --------------------------- | -------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------ | -------- |
| Przełącznik                 | Lokalny `SegmentedControl.vue` na reka `ToggleGroup`, bez CLI                                | Roving focus za darmo, bez nowych pakietów; `components.json` wskazuje nieistniejący config      | Research |
| Siatka i etykiety           | `.month-plan-grid`, `.column-header`, `.section-label` w `@layer components`                 | Wzorzec `.category-meter-*`; zatwierdzone 11 px dostaje nazwę zamiast kopii                      | Research |
| Fokus `ring-[3px]`          | W klasie `.segmented-control-item` w `app.css`, bez wyjątku w skanie                         | Precedens `.native-select`; skan obejmuje widoki, nie źródło tokenów                             | Plan     |
| Wiersz tytułu               | Wzorzec `Budget.vue:195-210`, nie `Heading`                                                  | `Heading` to `h2` bez slotów, tylko dla ustawień                                                 | Research |
| Zakres przełącznika rodzaju | Nota raz pod listą wydatków + ton `neutral` + spinner i błąd w wierszu + dopisek o „Anuluj” | Zakres powiedziany raz, bez szumu na 20 wierszach                                                | Plan     |
| Układ edycji                | Read mode: „Zmień plan” w tytule; edycja: przyklejony pasek z „Do wydania” i akcjami        | Suma i zapis zawsze na ekranie przy wpisywaniu w dolne wiersze                                   | Plan     |
| Wybór miesiąca              | Pasek miesięcy pod tytułem, bez `Card`                                                       | Za szeroki na wiersz tytułu; karta była tylko opakowaniem                                       | Plan     |
| Błędna kwota                | Oznaczenie po blur, znika od razu po poprawie; licznik „Niepoprawne kwoty: N” zawsze na żywo | Wyczyszczenie pola nie świeci na czerwono, a wyłączony przycisk jest zawsze wyjaśniony           | Plan     |
| Bramka wizualna             | Kitchen sink tylko dla `local` + zrzuty ad hoc z cache Playwrighta; w CI tylko skan Pest     | Bez nowej zależności; najtańsza forma z `/10x-ui`                                                | Plan     |
| Bramka literałów            | `tests/Unit/MonthPlanHardcodedUiTest.php`, nie `vp check`                                    | Oxlint nie widzi `class` w `<template>`; precedens `LandingPageSourceTest`                       | Research |

## Scope

**In scope:**
- `MonthPlan.vue`, `MonthPlanItemRow.vue`;
- nowe `SegmentedControl.vue` i `MonthPlanEditBar.vue`;
- klasy w `app.css`;
- `lib/month-plan-amount.ts` z testem;
- tłumaczenia (PHP + TS + `assertInertia`);
- trasa i strona kitchen sink;
- test skanujący;
- `.ai/rules/js.md` i `.docs/design-system.md`.

**Out of scope:**
- domena rodzaju (endpoint, `can_change_kind`, „Anuluj” go nie cofa);
- kalkulatory i ich kopie TS;
- inne widoki z tymi samymi ciągami (D1);
- `AppearanceTabs` (D2);
- `PageHeader` (D6);
- `BudgetMonthPicker`;
- nowe zależności i bramka zrzutów w CI;
- liczba mnoga.

## Architecture / Approach

Kontrakt przed pikselami. Klasy i `SegmentedControl` powstają najpierw w kitchen sink (faza 2), widok je przyjmuje (faza 3), a stany dochodzą na końcu (faza 4). Strona trzyma stan rodzaju w toku i jego błąd (per wiersz) oraz zbiór dotkniętych pól. `MonthPlanItemRow` dostaje propsy `invalid`, `kindPending` i `kindError`. `MonthPlanEditBar` stoi jako ostatnie dziecko `<Form>` ze `sticky bottom-0` i wysyła formularz. Każdy nowy tekst zmienia `ui.php` i `UiTranslations` w tym samym commicie.

## Phases at a Glance

| Phase                          | What it delivers                                                                | Key risk                                                                     |
| ------------------------------ | ------------------------------------------------------------------------------- | ---------------------------------------------------------------------------- |
| 1. Kitchen sink i odniesienie  | `/dev/month-plan` (tylko `local`), dane testowe, test 404, zrzuty obecnego stanu | Import Wayfinder dla trasy tylko dla `local` złamie `types:check` w CI       |
| 2. Elementy kontraktu          | Klasy w `app.css` i `SegmentedControl` (tony `primary` / `neutral`)             | Reka odznacza aktywną opcję w trybie `single`, wrapper musi to ignorować     |
| 3. Widok (C1, C2, C4)          | Przełączniki, siatka, etykiety, wiersz tytułu, pasek miesięcy, pasek edycji     | Pasek sticky zasłania ostatni wiersz albo nie przykleja się w układzie       |
| 4. Stany (C3, C5)              | Nota i stan zapisu rodzaju, błędna kwota po blur, licznik, macierz 7 stanów     | Stan dotkniętych pól nie resetuje się przy zmianie miesiąca lub trybu        |
| 5. Utrwalenie                  | Test skanujący, reguła w `js.md`, `design-system.md`, zrzuty końcowe, `ci:check` | Regex PCRE przepuszcza literał, który łapie grep z `/10x-ui`                 |

**Prerequisites:** świeży worktree wg AGENTS.md (`composer install`, `.env`, `npm ci`, `npm run build`); podgląd z worktree na osobnej bazie (http://localhost:8090, `test@example.com` / `password`); Chromium w `~/.cache/ms-playwright`.
**Estimated effort:** ~3–4 sesje w 5 fazach; najcięższe są fazy 3 i 4.

## Open Risks & Assumptions

- Wersję `playwright` do skryptu zrzutów trzeba dobrać do rewizji Chromium w cache (1248). Skrypt jest jednorazowy i niecommitowany.
- `@variant lg` w `@layer components` nie był dotąd uruchamiany w przeglądarce. Kitchen sink w fazie 2 to sprawdzi.
- Treści komunikatów (`kind_scope_note`, `amount_invalid`, nowy `changing_hint`) to propozycje. Ostateczne brzmienie potwierdza się przy przeglądzie fazy 4.

## Success Criteria (Summary)

- Na telefonie zatwierdzenie lub zapis planu nie wymaga przewijania na koniec strony, a „Do wydania” jest widoczne przy wpisywaniu.
- Użytkownik wie, które pole jest złe, i wie, że zmiana rodzaju zapisuje się od razu dla wszystkich miesięcy.
- `composer ci:check` failuje, gdy ktoś wpisze wartość arbitralną w plikach widoku; kitchen sink pokazuje 7 stanów.
