---
project: 'Household Treasurer'
version: 2
status: draft
created: 2026-11-04
context_type: brownfield
product_type: web-app
target_scale:
    users: small
    qps: low
    data_volume: small
timeline_budget:
    delivery_weeks: 8
    hard_deadline: null
    after_hours_only: true
---

# Household Treasurer — PRD: kategorie i raporty okresowe

## Current System Overview

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

## Problem Statement & Motivation

Zmiana: uporządkować listę kategorii i dodać odczyt „ile i na co wydaję / zarabiam” w wybranym okresie, z porównaniem w czasie (miesiąc do miesiąca, rok do roku).

Ból (wskazany przez użytkownika):
- brak odczytu sum przychodów i wydatków według kategorii za wybrany okres,
- brak porównania w czasie (trend),
- nieuporządkowana lista kategorii: nie wiadomo, jakie kategorie mieć, jak je grupować i jakie typy im nadać,
- wydatki nieregularne (prąd 4–5 razy w roku, ubezpieczenie auta, wakacje) nie mają jasnego miejsca w planie miesiąca; dotąd użytkownik odkładał stałą kwotę na inne konto.

> PYTANIE: jakie jeszcze inne kategorie z moich wydatkow? Jakie typy tych kategorii? Czy nie trzeba miec stalej listy kategorii? A co wykresami w ciagu miesiaca, lat? Chcialbym wiedzic ile i na co wydaje/zarabaim w jakims okresie

Wgląd (dlaczego dotąd tego nie było):
- kategorie należą do roku, więc porównanie lat wymaga łączenia ich po nazwie, a nazwy się zmieniają,
- priorytetem była kontrola miesiąca („do wydania”), analiza wstecz jest kolejnym krokiem,
- raporty na nieuporządkowanej liście kategorii byłyby mylące, więc porządek kategorii idzie razem z raportami.

Obecne obejście: stała kwota odkładana na inne konto na wydatki nieregularne oraz arkusz `budget_szablon.xlsx` (Secondary w `## Success Criteria`).

Koszt obejścia: co miesiąc użytkownik ręcznie odkłada stałą kwotę na inne konto, a plan miesiąca w aplikacji tych pieniędzy nie pokazuje. Do analizy wstecz (ile i na co w okresie) i do porównań lat wraca do arkusza albo liczy ręcznie.

## User & Persona

Jedna osoba prowadzi budżet całego domu: loguje się i wpisuje wszystko, także dochody i wydatki drugiej osoby. Raporty czyta ta sama osoba.

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

## User Stories

### US-01: Odczyt roku według kategorii

- **Given** budżet 2026 w wykonaniu z operacjami
- **When** użytkownik otwiera „Raporty” i wybiera rok 2026
- **Then** widzi przychody, wydatki i wynik oraz kategorie z kwotą i udziałem % (wykres i tabela)

Wcześniej: aplikacja nie miała widoku raportów; najbliżej były Podsumowanie miesiąca i kolumny wykonania w budżecie rocznym.

#### Acceptance Criteria
- Suma wydatków w raporcie równa się wykonaniu kategorii wydatków w budżecie rocznym 2026.

### US-02: Trend kategorii w roku

- **Given** budżet 2026 z operacjami styczeń–wrzesień
- **When** użytkownik w „Raportach” przełącza na trend 2026
- **Then** widzi kwoty kategorii i sumy w kolejnych miesiącach (wykres i tabela)

Wcześniej: porównanie miesięcy było możliwe tylko dla planów (porównanie planów miesięcy z rokiem), nie dla wykonania według kategorii.

#### Acceptance Criteria
- Suma miesięcy kategorii jest równa jej kwocie w raporcie roku.
- Wynik każdego miesiąca jest równy Wynikowi miesiąca (Month Result).

### US-03: Wydatek nieregularny w planie miesiąca

- **Given** prąd z planem rocznym 1800 zł, oznaczony w styczniu jako nieregularny
- **When** użytkownik otwiera plan lutego (bez płatności za prąd)
- **Then** prąd ma 150 zł w „do wydania”, a przy pozycji widać „zebrane” 300 zł

Wcześniej: prąd jako „jednorazowa” startował w Propozycji planu od 0, a jako równomierny rachunek obniżał „do wydania” skokiem w miesiącu płatności.

#### Acceptance Criteria
- „Zebrane” widać też w Podsumowaniu miesiąca.
- Salda kont i Spendable Liquidity się nie zmieniają.

### US-04: Płatność wydatku nieregularnego

- **Given** prąd z „zebranym” 450 zł w marcu (styczeń–marzec)
- **When** użytkownik importuje rachunek 600 zł
- **Then** „zebrane” spada do 0, a „do wydania” w marcu obniża się dodatkowo o 150 zł

Wcześniej: cała płatność obniżała „do wydania” w miesiącu płatności.

#### Acceptance Criteria
- Rachunek 430 zł: bez nadwyżki, „zebrane” zostaje 20 zł.
- Minione miesiące się nie zmieniają.

## Scope of Change

Raport pokazuje każdy odczyt jako wykres i tabelę (decyzja użytkownika; forma do ustalenia przy projekcie widoku).

Zakres i czas: cztery niezależne części (raport okresu, trend, kategoria przez lata, wydatki nieregularne w planie miesiąca), cel 8 tygodni po godzinach, zaakceptowany przez użytkownika 2026-11-04. Użytkownik odrzucił zmniejszenie zakresu. Po rundzie Socratic rok do roku, kategoria przez lata i przejście do operacji stały się nice-to-have; użytkownik zostawił 8 tygodni jako zapas.

### Raport okresu

- [new] FR-001: User can wybrać okres raportu: miesiąc albo rok. Priority: must-have.
  > Socratic: Kontrargument: własny zakres dat jest zbędny i kosztuje. Rozstrzygnięcie: tylko miesiąc i rok; własny zakres usunięty z FR.
- [new] FR-002: User can zobaczyć przychody, wydatki i wynik wybranego okresu. Priority: must-have.
  > Socratic: Kontrargument: dla jednego miesiąca to ta sama liczba co „Wynik” w Podsumowaniu miesiąca. Rozstrzygnięcie: raport liczy wynik tak samo jak Wynik miesiąca (Month Result), więc dla miesiąca obie liczby są równe.
- [new] FR-003: User can zobaczyć kategorie przychodów i wydatków z kwotą i udziałem % w wybranym okresie. Priority: must-have.
  > Socratic: Rozważono: 37 kategorii na jednym wykresie, zwroty obniżające kwotę, brak grup. Rozstrzygnięcie: bez kontrargumentu, zostaje jak zapisano.
- [new] FR-004: User can przejść z kategorii w raporcie do jej operacji w wybranym okresie. Priority: nice-to-have.
  > Socratic: Kontrargument: lista operacji już istnieje. Rozstrzygnięcie: raport prowadzi do strony Operacje z filtrem kategorii i okresu, bez nowej listy.

### Trend

- [new] FR-005: User can zobaczyć kwoty kategorii i sumy w kolejnych miesiącach wybranego roku. Priority: must-have.
  > Socratic: Rozważono: niepełny bieżący miesiąc, skoki przy wydatkach nieregularnych, brak planu obok wykonania. Rozstrzygnięcie: bez kontrargumentu, zostaje jak zapisano.
- [new] FR-006: User can porównać okres z tym samym okresem rok wcześniej. Priority: nice-to-have.
  > Socratic: Kontrargument: skoro rok do roku jest poza v1, łączenie kategorii między latami też może poczekać. Rozstrzygnięcie: FR-007, FR-008 i FR-009 przesunięte do nice-to-have.

### Kategoria przez lata

- [modified] FR-007: User can utworzyć nowy budżet, który kopiuje kategorie razem z ich powiązaniem z kategoriami poprzedniego roku. Priority: nice-to-have. (Było: nowy budżet kopiuje aktywne kategorie z flagami, bez powiązania między latami.)
  > Socratic: Kontrargument: istniejące budżety (2026 i mock 2025) powstały bez powiązań. Rozstrzygnięcie: powiązanie startowe po nazwie i typie, potem ręczna korekta.
- [modified] FR-008: User can zmienić nazwę kategorii bez zerwania porównania z innymi latami. Priority: nice-to-have. (Było: lata łączy nazwa i typ, więc zmiana nazwy zrywa porównanie.)
  > Socratic: Kontrargument: którą nazwę pokazuje raport, gdy nazwy w latach się różnią. Rozstrzygnięcie: raport pokazuje nazwę z najnowszego roku w okresie.
- [new] FR-009: User can ręcznie powiązać kategorię z kategorią z innego roku. Priority: nice-to-have.
  > Socratic: Kontrargument: podział i scalenie (np. „Telefon + Internet” rozbity na dwie kategorie). Rozstrzygnięcie: powiązanie tylko 1:1; podział zrywa porównanie.

### Wydatki nieregularne

- [new] FR-010: User can oznaczyć wydatek jako nieregularny: płacony kilka razy albo raz w roku. Priority: must-have.
  > Socratic: Kontrargument: może wystarczy flaga „jednorazowa”, ale w Propozycji planu startuje ona od 0, więc prąd znika z miesięcy bez płatności. Rozstrzygnięcie: potrzebny trzeci rodzaj albo nowa flaga; obecne flagi nie wystarczają.
- [modified] FR-011: User can zobaczyć miesięczną kwotę takiego wydatku w planie miesiąca i w „do wydania”. Priority: must-have. (Było: wydatek płacony kilka razy w roku jako „jednorazowa” startuje w Propozycji planu od 0, a jako równomierny rachunek obniża „do wydania” skokiem w miesiącu płatności.)
  > Socratic: Kontrargument: odjęcie miesięcznej kwoty, gdy nic nie płacisz, zostawia pieniądze na koncie, a „do wydania” mówi „nie wydawaj”. Rozstrzygnięcie: zebrane pieniądze muszą być widoczne jako „zebrane na …”; szczegóły w regule biznesowej.

### Zachowane

- [preserved] FR-012: User can korzystać z budżetu rocznego (plan początkowy, plan aktualny, wykonanie) jak dotąd. Priority: must-have.
  > Socratic: Kontrargument: nowe oznaczenie może zmienić „Pozostało w miesiącu” i pulę równomiernych w minionych miesiącach. Rozstrzygnięcie: budżet roczny i minione miesiące bez zmian; oznaczenie działa tylko na plan miesiąca.
- [preserved] FR-013: User can importować operacje z podpowiedziami kategorii jak dotąd. Priority: must-have.
  > Socratic: Kontrargument: porządkowanie danych (np. Prime Video do Subskrypcji) zmieni podpowiedzi zamierzenie. Rozstrzygnięcie: trafność porównywać na tych samych danych przed i po zmianie kodu.
- [preserved] FR-014: User can korzystać z istniejących budżetów, kategorii i operacji bez ręcznej migracji, a backup i restore obejmują nowe dane. Priority: must-have.
  > Socratic: Kontrargument: snapshot sprzed zmiany nie ma nowych pól. Rozstrzygnięcie: restore starego formatu działa i ustawia wartości domyślne (wydatek nie jest nieregularny).

### Poza aplikacją: lista kategorii (dane użytkownika)

Lista kategorii należy do użytkownika; aplikacja jej nie narzuca. To praca na danych, nie funkcja aplikacji. Decyzje użytkownika z tej sesji:
- Dodać: Subskrypcje (streaming, chmura: Prime Video, Disney+, Netflix, Google One), Eksploatacja auta (myjnie, drobne rzeczy; „Serwis samochodu” dla napraw i przeglądów), Sport (np. Decathlon), optyk i okulary do zdrowia.
- Nie dodawać: Podatki i urzędy, Kawa i słodycze na mieście.
- Wakacje: odkładać na hotel, zakwaterowanie, atrakcje — „to jednak koszt wakacji” (słowa użytkownika).
- Do poprawienia przez użytkownika w danych: rodzaj „rachunek” dla stałych opłat (dziś wszystkie „bieżąca”), literówki „Restaruacje”, „Subkrypcje AI”, błędnie przypisane operacje (Sinsay i Allegro w Artykułach spożywczych, Żabka w Przedszkolu), wypłata gotówki zaksięgowana jako wydatek (Rekolekcje, Planet Cash).

## Constraints & Compatibility

### Zachowane zachowanie

Nie mogą się zepsuć:
- Budżet roczny: plan początkowy, plan aktualny, wykonanie, „Pozostało w miesiącu” i pula równomiernych w minionych miesiącach. Oznaczenie „nieregularny” działa tylko na plan miesiąca (wyjątek: koniec roku obniża plan roczny o niewydane „zebrane”).
- Historia operacji i Import, w tym trafność podpowiedzi kategorii (porównywana na tych samych danych przed i po zmianie kodu).
- Salda kont, lokat, Spendable Liquidity i Total funds bez zmian.

Plan miesiąca i „do wydania” zmieniają się świadomie w zakresie wydatków nieregularnych; reszta reguły „do wydania” zostaje.

### Kompatybilność wsteczna

- `user:backup` i `user:restore`: format dostaje nową wersję i obejmuje nowe dane; snapshot w starym formacie nadal się wczytuje z wartościami domyślnymi.
- Zmiana reguły „do wydania” wymaga nowego ADR i wpisów w `context/foundation/glossary.md` przed kodem (wymóg wskazany przez użytkownika).

### Migracja danych

- Istniejące budżety, kategorie i operacje bez ręcznej migracji i bez zmiany kwot. Domyślnie żaden wydatek nie jest nieregularny.
- Dane 2025 tylko na devie (mock), do sprawdzenia trendu i późniejszego porównania rok do roku.

### Wycofanie zmiany

- Wycofanie to wdrożenie poprzedniej wersji aplikacji. Nowe dane (oznaczenie „nieregularny”, powiązania kategorii między latami) zostają nieużywane, a „do wydania” wraca do poprzedniej reguły. Snapshot w nowym formacie nie wczyta się w poprzedniej wersji, więc przed wdrożeniem trzeba zrobić `user:backup`.

### Istniejące integracje

- Import i snapshot `user:backup` / `user:restore` działają dalej na warunkach opisanych powyżej.

### Wymagania jakościowe

- Raport roku otwiera się w czasie do 2 sekund przy 10 000 operacji (obecne wymaganie aplikacji, `context/foundation/prd-mvp.md` sekcja 10).
- Kwoty w raporcie są co do grosza równe wykonaniu kategorii w budżecie rocznym i Wynikowi miesiąca dla tego samego okresu.
- Istniejące wymagania aplikacji obowiązują nowe widoki: izolacja danych użytkownika, kwoty bez obliczeń zmiennoprzecinkowych.

## Business Logic Changes

Wydatek nieregularny obniża „do wydania” co miesiąc o 1/12 swojego planu rocznego, a jego płatności pokrywa kwota zebrana do tej pory; „do wydania” w miesiącu płatności obniża tylko nadwyżka ponad zebrane.

Obecnie system liczy „do wydania” jako: przychody (plan, a po wpływie kwota faktyczna) − pozycje „rachunek” (plan, a po wpłacie kwota faktyczna) − pozycje „bieżąca” (większa z: plan, kwota faktyczna) − odkładam + dobieram (`context/foundation/glossary.md`, To Spend). Wydatek płacony kilka razy w roku nie pasuje: jako „jednorazowa” startuje w Propozycji planu od 0, a jako równomierny rachunek obniża „do wydania” skokiem w miesiącu płatności. Ta zmiana modyfikuje regułę: wydatek nieregularny (płacony kilka razy albo raz w roku, np. prąd, ubezpieczenie auta, wakacje, rekolekcje) wchodzi do „do wydania” kwotą 1/12 planu rocznego w każdym miesiącu. Ta kwota zwiększa „zebrane” tej pozycji już w tym miesiącu. Płatność najpierw zmniejsza „zebrane”; część płatności ponad „zebrane” obniża „do wydania” w miesiącu płatności, a „zebrane” spada do 0. Przykład: prąd, plan roczny 1800 zł → 150 zł co miesiąc; w marcu „zebrane” to 450 zł (styczeń–marzec), więc rachunek 430 zł nie obniża dodatkowo „do wydania” i zostaje 20 zł, a rachunek 600 zł obniża „do wydania” o 150 zł.

„Zebrane” widać przy pozycji w planie miesiąca i w Podsumowaniu miesiąca. Nie zmienia sald kont ani Spendable Liquidity, tak jak „odkładam”. Zbieranie zaczyna się od miesiąca, w którym użytkownik oznaczył wydatek jako nieregularny (zebrane startuje od 0); minione miesiące się nie zmieniają. Na koniec roku niewydane „zebrane” obniża plan roczny tej kategorii i przechodzi do wyniku roku.

Raport okresu (nowy odczyt, bez nowej reguły domenowej): kwoty kategorii w okresie to wykonanie z operacji datowanych w tym okresie, zwroty netto, bez transferów własnych i ruchów kapitału. Wynik miesiąca w raporcie jest równy Wynikowi miesiąca (Month Result). Suma roku jest równa wykonaniu w budżecie rocznym.

### Rozstrzygnięcia domenowe (2026-11-04)

- **Zmiana planu rocznego w trakcie roku:** od miesiąca zmiany kwota miesięczna to 1/12 nowego planu. Minione miesiące i dotychczas zebrane się nie zmieniają; brak dopłaty wychodzi jako nadwyżka w miesiącu płatności.
- **Nieregularny a obecne flagi:** „nieregularny” to trzeci rodzaj pozycji planu miesiąca obok „rachunek” i „bieżąca”. Flaga roczna równomierna / jednorazowa zostaje niezależna, więc budżet roczny, pula równomiernych i „Pozostało w miesiącu” się nie zmieniają. W Propozycji planu wydatek nieregularny ma zawsze 1/12 planu rocznego, także gdy jest jednorazowy.
- **„Zebrane” w miesiącu płatności** obejmuje już kwotę tego miesiąca.
- **Odrzucone:** wyrównanie kwoty do końca roku po zmianie planu (kwota skacze, odchodzi od „1/12”); osobna niezależna flaga (kombinacje z rachunkiem i bieżącą bez znaczenia); trzeci rodzaj flagi rocznej (zmienia pulę równomiernych, łamie FR-012).

## Access Control Changes

No access control changes — current model preserved.

Obecny model: konta tworzy administrator (`user:create`), samodzielna rejestracja jest wyłączona, każdy użytkownik widzi wyłącznie własne dane. Dostępne 2FA i passkeys.

## Non-Goals

- **Grupy kategorii i domyślna lista kategorii.** Lista kategorii jest danymi użytkownika; aplikacja jej nie narzuca i nie grupuje.
- **Oznaczanie wyjazdów.** Bez tagów ani oznaczeń operacji ponad kategorię (np. „Wakacje 2026”); koszt wakacji to kategoria Wakacje (hotel, zakwaterowanie, atrakcje).
- **Własny zakres dat i eksport raportu.** Okres to miesiąc albo rok; bez eksportu CSV ani PDF.
- **Pełna dostępność wykresu.** Wykres nie musi być w pełni dostępny dla czytników ekranu; dostępną formą tych samych danych jest tabela.
- **Wydruk raportu.** Bez układu raportu przygotowanego do druku, spójnie z brakiem eksportu.

## Open Questions

Brak otwartych pytań. Rozstrzygnięte przez użytkownika 2026-11-04:

1. **Zmiana planu rocznego w trakcie roku** — od miesiąca zmiany 1/12 nowego planu (`## Business Logic Changes`, Rozstrzygnięcia domenowe).
2. **Nieregularny a obecne flagi** — trzeci rodzaj pozycji planu miesiąca; flaga roczna niezależna (tamże).
3. **Historyjki dla trendu i wydatku nieregularnego** — dodane US-02, US-03, US-04.
4. **Plan wycofania zmiany** — poprzednia wersja aplikacji, backup przed wdrożeniem (`## Constraints & Compatibility`, Wycofanie zmiany).
5. **Koszt obecnego obejścia** — opisany w `## Problem Statement & Motivation`.
6. **Niefunkcjonalne non-goals** — pełna dostępność wykresu, wydruk raportu (`## Non-Goals`).
7. **„Zebrane” w miesiącu płatności** (luka znaleziona przy rozstrzyganiu) — obejmuje kwotę bieżącego miesiąca; przykład w regule poprawiony.
