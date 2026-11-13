# Poprawki po 1.4.3: ciemny motyw, pickery i Plan miesiąca — Plan Brief

> Full plan: `context/changes/v1-4-3-fixes/plan.md`
> Research: `context/changes/v1-4-3-fixes/research.md`

## What & Why

Audyt `/10x-ui` znalazł pięć zarzutów (C1–C5):
- w ciemnym motywie nie widać ikony kalendarza ani koloru wybieranej kategorii, a logo Revolut znika;
- lokaty i filtr kategorii Operacji wybiera się inaczej niż konta i kategorie w oknach operacji;
- w Planie miesiąca na telefonie kwoty się ucinają, a oszczędność na wydatku wygląda jak brak danych.

Plan naprawia to w warstwie tokenów i współdzielonych komponentów, nie w pojedynczych klasach.

## Starting Point

Ciemny motyw żyje w tokenach `:root` / `.dark`, ale nie ustawia `color-scheme`. Kafelek kategorii dokleja alfę `26` do koloru z danych. Selecty lokat i filtr kategorii są natywne z zapisanych decyzji (ADR-0054, ADR-0069). Siatka Planu miesiąca ma poniżej `xl` cztery kolumny po ok. 75 px przy 390 px. Fiksury sinka Planu miesiąca mają odwrócony znak odchylenia.

## Desired End State

W ciemnym motywie kontrolki natywne są ciemne, 24 próbki kolorów różne, a ikona kafelka ma ≥ 3:1 dla każdej barwy w obu motywach (test). Logo banku stoi na jasnej płytce. Lokaty wybiera się z awatarem jak konta. Filtr kategorii ma „Wszystkie kategorie”, sekcje Dochody/Wydatki, kolejność listy z grupami i sufiks archiwalnej. W Planie miesiąca kwoty stoją 2×2 na telefonie, pole planu startuje z `8 500,00`, a odchylenie i karta podsumowania mają kolor wg korzyści.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Kolor odchylenia (D1) | wg statusu: przekroczenie `destructive`, wydatek poniżej planu `success`, dochód > 0 `success`; etykieta „Odchylenie” bez zmian | „zostało / przekroczono” dotykałoby glosariusza i przeczyłoby odznace dla rocznej i nieregularnej | Research |
| Filtr kategorii (D2) | `CategorySelect` jak w oknie operacji, ADR zmienia ADR-0069 tylko dla filtra | jedna kolejność i wygląd kategorii w całej aplikacji; paleta zostaje płaska | Research |
| Znaki banków i lokaty (D3) | jasna płytka tylko pod logo + selecty lokat z `AccountAvatar` | Revolut ma dziś ok. 1,35:1, a lokaty wyglądają inaczej niż konta | Research |
| Kolory kategorii (D4) | para tokenów mieszanych w `srgb`, pełny kolor w próbce, test 24 × 2 | ikona ≥ 3:1 w obu motywach, liczone wprost z `app.css` | Research |
| Archiwum w filtrze (P1) | sufiks wg linii budżetu wybranego roku, bez roku najnowsza linia, fallback lista | ta sama reguła co w Raportach (ADR-0074) | Plan |
| Układ kwot (P2) | 2×2 poniżej `sm`, 4 kolumny `sm`–`xl`, etykieta nad wartością | przy 390 px kwota ma ok. 155 px, a tablet zostaje w jednym rzędzie | Plan |
| Separator w polu (P3) | tylko w wartości startowej, bez przeformatowania przy blur | parser już usuwa spacje; zero ryzyka dla wykrywania zmian i fokusu | Plan |
| Karta podsumowania (P4) | ta sama reguła korzyści co D1, jeden helper | ten sam miesiąc nie daje sprzecznych sygnałów | Plan |
| ADR-y | 0092–0095 wszystkie w fazie 1, przed kodem | 0090 zajmuje niezmergowana gałąź; numery po kolei | Plan |
| Bramka wizualna | kitchen sinki + zrzuty, bez `toHaveScreenshot` | repo nie ma narzędzia zrzutowego, skill zabrania instalacji tylko dla bramki | Plan |

## Scope

**In scope:**
- `color-scheme` w obu motywach i w `app.blade.php`
- tokeny kafelka i próbki kategorii z testem kontrastu
- płytka logo banku i trzy selecty lokat z awatarem, w tym chip filtra lokaty
- filtr kategorii Operacji (serwer + `CategorySelect`)
- Plan miesiąca: 2×2, etykiety, separator, ton odchylenia w wierszu i karcie, fiksury sinka
- ADR-0092–0095, `design-system.md`, reguły w `.ai/rules/`

**Out of scope:**
- zmiana etykiety „Odchylenie” i glosariusza
- grupy w palecie poleceń
- sufiks archiwum w chipach filtrów
- logo dla ING/Pekao/Santander
- przeformatowanie pola planu przy blur
- nazwy kolorów w `aria-label` próbek
- `toHaveScreenshot`

## Architecture / Approach

Tokeny najpierw: `:root` / `.dark` w `app.css` dostają `color-scheme`, `--category-tile-tint`, `--category-icon-tint` i `--bank-mark-plate`, a komponenty czytają je przez nazwane klasy (`.category-tile`, `.category-swatch`) i `--category-color` ustawiane inline. Pickery rozszerzają istniejące komponenty: `AccountSelect` obsługuje lokaty przez mapowanie opcji, a `CategorySelect` dostaje propsy „wszystkie”, sekcje i sufiks na wzór `AccountSelect`. Serwer liczy kolejność filtra przez `CategoryListOrder::tree()`. Plan miesiąca bierze ton z jednego modułu w `lib`, a siatka zmienia się w `app.css`.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Tokeny motywu | rebase, ADR-0092–0095, `color-scheme`, kafelek/próbka kategorii z testem, płytka logo | dobór procentów tak, by 48 par miało ≥ 3:1 bez utraty tożsamości koloru |
| 2. Lokaty z awatarem | `bank`/`mark_url` w 3 propach, 3 selecty → `AccountSelect`, chip z awatarem | wiersz importu: `disabled`, `name` tablicowe i strażnik `ImportPreviewHardcodedUiTest` |
| 3. Filtr kategorii | kolejność listy, grupy, `is_archived` wg roku, `CategorySelect` z sekcjami | kolejność na PostgreSQL (collation `name_key`), test zależny od indeksu |
| 4. Plan miesiąca | 2×2 poniżej `sm`, separator, ton odchylenia w wierszu i karcie, poprawne fiksury | wiersze grupy/odkładania/sum w siatce 2×2 |
| 5. Stany i bramka | macierz 7 stanów, zrzuty 1440/390 jasny/ciemny, skan, reguły, `ci:check` na PostgreSQL | lista natywnego selecta sprawdzalna tylko ręcznie na Windows |

**Prerequisites:** rebase na `development` (rhythm-hints); osobna baza testowa `testing_v1_4_3_fixes` i podgląd worktree (`context/foundation/agents/parallel-changes.md`, `worktree-preview.md`).
**Estimated effort:** ~3–4 sesje, 5 faz, każda kończona commitem i ręcznym potwierdzeniem.

## Open Risks & Assumptions

- Wygląd listy natywnego selecta w ciemnym motywie to wniosek z researchu, nieobserwowalny w headless; potwierdza go ręczny krok 5.7.
- Test kontrastu liczy kafelek na `card`. Na innych powierzchniach (popover, wiersz `muted`) kontrast sprawdza się wzrokowo.
- Numery ADR 0092–0095 zakładają, że nikt nie zajmie ich na `development` przed fazą 1. Jeśli zajmie, przesuwamy wszystkie cztery.
- Revolut i Lokaty Facto nie występują w danych demo; dowód z sinka `/dev/ui/accounts` i fiksur.

## Success Criteria (Summary)

- W ciemnym motywie użytkownik widzi kalendarz, kolor, który wybiera dla kategorii, i logo każdego banku z logo.
- Kategorię i lokatę wybiera się wszędzie tym samym pickerem, z ikoną i w kolejności listy.
- Na telefonie każda kwota Planu miesiąca jest cała, a kolor odchylenia mówi, czy jest dobrze, bez czytania znaku.
