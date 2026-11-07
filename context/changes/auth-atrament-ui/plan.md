# Ekrany wejścia według Atramentu — plan implementacji

## Overview

Siedem ekranów wejścia (`pages/auth/*`), ich układ `AuthCardLayout` oraz `PasskeyVerify` przechodzą na kartę Page* z design systemu Atrament. Ciemny panel na tokenach `sidebar-*` stoi obok karty 400 px, potwierdzenia są w Alert `ok`, przycisk ma 34 px na pełną szerokość, link jest jeden, a teksty są polskie. Przy okazji zmiana naprawia trzy błędy zachowania, które leżą na tej samej drodze: logowanie kluczem dostępu ląduje na `/` zamiast na dashboardzie (C13), komunikaty serwera są po angielsku (C11), a reset hasła bez `?email=` kończy się ślepą uliczką (C15). Na końcu ekrany dostają kitchen sink `/dev/ui/auth` i ochronę `check:ui`.

## Current State Analysis

Pełny audyt: `context/changes/auth-atrament-ui/research.md` (zarzuty C1–C17, mapowanie klas, decyzje Q1–Q7). W skrócie:

- `layouts/auth/AuthCardLayout.vue:24-45` to centrowana `Card` `rounded-xl` na `bg-muted`. Brakuje panelu, karty 400 px, ikony i stopki (C4).
- Tokeny `--sidebar-*` (`app.css:174-182`, dark `227-235`), `--accent`, `--brand-text` i `--field-height` 34 px (`app.css:161`) już istnieją. Brakuje klas układu i stylów tekstu `display`/`heading`/`lead`/`card-title` (C3), wariantu Alert `ok` (C8) i przycisku 34 px (C7).
- Potwierdzenia to goły `text-success` (`Login.vue:34`, `ForgotPassword.vue:30`, `VerifyEmail.vue:27`; C1). Linki mają literały palety `neutral` (`TextLink.vue:21`, `TwoFactorChallenge.vue:92,124`; C2).
- VerifyEmail, ConfirmPassword, TwoFactorChallenge, `PasskeyVerify` i `PasswordInput` mają angielskie literały, choć klucze `auth.verify_email`, `auth.confirm_password` i `auth.two_factor` są w `lang/pl/ui.php:74-93` (C10). Nie ma `lang/pl/auth.php` ani `passwords.php` (C11).
- `Login.vue:70,110` ma podwójne `tabindex="5"` (C14), a `PasswordInput.vue:40` ma `tabindex=-1` (C9).
- `PasskeyLoginResponse` kieruje na `redirect()->intended(config('passkeys.redirect', '/'))` (`vendor/laravel/passkeys/src/Http/Responses/PasskeyLoginResponse.php:24,28`). Aplikacja nie ma `config/passkeys.php`, a `/` to `Welcome` (C13). Testu logowania kluczem nie ma.
- `ResetPassword.vue:20-26` ma `email: string` i pole `readonly`, a `FortifyServiceProvider.php:56-60` przekazuje `null`, gdy w URL nie ma parametru (C15).
- `scripts/check-ui-literals.mjs` obejmuje tylko `AuthCardLayout.vue`, nie ma kitchen sinka auth, a selektor e2e `getByRole('button', { name: 'Zaloguj się' })` (`tests/e2e/auth.setup.ts:27`) złapie przycisk klucza (C16, C17).

## Desired End State

- Każdy ekran wejścia wygląda jak jego karta Page*. Na `lg` i szerzej ciemny panel `--auth-aside-width` stoi obok karty `--auth-card-width`; poniżej `lg` panel zwija się do paska ze znakiem produktu.
- Potwierdzenia to `<Alert variant="ok">`, główna akcja to `Button` z klasą `.auth-submit` (34 px, pełna szerokość), a linki to `TextLink` na `brand-text`.
- Nie ma angielskiego tekstu w UI wejścia ani w błędzie logowania i statusach resetu hasła.
- Logowanie kluczem dostępu bez „intended” prowadzi na `/dashboard`.
- Reset hasła bez `?email=` ma edytowalne pole e-mail.
- Kolejność tabulacji wynika z DOM: klucz, e-mail, „Nie pamiętasz hasła?”, hasło, „Pokaż hasło”, zapamiętaj, zaloguj.
- `/dev/ui/auth` pokazuje każdy ekran w każdym stanie jako pełną stronę. Pliki auth są na liście `check:ui`, a ADR-0056 zapisuje decyzje.

Weryfikacja: `composer ci:check`, `npm run check:ui`, `npm test`, testy Pest z tego planu na PostgreSQL oraz zrzuty `/dev/ui/auth` przy 1280 i 390 px w obu motywach.

### Key Discoveries:

- `ui/badge/index.ts:23`: Badge `ok` to już `bg-accent text-accent-foreground`, więc `accent` ma w repo znaczenie „ok” dla statusu. Alert `ok` rozszerza istniejące znaczenie, nie tworzy nowego.
- Warianty `ui/*` dodawano wcześniej z ADR: Alert `note` (ADR-0042) i `danger` (ADR-0049), Button `brand` i `outline-destructive`. Alert `ok` ma trzech konsumentów, więc nie jest „edycją pod jeden ekran”.
- `PasskeyLoginController::store` (`vendor/laravel/passkeys/src/Http/Controllers/PasskeyLoginController.php:43-66`) dostaje `VerifyPasskey` z kontenera. Test może podmienić tę akcję na mock i nie potrzebuje prawdziwego WebAuthn. Przy JSON odpowiedź to `{redirect: <url>}` (`PasskeyLoginResponse.php:22-25`).
- Propy z `Inertia::render` nadpisują propy współdzielone, także `errors`. Kontroler sinka może więc renderować prawdziwy komponent `auth/*` z błędami z fiksur, a resolver z `app.ts:17-18` sam da mu `AuthLayout`. Wzór odrzucania pól: `DialogsKitchenSinkController::validate`.
- `PasswordInput` używają też `settings/Security.vue` i `DeleteUser.vue`, więc zmiana tabulacji i etykiety obejmie ustawienia. Tak ma być, ale trzeba to sprawdzić ręcznie.
- `ui-contract.test.ts:324` wymaga słowa `BrandMark` w `AuthCardLayout.vue`. Panel i pasek telefonu używają `BrandMark` (ADR-0053).

## What We're NOT Doing

- Pełnego `lang/pl/validation.php` (Q1). Zostaje odroczony i trafi do osobnej zmiany. Tu powstają tylko `auth.php` i `passwords.php`.
- Włączania rejestracji ani zmian w `config/fortify.php`. `Register.vue` restylujemy, ale zostaje nieosiągalny poza sinkiem (Q2).
- `Welcome.vue`: `display` i `card-title` trafiają do `app.css` bez konsumenta, do czasu osobnej zmiany strony powitalnej (Q3).
- Edycji `ui/input-otp`, `ui/button` i `ui/card`. Rozmiar OTP i przycisk 34 px są klasami nazwanymi w `app.css` (D3). Jedyna zmiana w `ui/*` to wariant `ok` w `ui/alert`.
- Własnego breakpointu 860 px z karty. Panel przełącza się na `lg`, bo 440 + 400 px z marginesami nie mieszczą się przy `md`.
- Prefiksu `ht-` w nazwach klas: repo nazywa klasy bez prefiksu.
- Zmian w sekcjach 2FA i kluczy w ustawieniach (`Heading` zostaje).

## Implementation Approach

Kolejność jak w poprzednich zmianach Atramentu: najpierw decyzje (ADR i dokumenty), potem poprawki zachowania od nieprzechodzącego testu, potem tokeny i komponenty współdzielone, potem widoki, na końcu sink i bramka wizualna. Test regresji C13 idzie przed widokami (decyzja Q4). Każda faza to jeden commit `feat(auth-atrament-ui): …` (`(p2)` itd.; Faza 1 `docs(auth-atrament-ui): …`).

## Critical Implementation Details

- **Sink renderuje prawdziwe strony.** `/dev/ui/auth?screen=<ekran>&state=<stan>` zwraca `Inertia::render('auth/<Ekran>', <propy z fiksur>)`, więc `setLayoutProps`, `Form` i `AuthLayout` działają jak na produkcji. Bez `screen` sink zwraca indeks `dev/AuthKitchenSink` z linkami do wszystkich par ekran × stan, w zwykłym `AppLayout`. To realizacja wybranej opcji „pełna strona `?screen=`”. Ta sama komponenta nie jest kopiowana do sinka, a resolver w `app.ts` nie potrzebuje wyjątku.
- **`VerifyEmail` przechodzi z `defineOptions({ layout })` na `setLayoutProps`.** `defineOptions` nie może wołać `useTranslations`, a inne strony auth już używają `setLayoutProps`.
- **C17 w tym samym commicie co przycisk klucza na Login.** Bez `{ exact: true }` setup e2e padnie w trybie strict, gdy na Login pojawi się „Zaloguj się kluczem dostępu”.

## Faza 1: Decyzje i dokumenty

### Overview

Zapisuje ADR-0056 i aktualizuje dokumenty oraz reguły, zanim powstanie kod (AGENTS.md: nowy token lub znaczenie koloru trafia na stronę design systemu i do ADR).

### Changes Required:

#### 1. ADR-0056

**File**: `context/foundation/adr/0056-auth-follows-page-login.md`

**Intent**: Zapisać decyzje tej zmiany, żeby kolejny agent nie wracał do kart artefaktu po wartości.

**Contract**: Sekcje w formacie poprzednich ADR (0053, 0055). Treść:
- układ `.auth-shell` z ciemnym panelem `--sidebar-*` i kartą, przełączany na `lg` zamiast 860 px, z uzasadnieniem;
- mapowanie `ht-*` na nazwy repo (tabela z research.md, bez prefiksu);
- Alert `ok` na `accent` jako potwierdzenie bez kwoty, wyjątek od „`accent` nie do statusu” i „`success` tylko kwoty”, z precedensem Badge `ok`;
- `.auth-submit` 34 px jako klasa nazwana, bez rozmiaru w `ui/button`;
- `.auth-otp` nadpisuje rozmiar slotów bez edycji `ui/input-otp`;
- style `display`, `heading`, `lead` i `card-title`, przy czym h1 karty to `page-title`;
- ConfirmPassword zachowuje klucz dostępu z separatorem `.auth-or`, co jest różnicą względem karty PageConfirmPassword (Q7);
- kolejność tabulacji = DOM, bez `tabindex` (Q5);
- blok roku w panelu liczony z daty po stronie klienta (Q6);
- reset bez e-maila ma edytowalne pole (C15).

#### 2. Design system

**File**: `context/foundation/design-system.md`

**Intent**: Strona design systemu nadąża za ADR-0056.

**Contract**:
- w nagłówku lista ADR rozszerzona o 0056;
- w tabeli „Kolor znaczy coś…” wiersz `accent` dostaje wyjątek „potwierdzenie bez kwoty: Alert `ok`”;
- w tabeli typografii cztery nowe style;
- na liście „Gotowe elementy”: Alert `ok`, `.auth-shell` i jego części, `.auth-submit`, `.auth-or`, `.auth-otp`, `TextLink`;
- w „Kitchen sink i zrzuty” akapit o `/dev/ui/auth` z macierzą stanów;
- nowe tokeny wymiaru `--auth-aside-width` 440 px i `--auth-card-width` 400 px.

#### 3. Reguły

**Files**: `.ai/rules/css.md`, `.ai/rules/js.md`, nowy `.ai/rules/js-auth.md`, `.ai/rules/index.md`

**Intent**: Reguły ładowane przy edycji plików auth mówią, jak utrzymać ekrany po zmianie.

**Contract**:
- `css.md`: zamknięta lista stylów z czterema nowymi i tokeny `--auth-aside-width` oraz `--auth-card-width`;
- `js.md`: wyjątek `accent` dla Alert `ok` i `ok` dopisane do listy wariantów Alert w sekcji ostrzeżeń;
- `js-auth.md` z `paths:` `resources/js/pages/auth/**`, `resources/js/layouts/auth/**`, `resources/js/layouts/AuthLayout.vue`, `resources/js/components/PasskeyVerify.vue`, `resources/js/components/PasswordInput.vue`, `resources/js/components/TextLink.vue` oraz `resources/js/lib/auth-*.ts`. Treść:
  - układ `.auth-shell`;
  - potwierdzenie przez Alert `ok`, nigdy `text-success`;
  - akcja w `.auth-submit`;
  - teksty z `t.auth.*`;
  - bez `tabindex`, trasy z Wayfindera;
  - nowy stan dopisany do `/dev/ui/auth`.

  Potem `php artisan boost:index-rules`.

### Success Criteria:

#### Automated Verification:

- ADR-0056 istnieje, a numer jest kolejnym wolnym: `ls context/foundation/adr/0056-*.md`
- Indeks reguł zawiera `js-auth.md` po `php artisan boost:index-rules`: `grep js-auth .ai/rules/index.md`

#### Manual Verification:

- ADR-0056 i `design-system.md` zgadzają się z tabelą decyzji w `plan-brief.md` (kolor `ok`, klasy zamiast wariantu przycisku, `lg`, ConfirmPassword z kluczem)

**Implementation Note**: Po fazie zatrzymaj się na ręczne potwierdzenie.

---

## Faza 2: Serwer, najpierw test (C11, C13, C15)

### Overview

Trzy błędy zachowania dostają testy, które najpierw nie przechodzą, a potem poprawki. Dochodzi poprawka selektora e2e (C17), zanim przycisk klucza pojawi się na Login.

### Changes Required:

#### 1. Test regresji: logowanie kluczem dostępu (C13)

**File**: `tests/Feature/Auth/PasskeyLoginRedirectTest.php` (nowy)

**Intent**: Udowodnić, że logowanie kluczem bez „intended” prowadzi dziś na `/`, a po poprawce na dashboard.

**Contract**:
- `postJson(route('passkey.login'), …)` z `VerifyPasskey` podmienionym w kontenerze (`$this->mock`), tak by zwracał klucz dostępu użytkownika z fabryki;
- asercja `redirect` = `url('/dashboard')` i `assertAuthenticatedAs`;
- drugi przypadek: przy ustawionym `url.intended` zwraca ten adres.

Przed poprawką pierwszy przypadek ma nie przechodzić, bo zwraca `url('/')`.

#### 2. Poprawka przekierowania

**File**: `config/passkeys.php` (nowy, `php artisan vendor:publish` z tagiem konfiguracji pakietu); `resources/js/components/PasskeyVerify.vue`

**Intent**: Klucz dostępu kieruje tam, gdzie logowanie hasłem (`fortify.home`).

**Contract**: `'redirect' => '/dashboard'` w opublikowanej konfiguracji; pozostałe klucze bez zmian względem vendor. W `PasskeyVerify.vue:33` literał `'/dashboard'` zastępuje `dashboard()` z Wayfindera (`@/routes`).

#### 3. Polskie komunikaty serwera (C11)

**Files**: `lang/pl/auth.php`, `lang/pl/passwords.php` (nowe); `tests/Feature/Auth/PolishAuthMessagesTest.php` (nowy)

**Intent**: Błąd logowania, blokada po próbach i statusy resetu są po polsku.

**Contract**:
- `auth.php` z kluczami `failed`, `password`, `throttle` (z `:seconds`);
- `passwords.php` z kluczami `reset`, `sent`, `throttled`, `token`, `user` (zestaw z `lang/en` frameworka);
- test, który najpierw nie przechodzi: nieudane logowanie (`post(route('login.store'))` ze złym hasłem) daje błąd pola `email` równy `__('auth.failed')` i różny od angielskiego „These credentials do not match our records.”;
- test, że `post(route('password.email'))` daje status po polsku.

Test sprawdza, że `app.locale` to `pl`; jeśli nie, ustawia lokalizację jawnie.

#### 4. Reset hasła bez e-maila (C15) — kontrakt propa

**Files**: `tests/Feature/Auth/PasswordResetTest.php`, `resources/js/pages/auth/ResetPassword.vue` (tylko typ)

**Intent**: Prop `email` może być `null` po obu stronach, potwierdzone `assertInertia` (reguła inertia-props).

**Contract**:
- nowy przypadek: `GET /reset-password/{token}` bez `?email=` → `component('auth/ResetPassword')` i `where('email', null)`;
- istniejący przypadek z e-mailem asertuje wartość;
- typ w `defineProps` zmienia się na `email: string | null`.

Zachowanie pola dochodzi w Fazie 4.

#### 5. Selektor e2e (C17)

**File**: `tests/e2e/auth.setup.ts`

**Intent**: Setup e2e nie łapie przycisku klucza dostępu.

**Contract**: `getByRole('button', { name: 'Zaloguj się', exact: true })` i `getByRole('textbox', { name: 'Hasło', exact: true })`.

### Success Criteria:

#### Automated Verification:

- Nowe testy nie przechodziły przed poprawką (zapis czerwonego uruchomienia w opisie commitu)
- Testy auth przechodzą na PostgreSQL: `docker compose exec -u sail -e DB_CONNECTION=pgsql -e DB_HOST=pgsql -e DB_DATABASE=testing laravel.test php artisan test --compact tests/Feature/Auth`
- `npm run types:check` przechodzi po typie `email: string | null`
- `vendor/bin/pint --dirty --format agent` bez zmian do zrobienia

#### Manual Verification:

- Logowanie kluczem dostępu w przeglądarce (Chromium z kluczem zapisanym w ustawieniach) kończy się na `/dashboard`

**Implementation Note**: Po fazie zatrzymaj się na ręczne potwierdzenie.

---

## Faza 3: Tokeny, klasy i komponenty współdzielone

### Overview

Wszystkie wartości, z których skorzystają widoki: tokeny, style tekstu, klasy układu, wariant Alert, link, pole hasła i czysta funkcja bloku roku.

### Changes Required:

#### 1. Tokeny i style tekstu

**File**: `resources/css/app.css`

**Intent**: Wartości z kart mają jedno źródło.

**Contract**:
- `--auth-aside-width: 440px` i `--auth-card-width: 400px` w `:root` (wymiary, bez `.dark`);
- `@utility display` (36/40, 600), `heading` (24/30, 600), `lead` (15/22) i `card-title` (14/20, 600), obok `page-title` (`app.css:252-300`).

Wartości bierz z `tokens.json` artefaktu.

#### 2. Klasy układu wejścia

**File**: `resources/css/app.css` (`@layer components`)

**Intent**: Nazwane klasy zastępują `ht-auth*`, `ht-btn--block`, `ht-or` i `ht-otp` z mapowania w research.md.

**Contract**:
- `.auth-shell`: siatka `var(--auth-aside-width) minmax(0,1fr)` od `lg`, jedna kolumna poniżej, `min-h-svh`, tło `background`;
- `.auth-aside`: `bg-sidebar`, tekst `sidebar-foreground`, nagłówki `sidebar-strong`, ramka `sidebar-border`. Od `lg` pełny panel, poniżej pasek ze znakiem;
- części panelu: `.auth-pitch`, `.auth-year`, `.auth-months` (12 kafelków: miniony, bieżący, przyszły), `.auth-points`, `.auth-legal`;
- `.auth-main`, `.auth-card` (`card`, pełny `border`, `rounded-lg`, szerokość `--auth-card-width`), `.auth-card-head`, `.auth-card-icon` (36 px na `accent`), `.auth-card-body`, `.auth-card-foot`;
- `.auth-submit`: `w-full` i `h-control-field` (34 px), wyśrodkowanie;
- `.auth-or`: „lub” 12/16 `muted-foreground` z liniami `border`;
- `.auth-otp`: sloty 40×44, JetBrains Mono 18/500, odstęp 8 px, kreska po trzecim znaku.

Nadpisanie klas `InputOTPSlot` sprawdź w przeglądarce, bo C6 jest niezweryfikowane. Jeśli bazowe klasy wygrywają, użyj selektora potomnego `.auth-otp [data-slot=input-otp-slot]` w warstwie `components`.

#### 3. Alert `ok`

**File**: `resources/js/components/ui/alert/index.ts`

**Intent**: Potwierdzenie bez kwoty ma wariant współdzielony (D2, D3).

**Contract**: `ok: "bg-accent text-accent-foreground border-transparent [&>svg]:text-accent-foreground *:data-[slot=alert-title]:text-accent-foreground *:data-[slot=alert-description]:text-accent-foreground"`. Kontrast `accent-foreground` na `accent` ≥ 4,5:1 sprawdź w obu motywach (reguła css.md).

#### 4. `TextLink` na tokenach

**File**: `resources/js/components/TextLink.vue`

**Intent**: Jeden wygląd linku (C2).

**Contract**: `text-brand-text font-medium`, bez podkreślenia w spoczynku, `hover:underline` z `underline-offset-4`, fokus `ring`. Bez literałów `neutral`. Komponent przyjmuje też `as="button"`, żeby zastąpić kopię w `TwoFactorChallenge.vue:92,124`; zwraca `Link` albo `button` z tymi samymi klasami.

#### 5. `PasswordInput`

**File**: `resources/js/components/PasswordInput.vue`

**Intent**: „Pokaż hasło” jest w kolejności tabulacji i ma polską etykietę (C9, C10).

**Contract**:
- bez `:tabindex="-1"`;
- `aria-label` i `aria-pressed` z nowych kluczy `t.auth.password_toggle.show` i `.hide`;
- przycisk w polu w stylu `ht-field__action`: ikona 16 px, `IconAction`-podobny, pole z paddingiem na przycisk.

Konsumenci w ustawieniach (`Security.vue`, `DeleteUser.vue`) dostają to samo zachowanie.

#### 6. Blok roku w panelu (Q6)

**Files**: `resources/js/lib/auth-year-progress.ts`, `resources/js/lib/auth-year-progress.test.ts` (nowe)

**Intent**: Panel pokazuje rok i postęp roku z bieżącej daty, bez propa Inertia.

**Contract**: `authYearProgress(today: Date): { year: number; elapsedPercent: number; months: Array<'past' | 'current' | 'future'> }`. `elapsedPercent` to liczba całkowita z dni roku. Test obejmuje:
- 1 stycznia (bieżący miesiąc 0, pozostałe przyszłe);
- 31 grudnia (100% albo 99%, zgodnie z liczeniem dni);
- 29 lutego roku przestępnego;
- datę ze strefy, w której zmienia się doba (liczone w czasie lokalnym).

Import z `vite-plus/test`, ścieżki względne (reguła frontend-toolchain).

#### 7. Klucze tłumaczeń

**File**: `lang/pl/ui.php`

**Intent**: Wszystkie nowe teksty wejścia mają klucze, zanim widoki ich użyją (C10).

**Contract**: W `auth`:
- `aside` (hasło, lead, trzy punkty, „Rok budżetu”, „Postęp roku”, stopka „© :year”);
- `password_toggle.show` i `.hide`;
- `passkey.login` („Zaloguj się kluczem dostępu”), `passkey.confirm` („Potwierdź kluczem dostępu”) i `passkey.or` („lub”);
- w `verify_email`: `sent_title`, `sent_body` z `:email` i `wrong_address`;
- w `two_factor`: `no_phone`, `recovery_placeholder`, `otp_label`.

`tests/Unit/UiTranslationKeysTest.php` musi przejść.

### Success Criteria:

#### Automated Verification:

- `npm test` przechodzi, w tym `auth-year-progress.test.ts`
- `npm run check` przechodzi bez ostrzeżeń
- `npm run check:ui` przechodzi z `TextLink.vue` i `PasswordInput.vue` dopisanymi do `FILES`
- `php artisan test --compact tests/Unit/UiTranslationKeysTest.php tests/Feature/Settings` przechodzi

#### Manual Verification:

- W ustawieniach (Bezpieczeństwo, usuwanie konta) Tab dochodzi do „Pokaż hasło”, a czytnik ekranu podaje polską etykietę

**Implementation Note**: Po fazie zatrzymaj się na ręczne potwierdzenie.

---

## Faza 4: Układ i siedem widoków

### Overview

`AuthCardLayout` i strony auth przechodzą na klasy z Fazy 3, teksty z `t.auth.*`, trasy z Wayfindera, bez `tabindex`.

### Changes Required:

#### 1. Układ

**File**: `resources/js/layouts/auth/AuthCardLayout.vue`

**Intent**: Jeden układ dla siedmiu kart (C4).

**Contract**:
- `.auth-shell` z `.auth-aside`: `BrandMark` z nazwą w linku `home()`, `h2.heading`, `p.lead`, blok roku z `authYearProgress(new Date())` z `aria-hidden` na kafelkach, trzy punkty, stopka;
- `.auth-main` z kartą: opcjonalna ikona w `.auth-card-icon`, `h1.page-title` z `title`, `p.meta` z `description`, slot, opcjonalny slot `footer` w `.auth-card-foot`;
- bez `Card` i bez `rounded-xl`.

Propy `title`, `description` i nowy `icon?: Component` przechodzą przez `AuthLayout.vue`.

#### 2. Strony

**Files**: `resources/js/pages/auth/{Login,Register,ForgotPassword,ResetPassword,VerifyEmail,ConfirmPassword,TwoFactorChallenge}.vue`, `resources/js/components/PasskeyVerify.vue`

**Intent**: Każda strona odpowiada swojej karcie Page*.

**Contract**:
- status → `<Alert variant="ok">` z ikoną, nigdy `text-success` (C1);
- główna akcja → `Button` z klasą `auth-submit`, a w VerifyEmail ponowna wysyłka jako `variant="outline"` (karta);
- pola przez `FormField` albo ten sam wzór (etykieta, kontrolka, błąd przez `aria-describedby`). Wiersz etykiety z linkiem „Nie pamiętasz hasła?” przed polem hasła (Q5);
- bez żadnego `tabindex` (C14);
- Login: `PasskeyVerify` z `t.auth.passkey.login` i `.or` nad formularzem; link rejestracji tylko za `canRegister`, bez `href="/register"`. Brak trasy w Wayfinderze oznacza, że linku nie ma w markupie (C12);
- ResetPassword: z e-mailem pole `readonly` na `muted` (klasa `.form-readonly-text` albo `readonly` na `Input` z tłem `muted`), bez e-maila pole edytowalne z `autocomplete="email"`, a `transform` wysyła wartość pola zamiast propa (C15);
- VerifyEmail: `setLayoutProps` zamiast `defineOptions`, teksty z `t.auth.verify_email`, e-mail z `usePage().props.auth.user.email`, ikona `Mail`;
- ConfirmPassword: teksty z `t.auth.confirm_password`, ikona `ShieldCheck`, `PasskeyVerify` z `t.auth.passkey.confirm` i separatorem `.auth-or` (Q7);
- TwoFactorChallenge: tytuły i przyciski z `t.auth.two_factor`, `InputOTP` w `.auth-otp` z `aria-label` z `otp_label`, przełącznik trybu jako `TextLink as="button"`, stopka „Nie masz telefonu?”;
- ForgotPassword: ikona `Lock`;
- `PasskeyVerify`: wymagane propy etykiet bez angielskich wartości domyślnych, separator `.auth-or` zamiast ręcznego `Separator` (C5), przycisk `outline` z klasą `auth-submit`.

#### 3. Straż literałów i kontraktu

**Files**: `scripts/check-ui-literals.mjs`, `resources/js/ui-contract.test.ts`

**Intent**: Oczyszczone pliki nie wrócą do literałów (C16).

**Contract**:
- do `FILES` dochodzą wszystkie `pages/auth/*.vue`, `layouts/AuthLayout.vue` i `components/PasskeyVerify.vue`;
- w `ui-contract.test.ts` nowy `it.each` po plikach auth: brak `tabindex`, brak `text-success`, brak `href="/`, brak `'/dashboard'`;
- asercja `BrandMark` w `AuthCardLayout.vue` zostaje.

### Success Criteria:

#### Automated Verification:

- `npm run check:ui` przechodzi z nowymi plikami
- `npm test` przechodzi (w tym nowe asercje `ui-contract.test.ts`)
- `npm run build && npm run types:check` przechodzą
- `composer ci:check` przechodzi (w tym `tests/Feature/Auth`)
- `vendor/bin/sloppy ci` przechodzi
- Setup e2e loguje się: `npx playwright test tests/e2e/auth.setup.ts`

#### Manual Verification:

- Każdy z siedmiu ekranów przy 1280 px wygląda jak jego karta Page*, a przy 390 px panel jest paskiem ze znakiem
- Na Login Tab idzie: klucz, e-mail, „Nie pamiętasz hasła?”, hasło, „Pokaż hasło”, zapamiętaj, zaloguj
- Błędne hasło pokazuje polski komunikat pod polem e-mail
- W UI wejścia nie ma angielskiego tekstu (Login, VerifyEmail, ConfirmPassword, TwoFactorChallenge w obu trybach)

**Implementation Note**: Po fazie zatrzymaj się na ręczne potwierdzenie.

---

## Faza 5: Kitchen sink i bramka wizualna

### Overview

`/dev/ui/auth` pokazuje każdy ekran w każdym stanie jako pełną stronę. Zrzuty zamykają zmianę.

### Changes Required:

#### 1. Kontroler, trasa i test

**Files**: `app/Http/Controllers/Dev/AuthKitchenSinkController.php`, `routes/web.php`, `tests/Feature/AuthKitchenSinkTest.php` (nowe/zmienione)

**Intent**: Sink tylko w `local`, z pełną stroną dla `?screen=&state=`.

**Contract**:
- `GET dev/ui/auth` → `dev.ui.auth` w grupie `auth`+`verified` obok innych sinków (`routes/web.php:30-38`);
- `show(Request)`: `abort_unless(app()->isLocal(), 404)`. Bez `screen` zwraca `dev/AuthKitchenSink` z propem `screens` (lista ekranów i ich stanów). Z `screen` mapuje go na `auth/<Ekran>` i przekazuje propy z fiksur dla `state`:
  - `default`;
  - `error`: prop `errors` z polskimi komunikatami (`__('auth.failed')` dla Login);
  - `status`: dla Login, ForgotPassword i VerifyEmail;
  - `no-email`: ResetPassword z `email` = `null`;
  - `recovery`: TwoFactorChallenge;
- nieznany `screen` albo `state` → 404.

Test:
- gość → login;
- produkcja → 404;
- `local` bez `screen` → `dev/AuthKitchenSink` i `screens`;
- `local` `?screen=login&state=error` → `auth/Login` z `errors.email`;
- `?screen=reset-password&state=no-email` → `email` null;
- nieznany ekran → 404.

#### 2. Strona indeksu

**File**: `resources/js/pages/dev/AuthKitchenSink.vue`

**Intent**: Lista linków do każdej pary ekran × stan oraz opis stanów N/A.

**Contract**:
- `defineProps<{ screens: Array<{ key: string; label: string; states: string[] }> }>()`, linki przez Wayfinder `dev.ui.auth({ query: { screen, state } })`;
- notka o stanach N/A: `loading` to spinner podczas wysyłki, a `hover` i `focus-visible` widać na zrzutach z działającej aplikacji;
- plik na liście `check:ui`.

#### 3. Zrzuty i bramka

**Files**: `context/changes/auth-atrament-ui/screenshots/`; jednorazowy skrypt Playwright, nie commitowany (wzór z `design-system.md`, „Kitchen sink i zrzuty”)

**Intent**: Wizualny dowód zgodności z kartami.

**Contract**: Pełne zrzuty każdej pary ekran × stan przy 1280 i 390 px, w jasnym i ciemnym motywie, plus zrzut fokusa na Login.

### Success Criteria:

#### Automated Verification:

- `php artisan test --compact tests/Feature/AuthKitchenSinkTest.php` przechodzi
- `npm run build && npm run types:check` przechodzą po nowej trasie
- `npm run check:ui` przechodzi z `dev/AuthKitchenSink.vue`
- `composer ci:check` i `vendor/bin/sloppy ci` przechodzą

#### Manual Verification:

- Zrzuty wszystkich par ekran × stan przy 1280 i 390 px w obu motywach są w `screenshots/` i odpowiadają kartom Page*
- Alert `ok` i tekst panelu są czytelne w ciemnym motywie
- Pola OTP mają 40×44 px i kreskę po trzecim znaku

**Implementation Note**: Po fazie zatrzymaj się na ręczne potwierdzenie.

---

## Testing Strategy

### Unit Tests:

- `auth-year-progress.test.ts`: granice roku, rok przestępny, czas lokalny
- `ui-contract.test.ts`: pliki auth bez `tabindex`, `text-success`, `href="/` i `'/dashboard'`; `BrandMark` w układzie
- `UiTranslationKeysTest`: nowe klucze `auth.*`

### Integration Tests:

- `PasskeyLoginRedirectTest`: bez „intended” → `/dashboard`, z „intended” → ten adres (czerwony przed poprawką)
- `PolishAuthMessagesTest`: `auth.failed` i `passwords.sent` po polsku (czerwony przed poprawką)
- `PasswordResetTest`: `email` null i z wartością w `assertInertia`
- `AuthKitchenSinkTest`: dostęp, mapowanie ekranów i stanów, 404
- e2e `auth.setup.ts` z `exact: true`

### Manual Testing Steps:

1. Zaloguj się hasłem, potem kluczem dostępu; oba kończą się na `/dashboard`.
2. Wpisz złe hasło; komunikat jest polski i stoi pod polem e-mail.
3. Otwórz link resetu bez `?email=`; wpisz e-mail i ustaw hasło.
4. Przejdź Tabem przez Login i ConfirmPassword; kolejność zgodna z DOM, fokus widoczny.
5. Zwęż okno poniżej 1024 px; panel staje się paskiem, karta mieści się bez poziomego przewijania przy 390 px.

## Performance Considerations

Brak: blok roku liczy się raz po stronie klienta, bez żądań.

## Migration Notes

Bez migracji danych. `config/passkeys.php` jest nowy; na produkcji po wdrożeniu `php artisan config:cache` jak przy każdej zmianie konfiguracji.

## References

- Research: `context/changes/auth-atrament-ui/research.md`
- Karty Page* (podgląd, nie źródło wartości): https://claude.ai/artifact/9gJSdtfpFgUAa8oEMgR4HA
- Wzór poprzedniej zmiany Atramentu: `context/archive/2026-11-06-sidebar-atrament-ui/plan.md`
- Wzór sinka z odrzucaniem pól: `app/Http/Controllers/Dev/DialogsKitchenSinkController.php`
- Precedens `accent` = ok: `resources/js/components/ui/badge/index.ts:23`
- Odpowiedź logowania kluczem: `vendor/laravel/passkeys/src/Http/Responses/PasskeyLoginResponse.php:20-29`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Decyzje i dokumenty

#### Automated

- [x] 1.1 ADR-0056 istnieje, a numer jest kolejnym wolnym — d8fa003
- [x] 1.2 Indeks reguł zawiera `js-auth.md` po `php artisan boost:index-rules` — d8fa003

#### Manual

- [x] 1.3 ADR-0056 i `design-system.md` zgadzają się z tabelą decyzji w `plan-brief.md` — d8fa003

### Phase 2: Serwer, najpierw test (C11, C13, C15)

#### Automated

- [x] 2.1 Nowe testy nie przechodziły przed poprawką — 8c7e3ae
- [x] 2.2 Testy auth przechodzą na PostgreSQL — 8c7e3ae
- [x] 2.3 `npm run types:check` przechodzi po typie `email: string | null` — 8c7e3ae
- [x] 2.4 `vendor/bin/pint --dirty --format agent` bez zmian do zrobienia — 8c7e3ae

#### Manual

- [x] 2.5 Logowanie kluczem dostępu w przeglądarce kończy się na `/dashboard` — 8c7e3ae

### Phase 3: Tokeny, klasy i komponenty współdzielone

#### Automated

- [x] 3.1 `npm test` przechodzi, w tym `auth-year-progress.test.ts` — 84125e8
- [x] 3.2 `npm run check` przechodzi bez ostrzeżeń — 84125e8
- [x] 3.3 `npm run check:ui` przechodzi z `TextLink.vue` i `PasswordInput.vue` w `FILES` — 84125e8
- [x] 3.4 `UiTranslationKeysTest` i testy ustawień przechodzą — 84125e8

#### Manual

- [x] 3.5 W ustawieniach Tab dochodzi do „Pokaż hasło” z polską etykietą — 84125e8

### Phase 4: Układ i siedem widoków

#### Automated

- [x] 4.1 `npm run check:ui` przechodzi z nowymi plikami
- [x] 4.2 `npm test` przechodzi (w tym nowe asercje `ui-contract.test.ts`)
- [x] 4.3 `npm run build && npm run types:check` przechodzą
- [x] 4.4 `composer ci:check` przechodzi
- [x] 4.5 `vendor/bin/sloppy ci` przechodzi
- [x] 4.6 Setup e2e loguje się

#### Manual

- [x] 4.7 Siedem ekranów przy 1280 px jak karty Page*, przy 390 px panel jest paskiem
- [x] 4.8 Kolejność Tab na Login zgodna z DOM
- [x] 4.9 Błędne hasło pokazuje polski komunikat pod polem e-mail
- [x] 4.10 W UI wejścia nie ma angielskiego tekstu

### Phase 5: Kitchen sink i bramka wizualna

#### Automated

- [ ] 5.1 `AuthKitchenSinkTest` przechodzi
- [ ] 5.2 `npm run build && npm run types:check` przechodzą po nowej trasie
- [ ] 5.3 `npm run check:ui` przechodzi z `dev/AuthKitchenSink.vue`
- [ ] 5.4 `composer ci:check` i `vendor/bin/sloppy ci` przechodzą

#### Manual

- [ ] 5.5 Zrzuty wszystkich par ekran × stan przy 1280 i 390 px w obu motywach
- [ ] 5.6 Alert `ok` i tekst panelu czytelne w ciemnym motywie
- [ ] 5.7 Pola OTP 40×44 px z kreską po trzecim znaku
