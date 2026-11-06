# Podgląd importu, krok 2 w Atramencie — Plan Brief

> Full plan: `context/changes/import-preview-atrament-ui/plan.md`
> Research: `context/changes/import-preview-atrament-ui/research.md`

## What & Why

Krok 2 importu (`OperationsImportPreview.vue` z `ImportPreviewRow.vue` i `ImportPreviewSubmitBar.vue`) przechodzi na kartę `PageImportPreview` design systemu „C · Atrament i morski”, tak jak krok 1 przeszedł w ADR-0048. Przy okazji trzeba naprawić błąd widoczny dla użytkownika: powody odrzucenia wiersza i powód kandydata do pary przychodzą z serwera po angielsku, z datą ISO, a żaden test tego nie łapie.

## Starting Point

Research potwierdził sześć zarzutów:
- Angielskie powody z `CsvImportPreviewValidator` (7 zdań) i `ImportPairCandidateDetector` (2 zdania). Repo nie ma `lang/pl.json`, a asercje testów są tautologiczne.
- Tytuł `text-lg`, panel `Card`/`CardTitle text-base`.
- Odznaki `secondary` i pełne `destructive`, kwota przez `amountToneClass`.
- Ręczne potwierdzenie duplikatów bez powiązanego błędu, podpisy `text-xs`.
- Brak strażnika typografii i sink, który nie montuje strony.
- Tytuł „Pokaż podgląd”, a „Wróć do operacji” prowadzi do kroku 1.

## Desired End State

Użytkownik czyta polskie powody z datą `dd.mm.rrrr`. Strona ma wiersz tytułu i nawigację jak krok 1, panel podsumowania w przepisie Atramentu, miękkie pigułki i kwotę z „zł”, której znak i kolor wynikają z wybranego typu, jak na liście Operacji. Potwierdzenie duplikatów jest dostępne dla czytnika ekranu. Strażnik `ImportPreviewHardcodedUiTest` i sink `/dev/import-preview` montujący prawdziwą stronę pilnują, żeby style sprzed Atramentu nie wróciły.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Spolszczenie powodów (P1) | Serwer wysyła kod odrzucenia (enum `ImportRowInvalidReason`), kandydat do pary traci `reason`, UI składa zdanie z `lang/pl/ui.php` + `formatDate`; nieznany kod pokazuje się bez zmian | Konwencja repo i ADR-0043: polski tekst tylko po stronie klienta, a stara sesja nie traci dowodu | Plan (rekomendacja z Research) |
| Kolor i znak kwoty (P2) | Według wybranego typu: dochód i zwrot „+” `success`, wydatek „−” `foreground`; transfer i ruch kapitałowy ze znakiem z pliku, bez koloru | Podgląd pokazuje to, co serwer zapisze, i wygląda jak ta sama operacja na liście Operacji | Plan |
| Jednostka kwoty (P3) | `formatMoney` z „zł”, styl `amount`; trzeci wyjątek od ADR-0037 | Zgodne z kartą i listą Operacji, na którą trafią te wiersze | Plan |
| Tytuł i wyjście (P5) | h1/`<Head>` „Import operacji” + meta „Krok 2”; „Wróć do operacji” → Operacje; okruszek „Podgląd importu”; stan pusty „Wybierz inny plik” → krok 1 | Etykieta mówi prawdę, a tytuł jest nazwą strony, nie poleceniem | Plan |
| Checkbox wiersza (P4) | Nazwana klasa `.import-preview-select-check` 24 px, uzasadniona WCAG 2.5.8 | Zachowuje cel dotyku na telefonie i zamienia jednorazowe nadpisanie w udokumentowaną decyzję | Plan |
| Strażnik (P6) | Piąta kopia `*HardcodedUiTest` z zakazami `text-xs/sm`, `font-semibold`, `Badge secondary/destructive`, `amountToneClass`, `CardTitle`; F6 otwarty | Zakres zostaje przy jednym widoku | Plan |
| Pigułki | „Poprawne” `neutral`, „Niepoprawne” `destructive-soft`, duplikat i para `outline` | Mapowanie na wzorzec; nowe znaczenie `destructive-soft` zapisane w ADR-0052 | Research |
| Panel podsumowania | `ListSurface` ze slotem `#header` (`h2.section-title` + `p.meta`) | `.form-panel` jest dla formularza, prop `title` renderuje `text-base` | Research |

## Scope

**In scope:**
- ADR-0052 i aktualizacja `design-system.md`, `js.md`, `js-import.md`
- Kody odrzucenia i kandydat do pary bez zdania, polskie klucze, czyste funkcje tekstu
- Restyl strony, wiersza i paska. Nowy komponent `ImportPreviewDuplicateAcknowledgement` i funkcja `importRowAmount`
- Strażnik, kontroler sinka z testami, sink montujący stronę, fikstury bez hex, `check:ui`, zrzuty

**Out of scope:**
- Pozostałe angielskie komunikaty serwera (błędy `rows.*`, toast, walidacja Laravela; D1)
- Wspólna klasa wiersza (D2), wspólny helper strażników (F6), dług `border-border/60` w `.operations-row`
- Kontrakt `store`, dane w bazie, nowe tokeny, zmiany `components/ui/*`, nowy test E2E

## Architecture / Approach

Serwer przestaje wysyłać zdania. `CsvImportPreviewValidator` zwraca wartość enuma `ImportRowInvalidReason`, a `ImportPairCandidateDetector` zwraca tylko dane kandydata. Po stronie klienta `import-row-reasons.ts` mapuje kod albo rodzaj pary na klucz `t.operations.*` i wstawia `formatDate`. `import-row-amount.ts` mapuje pięć typów wiersza na helpery kwot Operacji. Widok tylko je wywołuje. Test PHP po przypadkach enuma pilnuje, że każdy kod ma polski klucz, bo `UiTranslationKeysTest` nie widzi kluczy dynamicznych.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Decyzje i dokumenty | ADR-0052, strona design systemu i reguły | Sprzeczność z ADR-0037/0038/0048 |
| 2. Powody po polsku test-first (C1) | Czerwone testy → kody, kandydat bez `reason`, zdania w UI; jeden commit `fix` | Zmiana kształtu propa musi objąć PHP, typy, fikstury i testy naraz |
| 3. Widok w Atramencie (C2–C4, C6) | Czerwony strażnik i test kwoty → tytuł, panel, pigułki, kwota wg typu, potwierdzenie duplikatów | Szerokość kolumny kwoty na `xl` po dodaniu „zł” |
| 4. Sink, zrzuty i bramka (C5) | Kontroler sinka z 3 testami, sink z macierzą 7 stanów, zrzuty, `ci:check` na PostgreSQL | Montowanie strony z `Form` i `setLayoutProps` w sinku |

**Prerequisites:** worktree z `composer install`, `.env`, `npm ci`, `npm run build`; stos Sail działa (sieć `household-treasurer_sail`) dla testów na PostgreSQL.
**Estimated effort:** ~2–3 sesje w 4 fazach.

## Open Risks & Assumptions

- Podgląd zapisany w sesji przed wdrożeniem pokaże angielski powód odrzucenia bez zmian (świadomy fallback). Nowy podgląd daje polski tekst.
- Copy powodów (np. jeden kod `missing_date` dla braku i złej daty) opiera się na tym, że parser zwraca `null` w obu przypadkach.
- Kartę `PageImportPreview` research sprawdził w źródle, nie w renderze. Zgodność potwierdzają zrzuty w fazie 4.

## Success Criteria (Summary)

- Każdy niepoprawny wiersz i każdy kandydat do pary ma polski powód z datą `dd.mm.rrrr`, a kod bez tłumaczenia czerwieni test
- Kwota wiersza wygląda jak ta sama operacja na liście Operacji i zmienia się z typem
- `ImportPreviewHardcodedUiTest`, `check:ui` i `ci:check` na PostgreSQL przechodzą, a zrzuty odpowiadają karcie z odstępstwami z ADR-0052
