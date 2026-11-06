# Ustawienia według strony design systemu (ADR-0049) — Plan Brief

> Full plan: `context/changes/settings-atrament-ui/plan.md`
> Research: `context/changes/settings-atrament-ui/research.md`

## What & Why

Ustawienia (Profil, Bezpieczeństwo, Wygląd) to ostatnie strony, które wyglądają jak starter sprzed Atramentu:

- sekcje leżą na tle bez paneli;
- strefa usuwania konta jest złożona ręcznie;
- przełącznik motywu ma klasy palety;
- część tekstów, także toasty serwera, jest po angielsku.

Wyrównujemy je do karty `PageSettingsProfile`, żeby wyglądały jak reszta aplikacji, były po polsku i miały bramkę, która nie pozwoli im się rozjechać.

## Starting Point

- **Layout:** `layouts/settings/Layout.vue` ma `Heading` zamiast `h1` i podnawigację z przycisków `ghost` bez `aria-current`.
- **Profil i hasło:** pola to ręczne `Label + Input` na tle.
- **Usuwanie konta:** `DeleteUser` to ręczny `Dialog` + `Form`.
- **Wygląd:** `AppearanceTabs` ma `bg-neutral-*` i etykiety „Light/Dark/System”.
- **Bramka:** żaden plik ustawień nie jest pod strażnikiem i nie ma sinka.
- **Worktree:** jest 6 commitów za `development` (ADR-0047).

## Desired End State

- **Ustawienia:**
  - tytuł „Ustawienia” z opisem;
  - podnawigacja z aktywną pozycją jak karta (na telefonie poziomy rząd);
  - każda sekcja w panelu `SettingsPanel` z polami `FormField` i „Zapisz” w stopce.
- **„Usuń konto”:** czerwony alert na `destructive-soft`, przycisk zarysowany w czerwieni i potwierdzenie przez `ConfirmActionDialog` z polem hasła.
- **Motyw:** przełącza się segmentami „Jasny / Ciemny / Systemowy”.
- **Toasty i błędy hasła:** po polsku.
- **Stany:** `/dev/ui/settings` pokazuje wszystkie stany, a strażnik i `check:ui` pilnują plików.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Numer ADR | 0049 | 0046 (Lokaty), 0047 (`development`) i 0048 (Import) są zajęte | Research / Plan |
| Zakres | Profil, `DeleteUser`, layout, formularz hasła, Wygląd; 2FA i passkeys odroczone | Wszystkie trzy zakładki dostają layout, a kreator 2FA (ponad 30 ciągów, własne dialogi) to osobna zmiana | Plan |
| Panel sekcji | Nowy `SettingsPanel` (domenowy, nazwane klasy w `app.css`) | Nie ruszamy `ListSurface`, z którego korzysta 6 widoków | Plan |
| Strefa usuwania | Nowe warianty `Alert` `danger` i `Button` `outline-destructive` | Wierne karcie; to role systemowe; kontrast 5,03 i 6,82:1 | Plan |
| „Anuluj” w `ConfirmActionDialog` | `ghost` dla wszystkich potwierdzeń | Domyka ADR-0045 dla potwierdzeń bez wyjątku dla `DeleteUser` | Plan |
| Fokus po złym haśle | Nowe zdarzenie `error` w `ConfirmActionDialog` | Bez niego przejście na komponent wspólny gubi dzisiejszy fokus | Plan |
| Tłumaczenia serwera | `lang/pl.json` (2 toasty) + `lang/pl/validation.php` (tylko `current_password`) | Przepływ Profilu i hasła jest w pełni po polsku; reszta walidacji to osobna zmiana i18n | Plan |
| Telefon | Poniżej `lg` podnawigacja w jednym rzędzie nad panelami | Karta nie opisuje telefonu; trzy krótkie etykiety mieszczą się przy 390 px | Plan |
| Motyw | `SegmentedControl` `neutral`, bez ikon, `aria-label` „Motyw” | `js.md`: ustawienie zapisywane od razu; karta nie ma ikon | Research |

## Scope

**In scope:**

- ADR-0049, `design-system.md`, `.ai/rules/js.md`
- warianty `Alert` i `Button`, poprawki `ConfirmActionDialog`, `SettingsPanel`, klasy i tokeny layoutu
- `Layout.vue`, `Profile.vue`, `DeleteUser.vue`, `Security.vue` (formularz hasła), `Appearance.vue`, `AppearanceTabs.vue`
- `lang/pl/ui.php`, `lang/pl.json`, `lang/pl/validation.php` z testami HTTP
- `/dev/ui/settings`, `SettingsHardcodedUiTest`, `check:ui`, zrzuty

**Out of scope:**

- 2FA i passkeys (teksty, `Heading`, dialogi)
- `PasswordInput` i `PasskeyVerify` (współdzielone z logowaniem)
- pełny `validation.php`
- „Anuluj” w dialogach formularzy
- `ListSurface`
- naprawa przeskoku zaznaczenia motywu (tylko sprawdzenie i follow-up)

## Architecture / Approach

Decyzje idą przed kodem. Elementy wspólne (`components/ui`, `ConfirmActionDialog`, `SettingsPanel`, klasy w `app.css`) powstają w osobnej fazie, żeby ich wpływ na resztę aplikacji dało się sprawdzić w `/dev/ui/dialogs`.

Widoki tylko składają te elementy:

- `<Form>` owija cały `SettingsPanel`, więc „Zapisz” ze stopki wysyła formularz;
- `FormField` przekazuje `id`, `aria-describedby` i `aria-invalid` do `Input` i `PasswordInput`.

Payload Inertii się nie zmienia.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Decyzje i dokumenty | Scalony `development`, ADR-0049, `design-system.md`, `js.md` | Konflikty w dokumentach z równoległymi zmianami (Lokaty, Import) |
| 2. Elementy wspólne | `Alert` `danger`, `Button` `outline-destructive`, `ConfirmActionDialog` (`ghost`, `error`), `SettingsPanel`, klasy layoutu | „Anuluj” zmienia wygląd wszystkich potwierdzeń w aplikacji |
| 3. Layout i Profil | Nowy layout, panele Profilu, `DeleteUser` na `ConfirmActionDialog`, polskie toasty i błąd hasła | Bezpieczeństwo i Wygląd muszą się renderować w nowym layoucie przed swoją fazą |
| 4. Bezpieczeństwo i Wygląd | Panel hasła po polsku, motyw na `SegmentedControl` | 2FA i passkeys zostają na tle obok panelu (przejściowo) |
| 5. Stany i bramka | `/dev/ui/settings`, strażnik, `check:ui`, zrzuty | Sink nie pokaże aktywnej pozycji podnawigacji (adres `/dev/...`); pokazują ją zrzuty tras |

**Prerequisites:** worktree `.claude/worktrees/settings-atrament-ui` z branchem `worktree-settings-atrament-ui`; Sail z PostgreSQL (`household-treasurer_sail`) do testów i podglądu.

**Estimated effort:** około 3–4 sesji w 5 fazach. Fazy 1–2 są małe, 3 i 5 największe.

## Open Risks & Assumptions

- **Kolejność scalania:** równoległe zmiany Lokat (ADR-0046) i Importu (ADR-0048) też edytują listę ADR w `design-system.md:3` i `js.md`, więc przy scalaniu do `development` spodziewać się konfliktów w tych liniach.
- **Fallback tłumaczeń:** zakładamy, że `lang/pl/validation.php` z jednym kluczem nie psuje innych komunikatów, bo brakujące klucze spadają na angielski (`fallback_locale=en`). Testy Feature innych formularzy, które sprawdzają treść błędów, wyjdą w `composer ci:check`.
- **Przeskok motywu:** zaznaczenie może przeskakiwać przy pierwszym renderze (`useAppearance` czyta `localStorage` w `onMounted`). Plan tylko to sprawdza i zapisuje w follow-upie.

## Success Criteria (Summary)

- Ustawienia przy 1280 i 390 px w obu motywach odpowiadają karcie `PageSettingsProfile`, z odejściem na telefonie zapisanym w ADR-0049.
- Profil, hasło, Wygląd i usuwanie konta są w całości po polsku, łącznie z toastami i błędem złego hasła.
- `composer ci:check`, `sloppy ci`, `check:ui` i `SettingsHardcodedUiTest` przechodzą, a `/dev/ui/settings` pokazuje każdy stan z macierzy.
