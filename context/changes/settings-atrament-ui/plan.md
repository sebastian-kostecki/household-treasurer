# Ustawienia według strony design systemu (ADR-0049) — plan implementacji

## Overview

Ustawienia (`layouts/settings/Layout.vue`, `pages/settings/Profile.vue`, `Security.vue`, `Appearance.vue`, `DeleteUser.vue`, `AppearanceTabs.vue`) wyrównujemy do strony `PageSettingsProfile` design systemu „C · Atrament i morski”:

- widoczny tytuł strony;
- podnawigacja z `aria-current`;
- sekcje w panelach;
- pola w `FormField`;
- strefa usuwania konta z alertem na `destructive-soft` i przyciskiem zarysowanym w czerwieni;
- potwierdzenie przez `ConfirmActionDialog`;
- przełącznik motywu jako `SegmentedControl` w tonie `neutral`.

Teksty Profilu, hasła i Wyglądu są po polsku, łącznie z dwoma toastami serwera i błędem złego hasła. Decyzje zapisuje ADR-0049, a pilnują ich: sink `/dev/ui/settings`, strażnik `SettingsHardcodedUiTest` i `npm run check:ui`. Payload Inertii się nie zmienia.

## Current State Analysis

Pełny audyt jest w `context/changes/settings-atrament-ui/research.md` (zarzuty C1–C7, wszystkie potwierdzone). Najważniejsze:

- **Layout** (`Layout.vue:35-73`):
  - tytuł „Ustawienia” to `Heading` (`h2`, `text-xl`); każda strona ma osobny ukryty `h1` (`Profile.vue:31`, `Security.vue:38`, `Appearance.vue:23`);
  - podnawigacja to `Button variant="ghost"` z `bg-muted`, bez `aria-current`, z `aria-label="Settings"`;
  - na telefonie pod podnawigacją stoi `Separator`, a szerokości dają `max-w-xl` / `md:max-w-2xl`;
  - układ wpina `app.ts:21-22` dla każdej strony `settings/*`.
- **Profil** (`Profile.vue:33-83`): `Heading variant="small"` i ręczne `div.grid gap-2 > Label + Input + InputError` na tle strony, bez panelu.
- **Usuwanie konta** (`DeleteUser.vue:27-110`):
  - ostrzeżenie złożone ręcznie: `border-destructive/20 bg-destructive/5`;
  - wypełniony przycisk `destructive`;
  - ręcznie złożony `Dialog` + `Form`, wbrew `js.md` „Confirmations use ConfirmActionDialog”;
  - „Anuluj” `secondary`;
  - fokus na polu hasła po błędzie (`:53`).
- **Bezpieczeństwo** (`Security.vue:41-106`): formularz hasła po angielsku („Update password”, „Current password”, „Save”), choć `t.settings.save` istnieje. Pod nim `ManageTwoFactor` i `ManagePasskeys` (ponad 30 angielskich ciągów, własne dialogi), które ta zmiana odracza.
- **Wygląd** (`AppearanceTabs.vue:8-26`): klasy palety (`bg-neutral-*`, `bg-white`, `hover:text-black`) i warianty `dark:`, etykiety „Light/Dark/System”, surowe `<button>` bez `aria-pressed`.
- **Tłumaczenia serwera:**
  - toasty `__('Profile updated.')` (`ProfileController.php:41`) i `__('Password updated.')` (`SecurityController.php:62`) idą przez `Inertia::flash('toast', …)`;
  - złe hasło przy usuwaniu konta i przy zmianie hasła daje angielski komunikat reguły `current_password` (`PasswordValidationRules::currentPasswordRules`);
  - `lang/` ma tylko `pl/pagination.php`, `pl/ui.php` i `vendor/`; `phpunit.xml:27` ustawia `APP_LOCALE=pl`.
- **Bramka:** żaden plik ustawień nie jest w `scripts/check-ui-literals.mjs`, nie ma strażnika `*HardcodedUiTest` dla ustawień ani sinka `/dev/ui/...`.

Weryfikacja podczas planowania dołożyła:

- **Worktree za `development`:** worktree `worktree-settings-atrament-ui` stoi na `c571c9d`, 6 commitów za `development` (`55b0585`). Brakuje mu ADR-0047, zmian w `design-system.md` (lista ADR, `mark-label`) i w `.ai/rules/js.md`.
- **Numery ADR:**
  - 0046 rezerwują Lokaty (`context/changes/term-deposits-atrament-ui/research.md` w głównym checkoucie, nieśledzony);
  - 0047 jest na `development`;
  - 0048 ma `.claude/worktrees/import-start-atrament-ui/context/foundation/adr/0048-import-start-follows-page-import-start.md`;
  - 0049 jest wolny.
- **„Anuluj” w `ConfirmActionDialog`:** `js-dialogs.md` i ADR-0045 wymagają `ghost`, a komponent ma `outline` (`ConfirmActionDialog.vue:67`), także na `development`. Zmiana wdrażająca ADR-0045 we wszystkich dialogach nie jest otwarta.
- **Zdarzenie błędu:** `ConfirmActionDialog` nie przekazuje zdarzenia błędu `Form` (`:45-52`), więc `DeleteUser` po przejściu na niego straciłby fokus na polu hasła.
- **Wariant przycisku:** `.ht-btn--danger` w `bundle.css:28` to przycisk `outline` (karta + `border`) z tekstem `destructive`, a nie wypełniony.
- **Warianty w repo:**
  - `Alert` nie ma wariantu na `destructive-soft` (`ui/alert/index.ts:11-19`), a `Badge` ma `destructive-soft` (`ui/badge/index.ts:21`);
  - kontrast `destructive` na `destructive-soft`: 5,03:1 (jasny) i 6,82:1 (ciemny); `foreground` na `destructive-soft` ponad 14:1.
- **Siatka bez telefonu:** `.ht-settings` (`bundle.css:313`) ma tylko siatkę `200px minmax(0, 640px)`, bez reguły dla telefonu.
- **`PasswordInput`:** ma `inheritAttrs: false` i `v-bind="$attrs"` na `Input` (`PasswordInput.vue:8,29`), więc `id`, `aria-describedby` i `aria-invalid` ze slotu `FormField` dojdą do pola.
- **Testy toastów:** w repo są już przez `assertInertiaFlash('toast.type', …)` (`MonthPlanPageTest.php:544`).

## Desired End State

- **Layout:**
  - widoczny `h1.page-title` „Ustawienia” i pod nim opis `text-muted-foreground text-sm` („Zarządzaj profilem i ustawieniami konta logowania”), jak wiersz tytułu Kont;
  - `nav` z `aria-label` „Ustawienia” i trzema `Link` w klasie `.settings-subnav-link`; aktywny ma `aria-current="page"` i wygląda jak karta (`card` + obwódka `border`);
  - od `lg` siatka `.settings-layout` (podnawigacja `--settings-nav-width` 200 px | treść do `--settings-content-width` 640 px), poniżej `lg` podnawigacja to jeden poziomy rząd nad panelami;
  - ukryte `h1` ze stron i `Separator` znikają.
- **Profil:** panel „Profil” (opis „Zaktualizuj imię i adres e-mail”) z dwoma `FormField` („Imię i nazwisko”, „Adres e-mail”) i stopką „Zapisz” (`primary`, spinner w trakcie).
- **Usuń konto:**
  - panel „Usuń konto” z `Alert` `danger` („Uwaga” / „Tej operacji nie można cofnąć.”) i przyciskiem `outline-destructive` „Usuń konto” z ikoną kosza;
  - przycisk otwiera `ConfirmActionDialog` (`destructive`) z polem „Hasło” w `FormField`;
  - po błędzie fokus wraca na pole, a komunikat jest po polsku;
  - „Anuluj” jest `ghost`, w tym i we wszystkich innych potwierdzeniach.
- **Bezpieczeństwo:** panel „Zmień hasło” z trzema `FormField` i „Zapisz”, po polsku. `ManageTwoFactor` i `ManagePasskeys` stoją pod nim bez zmian, nadal z `Heading`.
- **Wygląd:** panel „Wygląd” z `SegmentedControl` `tone="neutral"` („Jasny”, „Ciemny”, „Systemowy”, bez ikon), bez stopki, bo zapis jest natychmiastowy.
- **Serwer:** toast „Profil zapisany.” po zapisie profilu, „Hasło zmienione.” po zmianie hasła, a przy złym obecnym haśle „Podane hasło jest nieprawidłowe.”.
- **Bramka:**
  - `/dev/ui/settings` (tylko `local`) renderuje ustawienia w macierzy 7 stanów;
  - `SettingsHardcodedUiTest` i `npm run check:ui` obejmują oczyszczone pliki;
  - zrzuty 1280 i 390 px w obu motywach leżą w `context/changes/settings-atrament-ui/screenshots/`.

Weryfikacja: `composer ci:check`, `vendor/bin/sloppy ci`, `npm run types:check`, `npm run check:ui`, `npm run test`, testy Feature ustawień na PostgreSQL, zrzuty.

### Key Discoveries:

- `FormField.vue:6-13,30-66`: scoped slot daje `id`, `describedBy`, `invalid` i `controlClass`; `labelId`, `hintId` i `errorId` wynikają z `id`. Dziś tylko dialogi go używają, więc ustawienia są pierwszą stroną. `js-dialogs.md` zawęża regułę do dialogów, dlatego `js.md` dostaje osobne zdanie o ustawieniach.
- `SegmentedControl.vue`: `v-model`, `tone`, `ariaLabel` lub `ariaLabelledby`. Ponowne kliknięcie aktywnej opcji nie emituje zmiany. `useAppearance` daje `appearance` (`Ref<'light' | 'dark' | 'system'>`) i `updateAppearance`.
- `ConfirmActionDialog.vue:19-26,41-85`: propsy `form`, `title`, `description`, `confirmLabel`, `cancelLabel`, `destructive`; sloty `trigger` i domyślny; błędy serwera wypisuje `InputError` pod slotem. Glob `components/*Dialog.vue` podpina go pod `js-dialogs.md`.
- `DialogsKitchenSink.vue:46-49,162-163,288-313`: stany `confirm-*` i `DialogsKitchenSinkController.validate.form()` (1,5 s opóźnienia, błąd dla każdego pola) to gotowy sposób na stany „zapis w toku” i „błąd” bez prawdziwego endpointu.
- Strony `dev/*` dostają tylko `AppLayout` (`app.ts:11-23`), więc sink musi sam owinąć treść w `layouts/settings/Layout.vue`.
- `AppShellTest.php:80-90` sprawdza `translations.settings.profile === 'Profil'`, więc istniejące klucze `settings.*` zostają. Żaden test nie używa atrybutów `data-test` ustawień.

## What We're NOT Doing

- **2FA i passkeys** (`ManageTwoFactor`, `TwoFactorSetupModal`, `TwoFactorRecoveryCodes`, `ManagePasskeys`, `PasskeyItem`, `PasskeyRegister`, `useTwoFactorAuth.ts`): angielskie teksty, `Heading`, ręczne dialogi, `text-[11px]`. To osobna zmiana (C6b z researchu). Do tego czasu w Bezpieczeństwie panel hasła stoi obok dwóch sekcji na tle strony.
- **`PasswordInput`** (`aria-label` „Hide/Show password”, `tabindex="-1"`) i **`PasskeyVerify`**: współdzielone z ekranami logowania (`e2e/auth.setup.ts` zależy od ich nazw).
- **Pełny `lang/pl/validation.php`** i tłumaczenia innych komunikatów frameworka: osobna zmiana i18n. Tu tylko klucz `current_password` i dwa toasty.
- **`Heading.vue`** zostaje: używają go jeszcze `ManageTwoFactor` i `ManagePasskeys`.
- **„Anuluj” w dialogach formularzy** (`AccountFormDialog`, `TermDepositFormDialog`, dialogi operacji itd.): ta zmiana dotyka tylko `ConfirmActionDialog`. Reszta należy do zmiany wdrażającej ADR-0045.
- **`ListSurface`:** bez zmian, panel ustawień to osobny `SettingsPanel`.
- **Przeskok zaznaczenia motywu przy pierwszym renderze:** `useAppearance` czyta `localStorage` w `onMounted`. Ta zmiana go tylko sprawdza i odnotowuje w follow-upie, nie naprawia.
- **Nowe propsy PHP:** brak, więc nie ma nowego `assertInertia` (`inertia-props.md`).
- **Treść artefaktu claude.ai** (README `PageSettingsProfile`, stan „Propozycja”): ADR-0049 tylko odnotowuje odejścia.

## Implementation Approach

Decyzja idzie przed kodem (`AGENTS.md`: zmiana na stronie design systemu, która zmienia decyzję, ląduje w ADR). Kolejność faz:

1. Faza 1 scala `development`, zapisuje ADR-0049 i dokumenty.
2. Faza 2 dodaje elementy wspólne bez konsumentów w ustawieniach (warianty `Alert` i `Button`, poprawki `ConfirmActionDialog`, `SettingsPanel`, klasy i tokeny layoutu), żeby ich wpływ na resztę aplikacji dało się sprawdzić osobno w `/dev/ui/dialogs`.
3. Fazy 3 i 4 przebudowują widoki: najpierw layout i Profil z tłumaczeniami serwera, potem Bezpieczeństwo i Wygląd.
4. Faza 5 zamyka zmianę sinkiem, strażnikami i zrzutami.

## Critical Implementation Details

- **`Form` obejmuje cały panel:** przycisk „Zapisz” stoi w stopce `SettingsPanel`, poza slotem treści. W Profilu i w panelu hasła `<Form>` musi więc owijać cały `SettingsPanel`, a nie tylko pola, inaczej przycisk `type="submit"` nie wyśle formularza.
- **Błąd hasła w oknie usuwania:** `ConfirmActionDialog` wypisuje wszystkie błędy serwera pod slotem. Pole hasła w slocie nie dostaje więc propa `error` w `FormField`, bo komunikat pojawiłby się dwa razy. Fokus po błędzie daje nowe zdarzenie `error` komponentu.
- **Sink i `setLayoutProps`:** strony ustawień wywołują `setLayoutProps({ breadcrumbs })` w `setup`. Osadzone w sinku nadpiszą jego okruszki, a wygrywa ostatnia osadzona strona. W sinku to akceptowalne, a oczekiwany wynik opisuje macierz stanów.

## Faza 1: Decyzje i dokumenty

### Overview

Branch dostaje stan `development` (ADR-0047 i dokumenty po zmianie Kont). ADR-0049 zapisuje wszystkie decyzje tej zmiany i odejścia od karty, a `design-system.md` i `.ai/rules/js.md` opisują ustawienia tak, jak będą w kodzie.

### Changes Required:

#### 1. Scalenie `development`

**File**: branch `worktree-settings-atrament-ui`

**Intent**: Wyrównać worktree z `development`, zanim cokolwiek zmieni `design-system.md` i `js.md`, żeby nie powstały konflikty z ADR-0047.

**Contract**: `git merge development` bez konfliktów (development nie ma plików `context/changes/settings-atrament-ui/*`). Po scaleniu `context/foundation/adr/0047-*` i `ConfirmActionDialog.vue` w wersji z `development` są w drzewie.

#### 2. ADR-0049

**File**: `context/foundation/adr/0049-settings-follow-page-settings-profile.md`

**Intent**: Zapisać decyzje dotyczące ustawień i odejścia od karty `PageSettingsProfile`, zgodnie z regułą `design-system.md:3`: przed odejściem od strony zapisz ADR.

**Contract**: Styl ADR-0044, 0045 i 0047 (proza po angielsku, akapity tematyczne, na końcu „This supersedes in part…” i „We rejected…”). Treść:

- **Layout:**
  - widoczny `h1` `page-title` z opisem w layoucie zamiast `Heading`; ukryte `h1` stron znikają;
  - podnawigacja z `aria-current="page"`, aktywna pozycja jak karta;
  - od `lg` siatka 200 px | do 640 px (tokeny `--settings-nav-width`, `--settings-content-width`);
  - poniżej `lg` poziomy rząd linków nad panelami (karta nie opisuje telefonu).
- **Panel:** `SettingsPanel` (`card`, pełny `border`, `radius-lg`, pasek nagłówka z `section-title` + `meta`, treść, opcjonalna stopka z ramką u góry i akcją po prawej) jako przepis sekcji ustawień; `Heading` zostaje tylko w sekcjach 2FA i passkeys do ich zmiany.
- **Pola:** `FormField` także w panelach ustawień, nie tylko w dialogach.
- **Strefa usuwania:**
  - `Alert` `danger`: tło `destructive-soft`, ramka przezroczysta, ikona i tytuł `destructive`, opis `foreground`; kontrast 5,03:1 i 6,82:1;
  - `Button` `outline-destructive`: `outline` z tekstem `destructive`, dla przycisku otwierającego potwierdzenie usunięcia; w oknie potwierdzenia zostaje wypełniony `destructive`;
  - oba to role systemowe („akcje usuwające”), nie styl jednego ekranu.
- **`DeleteUser`:** przez `ConfirmActionDialog` z polem hasła w `FormField` i fokusem po błędzie (nowe zdarzenie `error`).
- **„Anuluj”:** `ghost` w `ConfirmActionDialog`, czyli ADR-0045 wdrożony dla wszystkich potwierdzeń. Dialogi formularzy zostają do zmiany wdrażającej ADR-0045.
- **Motyw:** `SegmentedControl` `tone="neutral"` z etykietami „Jasny”, „Ciemny”, „Systemowy”, bez ikon (karta ich nie ma), `aria-label` „Motyw”.
- **Teksty:**
  - teksty ustawień w `lang/pl/ui.php` (`settings.*`);
  - komunikaty serwera w `lang/pl.json` (toasty) i `lang/pl/validation.php` (na razie tylko `current_password`, reszta spada na angielski frameworka do zmiany i18n).
- **Zakres:** 2FA i passkeys odroczone; w Bezpieczeństwie panel hasła stoi obok dwóch sekcji na tle, przejściowo.
- **„Supersedes in part”:** zdanie „`Heading` służy sekcjom ustawień” w `design-system.md` (nie ADR) oraz ADR-0040 w części „Anuluj” w `ConfirmActionDialog` (już zastąpionej przez ADR-0045, tu wdrożonej).
- **„We rejected”:**
  - rozszerzenie `ListSurface` o tytuł w pasku (zmienia 6 widoków);
  - istniejące `Alert` `destructive` i wypełniony `Button` `destructive` (odchodzą od karty);
  - pionowa lista nawigacji na telefonie (spycha formularz);
  - pełny `validation.php` w tej zmianie.

#### 3. Strona design systemu

**File**: `context/foundation/design-system.md`

**Intent**: Opisać ustawienia i nowe elementy, żeby następny agent nie wrócił do `Heading` na tle ani do ręcznego pudełka ostrzeżenia.

**Contract**:

- (a) Lista ADR w akapicie wstępnym (`:3`) dostaje ADR-0049 wraz z tym, co zastępuje.
- (b) Wiersz o dialogu (`:20`) dostaje zdanie, że `ConfirmActionDialog` ma „Anuluj” `ghost` (ADR-0049).
- (c) „Gotowe elementy” (`:83`): `SettingsPanel` (opis z ADR-0049), `Alert` `danger` i Button `outline-destructive`.
- (d) Akapit `:85` „`Heading` służy sekcjom ustawień” przepisany: sekcje ustawień to `SettingsPanel`, `Heading` zostaje tylko w 2FA i passkeys do ich zmiany; strona ustawień ma `h1` w layoucie.
- (e) Wiersz `destructive-soft` w tabeli kolorów i sekcja „Alerty” wspominają `Alert` `danger` (strefa nieodwracalnej akcji, nie ostrzeżenie).
- (f) Sekcja „Kitchen sink i zrzuty” i punkt 4 „Zmiana systemu” (`:130`) dostają `/dev/ui/settings`.

#### 4. Reguła dla widoków

**File**: `.ai/rules/js.md`, potem `php artisan boost:index-rules`

**Intent**: Trwała reguła dla stron ustawień.

**Contract**:

- Nowa sekcja „Settings pages follow ADR-0049”:
  - sekcja ustawień to `SettingsPanel` z polami w `FormField` (`Form` owija cały panel, akcja w stopce);
  - strefa usuwania to `Alert` `danger` i Button `outline-destructive`, który otwiera `ConfirmActionDialog`;
  - teksty idą przez `t.settings.*` z `lang/pl/ui.php`, bez angielskich literałów;
  - `Heading` tylko w sekcjach 2FA i passkeys do ich zmiany;
  - motyw to `SegmentedControl` `neutral`.
- Lista sandboxów (`:46`) dostaje `/dev/ui/settings`.
- Po `boost:index-rules` różnice tylko w tej regule i jej indeksie.

### Success Criteria:

#### Automated Verification:

- `development` jest przodkiem `HEAD` (`git merge-base --is-ancestor development HEAD`)
- ADR-0049 istnieje i jest jedynym plikiem `0049-*` w `context/foundation/adr/`
- `php artisan boost:index-rules` kończy się bez błędu, a `git diff` pokazuje tylko zamierzone zmiany w regułach

#### Manual Verification:

- ADR-0049 zawiera layout (z telefonem), `SettingsPanel`, `FormField` na stronach, oba warianty z kontrastem, `ConfirmActionDialog` (ghost + `error`), motyw, tłumaczenia serwera, odroczenie 2FA/passkeys i odrzucone opcje
- `design-system.md` i `js.md` nie przeczą ADR-0049 (w szczególności nie mówią już, że `Heading` jest przepisem sekcji ustawień)

**Implementation Note**: Po fazie i zielonych kontrolach automatycznych zatrzymaj się na ręczne potwierdzenie treści ADR, zanim zaczniesz kod.

---

## Faza 2: Elementy wspólne

### Overview

Dodajemy to, czego ustawienia potrzebują, a czego nie ma w repo. Ustawienia jeszcze z tego nie korzystają. Jedyna zmiana widoczna poza ustawieniami to „Anuluj” `ghost` we wszystkich potwierdzeniach.

### Changes Required:

#### 1. Wariant `Alert` `danger`

**File**: `resources/js/components/ui/alert/index.ts`

**Intent**: Rola systemowa „strefa nieodwracalnej akcji”, symetryczna do `note` (ADR-0042), według `.ht-alert--danger`.

**Contract**: Nowy wariant `danger` w `alertVariants`: `bg-destructive-soft`, `border-transparent`, tekst `foreground`, ikona (`[&>svg]`) i `AlertTitle` (`*:data-[slot=alert-title]`) w `destructive`, opis w `foreground`. Pozostałe warianty bez zmian.

#### 2. Wariant `Button` `outline-destructive`

**File**: `resources/js/components/ui/button/index.ts`

**Intent**: Przycisk akcji usuwającej, który otwiera potwierdzenie, według `.ht-btn--danger`.

**Contract**: Wariant `outline-destructive`: jak `outline` (`border bg-card hover:bg-secondary`), ale `text-destructive`; stan wyłączony z `disabledSolid`. Fokus z klasy bazowej `buttonVariants`.

#### 3. `ConfirmActionDialog`: „Anuluj” `ghost` i zdarzenie `error`

**File**: `resources/js/components/ConfirmActionDialog.vue`

**Intent**: Wdrożyć ADR-0045 dla wszystkich potwierdzeń i pozwolić konsumentowi zareagować na błąd walidacji (fokus na polu w slocie).

**Contract**:
- Przycisk anulowania ma `variant="ghost"`.
- Komponent deklaruje `emit('error', errors)` i przekazuje je z `@error` swojego `Form`.
- Zachowanie istniejących konsumentów bez zmian, poza wyglądem „Anuluj”.

#### 4. `SettingsPanel`

**File**: `resources/js/components/SettingsPanel.vue` (nowy)

**Intent**: Jeden przepis sekcji ustawień według `.ht-panel` z `.ht-panel__head`, treścią i stopką.

**Contract**:
- Propsy: `title: string`, `description?: string`.
- Sloty: domyślny (treść) i `footer` (opcjonalny; bez niego nie ma paska stopki).
- Korzeń `section` z `aria-labelledby` wskazującym `h2` (id z `useId()`).
- Klasy z `app.css`, bez utility kolorów ani wartości arbitralnych w szablonie.

#### 5. Klasy i tokeny w `app.css`

**File**: `resources/css/app.css`

**Intent**: Nazwane klasy panelu i layoutu ustawień (`css.md`: rozmiary i style są tu nazwane, widoki ich używają).

**Contract**:
- **Tokeny w `:root`:** `--settings-nav-width: 200px` i `--settings-content-width: 640px`. To wymiary, bez wariantu `.dark`, obok `--aside-width`.
- **Klasy panelu w `@layer components`:**
  - `.settings-panel`: `card`, pełny `border`, `radius-lg`, bez cienia;
  - `.settings-panel-head`: pasek z `border-b`; `h2` w `section-title`, opis w `meta` `muted-foreground`;
  - `.settings-panel-body`: kolumna z odstępem między polami;
  - `.settings-panel-foot`: `border-t`, akcje do prawej, odstęp 8 px.
- **Klasy layoutu:**
  - `.settings-layout`: jedna kolumna z odstępem; od `lg` siatka `var(--settings-nav-width) minmax(0, var(--settings-content-width))`, `align-items: start`;
  - `.settings-subnav`: poniżej `lg` rząd z zawijaniem, od `lg` kolumna z odstępem 2 px;
  - `.settings-subnav-link`: `radius-md`, `muted-foreground`, waga 500, hover `secondary` + `foreground`; `[aria-current='page']` `card` + `foreground` + obwódka `border` (`box-shadow 0 0 0 1px`, jak na karcie); fokus 2 px `ring` przez `outline`, z odstępem 2 px;
  - `.settings-content`: stos paneli z odstępem 16 px, od `md` 20 px.

### Success Criteria:

#### Automated Verification:

- `npm run types:check` przechodzi
- `npm run check:ui` przechodzi z `SettingsPanel.vue` dopisanym do `FILES` w `scripts/check-ui-literals.mjs`
- `npm run test` (w tym `ui-contract.test.ts`) przechodzi
- `npm run build` przechodzi

#### Manual Verification:

- `/dev/ui/dialogs?open=confirm-destructive` i `?open=confirm-saving` mają „Anuluj” `ghost` (bez tła i obwódki, tekst `muted-foreground`) w obu motywach
- Potwierdzenia w Budżecie, Kontach i Operacjach otwierają się i zamykają jak wcześniej

**Implementation Note**: Po fazie i zielonych kontrolach automatycznych zatrzymaj się na ręczne potwierdzenie wyglądu potwierdzeń.

---

## Faza 3: Layout i Profil

### Overview

Layout dostaje tytuł, podnawigację i siatkę, a Profil panele i `FormField`. `DeleteUser` przechodzi na `ConfirmActionDialog`. Serwer mówi po polsku w toaście profilu i w błędzie hasła. Bezpieczeństwo i Wygląd dostają nowy layout „za darmo” i muszą się dalej renderować.

### Changes Required:

#### 1. Layout ustawień

**File**: `resources/js/layouts/settings/Layout.vue`

**Intent**: Wiersz tytułu i podnawigacja według karty, z `aria-current`, na nazwanych klasach.

**Contract**:
- Opakowanie strony jak w `Accounts.vue:127` (`p-4`, odstępy 16/20 px).
- Wiersz tytułu: `h1.page-title` `t.settings.title` + `p` `t.settings.description` (`text-muted-foreground text-sm`).
- Pod nim `.settings-layout` z `nav.settings-subnav` (`aria-label` `t.settings.title`) i `div.settings-content` ze slotem.
- Pozycje to `Link` z `.settings-subnav-link` i `aria-current="page"`, gdy `isCurrentOrParentUrl(item.href)`.
- `Heading`, `Button`, `Separator`, `<component :is="item.icon">` i klasy `max-w-*` / `lg:w-48` znikają.

#### 2. Profil

**File**: `resources/js/pages/settings/Profile.vue`

**Intent**: Sekcja profilu jako panel z polami `FormField`.

**Contract**:
- Ukryty `h1` i `Heading` znikają; `Head` i `setLayoutProps` zostają.
- `<Form v-bind="ProfileController.update.form()">` owija `SettingsPanel` (`title` `t.settings.profile_heading`, `description` `t.settings.profile_description`).
- Dwa `FormField` (`id` `name` / `email`, `label` `t.settings.name` / `t.settings.email`, `error` z `errors`); `Input` dostaje ze slotu `id`, `aria-describedby`, `aria-invalid` i `controlClass`; `name`, `default-value`, `required`, `autocomplete` jak dziś, bez placeholderów.
- Stopka: `Button` `type="submit"` `t.settings.save`, `Spinner` przy `processing`, `data-test="update-profile-button"`.
- `<DeleteUser />` pod panelem.

#### 3. Usuń konto

**File**: `resources/js/components/DeleteUser.vue`

**Intent**: Panel strefy usuwania według karty i potwierdzenie przez `ConfirmActionDialog`, zgodnie z `js.md`.

**Contract**:
- **Panel:** `SettingsPanel` (`t.settings.delete_account`, `t.settings.delete_account_description`). W treści:
  - `Alert variant="danger"` z ikoną `TriangleAlert`, `AlertTitle` `t.settings.delete_warning` i `AlertDescription` `t.settings.delete_warning_body`;
  - pod alertem `ConfirmActionDialog` z `destructive`, `title` `t.settings.delete_confirm_title`, `description` `t.settings.delete_confirm_body` i etykietami `t.settings.delete_account` / `t.settings.cancel`.
- **Slot `trigger`:** `DialogTrigger as-child` z `Button variant="outline-destructive"` (ikona `Trash2`, `data-test="delete-user-button"`).
- **Slot domyślny:** `FormField` (`id` `delete-user-password`, `label` `t.auth.login.password`) z `PasswordInput name="password" autocomplete="current-password"`, bez `error`.
- **Fokus:** `@error` przenosi fokus na to pole.
- **Propsy tylko dla sinka:**
  - opcjonalny `form` (domyślnie `ProfileController.destroy.form()`), jak prop `form` w `AccountFormDialog.vue:40-41`;
  - `v-model:open` przekazywany do `ConfirmActionDialog`.
- **Usuwane:** ręczne `Dialog`/`DialogContent`/`Form`, `Heading`, `border-destructive/20 bg-destructive/5` i `data-test="confirm-delete-user-button"` (żaden test go nie używa).

#### 4. Tłumaczenia serwera

**Files**: `lang/pl.json` (nowy), `lang/pl/validation.php` (nowy)

**Intent**: Polskie toasty po zapisie profilu i hasła oraz polski błąd złego obecnego hasła.

**Contract**:
- `lang/pl.json`: `"Profile updated."` → „Profil zapisany.”, `"Password updated."` → „Hasło zmienione.”.
- `lang/pl/validation.php`: zwraca tablicę z jednym kluczem `'current_password' => 'Podane hasło jest nieprawidłowe.'`. Pozostałe klucze spadają na angielski frameworka przez `fallback_locale=en` (`config/app.php`).
- Kod PHP bez zmian.

#### 5. Testy HTTP

**Files**: `tests/Feature/Settings/ProfileUpdateTest.php`, `tests/Feature/Settings/SecurityTest.php`

**Intent**: Regresja na polskie komunikaty z przepływów Profilu i hasła.

**Contract**:
- `ProfileUpdateTest`:
  - test zapisu profilu dostaje `assertInertiaFlash('toast.message', 'Profil zapisany.')`, jak `MonthPlanPageTest.php:544`;
  - test złego hasła przy usuwaniu dostaje `assertSessionHasErrors(['password' => 'Podane hasło jest nieprawidłowe.'])`.
- `SecurityTest`:
  - zmiana hasła: `toast.message` „Hasło zmienione.”;
  - złe obecne hasło (`:109-115`): komunikat `current_password` po polsku.
- Nowe asercje mają najpierw nie przejść (przed dodaniem plików `lang`).

### Success Criteria:

#### Automated Verification:

- Nowe asercje w `ProfileUpdateTest` i `SecurityTest` nie przechodzą przed dodaniem `lang/pl.json` i `lang/pl/validation.php`, a po dodaniu przechodzą
- Testy ustawień przechodzą na PostgreSQL: `docker run … artisan test --compact tests/Feature/Settings tests/Feature/AppShellTest.php` (polecenie z AGENTS.md dla worktree)
- `npm run types:check`, `npm run test`, `npm run build` przechodzą
- `vendor/bin/pint --dirty --format agent` bez zmian do zatwierdzenia

#### Manual Verification:

- `/settings/profile` przy 1280 px: tytuł „Ustawienia” z opisem, podnawigacja w kolumnie 200 px z „Profil” jak karta, panele „Profil” i „Usuń konto” według karty, w obu motywach
- Przy 390 px: podnawigacja w jednym rzędzie nad panelami, bez poziomego przewijania strony
- Zapis profilu pokazuje toast „Profil zapisany.”; puste imię pokazuje błąd pod polem z czerwoną ramką
- „Usuń konto” otwiera okno z polem „Hasło”; złe hasło daje „Podane hasło jest nieprawidłowe.” i fokus wraca na pole; „Anuluj” jest `ghost`; Esc i „Anuluj” zamykają okno, w trakcie zapisu okno się nie zamyka
- Tab przechodzi przez linki podnawigacji z widocznym fokusem 2 px; czytnik ekranu ogłasza aktywną pozycję (`aria-current`)
- `/settings/security` i `/settings/appearance` renderują się w nowym layoucie bez błędów w konsoli

**Implementation Note**: Po fazie i zielonych kontrolach automatycznych zatrzymaj się na ręczne potwierdzenie Profilu i strefy usuwania.

---

## Faza 4: Bezpieczeństwo i Wygląd

### Overview

Formularz hasła dostaje panel i polskie teksty, a przełącznik motywu zmienia się w `SegmentedControl`. 2FA i passkeys zostają bez zmian.

### Changes Required:

#### 1. Klucze tłumaczeń

**File**: `lang/pl/ui.php` (blok `settings`)

**Intent**: Polskie teksty formularza hasła i motywu.

**Contract**: Nowe klucze w `settings`:
- `password_heading` „Zmień hasło”;
- `password_description` „Użyj długiego, losowego hasła, żeby konto było bezpieczne”;
- `current_password` „Obecne hasło”, `new_password` „Nowe hasło”, `confirm_password` „Powtórz nowe hasło”;
- `appearance_theme` „Motyw”;
- `appearance_light` „Jasny”, `appearance_dark` „Ciemny”, `appearance_system` „Systemowy”.

Istniejące klucze bez zmian. Typ `settings: Record<string, string>` (`types/shell.ts:39`) nie wymaga zmian.

#### 2. Bezpieczeństwo

**File**: `resources/js/pages/settings/Security.vue`

**Intent**: Formularz hasła jako panel po polsku, jak Profil.

**Contract**:
- Ukryty `h1` i `Heading` znikają.
- `<Form v-bind="SecurityController.update.form()">` z dzisiejszymi opcjami (`preserveScroll`, `reset-on-success`, `reset-on-error`) owija `SettingsPanel` (`t.settings.password_heading`, `t.settings.password_description`).
- Trzy `FormField` z `PasswordInput` (`id` i `name` jak dziś: `current_password`, `password`, `password_confirmation`; `autocomplete` i `passwordrules` jak dziś, bez placeholderów, błędy z `errors`).
- Stopka: `t.settings.save` ze spinnerem, `data-test="update-password-button"`.
- `ManageTwoFactor` i `ManagePasskeys` pod panelem bez zmian.

#### 3. Wygląd

**Files**: `resources/js/pages/settings/Appearance.vue`, `resources/js/components/AppearanceTabs.vue`

**Intent**: Panel „Wygląd” z przełącznikiem motywu zgodnym z `js.md` (ustawienie zapisywane od razu → `SegmentedControl` `neutral`).

**Contract**:
- `Appearance.vue`: bez ukrytego `h1` i `Heading`; `SettingsPanel` (`t.settings.appearance_title`, `t.settings.appearance_description`) bez stopki, w treści `<AppearanceTabs />`.
- `AppearanceTabs.vue`:
  - `SegmentedControl` z `tone="neutral"` i `aria-label` `t.settings.appearance_theme`;
  - opcje `light` / `dark` / `system` z etykietami z `t.settings.appearance_*`, bez ikon;
  - `v-model` na wyliczeniu z getterem `appearance` i setterem `updateAppearance`;
  - klasy palety, `dark:` i surowe `<button>` znikają.

### Success Criteria:

#### Automated Verification:

- `npm run types:check`, `npm run test`, `npm run build` przechodzą
- `npm run check:ui -- resources/js/components/AppearanceTabs.vue` nie zgłasza literałów (dziś zgłasza `bg-neutral-*`, `dark:`)
- Testy ustawień przechodzą na PostgreSQL (`tests/Feature/Settings`)

#### Manual Verification:

- `/settings/security`: panel „Zmień hasło” z trzema polami po polsku; złe obecne hasło pokazuje polski błąd pod polem; poprawna zmiana pokazuje „Hasło zmienione.” i czyści pola; sekcje 2FA i passkeys działają jak wcześniej
- `/settings/appearance`: „Jasny / Ciemny / Systemowy” przełącza motyw od razu, aktywna opcja jak karta w obu motywach; strzałki zmieniają opcję, Tab zatrzymuje się raz
- Przy odświeżeniu z zapisanym motywem „Ciemny” sprawdzone, czy zaznaczenie przeskakuje z „Systemowy”; wynik zapisany w `context/changes/settings-atrament-ui/follow-ups/` (bez poprawki w tej zmianie)

**Implementation Note**: Po fazie i zielonych kontrolach automatycznych zatrzymaj się na ręczne potwierdzenie Bezpieczeństwa i Wyglądu.

---

## Faza 5: Stany i bramka

### Overview

Sink `/dev/ui/settings` pokazuje ustawienia w macierzy 7 stanów. Strażnik i `check:ui` pilnują oczyszczonych plików, a zrzuty zamykają zmianę.

### Changes Required:

#### 1. Sink ustawień

**Files**: `app/Http/Controllers/Dev/SettingsKitchenSinkController.php` (nowy), `routes/web.php`, `resources/js/pages/dev/SettingsKitchenSink.vue` (nowy), `tests/Feature/SettingsKitchenSinkTest.php` (nowy)

**Intent**: Stałe miejsce, w którym widać wszystkie stany ustawień, wzorem `/dev/ui/accounts`.

**Contract**:
- **Trasa i kontroler:**
  - `GET dev/ui/settings` → `SettingsKitchenSinkController@show` w grupie `auth` + `verified` (`routes/web.php:24-31`);
  - kontroler z `abort_unless(app()->isLocal(), 404)` renderuje `dev/SettingsKitchenSink` (jak `AccountsKitchenSinkController.php:11-16`);
  - test jak `AccountsKitchenSinkTest.php`: gość przekierowany, 404 poza `local`, komponent lokalnie.
- **Strona sinka:** tytuł `page-title`, tabela `stateMatrix` (stan → gdzie go widać / N/A z powodem) i sekcje z `data-section`. Treść owinięta w `layouts/settings/Layout.vue` (strony `dev/*` go nie dostają). Sekcje:
  - **default:** `Profile.vue`, `Security.vue` z fiksurami propsów (`canManageTwoFactor` i `canManagePasskeys` na `false`, potem wariant z `true`, żeby pokazać panel obok sekcji na tle) i `Appearance.vue`, osadzone przez `defineAsyncComponent` jak `AccountsKitchenSink.vue:11`;
  - **hover / focus-visible:** linki podnawigacji, `SegmentedControl`, przyciski stopki, `outline-destructive`;
  - **disabled:** zapis w toku: `DeleteUser` z `form` = `DialogsKitchenSinkController.validate.form()` i otwartym oknem (`?open=delete-account-saving`);
  - **error:** `SettingsPanel` z `FormField` z błędem (fiksura tekstu) i okno usuwania po walidacji (`?open=delete-account-error`, ten sam endpoint);
  - **empty:** N/A (ustawienia zawsze mają dane zalogowanego);
  - **loading:** spinner na „Zapisz” i w oknie usuwania.
- **Aktywna pozycja:** w sinku żaden link podnawigacji nie jest aktywny (adres to `/dev/ui/settings`); macierz kieruje do zrzutów prawdziwych tras.

#### 2. Strażnicy

**Files**: `tests/Unit/SettingsHardcodedUiTest.php` (nowy), `scripts/check-ui-literals.mjs`

**Intent**: Następny agent nie wróci do klas palety, `text-xl`, `bg-destructive/5` ani nie zostawi klucza `t.settings.*` bez tłumaczenia.

**Contract**:
- **Lista plików:** `layouts/settings/Layout.vue`, `pages/settings/Profile.vue`, `Security.vue`, `Appearance.vue`, `components/DeleteUser.vue`, `AppearanceTabs.vue`, `SettingsPanel.vue`, `pages/dev/SettingsKitchenSink.vue`.
- **Zakazy:** te same wzorce co `AccountsHardcodedUiTest.php:17-33` (hex, funkcje koloru, klasy palety, wartości arbitralne, `text-lg|xl|2xl|3xl`, `text-base`, `rounded-xl|2xl`, `border-border/N`, `opacity-N` itd.) plus `bg-destructive/N` i import `Heading`.
- **Test kluczy:** każde `t.settings.<klucz>` w tych plikach istnieje w `lang/pl/ui.php` (`__('ui.settings')`), bo typ `Record<string, string>` nie łapie literówek.
- **`check-ui-literals.mjs`:** te same pliki dopisane do `FILES`.

#### 3. Bramka wizualna

**Files**: `context/changes/settings-atrament-ui/screenshots/*.png`

**Intent**: Dowód, że widok odpowiada karcie w obu motywach i na telefonie.

**Contract**:
- Podgląd z worktree według `context/foundation/agents/worktree-preview.md`.
- Jednorazowy, niecommitowany skrypt Playwright (albo `playwright-cli`) robi pełne zrzuty `/dev/ui/settings`, `/settings/profile`, `/settings/security` (po potwierdzeniu hasła) i `/settings/appearance` przy 1280 i 390 px, w jasnym i ciemnym motywie.
- Dodatkowo `/dev/ui/dialogs?open=confirm-destructive` (Anuluj `ghost`).
- Nazwy plików `<strona>-<szerokość>-<motyw>.png`, jak w `context/archive/2026-11-06-accounts-design-system-page/screenshots/`.

### Success Criteria:

#### Automated Verification:

- `SettingsHardcodedUiTest` i `SettingsKitchenSinkTest` przechodzą
- `npm run check:ui` przechodzi z plikami ustawień na liście
- `composer ci:check` przechodzi (na PostgreSQL w worktree, polecenie z AGENTS.md)
- `vendor/bin/sloppy ci` przechodzi
- `npm run types:check`, `npm run test`, `npm run build` przechodzą

#### Manual Verification:

- `/dev/ui/settings` pokazuje każdy stan z macierzy albo uzasadnione N/A
- Zrzuty przy 1280 i 390 px w obu motywach są w `screenshots/` i zgadzają się z kartą `PageSettingsProfile` (tytuł, podnawigacja, panele, strefa usuwania), z odejściem na telefonie z ADR-0049
- Brak angielskich tekstów w Profilu, w panelu hasła i w Wyglądzie (poza odroczonymi sekcjami 2FA i passkeys)

**Implementation Note**: Po fazie i zielonych kontrolach automatycznych zatrzymaj się na przegląd zrzutów przez człowieka.

---

## Testing Strategy

### Unit Tests:

- `SettingsHardcodedUiTest`: zakazane wzorce w oczyszczonych plikach i istnienie każdego klucza `t.settings.*` w `lang/pl/ui.php`.
- `ui-contract.test.ts` (istniejący): bez martwych wiązań Checkboxa w nowych plikach.

### Integration Tests:

- `ProfileUpdateTest`: polski toast po zapisie, polski błąd złego hasła przy usuwaniu konta.
- `SecurityTest`: polski toast po zmianie hasła, polski błąd `current_password`.
- `SettingsKitchenSinkTest`: dostęp do sinka (gość, poza `local`, lokalnie).
- `AppShellTest` (istniejący): `settings/Profile` i `translations.settings.profile`.

### Manual Testing Steps:

1. Zaloguj się (`test@example.com` / `password`), wejdź w Ustawienia z menu użytkownika i przejdź Tabem przez podnawigację.
2. Zmień imię i zapisz; sprawdź toast i błąd przy pustym polu.
3. Otwórz „Usuń konto”, wpisz złe hasło, sprawdź komunikat i fokus; zamknij przez „Anuluj”.
4. Zmień hasło ze złym i dobrym obecnym hasłem.
5. Przełącz motyw strzałkami i myszą; odśwież stronę z motywem „Ciemny”.
6. Powtórz kroki 1–3 przy szerokości 390 px.

## Performance Considerations

Brak: zmiana dotyczy markupu, klas i plików językowych. Nowy `lang/pl.json` jest ładowany przez tłumacza Laravela tylko przy wywołaniu `__()` z kluczem JSON.

## Migration Notes

Brak migracji danych. Zapisany motyw (`localStorage` i cookie `appearance`) ma te same wartości `light` / `dark` / `system`.

## References

- Research: `context/changes/settings-atrament-ui/research.md`
- Wzorzec: karta `PageSettingsProfile` w https://claude.ai/artifact/9gJSdtfpFgUAa8oEMgR4HA (`bundle.css:28,57-58,188-189,249-251,311-317`)
- Precedens planu: `context/archive/2026-11-06-accounts-design-system-page/plan.md` (na `development`)
- Wiersz tytułu: `resources/js/pages/Accounts.vue:127-134`
- Sink: `resources/js/pages/dev/AccountsKitchenSink.vue`, `app/Http/Controllers/Dev/AccountsKitchenSinkController.php`, `tests/Feature/AccountsKitchenSinkTest.php`
- Strażnik: `tests/Unit/AccountsHardcodedUiTest.php`
- ADR-0037, 0040, 0042, 0045, 0047 w `context/foundation/adr/`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Decyzje i dokumenty

#### Automated

- [x] 1.1 `development` jest przodkiem `HEAD` (`git merge-base --is-ancestor development HEAD`) — 21ac00a
- [x] 1.2 ADR-0049 istnieje i jest jedynym plikiem `0049-*` w `context/foundation/adr/` — 21ac00a
- [x] 1.3 `php artisan boost:index-rules` kończy się bez błędu, a `git diff` pokazuje tylko zamierzone zmiany w regułach — 21ac00a

#### Manual

- [ ] 1.4 ADR-0049 zawiera layout (z telefonem), `SettingsPanel`, `FormField` na stronach, oba warianty z kontrastem, `ConfirmActionDialog` (ghost + `error`), motyw, tłumaczenia serwera, odroczenie 2FA/passkeys i odrzucone opcje
- [ ] 1.5 `design-system.md` i `js.md` nie przeczą ADR-0049 (w szczególności nie mówią już, że `Heading` jest przepisem sekcji ustawień)

### Phase 2: Elementy wspólne

#### Automated

- [x] 2.1 `npm run types:check` przechodzi — 5520370
- [x] 2.2 `npm run check:ui` przechodzi z `SettingsPanel.vue` dopisanym do `FILES` w `scripts/check-ui-literals.mjs` — 5520370
- [x] 2.3 `npm run test` (w tym `ui-contract.test.ts`) przechodzi — 5520370
- [x] 2.4 `npm run build` przechodzi — 5520370

#### Manual

- [ ] 2.5 `/dev/ui/dialogs?open=confirm-destructive` i `?open=confirm-saving` mają „Anuluj” `ghost` (bez tła i obwódki, tekst `muted-foreground`) w obu motywach
- [ ] 2.6 Potwierdzenia w Budżecie, Kontach i Operacjach otwierają się i zamykają jak wcześniej

### Phase 3: Layout i Profil

#### Automated

- [x] 3.1 Nowe asercje w `ProfileUpdateTest` i `SecurityTest` nie przechodzą przed dodaniem `lang/pl.json` i `lang/pl/validation.php`, a po dodaniu przechodzą — d677caa
- [x] 3.2 Testy ustawień przechodzą na PostgreSQL: `docker run … artisan test --compact tests/Feature/Settings tests/Feature/AppShellTest.php` (polecenie z AGENTS.md dla worktree) — d677caa
- [x] 3.3 `npm run types:check`, `npm run test`, `npm run build` przechodzą — d677caa
- [x] 3.4 `vendor/bin/pint --dirty --format agent` bez zmian do zatwierdzenia — d677caa

#### Manual

- [ ] 3.5 `/settings/profile` przy 1280 px: tytuł „Ustawienia” z opisem, podnawigacja w kolumnie 200 px z „Profil” jak karta, panele „Profil” i „Usuń konto” według karty, w obu motywach
- [ ] 3.6 Przy 390 px: podnawigacja w jednym rzędzie nad panelami, bez poziomego przewijania strony
- [ ] 3.7 Zapis profilu pokazuje toast „Profil zapisany.”; puste imię pokazuje błąd pod polem z czerwoną ramką
- [ ] 3.8 „Usuń konto” otwiera okno z polem „Hasło”; złe hasło daje „Podane hasło jest nieprawidłowe.” i fokus wraca na pole; „Anuluj” jest `ghost`; Esc i „Anuluj” zamykają okno, w trakcie zapisu okno się nie zamyka
- [ ] 3.9 Tab przechodzi przez linki podnawigacji z widocznym fokusem 2 px; czytnik ekranu ogłasza aktywną pozycję (`aria-current`)
- [ ] 3.10 `/settings/security` i `/settings/appearance` renderują się w nowym layoucie bez błędów w konsoli

### Phase 4: Bezpieczeństwo i Wygląd

#### Automated

- [x] 4.1 `npm run types:check`, `npm run test`, `npm run build` przechodzą — 033f9b5
- [x] 4.2 `npm run check:ui -- resources/js/components/AppearanceTabs.vue` nie zgłasza literałów (dziś zgłasza `bg-neutral-*`, `dark:`) — 033f9b5
- [x] 4.3 Testy ustawień przechodzą na PostgreSQL (`tests/Feature/Settings`) — 033f9b5

#### Manual

- [ ] 4.4 `/settings/security`: panel „Zmień hasło” z trzema polami po polsku; złe obecne hasło pokazuje polski błąd pod polem; poprawna zmiana pokazuje „Hasło zmienione.” i czyści pola; sekcje 2FA i passkeys działają jak wcześniej
- [ ] 4.5 `/settings/appearance`: „Jasny / Ciemny / Systemowy” przełącza motyw od razu, aktywna opcja jak karta w obu motywach; strzałki zmieniają opcję, Tab zatrzymuje się raz
- [ ] 4.6 Przy odświeżeniu z zapisanym motywem „Ciemny” sprawdzone, czy zaznaczenie przeskakuje z „Systemowy”; wynik zapisany w `context/changes/settings-atrament-ui/follow-ups/` (bez poprawki w tej zmianie)

### Phase 5: Stany i bramka

#### Automated

- [x] 5.1 `SettingsHardcodedUiTest` i `SettingsKitchenSinkTest` przechodzą
- [x] 5.2 `npm run check:ui` przechodzi z plikami ustawień na liście
- [x] 5.3 `composer ci:check` przechodzi (na PostgreSQL w worktree, polecenie z AGENTS.md)
- [x] 5.4 `vendor/bin/sloppy ci` przechodzi
- [x] 5.5 `npm run types:check`, `npm run test`, `npm run build` przechodzą

#### Manual

- [ ] 5.6 `/dev/ui/settings` pokazuje każdy stan z macierzy albo uzasadnione N/A
- [ ] 5.7 Zrzuty przy 1280 i 390 px w obu motywach są w `screenshots/` i zgadzają się z kartą `PageSettingsProfile` (tytuł, podnawigacja, panele, strefa usuwania), z odejściem na telefonie z ADR-0049
- [ ] 5.8 Brak angielskich tekstów w Profilu, w panelu hasła i w Wyglądzie (poza odroczonymi sekcjami 2FA i passkeys)
