---
project: 'Household Treasurer'
status: draft
created: 2026-11-17
updated: 2026-11-18
after_milestone: M-4
after_version: 1.5.1
---

# Kierunki rozwoju po M-4

Lista kandydatów na dalszy rozwój aplikacji, zebrana w rozmowie z 2026-11-17, po zamknięciu M-4 i wydaniu 1.5.1. To nie jest roadmapa: `roadmap.md` opisuje otwarty kamień milowy, ten dokument trzyma to, co jeszcze nie zostało wybrane.

## Jak korzystać

- Przeczytaj ten dokument przed rozmową o tym, co budować dalej, zanim zaproponujesz nowe kierunki.
- Rozróżniaj trzy rodzaje zapisów: **fakt** (sprawdzony w repo w dniu z nagłówka sekcji), **decyzja użytkownika** i **rekomendacja agenta**. Rekomendacja nie jest zatwierdzonym planem.
- Koszty („mały”, „średni”, „duży”) to szacunki agenta z lektury kodu, bez wyceny.
- Dokument jest edytowany w miejscu (`context/foundation/README.md`). Gdy kierunek wejdzie do kamienia milowego, zmień jego status na `w roadmapie (M-N)`; gdy zmiana zostanie zarchiwizowana, na `zrobione (<change-id>)`; gdy użytkownik go odrzuci, przenieś wiersz do „Odradzane i odrzucone” z powodem.
- Przed budowaniem na fakcie z sekcji „Stan wyjściowy” sprawdź go ponownie w kodzie.

## Stan wyjściowy (fakty, 2026-11-17)

- `roadmap.md`: M-4 zamknięty 2026-11-15, brak otwartego kamienia; `context/changes/` jest puste; brak otwartych issues na GitHubie.
- Produkcja: v1.5.1 na `budget.kostecki.dev`, budżety 2019–2027 (2027 jako szkic), lata 2019–2025 wczytane z arkuszy.
- Kryterium M-1 „pełny miesiąc prowadzony wyłącznie w aplikacji” ma w `roadmap.md` dopisek „do potwierdzenia w użyciu”.
- Year Close, Reserve, Earmark, Agenda i Cash Expectation są w `glossary.md` i ADR, ale nie w kodzie (`app/` nie zawiera żadnego z nich).
- `test-plan.md` ma datę 2026-11-06: fazy 2–5 mają status „not started”, a faza 1 wskazuje folder `context/changes/testing-import-integrity/`, którego nie ma.
- `tests/e2e` zawiera 15 plików `*.spec.ts`; `.github/workflows/tests.yml` nie uruchamia Playwrighta.
- `composer.json` nie ma pakietu do śledzenia błędów; aplikacja nie ma `app/Notifications` ani `app/Mail`, pocztę wysyła tylko monitor kopii zapasowych (`config/backup.php`).
- Wdrożenie jest ręczne (`scripts/deploy.sh`).
- Numer ADR 0008 jest użyty dwa razy (`0008-cross-import-own-transfer-pair.md`, `0008-user-domain-snapshots-via-artisan.md`).
- Kwoty są wyłącznie w PLN: konto ma typ `bank` albo `cash` i nie ma waluty, a Import odrzuca wiersz w obcej walucie („Import obsługuje tylko kwoty w złotych”).
- Lokata ma datę końca (`end_date`), nie ma oprocentowania.
- Operacja ma jedną kategorię (`category_id`); lista Operacji nie ma zaznaczania wielu wierszy.
- Skróty globalne: ⌘K (paleta), `/`, `N`, `1–9` (`resources/js/lib/global-shortcuts.ts`). Wewnątrz list i tabel nie ma nawigacji klawiaturą.
- Plan miesiąca przechowuje kwotę na kategorię, bez daty i bez konta. Rytm „Stała opłata” liczy plan, a po pierwszej operacji kwotę faktyczną, więc „zapłacone / czeka” da się odczytać z istniejących danych.
- Słownik oznacza jako _Avoid_: „Net worth” i „Wealth” (przy Liquidity) oraz „Forecast”, „Schedule” i „due date” (przy Cash Expectation i Agenda). Archiwalna specyfikacja Agendy (`context/archive/2026-09-10-agenda-cash-expectations/spec.md`) zastrzega, że Agenda nie jest prognozą płynności ani mechanizmem powtarzania.
- Unovis, używany w „Trendzie”, ma komponent Sankey (`node_modules/@unovis/ts/components/sankey`).

## Decyzje użytkownika (2026-11-17)

Zapadły w trakcie wywiadu o M-5, który użytkownik przerwał na czwartym pytaniu, a potem poprosił o rekomendację z pominięciem tej rozmowy. **M-5 nie został wtedy wybrany** (2026-11-18 użytkownik wybrał kamień jakości, patrz niżej). Decyzje obowiązują dla kierunku „Płynność w czasie”, jeśli do niego wróci:

1. Główny odczyt to najniższy punkt salda przed kolejnym wpływem („czy starczy i ile zostanie w najgorszym dniu”), a nie lista rachunków, kalendarz ani jedna liczba „wolne środki”.
2. Krzywa liczy sumę wszystkich kont, czyli płynność dysponowalną, bez nowych ustawień na koncie. Odrzucone: suma wybranych kont, jedno konto główne, każde konto osobno.

Wcześniejsze wypowiedzi użytkownika, które zawężają wybór:

- Ma w domu walutę obcą, której aplikacja nie widzi; sam zapytał o wielowalutowość i sekcję „Majątek”.
- Zapytał o obsługę „tylko klawiaturą” i o pomysły z YNAB.
- Poprosił o „pomniejsze poprawki” na teraz i większe etapy na potem.
- 2026-11-18: wybrał kamień jakości bez nowej funkcji (M-5 „Pierwszy pełny cykl na produkcji”); funkcja w styczniu z listy tarć.

## Rekomendacja agenta

Nie otwierać teraz kamienia z nową funkcją. Następny kamień powinien być kamieniem jakości („pierwszy pełny cykl na produkcji”): próba i prawdziwy przełom roku, odświeżona siatka testów, śledzenie błędów, lista tarć z grudnia i stycznia. Zamknięty, gdy plan stycznia 2027 jest zatwierdzony na produkcji bez ręcznych poprawek po przełomie roku. Funkcję wybrać w styczniu z listy tarć.

Jeśli funkcja ma powstać wcześniej: „Majątek z walutą”, bo to jedyny kierunek oparty na fakcie (waluta w domu, „Środki razem” zaniżone), a nie na domyśle o tym, co uwiera.

Uzasadnienie ostrożności: cztery kamienie powstały w dwa tygodnie, a wybór „suma wszystkich kont” przy prognozie salda sprawia, że ostrzeżenie prawie nigdy się nie pojawi, jeśli na kontach leżą oszczędności. To sygnał, że brak pieniędzy przed wypłatą może nie być realnym problemem.

## Na już (do końca 2026)

Kolejność to rekomendacja agenta. Wiersze 1–6 to terminy i zabezpieczenia; z wierszy 7–15 najwyżej trzy w grudniu.

| #   | Co                                               | Po co                                                                                      | Koszt            | Status  |
| --- | ------------------------------------------------ | ------------------------------------------------------------------------------------------ | ---------------- | ------- |
| 1   | Próba przełomu roku 2026→2027 na kopii produkcji | pierwszy przełom na prawdziwych danych: szkic 2027 w wykonanie, „zebrane” na nowy rok, plan stycznia z grudnia; termin 31 grudnia | mały             | zrobione (year-turn-rehearsal); procedura w README „Przełom roku” |
| 2   | Odświeżenie planu testów                         | plan z 6 listopada nie odpowiada stanowi repo                                              | mały             | w roadmapie (M-5, F-09) |
| 3   | E2E w CI                                         | testy istnieją, CI ich nie uruchamia                                                       | mały             | w roadmapie (M-5, S-44) |
| 4   | Śledzenie błędów na produkcji                    | dziś tylko log plikowy                                                                     | mały do średniego | w roadmapie (M-5, S-45) |
| 5   | Porządek w dokumentach                           | terminy bez kodu w słowniku, zdublowany numer ADR 0008                                     | mały             | zrobione (M-5, S-46): ADR-0104, ADR-0105 |
| 6   | Lista tarć                                       | użytkownik zapisuje przez grudzień i styczeń, co go spowalnia; podstawa wyboru etapu       | zerowy           | w roadmapie (M-5, MS-10) |
| 7   | Plan roczny 2027 z historii                      | propozycja kwot ze średniej i trendu lat 2019–2026, „co jeśli”; przydaje się tylko przy układaniu roku | średni           | otwarte |
| 8   | „Zapłacone / czeka” dla stałych opłat            | lista rachunków miesiąca z sumą „czeka” obok salda; bez nowych pól                          | mały             | otwarte |
| 9   | „Pokryj przekroczenie”                           | jedna akcja „pokryj z kategorii X” zamiast ręcznej zmiany dwóch kwot planu                  | mały             | otwarte |
| 10  | Zbiorcza zmiana kategorii w Operacjach           | zaznacz kilka, zmień kategorię                                                             | mały do średniego | otwarte |
| 11  | Klawiatura w Podglądzie Importu i ściąga `?`     | strzałki po wierszach, litery kategorii, Enter; najwięcej powtarzalnej pracy               | mały do średniego | otwarte |
| 12  | „Zapas w dniach”                                 | kafelek: płynność dysponowalna podzielona przez średni dzienny wydatek                      | mały             | otwarte |
| 13  | Oprocentowanie lokat                             | stopa na lokacie, prognoza odsetek, kalendarz zapadalności                                 | mały             | otwarte |
| 14  | Kreator końca miesiąca                           | uzgodnij salda → Import → podsumowanie → plan następnego; klocki istnieją, brakuje ścieżki  | mały             | otwarte |
| 15  | Tygodniowy e-mail                                | „do wydania”, tempo, rachunki czekające                                                    | mały             | otwarte |

Luki potwierdzone w próbie przełomu (kandydaci na osobne zmiany, szczegóły w `context/archive/2026-11-18-year-turn-rehearsal/rehearsal.md`): menu roku i domyślne daty dialogów używają czasu przeglądarki (1 stycznia 00:00–01:00 PL), rok w sesji zostaje 2026 po przełomie, Raport „Lata” liczy 1 stycznia tylko jeden dzień poprzednich lat, Import przy szkicu roku nie mówi, że trzeba uruchomić jego Budżet. Status: w roadmapie (M-5, F-08 i S-39…S-43).

Komendy startowe: wiersz 1 `/10x-new-research year-turn-rehearsal`, wiersz 2 `/10x-test-plan --refresh`, wiersz 4 `/10x-observability-audit` (wybór dostawcy należy do `/10x-infra-research`).

## Potem (kolejne etapy)

| Etap                    | Co wchodzi                                                                                                              | Dlaczego w tej kolejności                                                                 | Koszt             | Status  |
| ----------------------- | ----------------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------- | ----------------- | ------- |
| 1. Majątek z walutą     | konta, lokaty, waluta po kursie, ręczne aktywa i zobowiązania, historia miesiąc po miesiącu; budżet zostaje w PLN         | jedyny brak oparty na fakcie                                                              | średni            | otwarte |
| 2. Szybsze wprowadzanie | klawiatura w Operacjach i Planie miesiąca, reguły kontrahenta, auto-akceptacja pewnych podpowiedzi Importu                | najczęstsza czynność; auto-akceptacja wymaga najpierw pomiaru trafności                    | średni            | otwarte |
| 3. Płynność w czasie    | najniższy punkt salda przed wypłatą dla sumy wszystkich kont (decyzje użytkownika wyżej)                                 | najpierw sprawdzić na liście tarć, czy to realny problem                                   | średni do dużego  | otwarte |
| 4. Analityka            | podsumowanie roku (odczyt; Year Close odpada, ADR-0104), zmiany cen skorygowane o inflację, wykres przepływu, wykrywanie podwyżek        | korzysta z danych 2019–2026, nic nie blokuje                                              | mały do średniego na element | otwarte |
| 5. Do rozważenia        | dostęp dla drugiej osoby, liczniki mediów, serwer MCP do odczytu, aplikacja na telefon (PWA), majątek w czasie jako raport | każde wymaga potwierdzenia potrzeby                                                       | różny             | otwarte |

## Odradzane i odrzucone

| Co                                               | Powód                                                                                  |
| ------------------------------------------------ | -------------------------------------------------------------------------------------- |
| Pełne koperty z przenoszeniem reszty na kategorię | przebudowa modelu, który ADR-0033 świadomie odrzucił                                    |
| Pobieranie z banku przez API (PSD2)              | koszt agregatora i odnawianie zgód przy dwóch bankach i jednej osobie                   |
| Wydatki w obcej walucie w budżecie               | dotyka każdej sumy, raportów, Importu i snapshotu                                       |
| Nazwane cele oszczędnościowe                     | PRD v3 §Non-Goals: użytkownik ich nie używa                                             |
| Podział operacji na kilka kategorii              | duży koszt i ryzyko; wrócić, gdy pojawi się na liście tarć                               |
| Produkt dla innych użytkowników                  | inny projekt niż narzędzie dla jednego domu                                             |
| Jawne rekordy z datą (dawna Agenda)              | powstała przed rytmami i planem miesiąca; przy płynności w czasie kształtować od nowa    |

Zaparkowane wcześniej pozycje są w `roadmap.md` → `## Parked`.

## Szczegóły kierunków

Notatki z rozmowy, żeby nie wyprowadzać ich drugi raz. Każda wymaga kształtowania (`/10x-shape`) przed planem.

### Majątek z walutą

- Dwa poziomy waluty: (a) waluta jako składnik majątku: konto ma walutę, saldo w walucie, wycena w PLN po kursie, wymiana w kantorze to transfer własny z dwiema kwotami, budżet zostaje w PLN; (b) waluta w budżecie: odradzana. Rekomendowany poziom (a).
- Kurs zapisany jako liczba całkowita ze skalą, bo `float` nie wchodzi do kodu kwot. Źródło kursu (tabela NBP przez publiczne API) jest do sprawdzenia przy researchu.
- Proponowana kolejność wycinków: wpis w słowniku i ADR (dziś „Net worth” i „Wealth” są _Avoid_) → strona z historią kont i lokat z istniejących danych → waluta na koncie → ręczne aktywa i zobowiązania z wyceną na dzień → oprocentowanie lokat.
- Historia jest wiarygodna od momentu, gdy konta są prawdziwe; lata wczytane z arkusza nie mają sald (Sheet Account).
- Do rozstrzygnięcia: czy waluta wchodzi do płynności dysponowalnej, czy tylko do majątku; co poza kontami, lokatami i walutą ma się tam znaleźć (nieruchomość, IKE, obligacje, kredyt).

### Szybsze wprowadzanie

- Klawiatura: Podgląd Importu (strzałki, litery kategorii, Enter), Operacje (`j/k`, `e` edycja, `c` kategoria, `x` zaznaczenie), Plan miesiąca (Enter i Tab między kwotami), `?` jako ściąga. Bez migracji i decyzji domenowych; ryzykiem jest zarządzanie fokusem, więc każdy widok potrzebuje testu E2E.
- Reguły kontrahenta: jawne „zawiera X → kategoria Y” ponad statystyczną podpowiedzią (ADR-0034).
- Auto-akceptacja: najpierw pomiar trafności komendą `ImportSuggestionAccuracyCommand`.

### Płynność w czasie

- Otwarte, z rekomendacją agenta: dzień płatności i wpływu jako pole na kategorii, podpowiedziane z historii operacji i zatwierdzane raz; reszta planu wydatków bieżących rozłożona równo na dni do końca miesiąca; horyzont do końca bieżącego miesiąca, a po zatwierdzeniu planu następnego także ten następny; kafelek na Dashboardzie i sekcja w planie miesiąca, bez nowej pozycji w menu.
- Próg ostrzeżenia: przy sumie wszystkich kont próg „minimum < 0” rzadko zadziała; rekomendacja to minimum poniżej sumy „zebranych”, spójnie z Collected Coverage (ADR-0090).
- Proponowana kolejność wycinków: decyzje (słownik i ADR, bo „Forecast” jest _Avoid_ przy Current Plan, a nowy termin wymaga wpisu) → „zapłacone / czeka” i pesymistyczne minimum bez dat → dzień na kategorii i oś czasu z dniem minimum → ostrzeżenie na Dashboardzie → horyzont na następny miesiąc.
- Proponowane „Done when”: przez pełny miesiąc na produkcji minimum z prognozy porównane z faktycznym najniższym saldem, z różnicą akceptowalną dla użytkownika.

### Pomysły z innych aplikacji

- YNAB: „pokryj przekroczenie” (przesunięcie między kategoriami), „Age of Money” jako „zapas w dniach”. Odpowiedniki, które aplikacja już ma: „zebrane” (fundusze celowe), „Uzgodnij saldo” (reconciliation), „do wydania” („Ready to Assign”).
- Actual, Firefly III: jawne reguły kontrahenta.
- Monarch: wykres przepływu przychody → grupy → kategorie; nowy typ wykresu wymaga decyzji w planie i ADR (`.ai/rules/reports.md`).

### Rok 2027

- Podsumowanie roku („2026 w liczbach”: plan, wykonanie, odkładanie, największe zmiany) razem z decyzją, czy Year Close powstaje, czy znika ze słownika. Decyzja zapadła 2026-11-19 (ADR-0104): Year Close znika ze słownika, a podsumowanie roku, jeśli powstanie, jest odczytem z nowym ADR.
- Plan roczny z historii: propozycja kwoty na kategorię ze średniej i trendu oraz „co jeśli” (np. czynsz +10%). Wartość raz w roku.

## Otwarte pytania

1. ~~Czy następny kamień to jakość („pierwszy pełny cykl”), czy funkcja?~~ Rozstrzygnięte 2026-11-18: jakość, M-5 „Pierwszy pełny cykl na produkcji” w `roadmap.md`.
2. Które wiersze 7–15 „Na już” wchodzą w grudzień? Wiersze 2–6 są w M-5; z 7–15 użytkownik nie wybrał żadnego. — Właściciel: użytkownik.
3. Ile czasu zajmuje dziś cotygodniowy Import i jaka jest trafność podpowiedzi? — Do zmierzenia; rozstrzyga wagę etapu 2.
4. Czy zdarza się zejść blisko zera przed wypłatą? — Właściciel: użytkownik; rozstrzyga wagę etapu 3.
5. Pytania o Majątek i Płynność z sekcji „Szczegóły kierunków”. — Właściciel: użytkownik, przy kształtowaniu.
