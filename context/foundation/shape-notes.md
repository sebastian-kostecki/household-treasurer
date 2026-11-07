---
project: "Household Treasurer"
context_type: brownfield
created: 2026-11-04
updated: 2026-11-04
product_type: web-app
target_scale:
  users: small
  qps: low
  data_volume: small
timeline_budget:
  delivery_weeks: 8
  hard_deadline: null
  after_hours_only: true
checkpoint:
  current_phase: 8
  phases_completed: [1, 2, 3, 4, 5, 6, 7]
  gray_areas_resolved:
    - topic: "kontekst"
      decision: "brownfield, potwierdzone przez użytkownika"
    - topic: "rodzaj zmiany"
      decision: "raporty okresowe + porządek kategorii"
    - topic: "główna persona"
      decision: "jedna osoba prowadzi budżet całego domu, bez zmian"
    - topic: "dane dev"
      decision: "kategorie z września–października to dane testowe"
    - topic: "kontrola dostępu"
      decision: "bez zmian"
    - topic: "pierwszy raport"
      decision: "okres + kategorie z kwotą i udziałem, klik do operacji"
    - topic: "trend"
      decision: "miesiąc do miesiąca i rok do roku"
    - topic: "porządek kategorii"
      decision: "ta sama kategoria przez lata (nowy ADR)"
    - topic: "wydatki nieregularne"
      decision: "w tej zmianie, w planie miesiąca"
    - topic: "zakres vs czas"
      decision: "cały zakres, 8 tygodni, świadomie"
    - topic: "dane rok do roku"
      decision: "dev ma 2026; dla 2025 przygotować dane mock na devie"
    - topic: "wykresy"
      decision: "wykres i tabela"
    - topic: "priorytety"
      decision: "FR-004, FR-006, FR-007, FR-008, FR-009 nice-to-have; okres tylko miesiąc albo rok"
    - topic: "czas po zmniejszeniu zakresu"
      decision: "8 tygodni zostaje, zapas na nice-to-have"
    - topic: "kwota nieregularnego"
      decision: "plan roczny / 12 co miesiąc"
    - topic: "płatność nieregularnego"
      decision: "zebrane pokrywa, nadwyżka obniża do wydania"
    - topic: "widoczność zebranego"
      decision: "plan miesiąca i Podsumowanie miesiąca; salda bez zmian"
    - topic: "koniec roku"
      decision: "niewydane zebrane obniża plan roczny i przechodzi do wyniku roku"
    - topic: "zakres nieregularnego"
      decision: "kilka razy i raz w roku"
    - topic: "start zbierania"
      decision: "od miesiąca oznaczenia, zebrane od 0"
    - topic: "non-goals"
      decision: "bez grup i domyślnej listy, bez oznaczania wyjazdów, bez własnego zakresu i eksportu"
  frs_drafted: 14
  quality_check_status: accepted
---

# Shape notes: Household Treasurer — kategorie i raporty okresowe

Poprzednia sesja (plan miesiąca, M-1): `context/foundation/archive/shape-notes-2026-11-04-1211.md`.

## Seed idea (verbatim)

> kategorie
> - przychody - ok
> - wydatki
>     - kredyt (raz na miesiac)
>     - czynsz (raz na miesiac)
>     - subskrypcje AI (raz na miesiac, ale moga byc 2, zwykle 1)
>     - telefon + internet (mozna rozbic, wtedy raz na miesiac)
>     - prad (4-5 razy na rok - jak pokazac na planie miesiecznym, do tej pory odkladalem stala kwote na inne konto, aby odjac, ale moze tez zostac na koncie glownym; jak to pokazac na planie miesiecznym)
>     - ubezpieczenia na życie (2 razy (2 osoby) raz na miesiac)
>     - przedszkole (2 razy (w synow) raz na miesiac, czasem inne dodatki na wydarzenia; mozna rozbic na 2 kategorie)
>     - artykuły spozywcze (wydatki codzienne) (stala kwota na caly miesiac - bardzo wiele transakcji)
>     - apteka (jak artykuly spozywcze)
>     - leczenie prywatne (kilka wydatkow w ciagu roku)
>     - bilety i parking (wiele wydatkow w ciagu miesiaca, jak artykuly spozywcze)
>     - paliwo (stala kwota przeznaczona na miesiac; czasem przekraczam czasem mniej)
>     - serwis samochodu (raz albo razy do roku)
>     - rozrywka (jakies rzeczy dla zycia, wyjscia, co kupic na wyjsciu - stala kwota na miesiac, moze byc wiele wydatkow)
>     - restauracje (podobnie jak rozrywka, ale jedzenie na miescie)
>     - ??? coś innego jeszcze; jakies podobne kategorie ??
>     - ubrania (wiele wydatkow w ciagu roku, razej brak stalej kwoty na miesiac)
>     - rzeczy osobiste, edukacja, prezenty, usługi, dzieci, darowizny, ksiazki, elektronika, utrzymanie domu, wyposażenie domu (moze inaczej eksploatacja domu) - tak samo jak ubrania
>     - prowizje i oplaty to tez rozne kwoty w ciagu roku
>     - pozostale -
>     - ubezpieczenie samochodu (raz na rok)
>     - rekolekcje (raz na rok)
>     - wakacje (raz na rok, ale wiele wydatkow)
> PYTANIE: jakie jeszcze inne kategorie z moich wydatkow? Jakie typy tych kategorii? Czy nie trzeba miec stalej listy kategorii? A co wykresami w ciagu miesiaca, lat? Chcialbym wiedzic ile i na co wydaje/zarabaim w jakims okresie

## Current System

Household Treasurer to responsywna aplikacja webowa do budżetu domowego. Słownik: `context/foundation/glossary.md`, poprzedni PRD (plan miesiąca): `context/foundation/prd.md`.

- **Architektura:** monolit Laravel z frontendem Inertia + Vue, baza PostgreSQL.
- **Użytkownicy:** konta tworzy administrator, dane każdego użytkownika są odizolowane. Faktycznie korzysta jedna osoba.
- **Funkcje dziś:** budżet roczny (plan początkowy, plan aktualny, wykonanie), plan miesiąca i „do wydania”, tempo, powiadomienia, Podsumowanie miesiąca, porównanie planów miesięcy z rokiem, operacje ręczne i Import CSV/XLSX z podpowiedziami kategorii, konta, transfery własne, lokaty, Dashboard.
- **Kategorie dziś:** należą do jednego budżetu (roku). Typ: przychód albo wydatek. Wydatek ma dwie flagi: równomierna / jednorazowa (Paced / One-off) oraz rachunek / bieżąca (Month Item Kind). Kategoria ma nazwę, ikonę, kolor i kolejność; można ją zarchiwizować. Nowy budżet kopiuje aktywne kategorie z poprzedniego roku razem z flagami. Nie ma jednej tożsamości kategorii przez lata: lata łączy zapytanie po nazwie i typie (`context/foundation/glossary.md`, Category: _Avoid_ „a Category identity that spans years”). Nie ma grup kategorii.
- **Raporty dziś:** brak widoku raportów i wykresów. Najbliżej: Podsumowanie miesiąca, porównanie planów miesięcy z rokiem, kolumny wykonania w budżecie rocznym.

### Stan danych na devie (2026-11-04)

Jeden budżet 2026 w wykonaniu, około 1000 operacji z Importu styczeń–wrzesień. Kategorie z września i października (Żywność, Mieszkanie, Transport, Zdrowie, Inne, Pensja, druga Rozrywka, drugie Odsetki) to dane testowe (potwierdził użytkownik). Obecna lista kategorii to propozycja użytkownika.

Obserwacje z operacji (do rozstrzygnięcia przez użytkownika, nie decyzje):
- Wszystkie wydatki mają rodzaj „bieżąca”, także Kredyt (2 raty miesięcznie), Czynsz, Ubezpieczenia (2 polisy), Telefon + Internet, Przedszkole.
- Streamingi (Prime Video, Disney+, Netflix) są w Rozrywce, Google One w Usługach.
- Podatek od odsetek jest w „Prowizje i opłaty bankowe”, opłata w Urzędzie Miasta w „Serwis samochodu”.
- Myjnie są w „Serwis samochodu”.
- Optyk jest w „Rzeczy osobiste”, okulary dziecka w „Dzieci”; Decathlon w „Rzeczy osobiste”.
- Prąd: 4 płatności w roku (marzec, maj, lipiec, sierpień).
- Literówki: „Restaruacje”, „Subkrypcje AI”.

## Vision & Problem Statement

Zmiana: uporządkować listę kategorii i dodać odczyt „ile i na co wydaję / zarabiam” w wybranym okresie, z porównaniem w czasie (miesiąc do miesiąca, rok do roku).

Ból (wskazany przez użytkownika):
- brak odczytu sum przychodów i wydatków według kategorii za wybrany okres,
- brak porównania w czasie (trend),
- nieuporządkowana lista kategorii: nie wiadomo, jakie kategorie mieć, jak je grupować i jakie typy im nadać,
- wydatki nieregularne (prąd 4–5 razy w roku, ubezpieczenie auta, wakacje) nie mają jasnego miejsca w planie miesiąca; dotąd użytkownik odkładał stałą kwotę na inne konto.

Wgląd (dlaczego dotąd tego nie było):
- kategorie należą do roku, więc porównanie lat wymaga łączenia ich po nazwie, a nazwy się zmieniają,
- priorytetem była kontrola miesiąca („do wydania”), analiza wstecz jest kolejnym krokiem,
- raporty na nieuporządkowanej liście kategorii byłyby mylące, więc porządek kategorii idzie razem z raportami.

## User & Persona

Jedna osoba prowadzi budżet całego domu: loguje się i wpisuje wszystko, także dochody i wydatki drugiej osoby. Raporty czyta ta sama osoba.

## Access Control

Obecny model: konta tworzy administrator (`user:create`), samodzielna rejestracja jest wyłączona, każdy użytkownik widzi wyłącznie własne dane. Dostępne 2FA i passkeys.

No changes planned — current model preserved.

## Success Criteria

### Primary
- Użytkownik przechodzi przepływ zmiany w aplikacji:
  1. otwiera widok „Raporty”,
  2. wybiera okres: miesiąc albo rok,
  3. widzi przychody, wydatki, wynik i kategorie z kwotą i udziałem %,
  4. przełącza na trend: kategorie w kolejnych miesiącach roku,
  5. w planie miesiąca wydatek nieregularny (np. prąd płacony 4–5 razy w roku) ma miesięczną kwotę.

### Secondary
- Przejście z kategorii do jej operacji; porównanie z tym samym okresem rok wcześniej; kategoria rozpoznawana między latami, także po zmianie nazwy (przesunięte z Primary po rundzie Socratic).
- Użytkownik nie potrzebuje `budget_szablon.xlsx` także do analizy wstecz.
- Po raporcie użytkownik wskazuje kategorię, na której wydaje więcej, niż sądził.
- Prąd i inne nieregularne wydatki nie wymagają odkładania stałej kwoty na osobne konto, żeby plan miesiąca był właściwy.

### Guardrails
- Suma kategorii w raporcie za rok jest równa wykonaniu tych kategorii w budżecie rocznym (zwroty netto, bez transferów własnych i ruchów kapitału).
- Podpowiedzi kategorii w Imporcie trafiają co najmniej tak dobrze jak przed zmianą.
- Istniejące operacje, budżety i kategorie przechodzą bez ręcznej migracji i bez zmiany kwot.
- `user:backup` i `user:restore` obejmują nowe dane.
- Budżet roczny (plan początkowy, plan aktualny, wykonanie) działa jak dotąd.

Najszybszy sygnał awarii (wskazany przez użytkownika): sumy raportu różne od budżetu; gorsze podpowiedzi Importu.

Plan miesiąca i „do wydania” nie są na liście rzeczy do zachowania bez zmian: zmiana świadomie dotyka reguły „do wydania” dla wydatków nieregularnych.

## Timeline acknowledgment

Acknowledged on 2026-11-04: 8-week change requires sustained dedication; user accepted. Zakres obejmuje cztery niezależne części: raport okresu, trend (miesiąc do miesiąca i rok do roku), kategorię przez lata (zmiana zasady ze słownika, nowy ADR), wydatki nieregularne w planie miesiąca (zmiana reguły „do wydania”). Użytkownik odrzucił zmniejszenie zakresu. Po rundzie Socratic rok do roku, kategoria przez lata i przejście do operacji stały się nice-to-have; użytkownik zostawił 8 tygodni jako zapas.

## Functional Requirements

Raport pokazuje każdy odczyt jako wykres i tabelę (decyzja użytkownika; forma do ustalenia przy projekcie widoku).

### Raport okresu

- FR-001: User can wybrać okres raportu: miesiąc albo rok. Priority: must-have. Change: new
  > Socratic: Kontrargument: własny zakres dat jest zbędny i kosztuje. Rozstrzygnięcie: tylko miesiąc i rok; własny zakres usunięty z FR.
- FR-002: User can zobaczyć przychody, wydatki i wynik wybranego okresu. Priority: must-have. Change: new
  > Socratic: Kontrargument: dla jednego miesiąca to ta sama liczba co „Wynik” w Podsumowaniu miesiąca. Rozstrzygnięcie: raport liczy wynik tak samo jak Wynik miesiąca (Month Result), więc dla miesiąca obie liczby są równe.
- FR-003: User can zobaczyć kategorie przychodów i wydatków z kwotą i udziałem % w wybranym okresie. Priority: must-have. Change: new
  > Socratic: Rozważono: 37 kategorii na jednym wykresie, zwroty obniżające kwotę, brak grup. Rozstrzygnięcie: bez kontrargumentu, zostaje jak zapisano.
- FR-004: User can przejść z kategorii w raporcie do jej operacji w wybranym okresie. Priority: nice-to-have. Change: new
  > Socratic: Kontrargument: lista operacji już istnieje. Rozstrzygnięcie: raport prowadzi do strony Operacje z filtrem kategorii i okresu, bez nowej listy.

### Trend

- FR-005: User can zobaczyć kwoty kategorii i sumy w kolejnych miesiącach wybranego roku. Priority: must-have. Change: new
  > Socratic: Rozważono: niepełny bieżący miesiąc, skoki przy wydatkach nieregularnych, brak planu obok wykonania. Rozstrzygnięcie: bez kontrargumentu, zostaje jak zapisano.
- FR-006: User can porównać okres z tym samym okresem rok wcześniej. Priority: nice-to-have. Change: new
  > Socratic: Kontrargument: skoro rok do roku jest poza v1, łączenie kategorii między latami też może poczekać. Rozstrzygnięcie: FR-007, FR-008 i FR-009 przesunięte do nice-to-have.

### Kategoria przez lata

- FR-007: User can utworzyć nowy budżet, który kopiuje kategorie razem z ich powiązaniem z kategoriami poprzedniego roku. Priority: nice-to-have. Change: modified
  > Socratic: Kontrargument: istniejące budżety (2026 i mock 2025) powstały bez powiązań. Rozstrzygnięcie: powiązanie startowe po nazwie i typie, potem ręczna korekta.
- FR-008: User can zmienić nazwę kategorii bez zerwania porównania z innymi latami. Priority: nice-to-have. Change: modified
  > Socratic: Kontrargument: którą nazwę pokazuje raport, gdy nazwy w latach się różnią. Rozstrzygnięcie: raport pokazuje nazwę z najnowszego roku w okresie.
- FR-009: User can ręcznie powiązać kategorię z kategorią z innego roku. Priority: nice-to-have. Change: new
  > Socratic: Kontrargument: podział i scalenie (np. „Telefon + Internet” rozbity na dwie kategorie). Rozstrzygnięcie: powiązanie tylko 1:1; podział zrywa porównanie.

### Wydatki nieregularne

- FR-010: User can oznaczyć wydatek jako nieregularny: płacony kilka razy albo raz w roku. Priority: must-have. Change: new
  > Socratic: Kontrargument: może wystarczy flaga „jednorazowa”, ale w Propozycji planu startuje ona od 0, więc prąd znika z miesięcy bez płatności. Rozstrzygnięcie: potrzebny trzeci rodzaj albo nowa flaga; obecne flagi nie wystarczają.
- FR-011: User can zobaczyć miesięczną kwotę takiego wydatku w planie miesiąca i w „do wydania”. Priority: must-have. Change: modified
  > Socratic: Kontrargument: odjęcie miesięcznej kwoty, gdy nic nie płacisz, zostawia pieniądze na koncie, a „do wydania” mówi „nie wydawaj”. Rozstrzygnięcie: zebrane pieniądze muszą być widoczne jako „zebrane na …”; szczegóły w regule biznesowej.

### Zachowane

- FR-012: User can korzystać z budżetu rocznego (plan początkowy, plan aktualny, wykonanie) jak dotąd. Priority: must-have. Change: preserved
  > Socratic: Kontrargument: nowe oznaczenie może zmienić „Pozostało w miesiącu” i pulę równomiernych w minionych miesiącach. Rozstrzygnięcie: budżet roczny i minione miesiące bez zmian; oznaczenie działa tylko na plan miesiąca.
- FR-013: User can importować operacje z podpowiedziami kategorii jak dotąd. Priority: must-have. Change: preserved
  > Socratic: Kontrargument: porządkowanie danych (np. Prime Video do Subskrypcji) zmieni podpowiedzi zamierzenie. Rozstrzygnięcie: trafność porównywać na tych samych danych przed i po zmianie kodu.
- FR-014: User can korzystać z istniejących budżetów, kategorii i operacji bez ręcznej migracji, a backup i restore obejmują nowe dane. Priority: must-have. Change: preserved
  > Socratic: Kontrargument: snapshot sprzed zmiany nie ma nowych pól. Rozstrzygnięcie: restore starego formatu działa i ustawia wartości domyślne (wydatek nie jest nieregularny).

## User Stories

### US-01: Odczyt roku według kategorii

- **Given** budżet 2026 w wykonaniu z operacjami
- **When** użytkownik otwiera „Raporty” i wybiera rok 2026
- **Then** widzi przychody, wydatki i wynik oraz kategorie z kwotą i udziałem % (wykres i tabela)

#### Acceptance Criteria
- Suma wydatków w raporcie równa się wykonaniu kategorii wydatków w budżecie rocznym 2026.

## Lista kategorii (dane użytkownika, nie funkcja aplikacji)

Lista kategorii należy do użytkownika; aplikacja jej nie narzuca. Decyzje użytkownika z tej sesji:
- Dodać: Subskrypcje (streaming, chmura: Prime Video, Disney+, Netflix, Google One), Eksploatacja auta (myjnie, drobne rzeczy; „Serwis samochodu” dla napraw i przeglądów), Sport (np. Decathlon), optyk i okulary do zdrowia.
- Nie dodawać: Podatki i urzędy, Kawa i słodycze na mieście.
- Wakacje: odkładać na hotel, zakwaterowanie, atrakcje — „to jednak koszt wakacji” (słowa użytkownika).
- Do poprawienia przez użytkownika w danych: rodzaj „rachunek” dla stałych opłat (dziś wszystkie „bieżąca”), literówki „Restaruacje”, „Subkrypcje AI”, błędnie przypisane operacje (Sinsay i Allegro w Artykułach spożywczych, Żabka w Przedszkolu), wypłata gotówki zaksięgowana jako wydatek (Rekolekcje, Planet Cash).

## Business Logic

Wydatek nieregularny obniża „do wydania” co miesiąc o 1/12 swojego planu rocznego, a jego płatności pokrywa kwota zebrana do tej pory; „do wydania” w miesiącu płatności obniża tylko nadwyżka ponad zebrane.

Obecnie system liczy „do wydania” jako: przychody (plan, a po wpływie kwota faktyczna) − pozycje „rachunek” (plan, a po wpłacie kwota faktyczna) − pozycje „bieżąca” (większa z: plan, kwota faktyczna) − odkładam + dobieram (`context/foundation/glossary.md`, To Spend). Wydatek płacony kilka razy w roku nie pasuje: jako „jednorazowa” startuje w Propozycji planu od 0, a jako równomierny rachunek obniża „do wydania” skokiem w miesiącu płatności. Ta zmiana modyfikuje regułę: wydatek nieregularny (płacony kilka razy albo raz w roku, np. prąd, ubezpieczenie auta, wakacje, rekolekcje) wchodzi do „do wydania” kwotą 1/12 planu rocznego w każdym miesiącu. Ta kwota zwiększa „zebrane” tej pozycji. Płatność najpierw zmniejsza „zebrane”; część płatności ponad „zebrane” obniża „do wydania” w miesiącu płatności, a „zebrane” spada do 0. Przykład: prąd, plan roczny 1800 zł → 150 zł co miesiąc; rachunek 430 zł przy zebranych 300 zł → 130 zł obniża „do wydania”.

„Zebrane” widać przy pozycji w planie miesiąca i w Podsumowaniu miesiąca. Nie zmienia sald kont ani Spendable Liquidity, tak jak „odkładam”. Zbieranie zaczyna się od miesiąca, w którym użytkownik oznaczył wydatek jako nieregularny (zebrane startuje od 0); minione miesiące się nie zmieniają. Na koniec roku niewydane „zebrane” obniża plan roczny tej kategorii i przechodzi do wyniku roku.

Raport okresu (nowy odczyt, bez nowej reguły domenowej): kwoty kategorii w okresie to wykonanie z operacji datowanych w tym okresie, zwroty netto, bez transferów własnych i ruchów kapitału. Wynik miesiąca w raporcie jest równy Wynikowi miesiąca (Month Result). Suma roku jest równa wykonaniu w budżecie rocznym.

## Non-Functional Requirements

- Raport roku otwiera się w czasie do 2 sekund przy 10 000 operacji (obecne wymaganie aplikacji, `context/foundation/prd-mvp.md` sekcja 10).
- Kwoty w raporcie są co do grosza równe wykonaniu kategorii w budżecie rocznym i Wynikowi miesiąca dla tego samego okresu.
- Istniejące wymagania aplikacji obowiązują nowe widoki: izolacja danych użytkownika, kwoty bez obliczeń zmiennoprzecinkowych.

## Constraints & Preserved Behavior

Nie mogą się zepsuć:
- Budżet roczny: plan początkowy, plan aktualny, wykonanie, „Pozostało w miesiącu” i pula równomiernych w minionych miesiącach. Oznaczenie „nieregularny” działa tylko na plan miesiąca (wyjątek: koniec roku obniża plan roczny o niewydane „zebrane”).
- Historia operacji i Import, w tym trafność podpowiedzi kategorii (porównywana na tych samych danych przed i po zmianie kodu).
- Istniejące budżety, kategorie i operacje bez ręcznej migracji i bez zmiany kwot. Domyślnie żaden wydatek nie jest nieregularny.
- `user:backup` i `user:restore`: format dostaje nową wersję i obejmuje nowe dane; snapshot w starym formacie nadal się wczytuje z wartościami domyślnymi.
- Salda kont, lokat, Spendable Liquidity i Total funds bez zmian.

Wymogi istniejącego systemu (wskazane przez użytkownika):
- Zmiana reguły „do wydania” wymaga nowego ADR i wpisów w `context/foundation/glossary.md` przed kodem.
- Dane 2025 tylko na devie (mock), do sprawdzenia trendu i późniejszego porównania rok do roku.

Plan miesiąca i „do wydania” zmieniają się świadomie w zakresie wydatków nieregularnych; reszta reguły „do wydania” zostaje.

## Product framing

- Typ produktu: No change — existing web-app.
- Skala: No change — jedna osoba (small).
- Termin: brak twardego terminu; cel 8 tygodni, praca po godzinach.

## Non-Goals

- **Grupy kategorii i domyślna lista kategorii.** Lista kategorii jest danymi użytkownika; aplikacja jej nie narzuca i nie grupuje.
- **Oznaczanie wyjazdów.** Bez tagów ani oznaczeń operacji ponad kategorię (np. „Wakacje 2026”); koszt wakacji to kategoria Wakacje (hotel, zakwaterowanie, atrakcje).
- **Własny zakres dat i eksport raportu.** Okres to miesiąc albo rok; bez eksportu CSV ani PDF.

## Open Questions

1. **Jak zmienia się miesięczna kwota wydatku nieregularnego, gdy w trakcie roku zmienia się jego plan roczny?** Reguła mówi „1/12 planu rocznego”; nie ustalono, czy po zmianie planu kwota liczy się od nowego planu od tego miesiąca. Właściciel: użytkownik.
2. **Jak „nieregularny” łączy się z obecnymi flagami (równomierna / jednorazowa, rachunek / bieżąca)?** FR-010 rozstrzyga tylko, że obecne flagi nie wystarczają (trzeci rodzaj albo nowa flaga). Właściciel: użytkownik, przy nowym ADR.

## Forward: technical-roadmap

- Nowy ADR i wpisy w `context/foundation/glossary.md` (nowe pojęcia: wydatek nieregularny, „zebrane”) przed kodem; zmiana dotyka To Spend, Month Plan, Proposal, Month Summary i Year Close / Align na koniec roku.
- Mock budżetu 2025 z operacjami tylko na devie (seeder), do sprawdzenia trendu i późniejszego porównania rok do roku.
- `user:backup` / `user:restore`: nowa wersja formatu; stary format wczytuje się z wartościami domyślnymi.
- Łączenie kategorii po nazwie i typie jest dziś używane w kilku miejscach (kopiowanie budżetu, podpowiedzi Importu, porównanie planów miesięcy z rokiem, Earmark). FR-007…FR-009 (nice-to-have) muszą je uwzględnić.
- Trafność podpowiedzi Importu: pomiar przed i po zmianie kodu na tych samych danych (istnieje próg pomiaru z `import-category-suggestions`).
- Raport: istniejąca strona Operacje może przyjąć filtr kategorii i okresu (FR-004).

## Quality cross-check

Run 2026-11-04: Access Control, Business Logic, Project artifacts, Timeline-cost acknowledgment, Non-Goals, Preserved behavior — all present. Status: accepted.
