---
date: 2026-11-07T13:50:33+01:00
researcher: Claude (Sonnet 5.5)
git_commit: e05239f24e08038932af36de07295d4fb2f9be37
branch: development
repository: household-treasurer
topic: "Ekrany wejścia (AuthCardLayout, pages/auth/*, PasskeyVerify) względem kart Page* z design systemu Atrament"
tags: [research, ui, auth, atrament, design-system, i18n]
status: complete
last_updated: 2026-11-07
last_updated_by: Claude (Sonnet 5.5)
last_updated_note: "Dopisano decyzje użytkownika Q1-Q7"
---

# Research: ekrany wejścia względem kart Page* z Atramentu

**Data**: 2026-11-07 · **Commit**: `e05239f` · **Branch**: `development` (drzewo czyste poza nową zmianą `context/changes/auth-atrament-ui/`)

Zakres zbadany statycznie: kod i vendor Fortify/Passkeys oraz artefakt `9gJSdtfpFgUAa8oEMgR4HA` (wersja `1794036207-dcca`, pliki `project/components/Page{Login,Register,ForgotPassword,ResetPassword,VerifyEmail,ConfirmPassword,TwoFactorChallenge}`, `bundle.css`, `tokens.json`). Aplikacji nie uruchamiałem: zachowanie wejść bezpośrednich wynika z odczytu kodu, nie z przeglądarki (zob. Open Questions).

## Research Question

Które elementy ekranów wejścia odbiegają od kart Page* i od kontraktu Atramentu (tokeny, współdzielone komponenty, architektura wejścia), co z klas `ht-*` (`ht-auth` z ciemnym panelem, `ht-btn--block` 34 px, `ht-link`, `ht-alert--ok`, style `display`/`heading`/`lead`/`card-title`) ma odpowiednik w repo, i jakie decyzje trzeba zapisać w ADR-0056 przed planem.

## Summary

- Karty w artefakcie to **propozycja** („Stan: Propozycja”, `PageLogin/README.md`). Opisują jeden układ `ht-auth` dla wszystkich siedmiu ekranów: ciemny panel 440 px po lewej (tokeny `sidebar-*`), po prawej karta 400 px (`ht-panel ht-auth__card`), na telefonie (do 860 px) panel zwija się do paska.
- **Tokeny już są**, brakuje klas. Repo ma wszystkie `--sidebar-*` (`app.css:174-182`, dark `227-235`), `--accent`, `--brand-text`, `--ring`, `--field-height` 34 px (`app.css:161`) i publikacje `@theme inline` (`bg-sidebar`, `text-sidebar-strong`, `border-sidebar-border` itd., `app.css:84-92`). Nie ma żadnej klasy `ht-*` (0 trafień w `resources`), stylów `display`, `heading`, `lead`, `card-title`, wariantu Alert „ok” ani Button „block”.
- Repo nazywa klasy bez prefiksu (`.form-panel`, `.sidebar-year-segment`, `.command-palette` w `@layer components`; `@utility page-title`). Przejęcie nazw `ht-*` 1:1 złamałoby tę konwencję; nie znalazłem reguły, która zabrania prefiksu (to decyzja do ADR-0056).
- Najgroźniejsze rzeczy nie są wizualne: (a) `check:ui` nie obejmuje `pages/auth/*`, `PasskeyVerify`, `TextLink`, `PasswordInput`; (b) selektor e2e `getByRole('button', { name: 'Zaloguj się' })` (`tests/e2e/auth.setup.ts:27`) złapie po zmianie również przycisk „Zaloguj się kluczem dostępu” (strict mode); (c) rejestracja jest wyłączona, więc `Register.vue` jest nieosiągalny; (d) komunikaty błędów serwera są po angielsku, bo `lang/pl/auth.php` i `passwords.php` nie istnieją.
- Reguła `success` = tylko pieniądze i `accent` „nie do statusu” (`design-system.md:46-47`) stoi w sprzeczności z `ht-alert--ok` na `accent`. ADR-0056 musi to rozstrzygnąć wprost.

## Charges

Numeracja wiąże zarzuty z fazami planu (propozycja: **F1** środowisko/kontrakt → **F2** tokeny i klasy → **F3** widoki → **F4** stany i kitchen sink; **D** = odroczone).

### Brakujące tokeny / style

**C1. `text-success` jako status** — `pages/auth/Login.vue:34`, `ForgotPassword.vue:30`, `VerifyEmail.vue:27`: goły zielony tekst (`text-success mb-4 text-center text-sm font-medium`). Wpływ: komunikat „wysłano link” to jedyny sygnał po akcji, a `success` w Atramencie znaczy „dochód” (`design-system.md:47`, `.ai/rules/js.md`: „success = income”); użytkownik czyta zieleń jak kwotę, a w ciemnym motywie gołym tekstem łatwo go przeoczyć. Fix: wariant „ok” na `accent` (zob. D2). → F2/F3.

**C2. Literały palety w linkach** — `components/TextLink.vue:21` oraz `pages/auth/TwoFactorChallenge.vue:92` i `:124`: `decoration-neutral-300`, `dark:decoration-neutral-500` (trzy trafienia na plik wg skanu repo; `scripts/check-ui-literals.mjs` łapie prefiks `decoration`, ale plików nie ma na liście `FILES`). Wpływ: kolor podkreślenia omija tokeny, a link „Użyj kodu odzyskiwania” jest kopią klas `TextLink` w `<button>`, więc dwa wyglądy tego samego elementu. Dodatkowo `Register.vue:111` dokłada drugie `underline underline-offset-4` na `TextLink`. Fix: jeden `ht-link` (`brand-text`, 500, podkreślenie przy najechaniu; `Button variant="link"` ma już `text-brand-text underline-offset-4 hover:underline`, `ui/button/index.ts:~36`). → F2/F3.

**C3. Brak stylów tekstu `display`/`heading`/`lead`/`card-title`** — `app.css:252-300` ma `page-title`, `section-title`, `dialog-title`, `meta` itd., a pozostałe cztery nie istnieją; nie ma ich też w `.ai/rules/css.md` (lista zamknięta) ani w tabeli `design-system.md`. `Welcome.vue` używa surowego Tailwinda (`text-3xl sm:text-4xl lg:text-5xl`, `text-base sm:text-lg`) zamiast nich. Wpływ: hasło panelu (`heading` 24/30), zdanie `lead` 15/22 i tytuł karty nie mają źródła wartości; kolejny agent wpisze rozmiary w widoku. Uwaga: `tokens.json` opisuje `display` jako „tylko strona powitalna”, `heading` także „hasło w ciemnym panelu ekranów wejścia”, a `card-title` jako „tytuł karty funkcji i kroku na stronie powitalnej” — **karta auth używa `page-title`-podobnego h1 18/24** (`ht-auth__head h1`, `bundle.css:356`), nie `card-title`. Brief wymienia `card-title` wśród stylów auth; w artefakcie to styl Welcome (zob. Open Questions Q3). → F2.

**C4. Układ karty zamiast `ht-auth`** — `layouts/auth/AuthCardLayout.vue:24` (`bg-muted … items-center justify-center`, brak panelu), `:34-35` (`rounded-xl`, `px-10 pt-8`, `text-xl` w `CardTitle`), `:29` (`text-foreground font-semibold` przy nazwie). Wpływ: ekran wejścia nie odpowiada żadnej z siedmiu kart; dla `ht-auth` brakuje 440 px panelu, karty 400 px, ikony 36 px (`ht-auth__icon` na `accent`), stopki `ht-auth__foot`. Brak tokenów szerokości (`--aside-width` 300 px służy Dashboardowi, `app.css:164`, `:1376`, więc nie nadaje się). Test `ui-contract.test.ts:326-327` wymaga słowa `BrandMark` w `AuthCardLayout.vue`. → F2/F3.

**C5. Etykieta separatora na złym tle** — `components/PasskeyVerify.vue:62-67`: ręcznie sklejony separator (`relative` + `Separator` + `span.bg-background … uppercase`). Na karcie `bg-card` etykieta ma tło strony, a karta Atramentu każe `ht-or` („lub”, 12 px, bez wersalików, linie po bokach z `border`). Fix: nazwana klasa/komponent w `app.css`. → F2/F3.

**C6. Pola OTP o złym rozmiarze** — `ui/input-otp/InputOTPSlot.vue` (raport pomocniczy: `h-9 w-9`, `text-sm`, łączone ramki) vs. `ht-otp`: 40×44 px, JetBrains Mono 18 px, 500, odstęp 8 px, kreska pośrodku po trzech znakach (`bundle.css:330-333`). Wpływ: kod wygląda inaczej niż w karcie; `ui/*` nie wolno zmieniać pod jeden ekran (`AGENTS.md`, `.ai/rules/js.md:20`), więc trzeba nazwanej klasy nadpisującej. Czy nadpisanie klas slotu wygrywa z bazowymi — **niezweryfikowane**. → F2/F4.

### Brakujący współdzielony komponent / wariant

**C7. Button bez „block” i bez 34 px** — `ui/button/index.ts`: warianty `default, brand, destructive, outline, outline-destructive, secondary, ghost, ghost-inverse, link`; rozmiary `default h-control (32 px), sm, lg h-10, icon*`. Pełna szerokość robi się dziś `class="w-full"` (`Login.vue:95`, `ForgotPassword.vue:52`, `ConfirmPassword.vue:59`, `PasskeyVerify.vue:44`, `TwoFactorChallenge.vue:85,116`). `ht-btn--block` = `width:100%; height:34px` (`bundle.css:322`). Wpływ: przyciski auth 32 px przy polach 34 px; różne `mt-*` na każdym ekranie (`mt-2`, `mt-4`, `my-6`). Fix: klasa nazwana (np. `h-control-field w-full`) bez edycji `ui/*`, albo uzasadniony nowy rozmiar `ui/button` (zob. D3). → F2.

**C8. Alert bez wariantu „ok”** — `ui/alert/index.ts` ma `default, destructive, warning, note, danger`; `ht-alert--ok` = `background: accent`, ikona i tytuł `accent-foreground`, tytuł w `<b>` + treść (`bundle.css:323-324`, `PageVerifyEmail/preview.html`). Wpływ: wraz z C1 — nie ma komponentu na potwierdzenie bez kwoty. Fix: wariant `ok` w `alertVariants` (współdzielony: ForgotPassword, VerifyEmail, Login) lub klasy; rozstrzyga D2/D3. → F2.

**C9. Hasło: przycisk „Pokaż hasło” poza kolejnością tabulacji** — `components/PasswordInput.vue:40` `:tabindex="-1"`, a karta Login nakazuje kolejność „…hasło, pokaż hasło, zapamiętaj…” i `ht-iconbtn` w polu (`ht-field__action`). Wpływ: użytkownik klawiatury nie może pokazać hasła. Plus `aria-label` po angielsku (`PasswordInput.vue:39`: „Hide password”/„Show password”). → F3.

### Przypadkowa architektura

**C10. Angielskie teksty mimo gotowych kluczy** — klucze `auth.verify_email`, `auth.confirm_password`, `auth.two_factor` są w `lang/pl/ui.php:74-93`, a **żadna strona ich nie czyta** (sprawdzone `grep t.auth.` w `resources/js`; Login, Register, ForgotPassword, ResetPassword używają wszystkich swoich kluczy). Angielskie literały:
  - `VerifyEmail.vue:11-13,23,29-30,40,44` (layout przez `defineOptions`, który nie może wołać `useTranslations` → przejść na `setLayoutProps` jak inne strony);
  - `TwoFactorChallenge.vue:21-33,51,85,89,111,116,121` (tytuły/opisy/`buttonText` w `computed`, „Continue”, „or you can”, „Enter recovery code”);
  - `ConfirmPassword.vue:17-19,25,32-34,44,64` (brak `useTranslations`);
  - `PasskeyVerify.vue:52,53,68` (domyślne etykiety; `Login.vue` nie przekazuje żadnej, więc przycisk i separator na logowaniu są po angielsku);
  - `PasswordInput.vue:39`.
  Brakujące klucze (nie ma ich w `ui.php`): kafelek/tytuł/opis i alert „Wysłano nowy link” + „Sprawdź skrzynkę :email” (e-mail z shared `auth.user`; `VerifyEmail` nie dostaje propa `email`), stopka 2FA „Nie masz telefonu?”, placeholder kodu odzyskiwania, etykieta grupy OTP, „Zaloguj się kluczem dostępu”, „lub”, „Potwierdź kluczem dostępu”, „Pokaż hasło”/„Ukryj hasło”. Typ `auth: Record<string, Record<string,string>>` (`types/shell.ts:~40`) nie wymaga zmian, test `tests/Unit/UiTranslationKeysTest.php` łapie brakujące klucze, ale nie nieużywane ani angielskie literały. Wpływ: Polak na drodze do aplikacji widzi mieszankę języków. → F3.

**C11. Błędy serwera po angielsku** — `lang/pl/auth.php`, `lang/pl/passwords.php` nie istnieją; `lang/pl/validation.php` ma jeden klucz (`current_password`). Fortify używa `trans('auth.failed')` (`vendor/laravel/fortify/src/Actions/AttemptToAuthenticate.php:102`), `passwords.sent` itd.; przy `fallback_locale=en` (`config/app.php:83`) użytkownik widzi „These credentials do not match our records.” pod polem e-mail, a `status` po resecie hasła też jest angielski. Karta Login to sama przyznaje („Tekst błędu pochodzi z `lang/pl/auth.php`, którego jeszcze nie ma”). Wpływ: błąd logowania — najczęstszy stan błędu auth — jest po angielsku mimo polskiego UI. **Zakres do decyzji** (Q1). → D lub F3.

**C12. Rejestracja wyłączona, a strona i link żyją** — `config/fortify.php:163-174` nie zawiera `Features::registration()` (commit `eb27f34` „Disable public registration…”); `FortifyServiceProvider.php:70` rejestruje widok tylko, gdy funkcja włączona; `HandleInertiaRequests.php:47` daje `canRegister=false`. `Register.vue` jest więc nieosiągalny (trasa `/register` nie istnieje), a w `Login.vue:108-113` link `href="/register"` jest za `v-if="$page.props.canRegister"` (nigdy nie renderowany) i nie przez Wayfinder (w `resources/js/routes/index.ts` nie ma `register`). Karta PageRegister jest narysowana, ale nie ma jak jej zobaczyć poza kitchen sinkiem. Wpływ: praca nad nieosiągalnym ekranem i martwy link; ryzyko, że ktoś włączy rejestrację i dostanie ścieżkę bez Wayfindera. → decyzja Q2.

**C13. Passkey kieruje na `/`, nie na dashboard** — `PasskeyVerify.vue:33` ma fallback `'/dashboard'`, ale serwer zawsze zwraca `redirect` (`vendor/laravel/passkeys/src/Http/Responses/PasskeyLoginResponse.php:24`: `redirect()->intended(config('passkeys.redirect','/'))`), a aplikacja nie ma `config/passkeys.php` (domyślnie `'/'`, `vendor/laravel/passkeys/config/passkeys.php:115`). `fortify.home` to `/dashboard` (`config/fortify.php:76`). `/` to `Route::inertia('/','Welcome')` (`routes/web.php:25`) bez przekierowania. Wniosek (ścieżka z odczytu kodu): logowanie kluczem bez „intended” ląduje na stronie powitalnej, nie w aplikacji. Wpływ: pierwsza akcja na ekranie logowania wraca użytkownika na landing. Nie jest to sprawa wyglądu — **kandydat na osobną poprawkę** z testem (`/10x-tdd`). → D (osobna zmiana) lub F3 za zgodą (Q4).

**C14. Kolejność tabulacji** — `Login.vue:70` i `:110` oba `:tabindex="5"`, pozostałe 1-4; `Register.vue` ma `tabindex="5"` jako string i link logowania 6. Karta Login opisuje kolejność „klucz, e-mail, hasło, pokaż, zapamiętaj, zaloguj, »Nie pamiętasz hasła?«, rejestracja”, ale jej własny HTML (`PageLogin/preview.html`) stawia link „Nie pamiętasz hasła?” w `ht-field__head` **przed** polem hasła — opis i markup artefaktu się różnią. Wpływ: kolejność klawiatury nie jest jednoznaczna. Fix: usunąć jawne `tabindex`, kolejność wynika z DOM; rozstrzygnąć, który wariant jest wzorem (Q5). → F3.

**C15. Wejście bezpośrednie: reset hasła bez `?email=`** — `FortifyServiceProvider.php:56-60` przekazuje `email => $request->email` (null, gdy brak w URL); `ResetPassword.vue:26` `ref(props.email)`, pole `readonly`, więc użytkownik widzi puste, niedostępne do edycji pole i dostaje błąd walidacji „email wymagany” bez możliwości naprawy (wniosek z odczytu kodu, nie uruchomiony). Wpływ: link z maila bez parametru kończy się ślepą uliczką. Karta ResetPassword poprawia samo wygląd pola readonly (`muted`), nie ten przypadek. → F4 (stan „brak e-maila”) lub D.

**C16. Zabezpieczenia nie obejmują ekranów** — (a) `scripts/check-ui-literals.mjs`: `FILES` zawiera `layouts/auth/AuthCardLayout.vue` (linia 29), nie zawiera `pages/auth/*`, `PasskeyVerify.vue`, `TextLink.vue`, `PasswordInput.vue`, `layouts/AuthLayout.vue`; (b) brak kitchen sinka auth w `/dev/...` (istnieją Dashboard, Accounts, Import, Dialogs, Settings, Sidebar, TermDeposits, Budget, MonthPlan, Operations); (c) `assertInertia` mają tylko `PasswordConfirmationTest:22-23`, `TwoFactorChallengeTest:45-46`, `AppShellTest:78-95`; brak dla ForgotPassword, ResetPassword, VerifyEmail i propów Login (`canResetPassword`, `status`). → F1/F4.

**C17. Selektor e2e koliduje z nowym przyciskiem klucza** — `tests/e2e/auth.setup.ts:21,27`: `getByRole('textbox', { name: 'Hasło' })` i `getByRole('button', { name: 'Zaloguj się' })` (dopasowanie podciągu, bez `exact`). Karta wprowadza przycisk „Zaloguj się kluczem dostępu” (ten sam rola `button`, nazwa zawiera „Zaloguj się”), a Chromium wspiera WebAuthn (`isSupported`), więc strict mode da dwa dopasowania i setup e2e padnie (wniosek z reguł Playwright, **niewykonany**). Fix w tej samej fazie: `{ exact: true }`. → F3.

### Deferred (wstępnie)

Do `## Charges` dopiszemy w planie powód: C11 (jeśli Q1 = osobna zmiana), C13 (jeśli Q4 = osobna zmiana), C15, ewentualnie `Register.vue` (Q2).

## Mapowanie klas artefaktu na repo

| Artefakt | Co zawiera (`bundle.css`) | W repo dziś | Propozycja do ADR-0056 |
| --- | --- | --- | --- |
| `ht-auth`, `__aside`, `__pitch`, `__year`, `__months`, `__points`, `__legal`, `__main`, `__card`, `__head`, `__icon`, `__body`, `__foot`, `__after` (`bundle.css:335-359`) | grid `440px minmax(0,1fr)`; aside `sidebar-background`/`sidebar-strong`, karta 400 px | brak; tokeny `--sidebar-*` i `bg-sidebar` są | klasy nazwane w `@layer components` w `app.css`; szerokości jako tokeny wymiaru bez wariantu `.dark` (`--auth-aside-width` 440 px, `--auth-card-width` 400 px wg reguły `.ai/rules/css.md`); breakpoint 860 px do potwierdzenia |
| `ht-btn--block` | `width:100%; height:34px; justify-content:center` | `h-control-field` (34 px tylko w `.form-field`), `w-full` ręcznie | nazwana klasa; wariant `ui/button` tylko, jeśli reużywalny (D3) |
| `ht-link` | `brand-text`, 500, bez podkreślenia, hover z podkreśleniem 3 px | `Button variant="link"`, `TextLink` (palette) | jeden komponent `TextLink` na tokenach; usunąć literały (C2) |
| `ht-alert--ok` | tło `accent`, ikona/tytuł `accent-foreground` | brak | wariant `ok` w `ui/alert` lub klasa; wymaga wyjątku w regułach `accent`/`success` (D2) |
| `ht-or` | 12/16, `muted-foreground`, linie `border` | ręczny separator w `PasskeyVerify` | klasa w `app.css` |
| `ht-otp` | 40×44, Mono 18/500, kreska | `ui/input-otp` 36 px | klasa nadpisująca bez edycji `ui/*` (C6) |
| `display` 36/40, `heading` 24/30, `lead` 15/22, `card-title` 14/20 | `tokens.json` → grupa „Strona powitalna i wejście” | brak | `@utility` w `app.css` + lista w `css.md` i `design-system.md`; `Welcome.vue` przejmie je w osobnej zmianie (jeden widok na zmianę) |

## Detailed Findings

### Artefakt: co karty faktycznie wymagają

- Wspólny układ: aside z `BrandMark`-podobnym `ht-side__brand` (HT + nazwa), `h2` hasła (24/30, 600), `p` (15/22), blok „Rok budżetu 2026 · Postęp roku 84%” z 12 kafelkami miesięcy (`aria-hidden`), trzy punkty, stopka „© 2026 …” (`PageLogin/preview.html`). **Dane w bloku roku są zakodowane w makiecie**; gość nie ma budżetu, więc to dekoracja albo liczone z daty (Q6).
- Karta: ikona 36 px tylko na ForgotPassword (Lock), VerifyEmail (Mail), ConfirmPassword i TwoFactor (ShieldCheck); Login i Register bez ikony. Główna akcja `ht-btn--primary ht-btn--block`; na VerifyEmail ponowna wysyłka jest **zarysowana**, nie główna („główną akcją jest kliknięcie linku w poczcie”, `PageVerifyEmail/README.md`).
- Nowe pola: `ht-field__head` (etykieta + link w jednym wierszu), `ht-field__action` (przycisk w polu), `ht-check`, `ht-input--action` (padding 34 px).
- ResetPassword: e-mail `readonly` na `muted`/`muted-foreground`, podpowiedź „Co najmniej 12 znaków.”; ConfirmPassword: ShieldCheck + jedno pole + „Potwierdź”. Brak w kartach: wariantu z kluczem dostępu na ConfirmPassword, który w kodzie jest (`ConfirmPassword.vue:27-35`) — karta go nie pokazuje (Q7).

### Reguły repo, które ograniczają wykonanie

- `components/ui/*` nie edytujemy pod jeden ekran (`AGENTS.md`, `.ai/rules/js.md:20`).
- Nowy token/rozmiar: w `app.css`, nazwany, w `:root` i `.dark` (kolor) albo bez `.dark` (wymiar); kontrast tekstu 4,5:1; wpis w `design-system.md`; lista stylów w `css.md` jest zamknięta (`.ai/rules/css.md`).
- Nowy widok trafia do `check:ui` (`scripts/check-ui-literals.mjs`) i do kitchen sinka (`design-system.md` ~99-113; `.ai/rules/js.md:~50`).
- Zmiana reguły słownika/terminu _Avoid_ wymaga ADR przed kodem (`AGENTS.md`); tu dotyczy tylko reguł kolorów `accent`/`success` w `design-system.md`, nie `glossary.md`.
- Prop Inertia zmienia się po obu stronach w jednym commicie z `assertInertia` (`AGENTS.md`); dotyczy ewentualnego propa `email` dla VerifyEmail (rozwiązywalne bez propa przez `auth.user`).

### Kitchen sink dla auth

Wzorzec: kontroler `app/Http/Controllers/Dev/*KitchenSinkController.php` (`abort_unless(app()->isLocal(), 404); Inertia::render('dev/X')`), trasa w grupie `auth`+`verified` (`routes/web.php:30-38`), test z trzema przypadkami (gość → login, produkcja → 404, local → 200 + `component`), wg `SettingsKitchenSinkTest`. Strona `dev/AuthKitchenSink` dostaje w `app.ts:12-23` `AppLayout`, nie `AuthLayout` (rozstrzyga `name.startsWith('auth/')`), więc `ht-auth` na całą szerokość trafiłby do powłoki aplikacji z sidebarem. Opcje: `case name === 'dev/AuthKitchenSink': return null` w resolverze albo ramka o stałej wysokości (jak `SettingsKitchenSink`). Prawdziwe strony wołają `setLayoutProps`, które nie działa przy montowaniu strony jako komponentu potomnego, a ich `Form` wysłałby żądanie — sink musi przekazać `title`/`description` jawnie i nie wysyłać formularzy. Częściowo niezweryfikowane (agent nie sprawdził obsługi w `DialogsKitchenSink`).

### Wejścia bezpośrednie (z odczytu kodu i vendor)

- `GET /two-factor-challenge` bez oczekującego logowania → redirect na `login` (`TwoFactorAuthenticatedSessionController.php:43-45`, pokryte `TwoFactorChallengeTest:26`). Zalogowany trafia na `guest` → redirect do home.
- Weryfikacja e-maila to `GET /email/verify` (`verification.notice`), **nie** `/verify-email`; zweryfikowany użytkownik jest przekierowywany (`EmailVerificationPromptController.php:20-22`); gość → login (middleware `auth`). Po ponownej wysyłce `status = verification-link-sent` (literał, nie tłumaczony; mapowany w `VerifyEmail.vue`).
- `GET /user/confirm-password` renderuje stronę każdemu zalogowanemu, bez sprawdzania, czy potwierdzenie jest potrzebne.
- `GET /reset-password/{token}` bez `?email=` renderuje stronę z `email=null` (C15).
- Przycisk klucza nie pojawia się, gdy `isSupported` jest false (`PasskeyVerify.vue:48`), razem z separatorem — zgodnie z kartą.

## Code References

- `resources/js/layouts/auth/AuthCardLayout.vue:24-45` — centrowana karta `rounded-xl` na `bg-muted`; zawiera `BrandMark` (wymóg `ui-contract.test.ts:326`)
- `resources/js/layouts/AuthLayout.vue` — cienka otoczka przekazująca `title`, `description`
- `resources/js/app.ts:12-23` — resolver układu: `auth/*` → `AuthLayout`, `dev/*` → `AppLayout`
- `resources/js/pages/auth/Login.vue:34,70,96,110` — `text-success`, `tabindex=5` x2, `mt-4 w-full`, `href="/register"`
- `resources/js/pages/auth/VerifyEmail.vue:9-15,23-44` — `defineOptions` z angielskimi tekstami; `text-success`
- `resources/js/pages/auth/TwoFactorChallenge.vue:18-41,92,124` — angielskie `computed`, przycisk-link z literałami palety
- `resources/js/pages/auth/ConfirmPassword.vue:15-35,44,64` — angielskie teksty, `PasskeyVerify` z angielskimi propami
- `resources/js/components/PasskeyVerify.vue:33,44,50-53,62-68` — fallback `/dashboard`, angielskie domyślne etykiety, ręczny separator
- `resources/js/components/PasswordInput.vue:23-24,39-40` — `aria-label` po angielsku, `tabindex=-1`
- `resources/js/components/TextLink.vue:21` — literały `neutral`
- `resources/css/app.css:84-92,161,164,174-182,227-235,252-300` — publikacje sidebar, `--field-height`, `--aside-width`, tokeny sidebar, istniejące style tekstu
- `resources/js/components/ui/button/index.ts`, `ui/alert/index.ts`, `ui/input-otp/InputOTPSlot.vue` — warianty i rozmiary
- `scripts/check-ui-literals.mjs:9-76` — lista `FILES`
- `lang/pl/ui.php:36-95` — klucze `auth.*`; `lang/pl/` bez `auth.php`, `passwords.php`
- `app/Providers/FortifyServiceProvider.php:49-81` — widoki i propy; `config/fortify.php:76,134,163-174` — `home`, `views`, funkcje
- `tests/e2e/auth.setup.ts:21,27` — selektory logowania
- `vendor/laravel/passkeys/src/Http/Responses/PasskeyLoginResponse.php:24` — redirect `'/'`

## Architecture Insights

- Atrament jest wdrażany widok po widoku: tokeny w `app.css`, komponenty `ui/*` bez zmian, nazwane klasy w `@layer components`, kitchen sink, wpis w `check:ui`, ADR. Ta zmiana jest pierwszą dla układu bez sidebara; panel `ht-auth` czyta te same tokeny `sidebar-*`, więc nowych kolorów nie trzeba.
- Konwencja nazw repo to klasy bez prefiksu; artefakt ma `ht-*`. ADR-0056 powinien zapisać mapowanie zamiast kopiować nazwy.
- Pozostałe `AuthSimpleLayout`/`AuthSplitLayout` zostały usunięte (ADR-0053:15), więc `AuthCardLayout` jest jedynym układem auth — zmiana jest w jednym pliku plus strony.

## Historical Context (from prior changes)

- `context/foundation/adr/0053-sidebar-follows-sidebar-component.md:15` — BrandMark: „HT” w 26 px kafelku, zawsze z nazwą; stoi „nad kartą logowania” i w `Welcome`; usunięto nieużywane `AuthSimpleLayout`/`AuthSplitLayout` (**supported**; liczbę usuniętych układów podaje ADR, nie ponownie liczyłem).
- `context/archive/2026-11-06-sidebar-atrament-ui/research.md:111` — „HT” na ekranach auth i w Welcome pozostawione do decyzji; `:89` — konsumenci `AppLogoIcon` w układach auth (**partial**: dziś `AuthCardLayout` używa `BrandMark`, nie `AppLogoIcon`).
- `context/archive/2026-11-05-budget-atrament-ui/research.md:135` — układ auth wymieniony wśród konsumentów `Card` (**supported** historycznie; dziś `AuthCardLayout.vue:5-11` nadal importuje `Card`).
- Commit `eb27f34` — wyłączenie publicznej rejestracji i `user:create` (**supported**, zgodne z `config/fortify.php`).
- ADR-0055 to ostatni zajęty numer w `context/foundation/adr`; ADR-0056 jest wolny.

## Related Research

- `context/archive/2026-11-06-sidebar-atrament-ui/research.md` — wzorzec audytu dwukierunkowego i mapowania artefaktu na repo.
- `context/archive/2026-11-05-budget-atrament-ui/research.md` — Card/ListSurface i promienie.

## Open Questions

Pytania do użytkownika przed `/10x-plan` (pytać po polsku, narzędziem pytań; nie rozstrzygałem ich):

- **Q1 (C11).** Czy polskie komunikaty serwera (`lang/pl/auth.php`, `passwords.php`, rozszerzone `validation.php`) wchodzą do tej zmiany, czy osobna zmiana? Karta Login zakłada, że istnieją.
- **Q2 (C12).** `Register.vue` jest nieosiągalny (rejestracja wyłączona). Restylujemy go zgodnie z kartą (żeby był gotowy po włączeniu), pomijamy, czy usuwamy martwy kod i link w `Login.vue`? Wpływa na zakres i na test `RegistrationTest` (sam się pomija).
- **Q3 (C3).** `card-title` w `tokens.json` opisano jako styl strony powitalnej, a karty auth używają h1 18/24 (jak `page-title`). Czy ADR-0056 ma dodać `card-title` tylko jako zarejestrowany styl dla `Welcome` (poza zakresem tej zmiany), czy użyć go na kartach auth zamiast `page-title`?
- **Q4 (C13).** Przekierowanie klucza dostępu na `/` zamiast na dashboard: poprawiamy tu (np. `config/passkeys.php` + test), czy osobna zmiana przez `/10x-frame` → `/10x-tdd`?
- **Q5 (C14).** Wzór kolejności tabulacji: opis w README karty (link „Nie pamiętasz hasła?” po przycisku) czy markup karty (link w nagłówku pola, przed polem)? Rekomendacja: DOM = markup, bez jawnych `tabindex`.
- **Q6.** Blok „Rok budżetu 2026 / Postęp roku 84%” w panelu: statyczna dekoracja (`aria-hidden`), czy liczona z daty (kafelki miesięcy)? Dla gościa nie ma budżetu; propozycja: liczone po stronie klienta z bieżącej daty, bez propa, albo statyczne bez liczb.
- **Q7.** ConfirmPassword: karta nie pokazuje wariantu z kluczem dostępu, który kod ma. Zostawiamy go (z separatorem `ht-or`), czy karta ma być uzupełniona?
- **D2/D3 do ADR-0056.** (D2) Wyjątek w regułach `accent` („nie do statusu”) i `success` dla potwierdzeń bez kwot: `ht-alert--ok` na `accent`. (D3) Czy `ok` w `ui/alert` i ewentualny rozmiar 34 px/„block” w `ui/button` są reużywalne (poza auth), czy zostają klasami nazwanymi w `app.css`?
- **Luki w dowodach.** Aplikacja nie była uruchomiona: C15, C17 i zachowanie `/` dla zalogowanego po logowaniu kluczem wynikają z odczytu kodu; zweryfikować w fazie F1 (test lub przeglądarka) przed poleganiem. Nadpisanie klas `InputOTPSlot` (C6) niezweryfikowane. Breakpoint 860 px panelu i sposób wyświetlenia `ht-auth` w kitchen sinku (`return null` w resolverze vs ramka) do rozstrzygnięcia w planie.

## Decyzje użytkownika (2026-11-07)

Odpowiedzi na Q1-Q7 z narzędzia pytań; zastępują „Open Questions” powyżej (poza lukami w dowodach).

| # | Decyzja | Skutek dla planu |
| --- | --- | --- |
| Q1 | `lang/pl/auth.php` i `lang/pl/passwords.php` wchodzą do tej zmiany; pełne `validation.php` poza zakresem | C11 w zakresie częściowo: błąd logowania (`auth.failed`, `auth.throttle`) i statusy resetu hasła (`passwords.*`) po polsku; test, że `trans('auth.failed')` w `pl` jest polskie. Reszta `validation.php` → zarzut **deferred** (osobna zmiana) |
| Q2 | `Register.vue` restylowany według karty PageRegister | C12: strona w zakresie i w kitchen sinku; link w `Login.vue` zostaje za `canRegister`, bez martwego `href="/register"` jeśli Wayfinder nie ma trasy (rejestracja wyłączona) |
| Q3 | Wszystkie cztery style (`display`, `heading`, `lead`, `card-title`) w ADR-0056 i `app.css`; h1 karty = `page-title` | `heading`+`lead` czyta panel; `display` i `card-title` bez konsumenta do czasu zmiany Welcome; wpis na liście w `css.md` i w tabeli `design-system.md` |
| Q4 | **Naprawiamy w tej zmianie** (wybór inny niż rekomendacja: osobna zmiana) | C13 w zakresie: najpierw nieprzechodzący test (logowanie kluczem bez „intended” kieruje na `/`), potem `config/passkeys.php` z `redirect` na dashboard; fallback `/dashboard` w `PasskeyVerify.vue` albo usunąć, albo oprzeć na `dashboard()` z Wayfindera. Faza z testem regresji idzie przed fazą widoków |
| Q5 | Kolejność tabulacji = markup karty, bez jawnych `tabindex` | C14: usuwamy wszystkie `:tabindex`; kolejność z DOM (klucz, e-mail, „Nie pamiętasz hasła?”, hasło, „Pokaż”, zapamiętaj, zaloguj); przycisk „Pokaż hasło” traci `tabindex=-1` (C9) |
| Q6 | Blok roku w panelu liczony z daty po stronie klienta | czysta funkcja w `resources/js/lib/` z testem `*.test.ts`; bez propa Inertia; jednostki i etykiety z `ui.php` |
| Q7 | `ConfirmPassword` zachowuje klucz dostępu z separatorem `ht-or` | stan w kitchen sinku; różnica względem karty PageConfirmPassword do wpisania w ADR-0056 |

Nadal do rozstrzygnięcia w planie (nie pytałem): D2 (wyjątek `accent`/`success` dla potwierdzeń w ADR-0056), D3 (wariant `ok` w `ui/alert` i „block”/34 px w `ui/button` jako współdzielone vs klasy nazwane w `app.css`), sposób renderowania `ht-auth` w kitchen sinku, breakpoint 860 px, nazewnictwo (rekomendacja: bez prefiksu `ht-`), C15 (reset hasła bez `?email=`) i C17 (`exact: true` w `auth.setup.ts`, w zakresie).
