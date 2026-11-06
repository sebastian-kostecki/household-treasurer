# Import operacji, krok 1 w Atramencie — Plan Brief

> Full plan: `context/changes/import-start-atrament-ui/plan.md`
> Research: `context/changes/import-start-atrament-ui/research.md`

## What & Why

Krok 1 importu (`OperationsImport.vue`) to ostatni widok sprzed Atramentu w przepływie importu. Ma też błąd danych w formacie „Inny”:
- checkbox „Pierwszy wiersz zawiera nagłówki kolumn” jest martwy;
- formularz bez ruszania pola odpada na walidacji bez widocznego błędu;
- „bez nagłówka” nie da się wysłać, więc plik bez nagłówka traci pierwszą operację;
- wybór `.csv` przestawia „Inny” na mBank;
- kolumny się nie przeliczają;
- dwa brakujące klucze dają etykietę „Nadawca / odbiorca ()”.

Zmiana najpierw naprawia błąd test-first, potem przenosi widok na kartę `PageImportStart`.

## Starting Point

Strona używa `Card`, ręcznych bloków `div.grid` + `Label`, surowych radio i list `rounded-lg border p-4`. Nie ma `FormField`, stanu pustego, sinka ani strażnika. `ui-contract.test.ts` trzyma ją jako ostatni wyjątek z issue #186. Serwer (walidacja, parser, propsy) działa poprawnie.

## Desired End State

Wiersz tytułu ma `page-title` i „Wróć do operacji” ze strzałką. Pod nim stoi panel 640 px z nagłówkiem h2 + `meta`, polami `FormField` i „Pokaż podgląd” w stopce.

Format „Inny” działa:
- nagłówek domyślnie zaznaczony i wysyłany jako `1`/`0`;
- kolumny przeliczają się po zmianie pliku, nagłówka i formatu;
- „Układ kwoty” to segmenty;
- błędy są widoczne pod polami.

Bez aktywnych kont strona pokazuje stan pusty z linkiem do Kont. `/dev/ui/import` pokazuje wszystkie stany.

## Key Decisions Made

| Decision                    | Choice                                                                                  | Why (1 sentence)                                                                                    | Source   |
| --------------------------- | --------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------- | -------- |
| Zgadywanie formatu          | Rozszerzenie ustawia format tylko, gdy użytkownik nie wybrał go ręcznie                 | Skrót dla mBank/BNP zostaje, a ręczny wybór „Inny” nie jest nadpisywany.                            | Plan     |
| Test przeglądarkowy C2      | Spec Playwright: „Inny” + CSV bez nagłówka → pełny podgląd                              | Błąd leży między DOM a stanem Vue, więc Vitest i testy HTTP go nie widzą.                           | Plan     |
| Strażnik literałów          | `check:ui` + kopia `ImportStartHardcodedUiTest`; helper F6 osobno                       | Ten sam wzorzec co Konta, bez dotykania strażników Dashboardu i Planu.                              | Plan     |
| Test tłumaczeń              | Pest dla całego repo: `t.<grupa>.<klucz>` vs `lang/pl/ui.php`                           | Zatrzymuje każdy przyszły brakujący klucz, nie tylko te dwa.                                        | Plan     |
| Wysyłka `has_header`        | Jawny ukryty input `hasHeader ? 1 : 0`, `Checkbox` z `v-model` bez `name`               | Serwer dostaje to, co widać; usuwa rozjazd DOM i stanu Vue.                                          | Research |
| Logika kolumn               | `lib/import-start.ts` z Vitest, bez sztuczki `null → file`                              | Logika była nietestowana w stronie, a przeładowanie przez `null` nic nie robiło.                    | Research |
| Panel formularza strony     | Token `--form-panel-width` 640 + klasy `.form-panel*` w ADR-0048                        | Brak przepisu; `ListSurface` nie ma stopki z ramką, `max-w-*` nie daje 640.                         | Research |
| „Wróć do operacji”          | `outline` z `ArrowLeft`                                                                 | Spójnie z krokiem 2; `ghost` jest zarezerwowany dla „Anuluj” (ADR-0045).                            | Plan     |
| Brak aktywnych kont         | `IndexEmptyState` z linkiem do Kont na stronie importu; „Importuj” w Operacjach bez zmian | Wyjaśnia, czemu nie da się importować, bez wychodzenia poza jeden widok.                            | Research |
| Copy                        | „opcjonalnie”, „Brak kolumny”                                                           | Propozycja z audytu: mówi wprost, że kolumnę nadawcy można pominąć.                                 | Research |
| Stany zależne od pliku      | Komponenty `ImportRecognizedFields` i `ImportColumnMapping`, renderowane w sinku        | Sink nie ustawi `File`, a `FormField` z `useId` nie zdubluje `id`.                                  | Plan     |

## Scope

**In scope:**

- ADR-0048, `design-system.md`, `js-import.md`, `js.md`
- `lib/import-start.ts` z testem
- poprawka strony i wysyłki `has_header`
- 5 kluczy tłumaczeń
- `UiTranslationKeysTest`, spec E2E, Feature `has_header=0`, usunięcie wyjątku #186
- klasy `.form-panel*` i token w `app.css`
- przebudowa strony i dwa komponenty
- stan pusty
- `check:ui` i `ImportStartHardcodedUiTest`
- sink `/dev/ui/import` z testem
- zrzuty 390/1280 w obu motywach

**Out of scope:**

- krok 2 importu
- „Importuj” w `OperationsPageActions`
- walidacja, parser i propsy serwera
- helper F6
- powód przy wyłączonym przycisku
- zmiany `components/ui/*`, `AccountSelect`, `FormField`

## Architecture / Approach

Stan formularza żyje w stronie. Na serwer trafia tylko przez jawne ukryte inputy sterowane przez Vue (`has_header`, `SegmentedControl name="amount_layout"`). Czysta logika (etykiety, indeksy, format) siedzi w `lib/import-start.ts`.

Strona składa wiersz tytułu i `<Form class="form-panel">` z polami `FormField` oraz dwoma komponentami importu. Sink renderuje te same komponenty z fiksur. Poprawka błędu (faza 2) ląduje osobnym commitem przed restylingiem (faza 3).

## Phases at a Glance

| Phase                                  | What it delivers                                                                         | Key risk                                                                       |
| -------------------------------------- | ---------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------ |
| 1. Decyzje i dokumenty                 | ADR-0048, design system i reguły                                                         | Sprzeczność z ADR-0040/0045, jeśli przepisy nie trafią we wszystkie miejsca    |
| 2. Poprawka danych test-first (C1, C2) | 4 czerwone testy, Feature `has_header=0`, poprawka strony, zamknięte #186                | Wyścig asynchronicznego `file.text()` przy szybkiej zmianie pliku              |
| 3. Widok w Atramencie (C3–C5)          | Panel 640, `FormField`, segmenty, komponenty, stan pusty, strażnicy                      | `Input type="file"` w `FormField` nie ma jeszcze przykładu w repo              |
| 4. Sink, zrzuty i bramka               | `/dev/ui/import`, zrzuty, `ci:check` na PostgreSQL, Sloppy, pełny suite E2E              | Środowisko E2E (stos Sail, baza `household_treasurer_e2e`) musi być gotowe     |

**Prerequisites:**
- worktree po `composer install`, `.env`, `key:generate`, `npm ci`, `npm run build`;
- sieć `household-treasurer_sail` z PostgreSQL;
- dla E2E zatrzymane `composer run dev` i użytkownik z `.env` (`test-stack.md`).

**Estimated effort:** ~2–3 sesje w 4 fazach (faza 1 krótka, główna praca w fazach 2–3).

## Open Risks & Assumptions

- Zakładam, że test tłumaczeń jest zielony po dopisaniu dwóch kluczy, bo jednorazowy skan z researchu był dolnym ograniczeniem. Jeśli znajdzie więcej braków, dopisujemy je w tej zmianie, bez listy wyjątków.
- Spec E2E zakłada, że konto użytkownika e2e z `demo:seed-household` jest pierwsze na liście i ma datę otwarcia przed datami w pliku.
- `accounts-design-system-page` (równoległa zmiana) może zmienić `AccountMarkCompact`, czyli kafelek w polu „Konto”. Zrzuty z fazy 4 odzwierciedlają stan z chwili zrobienia.
- Czwarta kopia strażnika zwiększa dług F6.

## Success Criteria (Summary)

- Plik CSV bez nagłówka zaimportowany jako „Inny” daje podgląd ze wszystkimi operacjami, a formularz nigdy nie odpada po cichu.
- Krok 1 wygląda jak karta `PageImportStart` i jak reszta aplikacji w Atramencie, w obu motywach i na telefonie.
- `ui-contract` bez wyjątków (#186 zamknięte); `composer ci:check`, Sloppy, `check:ui`, nowi strażnicy i suite E2E są zielone.
