# Sidebar pokazuje aktualny rok budżetu po każdej wizycie — Implementation Plan

## Overview

Sidebar nadal zaznacza stary rok po przełączeniu albo utworzeniu roku, bo `useShell()` czyta `page.props.shell` jeden raz, a trwały `AppSidebarLayout` nie jest montowany ponownie między wizytami. Poprawka zmienia `useShell()` na `ComputedRef<ShellState>`, czyli odczyt reaktywny, i dopisuje `.value` w skryptach. Regresję łapią dwa testy: jednostkowy `useShell` (najpierw czerwony) i E2E w Playwright na prawdziwym przejściu Inertia.

## Current State Analysis

Research (`context/changes/sidebar-year-refresh/research.md`) potwierdził przyczynę i mechanizm w przeglądarce. Przy trzech przejściach (wybór roku, utworzenie roku, wybór roku z `/accounts`) strona pokazuje nowy rok, a sidebar stary. Po F5 jest dobrze, bo serwer zawsze wysyła świeży `shell`. Przy utworzeniu roku brakuje w sidebarze także nowego segmentu. Kliknięcie roku, który sidebar błędnie uważa za bieżący, nic nie robi (`BudgetYearSwitcher.vue:60`), więc użytkownik nie wraca do niego bez F5.

Narażone są dwa komponenty trwale zamontowane w `AppSidebarLayout`: `AppSidebar` (przez propsy z `shell`) i `CreateBudgetDialog` (domyślny rok i lista źródeł kopiowania). `Dashboard.vue` i `useFirstRunChecklist` mają ten sam wzorzec, ale strona jest montowana od nowa przy każdej wizycie bez `preserveState`, więc dziś nie dają widocznego błędu. `useTranslations` ma tę samą wadę techniczną bez skutku dla użytkownika (`translations` zależy tylko od `app.locale`).

## Desired End State

Po każdej wizycie Inertia sidebar zaznacza rok z bieżącego `shell.budgetYear`, pokazuje segmenty z bieżącego `shell.budgetYears`, a `CreateBudgetDialog` z layoutu proponuje rok i źródła kopiowania z bieżącego `shell`. Weryfikacja: test jednostkowy `useShell` przechodzi, spec E2E `sidebar-year-refresh` przechodzi, `vue-tsc` nie zgłasza błędów, a ręcznie przełączenie i utworzenie roku zmieniają zaznaczenie w sidebarze bez F5.

### Key Discoveries:

- `useShell()` zwraca surową migawkę (`resources/js/composables/useTranslations.ts:11-15`); Inertia podmienia `page.value` przy każdej wizycie (`node_modules/@inertiajs/vue3/dist/index.js:599`), a layout dostaje patch bez nowego `setup` (`:656-668`).
- `AppSidebarLayout.vue:14` łapie `shell` w `setup` i przekazuje `shell.budgetYears` oraz `shell.budgetYear` do `AppSidebar` (`:25-28`).
- `CreateBudgetDialog.vue:46` łapie `shell` i czyta go w skryptach w `:59`, `:67`, `:75`.
- `useCommandPalette.ts:126-135` już czyta `page.props.shell` w chwili wywołania (poprawka `fc45ffa`), a `BudgetYearSwitcher.vue:29-35` ma `segments` jako `computed` nad propsami, więc świeże propsy zadziałają bez zmian.
- W `<script setup>` ref najwyższego poziomu jest rozpakowywany w szablonie, więc `shell.budgetYears` (`AppSidebarLayout`) i `shell.firstRun.showChecklist` (`Dashboard.vue:119`) działają bez zmian; `.value` jest potrzebne tylko w skryptach.
- Żaden test nie importuje `useShell` ani `useFirstRunChecklist`; wzorzec `vi.mock('@inertiajs/vue3')` z `vi.hoisted` jest w `resources/js/lib/budget-year-selection.test.ts:1-9`.
- Usunięcie budżetu zapomina `budget_year` z sesji i przekierowuje na `/budget` (`BudgetController.php:119-135`), więc sprzątanie w E2E jest też przejściem, na którym sidebar musi się odświeżyć.

## What We're NOT Doing

- Nie ruszamy `useTranslations()` (88 wywołań). Wada ukryta zostaje opisana w regule sidebara; zmiana języka w trakcie sesji byłaby osobną decyzją.
- Nie dodajemy zależności do testów komponentów (`@vue/test-utils`, `happy-dom`).
- Nie zmieniamy PHP, `ShellState` ani kształtu propsu `shell`, więc reguła `inertia-props.md` nie obowiązuje.
- Nie zmieniamy wyglądu sidebara, `BudgetYearSwitcher`, `AppSidebar` ani miejsca montowania `CreateBudgetDialog`.
- Nie dodajemy osobnej asercji E2E na listę źródeł kopiowania w `CreateBudgetDialog`; ten odczyt pokrywa test jednostkowy reaktywności `shell`, a w przeglądarce potwierdzamy tylko sidebar.
- Nie dodajemy `preserveState` do Dashboardu ani zmian w `Dashboard.vue` poza `.value`.

## Implementation Approach

`useShell()` staje się `computed(() => page.props.shell as ShellState)`, zgodnie ze wzorcem `computed(() => page.props...)` z dokumentacji Inertia 3 („Persistent Layouts”). Zmiana typu zwracanego sprawia, że `vue-tsc` wskazuje każde pominięte `.value`, a `useFirstRunChecklist` przestaje być `computed` nad migawką. Najpierw pada test jednostkowy na obecnym kodzie, potem poprawka; E2E dochodzi w fazie drugiej, bo wymaga builda produkcyjnego i stosu Sail.

## Faza 1: Test czerwony, potem poprawka `useShell`

### Overview

Test regresji `useShell` pada na obecnym helperze. Potem helper zwraca `ComputedRef`, a 4 miejsca użycia dostają `.value` tam, gdzie czytają `shell` w skryptach.

### Changes Required:

#### 1. Test jednostkowy reaktywności `shell`

**File**: `resources/js/composables/useTranslations.test.ts` (nowy)

**Intent**: Odtworzyć w teście to, co robi Inertia: wizyta podmienia cały obiekt strony, a nie mutuje starego `shell`. Test ma paść na obecnym kodzie i przechodzić po poprawce.

**Contract**: `vi.mock('@inertiajs/vue3', …)` z `vi.hoisted`, jak w `lib/budget-year-selection.test.ts`; `usePage` zwraca obiekt, którego `props` to `computed` nad `ref` strony; test podmienia `page.value` na nowy obiekt z innym `shell`. Przypadki: (1) `useShell().value.budgetYear` i `.budgetYears` odzwierciedlają nowy `shell` po podmianie strony; (2) `useFirstRunChecklist().value` przelicza się po podmianie `shell.firstRun`. Importy względne, z `vite-plus/test` (reguła `frontend-toolchain.md`). Przed poprawką uruchomić test i potwierdzić, że pada z powodu przestarzałej wartości, a nie błędu mocka.

#### 2. `useShell` jako `ComputedRef`

**File**: `resources/js/composables/useTranslations.ts`

**Intent**: Odczyt `page.props.shell` ma być reaktywny, żeby każdy wywołujący widział `shell` bieżącej wizyty. `useFirstRunChecklist` ma czytać `shell.value`.

**Contract**: `useShell(): ComputedRef<ShellState>`; `useTranslations()` bez zmian; `useFirstRunChecklist()` czyta `shell.value.firstRun.*` wewnątrz `computed`.

#### 3. `.value` w miejscach użycia

**File**: `resources/js/components/CreateBudgetDialog.vue`, `resources/js/pages/Dashboard.vue`

**Intent**: Skryptowe odczyty `shell` dostają `.value`; szablony zostają, bo `<script setup>` rozpakowuje ref najwyższego poziomu.

**Contract**: `CreateBudgetDialog.vue:59,67,75` (`shell.value.budgetYears`, `shell.value.copyableBudgetSources`) i `Dashboard.vue:93` (`shell.value.budgetYears`). `AppSidebarLayout.vue` zostaje bez zmian: `shell.budgetYears` i `shell.budgetYear` w szablonie są rozpakowywane, a layout przekazuje je dalej jako propsy.

### Success Criteria:

#### Automated Verification:

- Test regresji `useShell` pada na helperze sprzed poprawki: `npm test -- resources/js/composables/useTranslations.test.ts` (uruchomiony przed krokiem 2)
- Test przechodzi po poprawce: `npm test -- resources/js/composables/useTranslations.test.ts`
- Wszystkie testy frontendu przechodzą: `npm test`
- Kontrola typów przechodzi na świeżych plikach Wayfinder: `npm run build && npm run types:check`
- Kontrola statyczna bez ostrzeżeń: `npm run check`
- Kontrola literałów UI przechodzi: `npm run check:ui`

#### Manual Verification:

- Na `/budget` z dwoma latami kliknięcie drugiego roku w sidebarze zmienia zaznaczenie w sidebarze razem z nagłówkiem, bez F5
- Utworzenie roku przez „<rok> +” dodaje segment nowego roku w sidebarze i zaznacza go, bez F5
- Z `/accounts` kliknięcie roku zaznacza ten rok w sidebarze

**Implementation Note**: Po tej fazie i zielonych kontrolach automatycznych zatrzymaj się na ręczne potwierdzenie przez człowieka, zanim zaczniesz fazę 2. Checkboxy tych punktów są w `## Progress`.

---

## Faza 2: E2E i reguła

### Overview

Spec Playwright odtwarza trzy przejścia z research na prawdziwym buildzie i pilnuje, że sidebar nie zostaje ze starym rokiem. Reguła w `js-sidebar.md` zapisuje wniosek dla kolejnych agentów.

### Changes Required:

#### 1. Spec E2E odświeżania roku w sidebarze

**File**: `tests/e2e/sidebar-year-refresh.spec.ts` (nowy)

**Intent**: Pokryć błąd z perspektywy użytkownika. Użytkownik E2E ma jeden rok (2026), więc spec sam tworzy drugi rok przez dialog „Utwórz budżet” i usuwa go na końcu, także gdy asercja padnie.

**Contract**: Lokator segmentów: `[data-sidebar="sidebar"] .sidebar-year-segments button`, stan z `aria-pressed`, segment tworzenia ma `aria-label` „Dodaj budżet na rok <rok>”. Scenariusze: (1) utworzenie roku przez segment „+”: po zamknięciu dialogu (tytuł „Nowy budżet”) sidebar ma zaznaczony nowy rok, segment poprzedniego roku jest niezaznaczony, a „+” proponuje następny rok; (2) wybór poprzedniego roku z sidebara zaznacza go w sidebarze; (3) wybór roku z `/accounts` przechodzi na `/budget` i zaznacza ten rok w sidebarze. Sprzątanie przez menu „Akcje budżetu” → „Usuń budżet” → potwierdzenie „Usuń budżet” w `ConfirmActionDialog`, w `finally` albo `afterEach`; po usunięciu sidebar wraca do jednego roku. Wzorzec: nagłówek komentarza z ryzykiem i sprzątanie własnych danych jak w `account-edit-keeps-bank.spec.ts`. Rok tworzony wynika z tekstu segmentu „+”, nie z literału.

#### 2. Reguła o reaktywnym odczycie `shell`

**File**: `.ai/rules/js-sidebar.md`

**Intent**: Nowa sekcja mówi, że komponent zamontowany trwale w layoucie czyta `shell` tylko przez `useShell()` (`ComputedRef`) albo `usePage().props` w `computed`, bez migawki w `setup`. Dodaje też, że `useTranslations()` zwraca migawkę i dziś jest bezpieczne tylko dlatego, że `translations` zależy od `app.locale`.

**Contract**: Nowy nagłówek `##` w stylu sąsiednich sekcji, bez zmian ich treści. Wpis przez `record-rule` zgodnie z `AGENTS.md`, potem `php artisan boost:index-rules`, żeby indeks `.ai/rules/index.md` został spójny.

### Success Criteria:

#### Automated Verification:

- Spec przechodzi na buildzie produkcyjnym przy działającym stosie Sail i bez `public/hot`: `npx playwright test tests/e2e/sidebar-year-refresh.spec.ts`
- Istniejące specy sidebara dalej przechodzą: `npx playwright test tests/e2e/sidebar-current-section.spec.ts tests/e2e/command-palette.spec.ts`
- Indeks reguł jest aktualny: `php artisan boost:index-rules`
- Kontrole statyczne frontendu przechodzą: `npm run check`

#### Manual Verification:

- Po przebiegu specu baza E2E ma znów tylko rok 2026 (spec posprzątał)
- Spec pada na buildzie bez poprawki z fazy 1 (sprawdzone raz, np. przez `git stash` zmian z `resources/js` i `npm run build`), z komunikatem o zaznaczeniu starego roku
- Nowa sekcja w `js-sidebar.md` jest zwięzła i zgodna z opisem w tym planie

**Implementation Note**: Po tej fazie i zielonych kontrolach automatycznych zatrzymaj się na ręczne potwierdzenie przez człowieka. Faza zamyka zmianę, więc dalej `/10x-impl-review` i archiwizacja.

---

## Testing Strategy

### Unit Tests:

- `useTranslations.test.ts`: `useShell()` i `useFirstRunChecklist()` odzwierciedlają `shell` po podmianie obiektu strony (jak robi Inertia), a nie tylko po mutacji.
- Pokrywa reaktywność helpera i skryptowe odczyty z `CreateBudgetDialog`/`Dashboard` pośrednio, przez typ `ComputedRef` i `vue-tsc`.

### Integration Tests:

- E2E `sidebar-year-refresh.spec.ts`: utworzenie roku, wybór roku, wybór roku z innej strony, sprzątanie własnych danych. Pokrywa trwałość layoutu i prawdziwe przejście Inertia, czego test jednostkowy nie widzi.

### Manual Testing Steps:

1. Przy dwóch latach w bazie lokalnej: kliknij drugi rok w sidebarze na `/budget` i sprawdź zaznaczenie bez F5.
2. Utwórz rok przez „<rok> +” i sprawdź nowy segment w sidebarze oraz proponowany rok po ponownym kliknięciu „+”.
3. Z `/accounts` wybierz rok i sprawdź zaznaczenie po przejściu na `/budget`.
4. Usuń utworzony szkic roku i sprawdź, że sidebar wraca do jednego roku bez F5.

## Performance Considerations

`computed` nad `page.props.shell` przelicza się tylko przy zmianie obiektu strony; kosztu na żądanie nie ma. Zmiana nie dotyka PHP.

## Migration Notes

Nie dotyczy. Kształt propsu `shell` i `ShellState` zostają, a jedyna zmiana API to typ zwracany `useShell()` w 4 miejscach.

## References

- Related research: `context/changes/sidebar-year-refresh/research.md`
- Wcześniejsze zauważenie wady: `context/archive/2026-11-06-sidebar-action/reviews/impl-review.md` (F9)
- Wzorzec mocka Inertia w testach: `resources/js/lib/budget-year-selection.test.ts:1-9`
- Wzorzec E2E ze sprzątaniem: `tests/e2e/account-edit-keeps-bank.spec.ts`, `tests/e2e/sidebar-current-section.spec.ts`
- Komendy i warunki Playwright: `context/foundation/test-stack.md`
- Reguły: `.ai/rules/js-sidebar.md`, `.ai/rules/frontend-toolchain.md`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Test czerwony, potem poprawka `useShell`

#### Automated

- [x] 1.1 Test regresji `useShell` pada na helperze sprzed poprawki: `npm test -- resources/js/composables/useTranslations.test.ts` (uruchomiony przed krokiem 2) — df5f001
- [x] 1.2 Test przechodzi po poprawce: `npm test -- resources/js/composables/useTranslations.test.ts` — df5f001
- [x] 1.3 Wszystkie testy frontendu przechodzą: `npm test` — df5f001
- [x] 1.4 Kontrola typów przechodzi na świeżych plikach Wayfinder: `npm run build && npm run types:check` — df5f001
- [x] 1.5 Kontrola statyczna bez ostrzeżeń: `npm run check` — df5f001
- [x] 1.6 Kontrola literałów UI przechodzi: `npm run check:ui` — df5f001

#### Manual

- [x] 1.7 Na `/budget` z dwoma latami kliknięcie drugiego roku w sidebarze zmienia zaznaczenie w sidebarze razem z nagłówkiem, bez F5 — df5f001
- [x] 1.8 Utworzenie roku przez „<rok> +” dodaje segment nowego roku w sidebarze i zaznacza go, bez F5 — df5f001
- [x] 1.9 Z `/accounts` kliknięcie roku zaznacza ten rok w sidebarze — df5f001

### Phase 2: E2E i reguła

#### Automated

- [x] 2.1 Spec przechodzi na buildzie produkcyjnym przy działającym stosie Sail i bez `public/hot`: `npx playwright test tests/e2e/sidebar-year-refresh.spec.ts`
- [x] 2.2 Istniejące specy sidebara dalej przechodzą: `npx playwright test tests/e2e/sidebar-current-section.spec.ts tests/e2e/command-palette.spec.ts`
- [x] 2.3 Indeks reguł jest aktualny: `php artisan boost:index-rules`
- [x] 2.4 Kontrole statyczne frontendu przechodzą: `npm run check`

#### Manual

- [x] 2.5 Po przebiegu specu baza E2E ma znów tylko rok 2026 (spec posprzątał)
- [x] 2.6 Spec pada na buildzie bez poprawki z fazy 1 (sprawdzone raz, np. przez `git stash` zmian z `resources/js` i `npm run build`), z komunikatem o zaznaczeniu starego roku
- [x] 2.7 Nowa sekcja w `js-sidebar.md` jest zwięzła i zgodna z opisem w tym planie
