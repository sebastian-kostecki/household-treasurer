# ui-fixes-2 — Plan Brief

> Full plan: `context/changes/ui-fixes-2/plan.md`
> Research: `context/changes/ui-fixes-2/research.md`

## What & Why

Seria poprawek wyglądu zgłoszonych w `/10x-ui`. Ta sama kwota wygląda dziś różnie w zależności od widoku. Aplikacja ma angielską nazwę i znak Laravela w favicon. Ikona zmiany planu nie mówi, w którą stronę plan się zmienił. Na szerokim ekranie zostają puste pasy. Zmiana porządkuje to w kodzie i w design systemie (ADR-y, `design-system.md`, `.ai/rules`).

## Starting Point

- Kwoty mają trzy polityki:
  - stopka „Kwoty w zł”;
  - „zł” w komórce na mocy ADR-0039/0044/0051/0052;
  - „zł” w komórkach Planu miesiąca bez żadnego ADR-u.
- `APP_NAME` wyznacza też ciasteczko sesji, prefiks cache i nazwę kopii Spatie.
- Znak „HT” to tekst (ADR-0053).
- `PlanChangedHint` pokazuje ikonę `History` bez kierunku.
- Kolumny kwot w Budżecie i na Dashboardzie mają stałe szerokości, a `--aside-width` to sztywne 300 px.

## Desired End State

- Każda tabela pokazuje kwoty bez „zł” i ma stopkę „Kwoty w zł”. „zł” zostaje w miarach, dialogach i alertach.
- Aplikacja nazywa się „Domowy Skarbnik” i ma znak „dom z monetą”, także jako favicon i apple-touch-icon.
- Zmieniony plan ma strzałkę ↑ lub ↓, kolorowaną wg typu kategorii, w Budżecie i w nowej kolumnie „Zatwierdzony” w Planie miesiąca.
- Gotówka ma ikonę monet.
- Na dużym ekranie miernik i kolumny kwot zajmują dostępną szerokość.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Zakres | Tylko wygląd i design system; rok w sidebarze i „plan = wydatki” osobno | Błąd i reguła domenowa wymagają własnych ścieżek (test regresji; glosariusz + ADR) | /10x-ui |
| Kolor strzałki | Wydatki ↓ zielona, ↑ czerwona; dochody odwrotnie | Kolor mówi, czy zmiana jest korzystna, a nie tylko jaki ma kierunek | /10x-ui |
| Zasięg zasady kwot | Wszystko w tabeli, także podpisy w wierszach; „zł” w miarach, dialogach, alertach | Wymaganie „na tabelach bez waluty, na każdym widoku” | Research + Plan |
| Znak | Kafelek `brand` z SVG „dom z monetą” | Łączy dom i skarbnika, a przy 16 px zostają dwa kształty | Plan |
| Wdrożenie nazwy | Przypięcie sesji, cache, Redis i kopii w config, potem zmiana `APP_NAME` | Bez wylogowania i bez nowego pustego katalogu kopii | Plan |
| Tokeny strzałki | Istniejące `success` / `destructive` z dopisanym znaczeniem | Kontrast już zmierzony; nowe tokeny duplikowałyby wartości | Plan |
| Plan miesiąca | Nowa kolumna „Zatwierdzony” przed „Plan” | Ten sam wzorzec co Plan początkowy w Budżecie, widoczna obok pola w trybie zmian | Plan |
| Szerokie ekrany | `clamp()` dla `--aside-width`, zakresy `minmax` kolumn, bez `max-width` i `2xl` | Jedna płynna reguła zamiast progów | Plan |
| Gotówka | Ikona `Coins` w ADR-0058 | „zł” to jednostka, a nie znak konta | Plan |

## Scope

**In scope:**

- ADR-0058–0061, `design-system.md`, `.ai/rules`;
- przypięcie config, nazwa w `.env` i tłumaczeniach, `BrandMark` SVG, favicon/ICO/apple-touch;
- kwoty bez „zł” w Operacjach, imporcie, Kontach, Lokatach, `DashboardHoldings` i podpisach Planu miesiąca; wspólny klucz `common.amounts_in_zl`;
- `PlanChangeArrow`, kolumna „Zatwierdzony”, `CategoryChip` `lg`;
- szerokości kolumn Dashboardu i Budżetu;
- dziewięć sinków.

**Out of scope:**

- rok w sidebarze;
- „plan = wydatki”;
- nowe propsy Inertii;
- `max-width` strony;
- nowe tokeny koloru;
- manifest PWA;
- identyfikatory techniczne (baza, katalog kopii, obraz);
- większy chip w Budżecie i na Dashboardzie.

## Architecture / Approach

Najpierw dokumenty decyzji, potem kod w czterech niezależnych fazach. Logika trafia do czystych funkcji w `resources/js/lib` z testami:

- kierunek i ton strzałki: `plan-change-direction.ts`;
- formatery bez jednostki;
- znak gotówki w `account-identity.ts`.

Komponenty tylko renderują. Układ zmienia się wyłącznie w nazwanych klasach i tokenach `app.css`. Strażnik w `ui-contract.test.ts` pilnuje, żeby wiersze tabel nie wróciły do `formatMoney`.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Decyzje na papierze | ADR-0058–0061, design system, reguły agentów | Rozjazd między ADR-em, stroną i regułą |
| 2. Nazwa i znak | „Domowy Skarbnik”, przypięty config, znak SVG, favicon | Produkcja ma inne `APP_NAME` niż przykłady → wylogowanie |
| 3. Jedna zasada kwot | Kwoty bez „zł” + stopka wszędzie, ikona gotówki | Pominięta tabela lub sink składający własny panel |
| 4. Strzałka zmiany planu | Strzałka w Budżecie, kolumna „Zatwierdzony”, chip `lg` | Układ piętrowy Planu miesiąca z czterema kwotami na telefonie |
| 5. Szerokie ekrany | `clamp()` aside, rosnące kolumny | Ucięte nazwy kategorii przy 1280 px |

**Prerequisites:** gałąź robocza z `development`; działający Sail (PostgreSQL); przed wdrożeniem Fazy 2 sprawdzenie `APP_NAME` w produkcyjnym `.env`.

**Estimated effort:** ~4–5 sesji `/10x-implement`, po jednej na fazę (Faza 1 krótka).

## Open Risks & Assumptions

- Zakładamy, że produkcja ma dziś `APP_NAME="Household Treasurer"`. Jeśli nie, przypięte wartości trzeba nadpisać w produkcyjnym `.env`.
- Strzałka w `destructive` poszerza znaczenie tokenu poza „przekroczenie”. Rozjaśnia to ADR-0060, ale wymaga uważnej korekty tabeli kolorów.
- Cztery kwoty w jednym wierszu przy 390 px w Planie miesiąca: do potwierdzenia na zrzutach.
- Renderowanie ICO i PNG z SVG bez nowej zależności npm zależy od narzędzia dostępnego na hoście (Playwright albo ImageMagick).

## Success Criteria (Summary)

- W żadnej tabeli aplikacji nie ma „zł” w kwocie, a pod każdą jest „Kwoty w zł”.
- Użytkownik widzi „Domowy Skarbnik” i nowy znak wszędzie, także w karcie przeglądarki, i nie zostaje wylogowany po zmianie nazwy.
- Zmiana planu ma czytelną strzałkę w Budżecie i Planie miesiąca, a na ekranie 1920+ px nie zostają puste pasy.
