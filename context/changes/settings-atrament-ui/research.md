---
date: 2026-11-06T15:37:30+01:00
researcher: Claude (Sonnet 5.5)
git_commit: de18d4220fc2247e82f5e89818412546895831ed
branch: worktree-settings-atrament-ui
repository: household-treasurer
topic: "Ustawienia (Profil) względem karty PageSettingsProfile w Atramencie: zarzuty dla /10x-ui"
tags: [research, codebase, ui, settings, atrament, profile, delete-user, appearance-tabs]
status: complete
last_updated: 2026-11-06
last_updated_by: Claude (Sonnet 5.5)
---

# Research: Ustawienia (Profil) względem karty PageSettingsProfile w Atramencie

**Date**: 2026-11-06T15:37:30+01:00
**Researcher**: Claude (Sonnet 5.5)
**Git Commit**: de18d4220fc2247e82f5e89818412546895831ed
**Branch**: worktree-settings-atrament-ui
**Repository**: household-treasurer

## Research Question

Dwukierunkowy audyt (`/10x-ui`) widoku `resources/js/pages/settings/Profile.vue` razem z podnawigacją ustawień (`layouts/settings/Layout.vue`) i `DeleteUser.vue` względem karty `PageSettingsProfile` z design systemu Atrament (https://claude.ai/artifact/9gJSdtfpFgUAa8oEMgR4HA), z polskimi tekstami (nagłówek „Update password” w `Security.vue`, etykiety Light/Dark/System w `AppearanceTabs.vue`). Nowy ADR ma numer 0049. Wynik: `## Charges`.

## Summary

Wzorzec to trzy elementy: tytuł „Ustawienia” z opisem, pionowa podnawigacja `ht-subnav` (siatka 200 px + do 640 px) i stos paneli `ht-panel` (nagłówek `h2` 13 px/600 + opis, treść, stopka z akcją). Widok dziś ma 0 trafień skanu literałów w `Profile.vue`, `Layout.vue`, `DeleteUser.vue`, `Heading.vue` i `Security.vue`, a 3 w `AppearanceTabs.vue:16,25,26`. Problem leży głębiej: ustawienia nie używają żadnego komponentu powierzchni, `DeleteUser` jest zbudowany ręcznie wbrew regule o `ConfirmActionDialog`, podnawigacja nie ma `aria-current`, a część widocznego tekstu (w tym toasty i błąd hasła przy usuwaniu konta) jest po angielsku. Zmiana ma siedem zarzutów (C1–C7). Trzy elementy wzorca nie mają odpowiednika w `components/ui` (tło `destructive-soft` w `Alert`, przycisk z czerwonym tekstem i obwódką, panel z paskiem nagłówka) i wymagają decyzji w ADR-0049. Zakres: Profil, `DeleteUser`, `Layout`, `AppearanceTabs`, polskie teksty w `Security.vue`. `ManageTwoFactor`, `ManagePasskeys` i okna 2FA/passkey (ponad 30 angielskich ciągów) proponuję odroczyć.

## Charges

Każdy zarzut ma plik, linię i wpływ na użytkownika. Kategorie: **T** brakujące tokeny, **K** brakujący współdzielony komponent, **A** przypadkowa architektura.

### C1 (T, K) `AppearanceTabs` to drugi przełącznik segmentowy z klas palety i po angielsku: potwierdzony

- **Gdzie**: `resources/js/components/AppearanceTabs.vue:16` (`bg-neutral-100 dark:bg-neutral-800`), `:25-26` (`bg-white`, `text-neutral-500`, `hover:text-black`, `dark:*`), `:8-10` (etykiety „Light”, „Dark”, „System”). Użyty tylko w `pages/settings/Appearance.vue:31`.
- **Wpływ**: klasy palety nie idą za tokenami motywu, więc przełącznik motywu wygląda inaczej niż reszta Atramentu w obu motywach; etykiety są po angielsku w polskiej aplikacji; przyciski nie mają nazwy grupy ani `aria-pressed`.
- **Reguła**: `.ai/rules/js.md` (bez klas palety i `dark:`); `.ai/rules/js.md:53` (`SegmentedControl` w tonie `neutral` dla ustawienia zapisywanego od razu); README wzorca: „w Atramencie to `ht-seg` z etykietami „Jasny”, „Ciemny”, „Systemowy””.
- **Wzorce w repo**: `SegmentedControl.vue` (`tone="neutral"`, wymaga `ariaLabel`, nie ma slotu na ikonę; `onUpdate` ignoruje ponowne kliknięcie aktywnej opcji). `useAppearance` ma wartości `'light' | 'dark' | 'system'` (`types/ui.ts:1`), więc `v-model` wiąże się wprost.
- **Rekomendacja**: `SegmentedControl` `tone="neutral"` z trzema etykietami bez ikon (wzorzec ich nie pokazuje; ikony Sun/Moon/Monitor wypadają), `ariaLabel` z `t.settings.appearance`. `AppearanceTabs.vue` zostaje jako cienki komponent albo znika.
- **Uwaga do weryfikacji w fazie stanów**: `appearance` jest hydratowane z `localStorage` dopiero w `onMounted` (`useAppearance.ts:89-97`, odczyt agenta), więc pierwszy render pokazuje „Systemowy” zanim zaznaczy się zapisana opcja. Do sprawdzenia w przeglądarce, czy to widoczny przeskok.

### C2 (K) Sekcje ustawień leżą na szarym tle bez paneli: potwierdzony

- **Gdzie**: `pages/settings/Profile.vue:33-83` (`Heading variant="small"` + formularz na tle), `components/DeleteUser.vue:27-37` (jw.), `pages/settings/Appearance.vue:25-31`, `Security.vue:41-106`. `Heading.vue:14-24` ma `text-base font-medium` i `text-xl font-semibold`, żaden nie jest `section-title` ani `page-title`. `Heading` jest używany wyłącznie w ustawieniach, w 7 miejscach (`Layout.vue:3`, `Profile.vue:6`, `Security.vue:4`, `Appearance.vue:4`, `DeleteUser.vue:5`, `ManageTwoFactor.vue:5`, `ManagePasskeys.vue:5`).
- **Wpływ**: granice sekcji są niewidoczne, więc użytkownik nie widzi, gdzie kończy się formularz profilu, a gdzie zaczyna strefa usuwania konta; ustawienia wyglądają jak inna aplikacja niż Konta i Budżet.
- **Reguła**: ADR-0037 (panel = `card`, pełna `border`, `radius-lg`, bez cienia spoczynkowego); wzorzec: `.ht-panel` + `.ht-panel__head` (`h2` 13 px/18 px/600 = `section-title`, pod nim opis `meta`) + treść z odstępem i stopka z akcją (`bundle.css:56-58,188-189`); `design-system.md:84`: „`Heading` służy sekcjom ustawień” (do przepisania w ADR-0049).
- **Wzorce w repo**: `ListSurface` w wariancie niefluszowym ma nagłówek `CardTitle class="text-base"` (`ListSurface.vue:44-52`), czyli typografię sprzed Atramentu, której nie wolno użyć (`AccountsHardcodedUiTest` zakazuje `text-base`); wariant `flush` ma pasek `header` z `border-b px-4 py-3` i stopkę `px-4 py-3` (`ListSurface.vue:25-38`), czyli najbliższy szkielet.
- **Rekomendacja**: mały komponent domenowy `SettingsPanel` (`components/`, nie `ui/`) na bazie `ListSurface flush` z paskiem nagłówka (`section-title` + opis) i slotami treści i stopki. Alternatywa: czysty `Card` z klasami nazwanymi w `@layer components`. Decyzja do planu.

### C3 (K, T) Strefa „Usuń konto” składa się ręcznie, a wzorzec żąda tła `destructive-soft` i przycisku `--danger`: potwierdzony

- **Gdzie**: `DeleteUser.vue:33-35` (`border-destructive/20 bg-destructive/5 … rounded-lg border p-4`, kolor z nieprzezroczystością zamiast tokenu), `:43` (przycisk `variant="destructive"`).
- **Wpływ**: ostrzeżenie „Uwaga / Tej operacji nie można cofnąć.” ma inną wagę wizualną niż pozostałe alerty (`note`, `warning`); wypełniony czerwony przycisk jako jedyna akcja panelu przyciąga uwagę bardziej, niż zamierza wzorzec.
- **Dowody**: wzorzec `.ht-alert--danger` (`bundle.css:311-312`): tło `destructive-soft`, ikona i pogrubiony tytuł w `destructive`; `.ht-btn--danger` (`bundle.css:28`) to przycisk z obwódką `border` na `card` i tekstem `destructive`. `Alert` w repo ma warianty `default`, `destructive` (`bg-card text-destructive`), `warning`, `note` (`components/ui/alert/index.ts:14-19`); `Button` ma `destructive` jako wypełniony czerwony z białym tekstem (`components/ui/button/index.ts:22-23`). `design-system.md:51` już mówi: `warning-soft`, `destructive-soft` = „tło pigułek i alertów odpowiedniej wagi”. `js.md:24`: `variant="destructive"` tylko dla prawdziwych błędów (`AlertError`) i akcji usuwających.
- **Rekomendacja** (do ADR-0049): nowy wariant `Alert` z tłem `destructive-soft` (symetria do `note` z ADR-0042, to rozszerzenie `components/ui` o rolę systemową, nie o ekran) oraz przycisk usunięcia jako `outline` z tekstem `destructive`. Pytanie, czy to wariant `Button` czy klasa, jest w Open Questions.

### C4 (K, A) `DeleteUser` buduje ręczny `Dialog` + `Form` wbrew regule o `ConfirmActionDialog`: potwierdzony

- **Gdzie**: `DeleteUser.vue:40-108` (`Dialog`, `DialogContent`, `Form` ręcznie), `:85` („Anuluj” w `variant="secondary"`), `:99` (potwierdzenie `destructive`), `:52-53` (`reset-on-success`, fokus pola hasła przy błędzie).
- **Wpływ**: okno ma inną stopkę niż pozostałe potwierdzenia (ADR-0045: „Anuluj” `ghost` w każdym dialogu); po błędnym haśle ten dialog przenosi fokus na pole (`DeleteUser.vue:53`), a `ConfirmActionDialog` tego nie robi; komunikat błędu stoi pod polem, nie w liście błędów jak w `ConfirmActionDialog`.
- **Reguła**: `design-system.md:20` („potwierdzenia przez `ConfirmActionDialog`, nigdy ręcznie złożony `DialogContent`”), `js.md:31-33`, `0045:11` („Anuluj” `ghost`).
- **Wzorce w repo**: `ConfirmActionDialog.vue` ma slot domyślny na treść (`:57`), `trigger` (`:43`), spinner, blokadę zamykania w trakcie (`:27-33`) i wypisuje wszystkie błędy serwera przez `InputError` (`:58-62`). Nie przekazuje zdarzenia `@error` ani fokusu, nie ma `reset-on-success`. Po udanym usunięciu konta serwer wylogowuje i przekierowuje na `/` (`ProfileController.php:51-62`), więc `reset-on-success` nie ma tu skutku, a brak fokusu po błędzie jest realną różnicą.
- **Rekomendacja**: przejść na `ConfirmActionDialog` z polem hasła w slocie domyślnym i `destructive`; fokus po błędzie: albo `autofocus` na polu w slocie, albo opcjonalna obsługa zdarzenia `error` w komponencie (rozszerzenie bazowe, wspólne z innymi potwierdzeniami). Decyzja do planu.

### C5 (A) Podnawigacja nie ogłasza aktywnej zakładki, a układ strony ignoruje wzorzec: potwierdzony

- **Gdzie**: `layouts/settings/Layout.vue:50-63` (pozycje to `Button variant="ghost"` w `Link`, aktywna przez `bg-muted` `:53`, bez `aria-current`), `:45` (`aria-label="Settings"`), `:58` (`<component :is="item.icon">` dla pozycji bez ikony), `:36-39` i `Heading` w wariancie domyślnym (`h2` „Ustawienia”), `:42,67,68` (`w-48`, `max-w-xl`, `md:max-w-2xl`, `space-y-12`). Strony mają ukryty `h1` (`Profile.vue:31`). Układ wpina `app.ts:21-22` dla każdej strony `settings/*`.
- **Wpływ**: czytnik ekranu nie wie, która zakładka jest aktywna; widoczny tytuł „Ustawienia” nie jest `h1`, a ukryty `h1` powtarza tytuł strony; na telefonie nawigacja i treść układają się inaczej niż reszta aplikacji.
- **Dowody**: wzorzec `.ht-settings` (siatka `200px minmax(0,640px)`, odstęp `space-5`) i `.ht-subnav a` z `aria-current="page"` (aktywna pozycja jak karta: `card` + obwódka `border`, `bundle.css:313-317`); tytuł `h1` z opisem jak w `Accounts.vue:129-133` (`page-title` + `text-muted-foreground text-sm`). `js-sidebar.md` obejmuje `layouts/**/*.vue`, ale nie opisuje układu ustawień.
- **Rekomendacja**: `<nav aria-label>` z `Link`ami i `aria-current`, klasy nazwane w `@layer components` (np. `.settings-layout`, `.settings-subnav`; zakaz wartości arbitralnych), widoczny `h1.page-title` w layoucie (ukryte `h1` ze stron znikają). Layout dotyka Security i Appearance, więc faza widoku musi je sprawdzić.

### C6 (A) Polskie teksty: nagłówek „Update password”, etykiety motywu, toasty i błędy po angielsku: potwierdzony

- **Gdzie (zakres zlecony)**: `Security.vue:43-44` („Update password”, „Ensure your account…”), `:62-104` (etykiety i placeholdery „Current password”, „New password”, „Confirm password”, przycisk „Save”, mimo że `t.settings.save` istnieje), `AppearanceTabs.vue:8-10`, `Layout.vue:45`.
- **Gdzie (skutek uboczny, widoczny z Profilu)**: toast po zapisie profilu `__('Profile updated.')` (`ProfileController.php:41`) i hasła `__('Password updated.')` (`SecurityController.php:62`); błąd błędnego hasła w oknie usuwania z reguły `current_password` (`PasswordValidationRules.php:27`). W `lang/` są tylko `pl/pagination.php`, `pl/ui.php` i `vendor/backup`, więc nie ma `pl/validation.php` ani `pl.json`; `config/app.php:81,83` daje `locale=pl`, `fallback_locale=en`, a angielski plik frameworka mówi `'current_password' => 'The password is incorrect.'` (`vendor/laravel/framework/src/Illuminate/Translation/lang/en/validation.php:41`).
- **Wpływ**: po kliknięciu „Zapisz” w polskim Profilu użytkownik dostaje angielski toast; błędne hasło przy usuwaniu konta daje angielski komunikat.
- **Mechanizm**: teksty ustawień idą z `lang/pl/ui.php` (klucz `settings`, wiersze 79-102) przez `UiTranslations` i prop `translations` do `useTranslations()`; typ `settings` to `Record<string, string>` (`types/shell.ts:39`), więc nowe klucze nie wymagają zmiany TypeScript, a literówka wychodzi dopiero w przeglądarce.
- **Rekomendacja**: dodać klucze do `lang/pl/ui.php`; toasty i komunikat `current_password` przetłumaczyć w tej zmianie (tłumaczenia serwerowe przez `lang/pl.json` albo `lang/pl/validation.php`), bo należą do przepływu Profilu. Bez nich karta Profilu nie jest w pełni po polsku. Odroczone: C6b poniżej.

### C7 (A) Ustawienia nie mają bramki: potwierdzony

- **Gdzie**: pliki ustawień nie są na liście `FILES` w `scripts/check-ui-literals.mjs:9-100`; brak `tests/Unit/SettingsHardcodedUiTest.php`; brak `/dev/ui/settings`; kitchen sink dialogów nie zawiera `DeleteUser` (według agenta, niezweryfikowane; do sprawdzenia w planie).
- **Wpływ**: następny agent może wrócić do `bg-neutral-*` w `AppearanceTabs` i nic tego nie wyłapie; dla ustawień nie ma kitchen sinka, więc stany `disabled`, `error` i `focus-visible` nie są nigdzie renderowane na stałe.
- **Wzorce w repo**: sink Kont: `AccountsKitchenSinkController.php:11-16` (`abort_unless(app()->isLocal(), 404)`), trasa `routes/web.php:26-31` w grupie `auth`+`verified`, test `tests/Feature/AccountsKitchenSinkTest.php:14-38`; strażnik `tests/Unit/AccountsHardcodedUiTest.php` (zakazy: hex, funkcje koloru, klasy palety, wartości arbitralne, `text-lg|xl|2xl|3xl`, `text-base`, `rounded-xl|2xl`, `border-border/N`, `opacity-N`).
- **Uwaga**: `Heading`, `InputError`, `PasswordInput` mają konsumentów poza ustawieniami (`InputError` importuje 22 pliki, `PasswordInput` 6 plików, w tym strony logowania i rejestracji; liczby z `grep -rl` po `resources/js`); `Heading` jest używany tylko w ustawieniach. Na listę strażnika trafiają wyłącznie pliki, które zmiana oczyszcza.

### Odroczone (poza tą zmianą)

- **C6b: `ManageTwoFactor`, `ManagePasskeys`, `PasskeyItem`, `PasskeyRegister`, `TwoFactorSetupModal`, `TwoFactorRecoveryCodes`, `useTwoFactorAuth.ts`** (ponad 30 wierszy w inwentarzu angielskich ciągów, `ManageTwoFactor.vue:34-79`, `ManagePasskeys.vue:36-58`, `TwoFactorSetupModal.vue:49-289` i inne). Powód: zlecono nagłówek „Update password” i etykiety motywu; te okna mają własne dialogi, które wymagają osobnej macierzy stanów. Proponowana osobna zmiana. Do czasu jej wykonania `Heading` zostaje w 2 komponentach (i musi zachować własny, zgodny z Atramentem krój).
- **`PasswordInput.vue:39`** (`aria-label` „Hide/Show password”): współdzielony z ekranami logowania; poza zakresem.
- **Panelowanie `ManageTwoFactor` i `ManagePasskeys`**: razem z C6b.

## Detailed Findings

### Wzorzec Atrament (`PageSettingsProfile`)

- Struktura: `h1` „Ustawienia” + opis „Zarządzaj profilem i ustawieniami konta logowania”, siatka `.ht-settings` (200 px | do 640 px), `nav.ht-subnav` z `aria-label="Ustawienia"` i `aria-current="page"` na „Profil”, dwa panele: „Profil” (pola „Imię i nazwisko”, „Adres e-mail”, stopka „Zapisz”) i „Usuń konto” (alert `ht-alert--danger` „Uwaga / Tej operacji nie można cofnąć.” + przycisk `--danger` z ikoną kosza). Źródło: `project/components/PageSettingsProfile/preview.html` i `README.md` artefaktu (stan „Propozycja”).
- README zaznacza: sekcje dostają panele zamiast `Heading` na tle; w Wyglądzie przełącznik to `ht-seg` z „Jasny”, „Ciemny”, „Systemowy”. Karta pokazuje tylko Profil; nie ma kart dla Bezpieczeństwa ani Wyglądu.
- Pola formularza w panelu to `ht-field` (etykieta, kontrolka). W repo odpowiada im `FormField` (34 px, `form-field-*` w `app.css:324-336`), którego dziś Profil nie używa; Profil ma ręczne `grid gap-2` + `Label` + `Input` + `InputError` (`Profile.vue:44-72`).

### Ustawienia dziś

- `Profile.vue:1-85`: dwa pola, `Form` Wayfinder z `ProfileController.update.form()`, `data-test="update-profile-button"` (`:77`); odczyt użytkownika z `page.props.auth.user` (`:16`). Strona dostaje propsy `mustVerifyEmail` i `status` (`ProfileController::edit`), których widok nie deklaruje i żaden test nie asercjonuje (odczyt agenta).
- Trasy: `routes/settings.php`: `profile.edit`/`profile.update` (grupa `auth`), `profile.destroy`, `security.edit` (z `RequirePassword`), `user-password.update` (`throttle:6,1`), `appearance.edit` (`Route::inertia`, bez propsów) w grupie `auth`+`verified`. Zmiana nie dotyka propsów PHP, więc reguła `inertia-props.md` nie wymaga nowego `assertInertia` (zmiana wyłącznie widoków i `lang`).
- Testy: `tests/Feature/Settings/ProfileUpdateTest.php` i `SecurityTest.php` działają na poziomie HTTP; `SecurityTest` sprawdza nazwę komponentu `settings/Security` (`:133-137,171-176`); `AppShellTest.php:80-90` sprawdza `settings/Profile` i `translations.settings.profile === 'Profil'`. Żaden test w `tests/` ani `*.test.ts` nie odwołuje się do atrybutów `data-test` ustawień (`update-profile-button`, `update-password-button`, `delete-user-button`, `confirm-delete-user-button`) ani do angielskich tekstów ustawień. Nazwy komponentów Inertia i klucz `settings.profile` muszą zostać.
- `ui-contract.test.ts` skanuje całe `resources/js` pod kątem martwych wiązań `:checked` Checkboxa; przepisanie ustawień nie może ich wprowadzić.
- Zakres kontaktu: `UserMenuContent.vue:35-37` linkuje do `edit()` z etykietą `t.settings.title`; nic poza `AppearanceTabs` nie przełącza motywu (`UserMenuContent`, sidebar bez przełącznika).

## Code References

- `resources/js/layouts/settings/Layout.vue:36-68` - tytuł (`Heading`), nawigacja z `Button ghost`, `bg-muted`, `aria-label="Settings"`, `max-w-*`
- `resources/js/app.ts:21-22` - `[AppLayout, SettingsLayout]` dla każdej strony `settings/*`
- `resources/js/pages/settings/Profile.vue:31-85` - ukryty `h1`, `Heading`, formularz, `<DeleteUser />`
- `resources/js/components/DeleteUser.vue:33-108` - ręczne pudełko ostrzeżenia i ręczny dialog
- `resources/js/components/AppearanceTabs.vue:8-26` - etykiety angielskie i klasy palety
- `resources/js/pages/settings/Security.vue:41-106` - angielski formularz hasła
- `resources/js/components/ConfirmActionDialog.vue:19-75` - baza potwierdzeń (bez `@error`)
- `resources/js/components/ui/alert/index.ts:11-19` - warianty `Alert`
- `resources/js/components/ui/button/index.ts:22-27` - warianty `Button`
- `resources/js/components/SegmentedControl.vue:1-56` - wybór neutralny z `aria-label`
- `lang/pl/ui.php:79-102` - klucze `settings`
- `scripts/check-ui-literals.mjs:9-100` - lista `FILES` bramki
- `.ai/rules/js.md:20,24,31-33,45-48,52` - miejsca, gdzie dopisać regułę ustawień

## Architecture Insights

- Wzorzec Atramentu rozstrzyga się w systemie ról tokenów (`card`, `border`, `radius-lg`, `destructive-soft`), ale `components/ui/*` ma luki: tło `destructive-soft` w `Alert` i wariant przycisku z czerwonym tekstem. Pierwsze jest symetryczne do `note` (ADR-0042) i potwierdzone zdaniem w `design-system.md:51`; drugie jest wyborem, który ADR musi nazwać.
- `Layout.vue` jest wspólnym układem trzech sprawdzonych stron `settings/*` (`app.ts:21-22`), więc zmiana `Layout.vue` zmienia też Security i Appearance. Zakres zmiany musi je zawierać na poziomie „nie psuje się”, nawet jeśli panele dostaje tylko Profil.
- Konwencja zmian 10x: ADR bez nagłówków sekcji (tytuł H1 po angielsku, 2–4 akapity, końcówka „This supersedes in part… We rejected…”), kitchen sink z kontrolerem `Dev/*` i trasą w grupie `auth`+`verified`, strażnik w `tests/Unit/*HardcodedUiTest.php` i wpisy w `check-ui-literals.mjs`.

## Historical Context (from prior changes)

- `context/archive/2026-11-06-accounts-atrament-ui/research.md` i `plan.md` - szablon zarzutów i faz (decyzje i dokumenty → widok → dialog → stany i bramka); w ustawieniach faza „kontrakt propsów” jest pusta (bez nowych propsów PHP).
- `context/archive/2026-09-07-app-shell-openship-visual-language/spec.md:41` - decyzja, że ustawienia (Profil, Bezpieczeństwo, Wygląd) zostają po polsku w strukturze startera; nikt nie decydował o ich wyglądzie w Atramencie.
- `context/archive/2026-11-05-category-dialog-atrament/plan.md:186,453` - punkt 2.11 sprawdził szerokość i stopkę `DeleteUser`, `PasskeyItem`, `TwoFactorSetupModal`; to było przed ADR-0045, który zmienił „Anuluj” na `ghost` (`DeleteUser.vue:85` nadal `secondary`).
- ADR-0037, 0040, 0042, 0045 (`context/foundation/adr/`) - panel, okno 460 px i pole 34 px, `note` i `brand`, „Anuluj” `ghost`; żaden nie decyduje o `Alert` w roli strefy niebezpiecznej ani o układzie ustawień.
- **Numeracja ADR**: w worktree najwyższy plik to 0045 (`context/foundation/adr/0045-…`). W głównym checkoucie, w katalogach jeszcze niezacommitowanych, numer 0046 rezerwuje `context/changes/term-deposits-atrament-ui/research.md:83,98,172`, a 0047 `context/changes/accounts-design-system-page/change.md:23-26`. Numeru 0048 nie znalazłem nigdzie. Ustalenie użytkownika (0049) pozostaje, ale dwie równoległe zmiany też edytują `design-system.md` (wiersz 3 z listą ADR, `:24-26`, `:99`, `:129`) i `.ai/rules/js.md`, więc przy scalaniu spodziewać się konfliktów w tych miejscach.

## Related Research

- `context/changes/term-deposits-atrament-ui/research.md` (tylko w głównym checkoucie, nieśledzony) - równoległa zmiana Lokat, ten sam szablon; ADR-0046.
- `context/archive/2026-11-06-accounts-atrament-ui/research.md` - najbliższy szablon zarzutów.

## Open Questions

1. **Zakres stron.** Panele dostaje Profil i `DeleteUser`. Czy w tej zmianie także formularz hasła w Security i Wygląd (tanie, ten sam `SettingsPanel`), a `ManageTwoFactor` i `ManagePasskeys` zostają na `Heading`? Rekomendacja: tak, Security (hasło) i Wygląd w zakresie, 2FA i passkeys odroczone razem z C6b (rozwiązuje spójność wizualną Wyglądu i hasła bez dotykania okien 2FA).
2. **Panel.** `SettingsPanel` na `ListSurface flush`, czy własny komponent na `Card`? Rekomendacja: własny cienki komponent domenowy z `section-title` i opisem (ListSurface flush nie ma tytułu w pasku).
3. **Przycisk usunięcia.** Nowy wariant `Button` (np. `outline-destructive`), czy `outline` z `text-destructive` w klasie? Rekomendacja: wariant w `components/ui/button/index.ts`, bo „akcje usuwające” (`design-system.md:47`) to rola systemowa, nie jednorazowy styl; nazwać w ADR-0049.
4. **Alert niebezpieczny.** Nowy wariant `Alert` z `destructive-soft` (rekomendowany) czy istniejący `destructive` (`bg-card`)? Jeśli istniejący, wzorzec wygląda inaczej niż karta.
5. **Fokus po błędnym haśle w `ConfirmActionDialog`.** Rozszerzyć komponent bazowy o obsługę błędu, czy dać `autofocus` w slocie? Rekomendacja: sprawdzić w fazie widoku, co robi `Form` slotu przy błędzie; jeśli wystarczy `autofocus`, bez zmiany komponentu bazowego.
6. **Tłumaczenia serwera.** Czy toasty „Profile updated.”/„Password updated.” i komunikat `current_password` mają wejść do tej zmiany (rekomendacja: tak, to przepływ Profilu), czy do osobnej zmiany na całe `lang/pl`? Wymaga decyzji, czy zakładamy `lang/pl.json` czy `lang/pl/validation.php` (nowy plik językowy, nie nowa zależność).
7. **Kitchen sink.** `/dev/ui/settings` jako `Inertia::render('dev/SettingsKitchenSink')` z fixture'ami (strony ustawień czytają `auth.user` z propsów współdzielonych i trasy Wayfinder, a `security.edit` ma `RequirePassword`); czy sink ma renderować `Profile.vue` z fixture'ami, czy komponenty bez trasy? Do rozstrzygnięcia w planie.
8. **Zmiany w dokumentach równoległych.** Koordynacja z zmianami Lokat (ADR-0046) i Kont (ADR-0047): kolejność scalania `design-system.md` i `js.md`. Nie wymaga decyzji produktowej, wymaga uwagi przy merge'u.
