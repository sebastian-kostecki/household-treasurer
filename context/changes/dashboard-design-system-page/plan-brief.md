# Dashboard według strony design systemu (ADR-0045) — Plan Brief

> Full plan: `context/changes/dashboard-design-system-page/plan.md`
> Research: `context/changes/dashboard-design-system-page/research.md`

## What & Why

ADR-0045 (2026-11-06) przywrócił stronę `PageDashboard` design systemu jako wzorzec Dashboardu, ale kod dalej realizuje zastąpiony ADR-0043: cztery miary, panel realizacji z zakładkami i kolumną Status, kreska roku zawsze włączona, dwulinijkowa karta alertu. Ta zmiana wyrównuje kod do dokumentacji i zapisuje w ADR-0050 to, co ADR-0045 zostawił otwarte: etykiety siedmiu rodzajów alertów, układ szkicu i telefonu, wagę tożsamości.

## Starting Point

`DashboardStatPanel.vue:116-123` dokłada czwartą miarę „Postęp roku”. `DashboardExecutionPanel.vue` ma `SegmentedControl`, wiersze grup i kolumny Plan i Status. `Dashboard.vue:131-155` trzyma „Otwórz budżet” w wierszu tytułu, a pasek miar planu tylko w szkicu. `DashboardNotices.vue:363-388` renderuje ikonę, tytuł i zdanie. Payload już zawiera wszystko, czego potrzeba (`planSummary`, sumy, `year_elapsed_percent` w obu fazach), więc PHP się nie zmienia.

## Desired End State

Panel miar ma trzy miary. Blok „Budżet <rok>” to nagłówek (Switch „Postęp roku” w realizacji i „Otwórz budżet”), miary planu i dwie tabele Dochody i Wydatki z wierszem sumy; „przekroczono” stoi obok procentu. Switch jest domyślnie włączony i zapamiętany w `localStorage` (`dashboard-year-elapsed`, try/catch z logiem). Karta alertu: etykieta rodzaju, tożsamość w `font-medium`, wartość po prawej w kolorze wagi.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Wartość alertu bez kwoty | Tempo „31% · upływ dni 17%”, plan niezatwierdzony bez wartości | Dane tempa są w payloadzie, a planu nie ma czym zastąpić bez wymyślania | Użytkownik |
| Etykiety rodzajów | Saldo ujemne, Do wydania poniżej zera, Ponad plan, Ponad limit, Plan niezatwierdzony, Tempo przed upływem dni | Etykieta mówi, czego dotyczy karta; „Blisko limitu” odpada (ADR-0037) | Użytkownik |
| Układ bloku | Nagłówek i panele obok siebie (strip + dwie tabele) | Unika karty w karcie i zmiany `BudgetPlanSummaryStrip` używanego przez Budżet | Użytkownik |
| Telefon | Kategoria, Pozostało, miernik z „przekroczono”; Plan i Realizacja ukryte | Zachowuje wzorzec grid-template-areas bez drugiego drzewa mobilnego | Użytkownik |
| Szkic | Nagłówek bez Switcha, miary planu, karta stanu pustego | Switch ma sens tylko tam, gdzie są mierniki | Użytkownik |
| Tożsamość pozycji | `category_name`, kwoty ze znakiem, waga `font-medium` | Zgodne z „Konto, Lokata albo Kategoria” i celem linku; ADR wygrywa z 600 z karty | Użytkownik / Plan |
| Właściciel stanu Switcha | `Dashboard.vue` (composable), blok dostaje `v-model` | Stan jest w przeglądarce, a sink pokazuje oba stany bez `localStorage` | Plan |
| Kolejność | ADR → czysta logika test-first → widok → sink | Ta sama pętla co w Kontach | Research |
| Numer ADR | 0050 | Wybrany w `change.md`, wolny na wszystkich gałęziach | Research |

## Scope

**In scope:**
- ADR-0050, `design-system.md`, reguła w `.ai/rules/js.md`
- `year-elapsed-mark-storage.ts`, `dashboard-notice-presentation.ts` (z testami), teksty w `lang/pl/ui.php`
- `DashboardStatPanel`, `DashboardNotices`, nowy `DashboardBudgetBlock`, `DashboardExecutionPanel`, `CategoryMeterRow`, `Dashboard.vue`, `app.css`
- Straże (`check-ui-literals.mjs`, `DashboardHardcodedUiTest`), sink `/dev/ui/dashboard`, zrzuty 390/1280 w obu motywach

**Out of scope:**
- `AccountMarkCompact`, `AccountIdentityBlock`, panele Konta i Lokaty
- PHP, payload, rodzaje, wagi i limit sześciu kart alertów, glosariusz
- Przeróbka `StatPanel`, `ListSurface`, `BudgetPlanSummaryStrip`; Switch w szkicu; podpisy Plan i Realizacja na telefonie

## Architecture / Approach

Najpierw decyzja w ADR, potem kod od czystych funkcji z testami Vitest (zapis Switcha, prezentacja alertów). Potem góra strony (miary i karta alertu), blok budżetu z tabelami i Switchem, na końcu sink, sprzątanie osieroconych tekstów i bramka wizualna. Propsy Inertii i testy Feature się nie zmieniają.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Decyzje i dokumenty | ADR-0050, `design-system.md`, reguła w `js.md` | Konflikty z równoległymi zmianami w tych samych plikach |
| 2. Logika kliencka test-first | Helper storage i prezentacja alertów z testami, nowe teksty | Przeniesienie typu `DashboardNotice` bez importu z `.vue` |
| 3. Trzy miary i karta alertu | Panel miar bez „Postęp roku”, karta z etykietą i wartością | Kontrast wartości `text-warning` na karcie, szerokość pierwszej miary |
| 4. Blok „Budżet <rok>” | Nagłówek, Switch, miary planu, dwie tabele, straże | Siatka pięciu kolumn i telefon, przeniesienie formularza „Otwórz budżet” |
| 5. Sink i bramka wizualna | Sekcja Switcha w obu stanach, sprzątanie, zrzuty, `ci:check` | Usunięcie kluczy lub `.segmented-control-tabs` używanych gdzie indziej |

**Prerequisites:** stos Sail (testy, `ci:check`), `npm run build` przed podglądem sinka.
**Estimated effort:** ~2 sesje, 5 faz.

## Open Risks & Assumptions

- Brak specyfikacji karty dla telefonu (0 reguł `@media`): układ telefonu to decyzja ADR-0050, potwierdza ją sink i zrzuty.
- Format wartości starego klucza `dashboard-year-elapsed` nie jest znany; każda wartość inna niż `'false'` oznacza „włączone”.
- SSR w produkcji nie został zweryfikowany, dlatego odczyt Switcha jest po zamontowaniu.
- Pięć etykiet alertów to moje propozycje (zatwierdzone przy planowaniu); brzmienie może wymagać korekty na sinku.
- Równoległe zmiany (0048, 0049, 0051) też edytują `design-system.md` i `js.md`; spodziewane konflikty przy scalaniu.

## Success Criteria (Summary)

- Dashboard wygląda jak `PageDashboard` poza odejściami z ADR-0050: trzy miary, blok „Budżet” z dwiema tabelami i Switchem, karta alertu z etykietą i wartością.
- Switch ukrywa i pokazuje kreskę, a wybór przeżywa odświeżenie i nie psuje strony, gdy storage jest niedostępny.
- `composer ci:check`, `vendor/bin/sloppy ci` i `npm run check:ui` przechodzą bez zmian w payloadzie i asercjach Feature.
