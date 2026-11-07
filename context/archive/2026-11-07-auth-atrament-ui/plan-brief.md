# Ekrany wejścia według Atramentu — Plan Brief

> Full plan: `context/changes/auth-atrament-ui/plan.md`
> Research: `context/changes/auth-atrament-ui/research.md`

## What & Why

Ekrany wejścia (Login, Register, ForgotPassword, ResetPassword, VerifyEmail, ConfirmPassword, TwoFactorChallenge) to dziś centrowana karta shadcn z mieszanką języków. Nie pasują do kart Page* z Atramentu. Przenosimy je na układ Atramentu i przy okazji naprawiamy trzy błędy na tej samej drodze: logowanie kluczem dostępu ląduje na stronie powitalnej, błąd logowania jest po angielsku, a link resetu bez `?email=` jest ślepą uliczką.

## Starting Point

Tokeny (`--sidebar-*`, `--accent`, `--brand-text`, `--field-height` 34 px) już są w `app.css`. Brakuje klas układu, stylów tekstu, wariantu Alert „ok” i przycisku 34 px. Klucze `auth.*` dla trzech ekranów leżą nieużywane w `lang/pl/ui.php`, a `lang/pl/auth.php` i `passwords.php` nie istnieją. Ekranów auth nie pilnuje `check:ui` ani kitchen sink.

## Desired End State

Na `lg` i szerzej ciemny panel (znak, hasło, blok roku z bieżącej daty) stoi obok karty 400 px, a na telefonie panel jest paskiem. Potwierdzenia to Alert `ok` na `accent`, cały tekst jest po polsku, a kolejność Tab wynika z DOM. Klucz dostępu prowadzi na `/dashboard`, a reset bez e-maila ma edytowalne pole. `/dev/ui/auth?screen=&state=` pokazuje każdy ekran w każdym stanie jako pełną stronę.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Komunikaty serwera | `lang/pl/auth.php` + `passwords.php`; pełne `validation.php` odroczone | Błąd logowania to najczęstszy stan błędu auth | Research (Q1) |
| Register | Restyl według karty, rejestracja dalej wyłączona | Gotowy po ewentualnym włączeniu | Research (Q2) |
| Style tekstu | `display`, `heading`, `lead`, `card-title` w `app.css`; h1 karty = `page-title` | Jedno źródło wartości dla auth i przyszłego Welcome | Research (Q3) |
| Przekierowanie klucza | Naprawiane tu, test regresji przed widokami | Pierwsza akcja logowania nie może wracać na landing | Research (Q4) |
| Kolejność Tab | Markup karty, bez `tabindex` | Link „Nie pamiętasz hasła?” przed polem hasła | Research (Q5) |
| Blok roku w panelu | Liczony z daty po stronie klienta, czysta funkcja w `lib/` | Gość nie ma budżetu; bez propa | Research (Q6) |
| ConfirmPassword | Zostaje klucz dostępu z separatorem „lub” | Funkcja istnieje w kodzie; różnica od karty w ADR | Research (Q7) |
| Kolor potwierdzenia | Alert `ok` na `accent` (wyjątek w ADR-0056) | Precedens: Badge `ok` już jest na `accent` | Plan (D2) |
| Gdzie warianty | `ok` w `ui/alert`; przycisk 34 px jako `.auth-submit` w `app.css` | Alert ma 3 konsumentów; 34 px ma sens tylko przy polach karty | Plan (D3) |
| Kitchen sink | Pełna strona: kontroler renderuje prawdziwe `auth/*` z fiksurami | Zrzuty pokazują dokładnie to, co użytkownik | Plan |
| Reset bez `?email=` | Pole edytowalne, `readonly` tylko z e-mailem | Usuwa ślepą uliczkę w widoku, który i tak przerabiamy | Plan (C15) |
| Breakpoint panelu | `lg` zamiast 860 px z karty | 440 + 400 px z marginesami nie mieszczą się przy `md` | Plan |
| Nazwy klas | `.auth-*` bez prefiksu `ht-` | Konwencja repo | Plan |

## Scope

**In scope:**
- `AuthCardLayout`, siedem stron `pages/auth/*`, `PasskeyVerify`, `PasswordInput`, `TextLink`
- tokeny, style tekstu i klasy `.auth-*` w `app.css`; Alert `ok`
- klucze `ui.php`, `lang/pl/auth.php`, `passwords.php`; `config/passkeys.php`
- ADR-0056, `design-system.md`, reguły `css.md`, `js.md` i nowa `js-auth.md`
- `/dev/ui/auth` z testem, `check:ui`, asercje `ui-contract.test.ts`, `exact: true` w e2e

**Out of scope:**
- pełne `validation.php`, włączenie rejestracji, `Welcome.vue`
- zmiany w `ui/button`, `ui/input-otp`, `ui/card`
- sekcje 2FA i kluczy w ustawieniach

## Architecture / Approach

Najpierw dokumenty (ADR-0056 i reguły), potem testy, które nie przechodzą, i poprawki serwera, potem wartości w `app.css` i komponenty współdzielone, potem widoki, na końcu sink i zrzuty. Sink nie kopiuje stron: kontroler `/dev/ui/auth` mapuje `screen` na prawdziwy komponent `auth/*` i podaje propy z fiksur (w tym `errors`, `status`, `email: null`). Dzięki temu resolver układu, `setLayoutProps` i `Form` działają jak na produkcji.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Decyzje i dokumenty | ADR-0056, design-system.md, reguły | Wyjątek `accent` zbyt szeroki — ograniczony do potwierdzeń bez kwoty |
| 2. Serwer, najpierw test | Klucz → `/dashboard`, polskie `auth`/`passwords`, prop `email` nullable, e2e `exact` | Mock `VerifyPasskey` musi omijać WebAuthn bez osłabienia asercji |
| 3. Tokeny, klasy, komponenty | `app.css`, Alert `ok`, `TextLink`, `PasswordInput`, `authYearProgress` | Nadpisanie slotów OTP bez edycji `ui/*` niezweryfikowane |
| 4. Układ i widoki | Siedem ekranów według kart, `check:ui` | `PasswordInput` zmienia się też w ustawieniach |
| 5. Kitchen sink i bramka | `/dev/ui/auth`, zrzuty 1280/390 w obu motywach | Prop `errors` z kontrolera musi dotrzeć do `Form` |

**Prerequisites:** działający Sail (PostgreSQL do testów auth), `npm ci`, przeglądarka z obsługą WebAuthn do ręcznego sprawdzenia klucza.
**Estimated effort:** ~3–4 sesje w 5 fazach (fazy 3 i 4 największe).

## Open Risks & Assumptions

- Kontrast `accent-foreground` na `accent` w Alert `ok` sprawdzamy w obu motywach. Jeśli wynik jest poniżej 4,5:1, potrzebna jest korekta tokenu przez ADR.
- Zakładamy, że propy z `Inertia::render` nadpisują współdzielone `errors`. Test sinka asertuje to w Fazie 5.
- Restylowany `Register.vue` jest osiągalny tylko w sinku, dopóki rejestracja jest wyłączona.

## Success Criteria (Summary)

- Każdy ekran wejścia przy 1280 i 390 px odpowiada swojej karcie Page*, w obu motywach i w całości po polsku
- Logowanie kluczem dostępu kończy się na dashboardzie, a reset bez e-maila daje się dokończyć, co potwierdzają testy regresji
- `composer ci:check`, `npm run check:ui` i `npm test` są zielone z plikami auth na listach ochrony
