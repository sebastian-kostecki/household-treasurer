# Report Redesign Decisions Implementation Plan

## Overview

Element przygotowawczy F-07 z `context/foundation/roadmap.md` (M-4). Zapisuje decyzje widoków Raportów **przed kodem** (AGENTS.md: zmiana reguły słownika i terminu z _Avoid_ zaczyna się od wpisu i ADR). Nowy ADR-0097 opisuje piąty segment okresu „Lata” (kategorie × lata liczone do tego samego dnia albo „Pełne lata”, zmiana od pierwszego roku, statystyki), ranking kategorii z panelem kategorii w miejsce wykresu słupkowego oraz kolory wykresów. Zmienia ADR-0070, ADR-0071, ADR-0074, ADR-0077, ADR-0079, ADR-0081 i ADR-0084. `context/foundation/glossary.md` dostaje akapity „Years”, „Ranking” i „Category Panel” we wpisie **Period Report**, `design-system.md` i design system Atrament dostają tokeny wykresu. Zmiana dotyczy wyłącznie dokumentów. Odblokowuje S-34 i S-35 (a przez nie S-36) i rozstrzyga pytania 1 i 2 z `## Open Roadmap Questions` oraz Unknown wycinka S-35.

## Current State Analysis

- **Kolejność raportu nigdy po kwocie.** ADR-0070:11 („Order is the order of the Category list … never by amount”) i odrzucenie „sorting rows by amount” w ADR-0070:19; ta sama reguła w słowniku (`glossary.md:169`) i `_Avoid_: … sort by amount` (`glossary.md:175`). Ranking łamie ją świadomie.
- **Wykres słupkowy.** ADR-0071:19 (poziome słupki, jeden na wiersz pierwszego poziomu), kolory ADR-0071:15–16 (`--success` przychód, `--chart-1` wydatek, kolor kategorii nigdy na słupkach), kolejne serie ADR-0071:23 (`--chart-2..5` po sprawdzeniu kontrastu i wpisie w „Wykresy”). Rezerwa miejsca na wykres: ADR-0084:7 (`reportChartHeightRem(barCount, compared)`, `Skeleton`).
- **Rok do roku tylko N−1.** ADR-0074:5 (porównanie wyłącznie z N−1 w realizacji), :7 (okres w toku cięty do „today’s day minus one year without overflow (29 February gives 28 February)”, okres zakończony porównany w całości, „today” to zawsze prawdziwy dzień UTC), :11 (różnica kwotą), :15 (`--chart-2` dla „rok wcześniej”), odrzucone: zmiana % (:23) i przełącznik „compare” (:25). W kodzie `app/Support/ReportComparisonPeriod.php:31` (`resolve(Budget, ?int $month, CarbonImmutable $today)`), cięcie `:51` (`subYearNoOverflow()`), `isPartial` `:53–55`, „today” z `ReportController.php:47` (`CarbonImmutable::today('UTC')`). Nic w kodzie nie liczy „do tego samego dnia” dla wielu lat.
- **Pełne złote i przewijanie tylko w Trendzie.** ADR-0077:9 („the only view with amounts rounded for display”), :11 (przewijanie poziome), :13 (Trend: `--success`/`--chart-1`, „no token is added”); słownik `glossary.md:174`.
- **Cztery segmenty.** ADR-0081:17 (`?view=last-12` czwarty segment; przy zmianie roku w sidebarze widok przechodzi do raportu roku, :25), ADR-0084:5 („Rok, Miesiąc, 12 miesięcy, Trend”).
- **Tokeny.** `resources/css/app.css`: `--chart-1` `#0b6f8a`/`#2fb4d4` (:153/:235), `--chart-2` `#7a8797`/`#8a97a8` (:154/:236), `--chart-3..5` nieuzgodnione domyślne shadcn (:155–157), `--success` (:138/:220), `--brand-text` `#0b6a7b`/`#6fdccd` (:128/:210). `design-system.md:152–166` „Wykresy” (tabela ról, kontrasty, `chart-3..5` nieużywane). W kodzie: `resources/js/lib/report-chart.ts:38–42`, `report-trend-chart.ts:10–12` i ich testy.
- **Design system Atrament** (artefakt https://claude.ai/artifact/9gJSdtfpFgUAa8oEMgR4HA): `project/tokens.json` nie ma tokenów wykresu; `project/README.md`: „Design system nie ma tokenów wykresu (aplikacja ma `chart-1`, `chart-2`); karty Raportów używają `brand-text`, `success` i `foreground`.” Karty `PageReportsYears` (wariant A) i `PageReports` (wariant C) to wybrane propozycje, niewdrożone: sparkline `ht-spark`, pasek udziału `ht-share`, słupki miesięcy `ht-colchart` w `brand-text`; rok bieżący/najwyższy `foreground`, pusty rok `track`, przychód `ht-share--inc` w `success`.
- **Format.** ADR-y raportów: tytuł `#`, akapity z etykietą („Source.”, „Rows.”, „Colour.”), lista „We rejected:”; zmiany w akapicie otwierającym z odwołaniem `ADR-NNNN:linia`. Odsyłacz w starym ADR to jedna linia na końcu (precedens: ostatnia linia ADR-0024, „Amended by ADR-0066: …”). Bez indeksu w `context/foundation/adr/`. Najwyższy numer 0096, więc następny to **0097**. Wzór zmiany: F-05 `context/archive/2026-11-09-rhythm-and-irregular-decisions/plan.md`. Baza zmiany: commit `87843ad`.

## Desired End State

- `context/foundation/adr/0097-report-years-ranking-and-chart-colours.md` zapisuje wszystkie Key Decisions poniżej z przykładami liczbowymi, nowe brzmienie klauzul ADR-0070/0071/0074/0077/0081/0084 i odrzucone opcje.
- ADR-0070, 0071, 0074, 0077, 0079, 0081 i 0084 mają na końcu po jednej nowej linii „Amended by ADR-0097: …”; ich dotychczasowe linie są nietknięte.
- `glossary.md`, wpis **Period Report**: akapity „Ranking (ADR-0097)”, „Category Panel (ADR-0097)” i „Years (ADR-0097)” z terminami Same-day Cut, Full Years, Change since First Year, Average Yearly Growth i Fastest Growing; zdanie „never by amount” zawężone do widoków bez rankingu; `_Avoid_` przepisane tak, że nie zakazuje tego, co ADR-0097 wprowadza.
- `design-system.md` „Wykresy” opisuje nowe role `chart-1`, `foreground` i `track` w znakach wykresu; artefakt Atrament ma `chart-1` i `chart-2` w `tokens.json` i `bundle.css`, a karty Raportów malują wypełnienia przez `chart-1`.
- Roadmapa: pytania 1 i 2 z `## Open Roadmap Questions` i Unknown S-35 oznaczone jako rozstrzygnięte przez ADR-0097.
- S-34 i S-35 mogą zaprojektować kod bez nowych decyzji domenowych; przykłady z Key Decisions stają się ich przypadkami testowymi.

### Key Discoveries:

- Cięcie N−1 w `ReportComparisonPeriod.php:51` uogólnia się na k lat przez `subYearsNoOverflow(k)` od dzisiejszego dnia UTC; ADR-0074 celowo nie tnie lat zakończonych, więc „Lata” to nowa reguła, nie rozszerzenie starej.
- `chart-1` w jasnym motywie praktycznie równa się `brand-text` z kart (`#0b6f8a` vs `#0b6a7b`); różnica jest tylko w ciemnym (`#2fb4d4` vs `#6fdccd`).
- Po rankingu we wszystkich trzech okresach seria „rok wcześniej” (`chart-2`) nie ma już odbiorcy w aplikacji; token zostaje zarezerwowany z tą samą rolą.

### Key Decisions:

Ustalone w tej sesji planowania (pytania 1–5):

1. **Kolory wykresów** (rozstrzyga Open Roadmap Question 1). Zostają tokeny aplikacji: wypełnienie wydatku (pasek udziału, sparkline, słupki miesięcy w panelu, słupki Trendu) to `chart-1`; przychodu `success`; rok bieżący w sparkline i najwyższy miesiąc w panelu `foreground`; rok bez pozycji w budżecie to kreska 2 px w `track`; miesiąc w toku w panelu to kreskowanie w `chart-1`. `chart-2` zostaje z rolą „ten sam okres rok wcześniej”. Wszystkie wartości z `app.css`; `chart-1` i `chart-2` dopisane do `tokens.json` Atramentu, a karty przechodzą z `brand-text` na `chart-1` w wypełnieniach. Odrzucone: `brand-text` jako wypełnienie (jeden token znaczyłby link i wykres; zmiana Trendu i testów bez zysku).
2. **Nazwa** (rozstrzyga Open Roadmap Question 2). Akapit „Years” we wpisie Period Report, obok „Last twelve months” i „Trend”; adres `?view=years`; polskie copy: segment „Lata”, tytuł „Raport: lata 2020–2026”, przełącznik „Do 14 listopada | Pełne lata”. Terminy: **Same-day Cut** („do tego samego dnia”), **Full Years** („Pełne lata”), **Change since First Year** („Zmiana od 2020” / „od 2022”), **Average Yearly Growth** („Średnio rocznie”), **Fastest Growing** („Najszybciej rośnie”). _Avoid_: wieloletni raport, historia kategorii, Multi-year Report, Years Comparison, rok kroczący (dla „Lat”).
3. **Ranking w Roku, Miesiącu i 12 miesiącach; panel tylko w Roku** (rozstrzyga Unknown S-35). Ranking zastępuje tabelę grup i wykres słupkowy we wszystkich trzech okresach, więc `ReportBarChart` i jego rezerwa miejsca z ADR-0084:7 znikają. W Roku nazwa kategorii otwiera panel kategorii (przejście do Operacji jest przyciskiem w panelu); w Miesiącu i 12 miesiącach nazwa prowadzi do Operacji jak dziś (S-31). Trend i Lata zostają w kolejności listy.
4. **„Pełne lata” liczą zmianę do ostatniego pełnego roku.** W trybie Full Years rok w toku pokazuje kwotę jak raport roku (bez cięcia, ADR-0074:7) z dopiskiem „w toku”, ale nie wchodzi do zmiany, „Średnio rocznie”, „Najszybciej rośnie” ani do pogrubienia najwyższego roku; nagłówek zmiany to „Zmiana 2020–2025”. Przykład (pełne lata przykładowe): Razem 2020 = 42 000, 2025 = 74 000, 2026 do 14.11 = 68 240 → „Zmiana 2020–2025” +76,2%, „Średnio rocznie” +12,0% (5 lat); w trybie „Do 14 listopada” z karty: 35 980 → 68 240 = +89,7%, „Średnio rocznie” +11,3% (6 lat).
5. **Zakres lat niezależny od wybranego roku.** Kolumny to lata Budżetów w realizacji od najwcześniejszego do bieżącego roku kalendarzowego (UTC); szkice i lata przyszłe pominięte (dziś 2020–2026, szkic 2027 pominięty). Rok bez Budżetu w realizacji w środku zakresu nie ma kolumny. Zmiana roku w sidebarze nie zmienia tabeli i zostawia użytkownika w „Lata” (inaczej niż ADR-0081:25 dla 12 miesięcy). Gdy bieżący rok nie ma Budżetu w realizacji (np. styczeń 2027 przy szkicu 2027), tabela kończy się na ostatnim roku w realizacji, wszystkie lata są pełne, a przełącznik „Do …” znika. Bez żadnego Budżetu w realizacji: stan pusty „Brak budżetu w realizacji” jak w 12 miesiącach. Widok jest rozstrzygany przed fazą wybranego Budżetu, więc wybrany szkic nie przejmuje go (jak ADR-0084 dla 12 miesięcy).

Ustalone przez agenta z kart Atramentu i ADR-ów, bez osobnego pytania:

- **Same-day Cut.** Dzień cięcia D = dzisiejszy dzień UTC. Rok Y w trybie „Do D” liczy operacje od 1 stycznia Y do dnia D w roku Y włącznie, bez przepełnienia: 29 lutego w roku nieprzestępnym daje 28 lutego. Rok bieżący nie jest cięty (jak ADR-0074:7, „The current column is not cut”): zawiera też operacje z datą po dziś i równa się raportowi roku; do D cięte są tylko lata wcześniejsze. Przykład: 14.11.2026 → 2020 liczony 01.01.2020–14.11.2020. Przykład przestępny: 29.02.2028 → 2027 do 28.02.2027, 2024 do 29.02.2024, etykieta „Do 29 lutego”. Domyślny tryb to „Do D”. Przełącznik zakresu nie jest przełącznikiem porównania z ADR-0074:25 (ten zostaje odrzucony).
- **Wiersze i komórki „Lat”.** Wiersz ma każda kategoria listy z co najmniej jedną operacją finansową w którymkolwiek liczonym zakresie lat (także archiwalna, z „zarchiwizowana” i wyciszeniem), w grupach jak raport roku i w kolejności listy (ADR-0069); grupa ma wiersz z sumami, zmianą i sparkline; wiersz „Razem” na typ. Kwoty to Execution jak w raporcie okresu (zwroty netto, bez Own Transfer i Capital Movement). Komórka „—” = kategoria nie ma pozycji budżetu w tym roku; 0 = pozycja jest, a kwota netto 0 lub brak operacji w zakresie. Komórka grupy „—”, gdy żadna jej kategoria nie ma pozycji w tym roku. Pogrubiony jest najwyższy rok w wierszu (wśród lat liczonych do zmiany). Zakładki „Wydatki | Przychody”.
- **Równości.** W trybie Full Years kwota roku zakończonego równa się kwocie tej kategorii w raporcie roku co do grosza; „Razem” roku równa się „Wydatki”/„Przychody” raportu roku. W trybie „Do D” rok bieżący równa się raportowi roku w toku.
- **Change since First Year.** (kwota roku końcowego − kwota pierwszego roku z kwotą w wierszu) ÷ kwota pierwszego roku, w dziesiątych procenta zaokrąglonych od zera, liczona z groszy. Rok końcowy: ostatnia kolumna w trybie „Do D”, ostatni pełny rok w Full Years. Gdy pierwszy rok wiersza nie jest pierwszą kolumną, dopisek „od 2022”. „—”, gdy wiersz nie ma kwoty w roku końcowym (np. zarchiwizowane Kino), gdy kwota pierwszego roku ≤ 0 albo gdy pierwszy rok jest rokiem końcowym. Ton jak ADR-0074:13 (wzrost wydatku `destructive`, spadek `success`; dla przychodu odwrotnie). Przykład z karty: Meble „+19,4% od 2022”, Internet −8,3% w `success`.
- **Average Yearly Growth.** Średnie roczne tempo wzrostu wiersza „Razem”: (koniec ÷ początek)^(1 ÷ (rok końcowy − pierwszy rok)) − 1, w dziesiątych procenta; „—” przy początku ≤ 0 albo jednym roku. Przykład: 35 980 → 68 240 w 6 lat = +11,3%.
- **Fastest Growing.** Kategoria (nie grupa) z najwyższym Average Yearly Growth liczonym od jej pierwszego roku z kwotą do roku końcowego, spośród kategorii z kwotą w co najmniej 4 kolumnach i w roku końcowym; remis rozstrzyga kolejność listy; bez kandydata kafelek pokazuje „—”. Przykład z karty: Wakacje „+25,5% rocznie od 2020”.
- **Przypadki brzegowe.** „Pierwszy rok z kwotą” to pierwsza kolumna z kwotą > 0 (komórka 0 i „—” się nie liczą); ta sama definicja w progu 4 kolumn Fastest Growing. Zmiana, Average Yearly Growth i Fastest Growing liczone są dla aktywnej zakładki („Wydatki” albo „Przychody”). W Full Years bez żadnego pełnego roku (jedyny rok w realizacji to bieżący) zmiana i statystyki pokazują „—”. Panel: „Średnio na pełny miesiąc” bez zamkniętego miesiąca (styczeń roku bieżącego) i „Najwięcej” przy wszystkich miesiącach ≤ 0 pokazują „—”; bez wydatków w roku bez wyboru zaznaczona jest pierwsza pozycja rankingu przychodów, bez obu panelu nie ma.
- **Kwoty „Lat”.** Tabela i sparkline w pełnych złotych jak Trend (każda komórka zaokrągla swoją kwotę od zera, „Razem” zaokrągla dokładną sumę), stopka „Kwoty w zł, zaokrąglone do pełnych złotych · „—” = kategoria bez pozycji w budżecie tego roku”; procenty z groszy; panel statystyk w złotych z groszami. Na wąskim ekranie tabela przewija się poziomo z przypiętą kolumną Kategoria (wyjątki ADR-0077:9 i :11 obejmują „Lata”).
- **Sparkline.** Te same liczby co wiersz, skalowane do maksimum wiersza; rok „—” to kreska w `track`; kwota ujemna to słupek zerowy (tabela zostaje dokładna); `aria-hidden`.
- **Forma znaków.** Pasek udziału rankingu, sparkline „Lat” i 12 słupków miesięcy w panelu to znaczniki CSS/SVG z tokenów, bez Unovis i bez `defineAsyncComponent` (zmienia ADR-0071:13 i :29: odrzucenie „CSS bars” dotyczy wykresów z osią i skalą; Unovis zostaje dla wykresu Trendu). Pasek i sparkline dublują liczby swojego wiersza, więc są `aria-hidden`. Słupki miesięcy w panelu nie mają tabeli obok, więc niosą dostępną listę 12 kwot (`sr-only`, „<miesiąc>: <kwota>”, miesiące przyszłe pominięte), a słupki są `aria-hidden` (zmienia ADR-0071:17: dostępną formą jest tabela albo ta lista).
- **Ranking.** Sortowanie malejąco po kwocie okresu; remis rozstrzyga kolejność listy; wiersze istniejące tylko przez porównanie (brak operacji w okresie) na końcu w kolejności listy. Przełącznik „Kategorie | Grupy”, domyślnie Kategorie: Kategorie = wszystkie kategorie płasko z numerem pozycji; Grupy = grupy i kategorie bez grupy jako wiersze pierwszego poziomu po sumie, grupa rozwija się do swoich kategorii, też po kwocie. Pasek udziału: szerokość = kwota wiersza ÷ największa kwota wiersza pierwszego poziomu, kwota ≤ 0 to pusty `track`. Kolumna „Udział” bez zmian (ADR-0070, tylko wydatki). „Różnica” tylko z porównaniem (Rok i Miesiąc przy N−1 w realizacji); 12 miesięcy bez niej. Archiwalne jak dziś. Przychody pod wydatkami, pasek w `success`, bez „Udziału”. Przykład remisu: Czynsz 1 800 i Prąd 1 800 → wyżej ten, który stoi wcześniej na liście kategorii. Ranking sortuje wynik raportu okresu; `ReportCategoryTree::ofType` zostaje w kolejności listy, bo czytają go też Trend, „Lata” i filtr Operacji (ADR-0093:9).
- **Category Panel (Rok).** Wybrana kategoria jest w adresie (nazwę parametru ustala S-35) i ma `aria-selected`; bez wyboru zaznaczona jest pierwsza pozycja rankingu wydatków. Zawartość: chip, „Wydatek · grupa Codzienne · rytm bieżąca”; kwota roku, kwota N−1 (przy porównaniu), Różnica kwotą i „±x,x% względem N−1” (procent tylko w tym podpisie, „—” przy kwocie N−1 ≤ 0), Udział z „największa pozycja” przy pozycji 1; sekcja „Wydatki w czasie” / „Przychody w czasie” z przełącznikiem „Miesiące 2026 | Lata” (zakładkę „Lata” wdraża S-36 z tego samego odczytu Years, domyślnie Same-day Cut); 12 słupków miesięcy w `chart-1`, najwyższy w `foreground`, miesiąc w toku kreskowany, miesiące przyszłe puste; „Średnio na pełny miesiąc (sty–paź)” = suma zamkniętych miesięcy ÷ ich liczba (rok zakończony: 12), „Najwięcej: <miesiąc>” (remis: wcześniejszy miesiąc), „<Miesiąc> do <D.MM> (w toku)” tylko w roku bieżącym; przycisk „Operacje: <kategoria>, <rok>” do Operacji z zakresem roku (S-31). Kwoty w złotych z groszami.
- **Procent.** Zmiana % jest dozwolona tylko w „Latach” (Change since First Year, Average Yearly Growth, Fastest Growing) i w podpisie Różnicy w panelu; kolumna „Różnica” w rankingu zostaje kwotą (ADR-0074:11).
- **Segmenty.** Przełącznik okresu ma pięć pozycji „Rok | Miesiąc | 12 miesięcy | Trend | Lata”; „Lata” jest ostatnia, Rok zostaje pierwszy i domyślny.
- **Nazwa w „Latach”.** W S-34 nazwa kategorii w tabeli „Lata” jest zwykłym tekstem (bez linku do Operacji). S-36 robi z niej link do raportu roku bieżącego (gdy bieżący rok nie ma Budżetu w realizacji: ostatniego roku w realizacji) z otwartym panelem tej kategorii na zakładce „Lata”; klik zmienia segment okresu na Rok. Nazwę parametru adresu ustala S-35 razem z parametrem wybranej kategorii.

## What We're NOT Doing

- Żadnego kodu, migracji, testów, propsów Inertia ani zmian w `resources/` (to S-34, S-35, S-36).
- Bez edycji `.ai/rules/reports.md`; reguła zmienia się razem z kodem w S-34/S-35 (precedens F-03/F-05). ADR-0097 wymienia, które zdania reguły przestaną obowiązywać.
- Bez zmian w PRD, `lang/` i `prd-*.md`.
- Bez nowych tokenów w `app.css` i bez użycia `chart-3..5`.
- Bez projektowania panelu dla Miesiąca i 12 miesięcy, bez „Lat” dla Miesiąca i 12 miesięcy (Parked), bez wykresu zmiany od 2020 i kafelków kategorii (warianty B i C odrzucone).
- Bez zmiany istniejących linii starych ADR-ów (tylko dopisana linia na końcu).

## Implementation Approach

Dwie fazy jak F-03 i F-05 (ADR, potem słownik) plus trzecia na design system i roadmapę, bo AGENTS.md wymaga, żeby nowe znaczenie koloru trafiło na stronę design systemu. Najpierw ADR jako źródło decyzji; słownik i design system cytują jego frazy dosłownie, co sprawdzają grep-y. Każda faza kończy się commitem `docs(report-redesign-decisions): … (pN)` i pauzą na ręczne potwierdzenie.

## Critical Implementation Details

- **Frazy wspólne.** Te ciągi muszą wystąpić dosłownie i w ADR-0097, i w `glossary.md`: `Same-day Cut`, `Full Years`, `Change since First Year`, `Average Yearly Growth`, `Fastest Growing`, `29 February gives 28 February`, `„Kategorie | Grupy”`, `„Rok | Miesiąc | 12 miesięcy | Trend | Lata”`.
- **Artefakt Atrament** to współdzielony design system użytkownika: przed publikacją przeczytać aktualną wersję (`Artifact read`), zmienić tylko wymienione pliki i opublikować pod tym samym `url`; przy konflikcie scalić z nowszą wersją, nigdy `force`.

## Phase 1: ADR-0097 i odsyłacze

### Overview

Nowy ADR z całą decyzją i po jednej linii odsyłającej w siedmiu starszych ADR-ach.

### Changes Required:

#### 1. Nowy ADR

**File**: `context/foundation/adr/0097-report-years-ranking-and-chart-colours.md`

**Intent**: Zapisać decyzje 1–5 i decyzje agenta z przykładami liczbowymi, tak by S-34/S-35 nie musiały niczego rozstrzygać.

**Contract**: Styl ADR-0074/0081: tytuł `#`, akapit otwierający wymieniający zmieniane klauzule z odwołaniami `ADR-0070:11`, `ADR-0070:19`, `ADR-0071:13`, `ADR-0071:15`, `ADR-0071:17`, `ADR-0071:19`, `ADR-0071:23`, `ADR-0071:29`, `ADR-0074:7`, `ADR-0074:15`, `ADR-0074:23`, `ADR-0077:9`, `ADR-0077:11`, `ADR-0079:5`, `ADR-0081:17`, `ADR-0084:5`, `ADR-0084:7`; akapity z etykietami (Years., Same-day Cut., Full Years., Columns., Cells., Change., Statistics., Amounts., Ranking., Category Panel., Navigation., Colour.); akapit „Rules to change with the code” wymieniający zdania `.ai/rules/reports.md`, które S-34/S-35 przepiszą (wykres obok tabeli, `ReportBarChartPlaceholder`, cztery segmenty, „only here” dla pełnych złotych); lista „We rejected:” (brand-text jako wypełnienie, osobny wpis „Multi-year Report”, ranking tylko w Roku, panel we wszystkich okresach, zmiana do roku w toku przy „Pełnych latach”, zakres do wybranego roku, przełącznik porównania).

#### 2. Odsyłacze w starszych ADR-ach

**File**: `context/foundation/adr/0070-*.md`, `0071-*.md`, `0074-*.md`, `0077-*.md`, `0079-*.md`, `0081-*.md`, `0084-*.md`

**Intent**: Czytelnik starego ADR widzi, która klauzula już nie obowiązuje.

**Contract**: Po jednej pustej linii i jednej linii na końcu pliku, wzór `Amended by ADR-0097: <co się zmienia>.` (np. ADR-0070: „the Year, Month and last-twelve-months reports are a Ranking by amount; the Trend and the Years view keep the list order”). Bez innych zmian w pliku.

### Success Criteria:

#### Automated Verification:

- ADR istnieje: `ls context/foundation/adr | grep -c '^0097-'` zwraca 1
- ADR-0097 cytuje każdy zmieniany ADR: `for n in 0070 0071 0074 0077 0079 0081 0084; do grep -q "ADR-$n" context/foundation/adr/0097-*.md || echo "brak $n"; done` nic nie wypisuje
- Każdy zmieniany ADR ma odsyłacz i nie stracił linii: `for n in 0070 0071 0074 0077 0079 0081 0084; do tail -n 1 context/foundation/adr/$n-*.md | grep -q '^Amended by ADR-0097:' || echo "brak $n"; git diff --numstat 87843ad -- context/foundation/adr/$n-*.md | awk '$2!=0{print "usunięto w '"$n"'"}'; done` nic nie wypisuje
- Frazy wspólne są w ADR: `for p in 'Same-day Cut' 'Full Years' 'Change since First Year' 'Average Yearly Growth' 'Fastest Growing' '29 February gives 28 February'; do grep -qF "$p" context/foundation/adr/0097-*.md || echo "brak: $p"; done` nic nie wypisuje
- Zakres zmian: `git diff --name-only 87843ad -- . ':!context/changes' ':!context/foundation/roadmap.md'` wymienia tylko `context/foundation/adr/0070…0084` i `0097-*`

#### Manual Verification:

- Użytkownik czyta ADR-0097 i potwierdza, że każda decyzja 1–5 jest zapisana tak, jak ją wybrał, i nic nie przeczy kartom `PageReportsYears` (A) i `PageReports` (C)
- Przykłady liczbowe (+89,7%, +11,3%, +76,2%, +12,0%, 14.11 i 29.02.2028) dają się przeliczyć z samego ADR

**Implementation Note**: Po zielonych kontrolach automatycznych zatrzymaj się na ręczne potwierdzenie przed fazą 2.

---

## Phase 2: Słownik

### Overview

Wpis **Period Report** opisuje ranking, panel i odczyt „Years”; `_Avoid_` przestaje zakazywać tego, co wprowadza ADR-0097.

### Changes Required:

#### 1. Period Report

**File**: `context/foundation/glossary.md` (wpis `**Period Report**`, dziś :168–175)

**Intent**: Nazwać nowe odczyty po angielsku i polsku i zawęzić reguły, które ranking łamie.

**Contract**:
- Zdanie „Order is the order of the Category list (ADR-0069), never by amount.” → kolejność listy dla Trendu i Years, Ranking dla Roku, Miesiąca i 12 miesięcy (ADR-0097).
- Nowy akapit „Ranking (ADR-0097): …” (sortowanie, remis, „Kategorie | Grupy”, pasek udziału, „Różnica” kwotą, kliknięcie nazwy).
- Nowy akapit „Category Panel (ADR-0097): …” (tylko Rok, zawartość, procent tylko w podpisie Różnicy, polskie copy „Operacje: <kategoria>, <rok>”).
- Nowy akapit „Years (ADR-0097): …” po akapicie Trend, z terminami Same-day Cut, Full Years, Change since First Year, Average Yearly Growth, Fastest Growing, zakresem lat, „—” vs 0, pełnymi złotymi i polskim copy („Lata”, „Do 14 listopada”, „Pełne lata”, „Zmiana od 2020”, „Średnio rocznie”, „Najszybciej rośnie”, „w toku”).
- Zdanie Trendu „this is the only view with amounts rounded for display” → Trend i Years.
- Zdanie Trendu „It has the same rows and the same order as the year report” → te same wiersze co raport roku, „in the order of the Category list (ADR-0069)”, bo raport roku jest Rankingiem.
- `_Avoid_` (:175): „sort by amount” → „sort by amount (outside the Ranking)”; „zmiana % (percent change year over year)” → „zmiana % in the Różnica column (a percent only in the Years view and the Category Panel caption)”; dopisać „wieloletni raport, historia kategorii, Multi-year Report, Years Comparison”; „a compare toggle” zostaje.

### Success Criteria:

#### Automated Verification:

- Frazy wspólne są w słowniku i ADR: `for p in 'Same-day Cut' 'Full Years' 'Change since First Year' 'Average Yearly Growth' 'Fastest Growing' '29 February gives 28 February' '„Kategorie | Grupy”' '„Rok | Miesiąc | 12 miesięcy | Trend | Lata”'; do grep -qF "$p" context/foundation/glossary.md && grep -qF "$p" context/foundation/adr/0097-*.md || echo "brak: $p"; done` nic nie wypisuje
- Akapity istnieją: `grep -c -E '^(Ranking|Category Panel|Years) \(ADR-0097\):' context/foundation/glossary.md` zwraca 3
- Stare zakazy zniknęły: `grep -c 'never by amount\.' context/foundation/glossary.md` i `grep -c 'the only view with amounts rounded' context/foundation/glossary.md` zwracają 0
- Zakres zmian fazy: `git diff --name-only HEAD~1 HEAD` po commicie fazy wymienia tylko `context/foundation/glossary.md` (i pliki zmiany)

#### Manual Verification:

- Użytkownik czyta wpis Period Report i potwierdza, że nazwy i polskie copy są takie, jak wybrał, a słownik nie urósł ponad zaplanowane akapity
- Każde zdanie słownika o kolejności, procentach i pełnych złotych zgadza się z ADR-0097

**Implementation Note**: Po zielonych kontrolach automatycznych zatrzymaj się na ręczne potwierdzenie przed fazą 3.

---

## Phase 3: Design system i roadmapa

### Overview

Nowe znaczenia kolorów trafiają na stronę design systemu i do artefaktu Atrament; roadmapa zamyka pytania, które F-07 rozstrzygnęło.

### Changes Required:

#### 1. Sekcja „Wykresy”

**File**: `context/foundation/design-system.md` (:72 w „Kwoty”, :152–166 „Wykresy”)

**Intent**: Zapisać role tokenów w nowych znakach wykresu (AGENTS.md: nowe znaczenie koloru ląduje na tej stronie).

**Contract**: Tabela ról: `chart-1` = wypełnienie wydatku (słupki Trendu, pasek udziału rankingu, sparkline „Lat”, słupki miesięcy panelu, kreskowanie miesiąca w toku); `chart-2` = ten sam okres rok wcześniej, zarezerwowany (po S-35 bez odbiorcy); `success` = wypełnienie przychodu; nowy wiersz `foreground` (rok bieżący w sparkline, najwyższy miesiąc w panelu) i zmieniony istniejący wiersz `track` (:161, dziś „tło pasa (nie niesie danych)”; po zmianie: tło pasa i rok bez pozycji w budżecie, pusty pasek; nie niesie kwoty; „—” niesie komórka tabeli, więc kreska w `track` nie musi mieć 3:1 wobec `card`). Akapit otwierający (:154) „obok stoi tabela z tymi samymi wierszami w tej samej kolejności” → wykres z osią ma obok tabelę z tymi samymi liczbami, znaczniki w wierszu dublują liczby wiersza, słupki miesięcy panelu niosą listę `sr-only` (ADR-0097). Sekcja „Kwoty” (:72): wyjątek pełnych złotych obejmuje tabele Trendu i „Lat” (ADR-0097). Akapit o kontrastach bez zmian; dopisać, że `foreground` na `card` spełnia 3:1 w obu trybach. Odwołanie do ADR-0097; zdanie, że karty Atramentu malują wypełnienia przez `chart-1`, nie `brand-text`.

#### 2. Artefakt Atrament

**File**: artefakt https://claude.ai/artifact/9gJSdtfpFgUAa8oEMgR4HA — `project/tokens.json`, `project/components/bundle.css`, `project/README.md`, `project/components/PageReports/README.md`, `project/components/PageReportsYears/README.md` i `preview.html` obu kart

**Intent**: Design system przestaje mówić „nie ma tokenów wykresu”, a karty wyglądają jak przyszła aplikacja.

**Contract**: `chart-1` (`#0b6f8a` / `#2fb4d4`) i `chart-2` (`#7a8797` / `#8a97a8`) w `tokens.json` i jako zmienne w `bundle.css` (oba motywy, wartości z `app.css`); `ht-spark`, `ht-share`, `ht-colchart` malują wypełnienie `chart-1` zamiast `brand-text`; README: zdanie o braku tokenów zastąpione rolami z ADR-0097; README kart: „Przy wdrożeniu: chart-1/chart-2 do tokens.json albo zastąpione…” → „rozstrzygnięte w ADR-0097”. Odczyt przed publikacją, publikacja pod tym samym `url`.

#### 3. Roadmapa

**File**: `context/foundation/roadmap.md`

**Intent**: Pytania rozstrzygnięte przez F-07 nie wiszą jako otwarte.

**Contract**: `## Open Roadmap Questions` pkt 1 i 2: dopisek „— Resolved by ADR-0097 (F-07): …” z wybraną odpowiedzią; Unknown S-35 („Czy ranking i panel dotyczą też widoków Miesiąc i 12 miesięcy…”) → rozstrzygnięty: ranking we wszystkich trzech, panel tylko w Roku (ADR-0097). Statusów elementów nie ruszać (robi to `/10x-implement` i `/10x-archive`).

### Success Criteria:

#### Automated Verification:

- Strona design systemu cytuje ADR i nowe role: `grep -c 'ADR-0097' context/foundation/design-system.md` ≥ 1 oraz `sed -n '/^## Wykresy/,/^## Tryb ciemny/p' context/foundation/design-system.md | grep -cE '^\| `foreground`'` zwraca 1, a ten sam wycinek `| grep -E '^\| `track`' | grep -c 'rok bez pozycji'` zwraca 1
- Roadmapa zamyka pytania: `grep -c 'Resolved by ADR-0097' context/foundation/roadmap.md` zwraca ≥ 2, a `sed -n '/^### S-35/,/^### S-36/p' context/foundation/roadmap.md | grep -c 'ADR-0097'` zwraca ≥ 1
- Artefakt ma tokeny: `Artifact read` pliku `project/tokens.json` zawiera `chart-1` i `chart-2`, a `project/README.md` nie zawiera „Design system nie ma tokenów wykresu”
- Brak zmian w kodzie: `git diff --name-only 87843ad -- resources app tests routes database .ai` jest puste

#### Manual Verification:

- Użytkownik otwiera karty `PageReports` i `PageReportsYears` w obu motywach i potwierdza, że wypełnienia są w `chart-1`, a układ się nie zmienił
- Użytkownik potwierdza, że pytania roadmapy i Unknown S-35 są zamknięte tak, jak wybrał

**Implementation Note**: Po zielonych kontrolach automatycznych zatrzymaj się na ręczne potwierdzenie; potem epilog i `/10x-impl-review`.

---

## Testing Strategy

### Unit Tests:

- Brak w tej zmianie (tylko dokumenty). Przykłady z Key Decisions są przypadkami testowymi dla S-34/S-35: cięcie 14.11 i 29.02.2028, „—” vs 0, Change since First Year z „od 2022” i „—” dla archiwalnej bez roku końcowego, Average Yearly Growth 35 980 → 68 240 = +11,3%, Full Years +76,2%/+12,0%, remis w rankingu po kolejności listy, „Najszybciej rośnie” z progiem 4 lat; przypadki brzegowe (komórka 0 przed pierwszą kwotą, styczeń, same zera, rok bez wydatków, Full Years bez pełnego roku).

### Integration Tests:

- Dla S-34: Full Years — rok zakończony i „Razem” równe raportowi roku co do grosza; „Do D” — rok bieżący równy raportowi roku w toku.

### Manual Testing Steps:

1. Przeczytać ADR-0097 i przeliczyć przykłady.
2. Przeczytać wpis Period Report i porównać frazy z ADR.
3. Otworzyć karty Atramentu w jasnym i ciemnym motywie.

## References

- Roadmap: `context/foundation/roadmap.md` (F-07, S-34, S-35, S-36, Open Roadmap Questions 1–2)
- Karty: artefakt Atrament `project/components/PageReportsYears/`, `project/components/PageReports/`
- ADR: `context/foundation/adr/0070-*`, `0071-*`, `0074-*`, `0077-*`, `0079-*`, `0081-*`, `0084-*`
- Kod: `app/Support/ReportComparisonPeriod.php:31-64`, `app/Http/Controllers/ReportController.php:47`, `resources/css/app.css:128,138,153-157,210,220,235-239`, `resources/js/lib/report-chart.ts:38-42`
- Wzór zmiany: `context/archive/2026-11-09-rhythm-and-irregular-decisions/plan.md`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: ADR-0097 i odsyłacze

#### Automated

- [x] 1.1 ADR istnieje — 54348b3
- [x] 1.2 ADR-0097 cytuje każdy zmieniany ADR — 54348b3
- [x] 1.3 Każdy zmieniany ADR ma odsyłacz i nie stracił linii — 54348b3
- [x] 1.4 Frazy wspólne są w ADR — 54348b3
- [x] 1.5 Zakres zmian — 54348b3

#### Manual

- [ ] 1.6 Użytkownik potwierdza decyzje 1–5 w ADR-0097 i zgodność z kartami
- [ ] 1.7 Przykłady liczbowe dają się przeliczyć z samego ADR

### Phase 2: Słownik

#### Automated

- [x] 2.1 Frazy wspólne są w słowniku i ADR — 74fafda
- [x] 2.2 Akapity istnieją — 74fafda
- [x] 2.3 Stare zakazy zniknęły — 74fafda
- [x] 2.4 Zakres zmian fazy — 74fafda

#### Manual

- [ ] 2.5 Użytkownik potwierdza nazwy, polskie copy i rozmiar słownika
- [ ] 2.6 Zdania słownika zgadzają się z ADR-0097

### Phase 3: Design system i roadmapa

#### Automated

- [x] 3.1 Strona design systemu cytuje ADR i nowe role — abccce3
- [x] 3.2 Roadmapa zamyka pytania — abccce3
- [x] 3.3 Artefakt ma tokeny — abccce3
- [x] 3.4 Brak zmian w kodzie — abccce3

#### Manual

- [ ] 3.5 Karty Atramentu w obu motywach mają wypełnienia w chart-1
- [ ] 3.6 Pytania roadmapy i Unknown S-35 zamknięte zgodnie z wyborem
