---
project: 'Household Treasurer'
version: 3
status: draft
created: 2026-11-08
context_type: brownfield
product_type: web-app
target_scale:
    users: small
    qps: low
    data_volume: small
timeline_budget:
    delivery_weeks: 10
    hard_deadline: null
    after_hours_only: true
---

# Household Treasurer — PRD: zarządzanie kategoriami

Źródło: `context/foundation/shape-notes.md` (sesja 2026-11-07). Ten dokument zastępuje `context/foundation/prd-v2.md` (kategorie i raporty okresowe); poprzednie sesje shape są w `context/foundation/archive/`.

## Current System Overview

Household Treasurer to responsywna aplikacja webowa do budżetu domowego. Słownik: `context/foundation/glossary.md`; PRD planu miesiąca: `context/foundation/prd.md`; PRD kategorii i raportów (poprzednie podejście): `context/foundation/prd-v2.md`.

- **Architektura:** monolit Laravel z frontendem Inertia + Vue, baza PostgreSQL.
- **Użytkownicy:** konta tworzy administrator, dane każdego użytkownika są odizolowane. Faktycznie korzysta jedna osoba.
- **Funkcje dziś:** budżet roczny (plan początkowy, plan aktualny, wykonanie), plan miesiąca i „do wydania” z sekcją „odkładam / dobieram”, tempo, powiadomienia, Podsumowanie miesiąca, porównanie planów miesięcy z rokiem, operacje ręczne i Import CSV/XLSX z podpowiedziami kategorii, konta, transfery własne, lokaty, Dashboard, `user:backup` / `user:restore`.
- **Kategoria dziś** (`categories`: `budget_id`, `name`, `icon`, `color`, `sort_order`, `type`, `is_paced`, `month_item_kind`, kwoty planu i wykonania, `archived_at`): należy do jednego budżetu (roku). Typ: przychód albo wydatek. Wydatek ma dwie flagi: równomierna / jednorazowa (Paced / One-off) oraz rachunek / bieżąca (Month Item Kind). Można ją zarchiwizować; archiwalna zachowuje wykonanie w tym budżecie. Nowy budżet kopiuje aktywne kategorie z poprzedniego roku jako nowe rekordy (ADR-0014). Nie ma jednej tożsamości kategorii przez lata: lata łączy zapytanie po nazwie i typie (słownik, Category: _Avoid_ „a Category identity that spans years”); to łączenie działa w kopiowaniu budżetu, podpowiedziach Importu (ADR-0034), porównaniu planów miesięcy z rokiem, filtrze strony Operacje (ADR-0016) i Earmark. Nie ma grup kategorii, opisu ani ustawień poza flagami.
- **Raporty dziś:** brak widoku raportów i wykresów. Najbliżej: Podsumowanie miesiąca, porównanie planów miesięcy z rokiem, kolumny wykonania w budżecie rocznym.
- **Reguła „do wydania” dziś:** przychody (plan, a po wpływie kwota faktyczna) − pozycje „rachunek” (plan, a po wpłacie kwota faktyczna) − pozycje „bieżąca” (większa z: plan, kwota faktyczna) − odkładam + dobieram (`context/foundation/glossary.md`, To Spend; ADR-0033).

### Dotychczasowe arkusze użytkownika (odczyt 2026-11-07)

`storage/app/private/budget_szablon.xlsx` (szablon) i `storage/app/private/Budżet_2025.xlsx` (prawdziwy budżet 2025):

- Zakładka „Ustawienia”: rok oraz domyślna kwota miesięczna per pozycja (rata kredytu, czynsz, telefony, internet, „stawka m-c za prąd” 150, przedszkole 1 800, ubezpieczenie, codzienne wydatki 2 900, apteka 300, ubezpieczenie auta 50/mies., fundusz remontowy, paliwo 300, amortyzacja, oszczędności = 10 % dochodu, rozrywka).
- Zakładki miesięcy: **dwa poziomy** — grupa (Opłaty stałe, Uroda, Zdrowie, Auto i transport, Oszczędności, Rozrywka, Wydatki nieregularne) → pozycja (np. Zdrowie: Apteka, Badania, Dentysta – ortodoncja, Leczenie prywatne). Kolumny: plan, wydatek, „✓” gdy równe. Osobna tabela „Wydatki nieregularne” w miesiącu: kategoria | nazwa | plan | wydatek.
- Przychody: praca (2 osoby), 500+/800+, darowizny, odsetki, pozostałe, zwrot podatku.
- „Roczny”: 17 pozycji (pozycja → kwota/mies. → kwota/rok → wykonanie → %) — użytkownik planuje grubo, księguje drobno (~40 kategorii). Notatka „trzeba dodać listę grubszych”: kurs angielskiego, mikrus, ubezpieczenie mieszkania, domeny, wakacje.
- „Analiza”: miesiące × grupy (sumy). 2025: przychody 134 671, wydatki 134 852; Opłaty stałe 60 295, Codzienne 35 282, Wydatki nieregularne 24 009 (plan 15 000, 160 %), Zdrowie 6 568, Auto 4 680, Rozrywka 4 018.
- Tabela „Wydatki nieregularne” za 2025 po kategoriach: Ubrania 6 547 (45 zakupów w 9 mies.), Dom 6 443, Osobiste 2 285, Usługi 2 035, Prezenty 1 500, Edukacja 598, Darowizny 320, Książki 199, Dziecko 24, Praca 10.
- **Trzy worki odkładania** na osobnych kontach: „PNB sejf” (prąd 150/mies., ubezpieczenie auta 50/mies., serwis 100/mies.), „PNB lokacyjny / oszczędnościowy” (Fundusz Wydatków Nieregularnych: wakacje, prezenty, ubrania, wyposażenie, praca — **same zera w 2025**), „mBank oszcz.” (oszczędności długoterminowe = 10 % dochodu; IKE, nadpłata — nieużywane). „Oszczędności” prowadzi też poduszkę finansową, fundusz awaryjny, rezerwę w gotówce, lokaty — to konta i lokaty, które aplikacja już ma.
- „Media”: odczyty liczników i koszt prądu z taryf (137–165/mies.) do kalibracji stawki. „Janeczka”: składniki wynagrodzenia. Oba poza zakresem.

### Stan danych na devie

Jeden budżet 2026 w wykonaniu, około 1 000 operacji z Importu styczeń–wrzesień; kategorie z września i października (Żywność, Mieszkanie, Transport, Zdrowie, Inne, Pensja, druga Rozrywka, drugie Odsetki) to dane testowe. Wszystkie wydatki mają rodzaj „bieżąca”, także Kredyt, Czynsz, Ubezpieczenia. Literówki: „Restaruacje”, „Subkrypcje AI”. Prąd: 4 płatności w roku (marzec, maj, lipiec, sierpień). Mocka 2025 nie ma.

## Problem Statement & Motivation

Zmiana: jedna, zarządzana lista kategorii użytkownika (grupy, rytm, opis, archiwum) używana w całej aplikacji — budżetach, operacjach, Imporcie, planie miesiąca i raportach — zamiast kategorii kopiowanych co rok; na niej odczyt „na co wydaję” za miesiąc i rok z porównaniem lat oraz plan miesiąca, w którym wydatki płacone kilka razy w roku mają stałą miesięczną stawkę i „zebrane”, a pieniądze zostają na koncie głównym.

Ból / luka (wskazane przez użytkownika):

1. nie ma osobnej tabeli kategorii zarządzanej w jednym miejscu; kategoria należy do roku, a archiwizacja jest per budżet,
2. kategoria nie ma opisu („czym jest ta kategoria”) ani ustawień poza dwiema flagami,
3. nie wiadomo, czy wystarczą płaskie kategorie, czy potrzebne są grupy albo podplany jednej kategorii (do planu miesiąca),
4. brak wykresów i raportów: ile i na co w miesiącu, w roku, z porównaniem lat,
5. plan miesiąca: prąd płacony 4–5 razy w roku po dodaniu rachunku w listopadzie widnieje jako plan miesiąca pełną kwotą, a użytkownik chce odkładać stałą kwotę; dotąd przelewał ją na inne konto i nie chce już tego robić,
6. sekcja „odkładam / dobieram” nie zna celów (prąd, wakacje, cokolwiek, na co się zbiera) — po analizie budżetu 2025 zredukowane do „zebranego” przy kategoriach nieregularnych; cele długoterminowe poza zakresem.

Dlaczego teraz (słowa użytkownika): przy MVP taka była rekomendacja — początkowo wydawało się, że osobne kategorie nie są potrzebne; priorytetem była kontrola miesiąca, teraz czas na porządek.

Obecne obejście i jego koszt: arkusz Excela z grupami, domyślnymi kwotami i tabelą wydatków nieregularnych prowadzony obok aplikacji oraz przelewy drobnych stawek (prąd, OC, serwis) na osobne konto. Dopowiedzenia użytkownika: „jeśli chodzi o oszczędności długoterminowe to generalnie tylko odkładam jakieś 10 % z miesiąca; IKE i innych nie używam; funduszu wydatków nieregularnych nie używam — wydaję tyle, ile trzeba w ciągu każdego miesiąca na ubrania; w kontekście obecnej aplikacji uważam bardziej, aby pilnować całego roku, np. ubrania 10 000 zł na cały rok i może się zdarzyć, że jednego miesiąca odłożę tylko 100 zł, a następnego już 1 500”; „w przypadku prądu, rekolekcji, OC i innych nie chcę już przelewać na oddzielne konto tych drobnych kwot, tylko zostawić na koncie głównym — tam niech sobie będzie, aż wykorzystam w którymś miesiącu”.

Rodzaj zmiany: nowy moduł (zarządzanie kategoriami) + znacząca funkcja (raporty i wykresy) + zmiana reguły (plan miesiąca dla wydatków nieregularnych) + migracja danych (istniejące operacje i budżety na nową listę).

## User & Persona

Jedna osoba prowadzi budżet całego domu: loguje się i wpisuje wszystko, także dochody i wydatki drugiej osoby. Ta sama osoba zarządza listą kategorii i czyta raporty. Bez zmian.

## Success Criteria

### Primary

- Użytkownik przechodzi przepływ zmiany w aplikacji (kolejność wdrożenia: 1–2, potem 3–4, potem 5):
  1. otwiera widok „Kategorie”: jedna lista przez lata, z grupami, rytmem, opisem i sekcją archiwum,
  2. edytuje kategorię (grupa, rytm, opis) i widzi zmianę w budżecie, operacjach i Imporcie; istniejące budżety i ~1 000 operacji wskazują na tę listę bez ręcznej pracy,
  3. w planie miesiąca wydatek płacony kilka razy w roku (prąd) ma stałą miesięczną stawkę i „zebrane” zamiast kwoty rachunku,
  4. w miesiącu rachunku „do wydania” nie spada o cały rachunek, tylko o stawkę (i nadwyżkę ponad zebrane),
  5. otwiera „Raporty”, wybiera miesiąc albo rok, widzi wydatki według grup i kategorii i porównuje z rokiem poprzednim.
- Najmniejszy przyrost, który dowodzi, że zmiana działa: kroki 1–2 (lista kategorii + migracja istniejących danych). Bez niego raporty porównują lata po nazwie.

### Secondary

- Użytkownik nie potrzebuje już arkusza: ustawienia kwot, grupy i worki odkładania z Excela mają odpowiednik w aplikacji (plan roczny, grupy, rytm nieregularny).
- Prąd i podobne wydatki nie wymagają osobnego konta na odkładanie: stawka i „zebrane” są widoczne w aplikacji, pieniądze zostają na koncie głównym.
- Nowe funkcje powstają w zgodzie z design systemem (`context/foundation/design-system.md`), a braki design systemu, które przy tym wyjdą, są w nim poprawiane.

### Guardrails

- Kwoty: wykonanie kategorii, salda kont i „do wydania” nie zmieniają się przez migrację; żadna istniejąca operacja nie zmienia kwoty ani kategorii.
- Import CSV/XLSX działa, a podpowiedzi kategorii trafiają co najmniej tak dobrze jak przed zmianą.
- Budżet roczny i plan miesiąca działają po wdrożeniu bez ręcznej migracji: użytkownik otwiera 2026 i wszystko jest na miejscu.
- `user:backup` i `user:restore` obejmują nową listę kategorii; stary snapshot nadal się wczytuje.
- Dane, które już istnieją (budżety, kategorie, operacje, konta, lokaty), przechodzą w całości.
- Kwoty w raporcie, planie miesiąca i Budżecie są co do grosza równe dla tego samego okresu: suma kategorii raportu za rok = wykonanie Budżetu, wynik miesiąca raportu = Wynik miesiąca; suma grupy = suma jej kategorii.
- Dotychczasowe wymagania aplikacji obowiązują nowe widoki: raport roku otwiera się w czasie do 2 sekund przy 10 000 operacji (`context/foundation/prd-mvp.md`, sekcja 10), izolacja danych użytkownika, kwoty bez obliczeń zmiennoprzecinkowych.

Najszybszy sygnał awarii (wskazany przez użytkownika): zmienione kwoty lub kategorie istniejących operacji; gorsze podpowiedzi Importu; budżet 2026 wymagający ręcznego przypisania kategorii.

## User Stories

### US-01: Jedna lista kategorii po migracji

- **Given** budżet 2026 w wykonaniu z ~1 000 operacji i kategoriami z flagami
- **When** użytkownik po wdrożeniu otwiera „Kategorie”
- **Then** widzi jedną listę kategorii z rytmem nadanym z flag, może nadać grupy i opisy, a budżet 2026, operacje i Import działają bez ręcznej pracy

Wcześniej: kategorie należały do budżetu 2026, nie było widoku „Kategorie”, grup, rytmu ani opisu.

#### Acceptance Criteria

- Wykonanie każdej kategorii budżetu 2026 i salda kont są co do grosza takie jak przed migracją.
- Żadna operacja nie zmieniła kategorii ani kwoty.

### US-02: Prąd jako wydatek nieregularny

- **Given** kategoria Prąd oznaczona jako nieregularna, plan roczny 1 800, zebrane 300 na 1 lipca
- **When** 12 lipca Import księguje rachunek 400 na Prąd
- **Then** w planie lipca Prąd pokazuje stawkę 150, rachunek 400 i zebrane 50; „do wydania” lipca jest obniżone o 150, nie o 400; saldo konta głównego spadło o 400

Wcześniej: Prąd jako „bieżąca” startował od ostatniej kwoty i obniżał „do wydania” lipca o cały rachunek; stawkę użytkownik przelewał na osobne konto.

#### Acceptance Criteria

- Przy rachunku 600 „do wydania” lipca jest obniżone o 300 (stawka 150 + nadwyżka 150), zebrane 0, wiersz oznaczony jako przekroczony.
- Wynik lipca i raport roku liczą 400 (faktyczny rachunek), nie 150.

## Scope of Change

Zakres i czas: cztery części — moduł zarządzania kategoriami, migracja istniejących danych, zmiana reguły planu miesiąca (wydatki nieregularne), raporty i wykresy — w jednym PRD, cel 10 tygodni po godzinach, zaakceptowany przez użytkownika 2026-11-07. Użytkownik odrzucił zmniejszenie zakresu do samego modułu kategorii; wdrożenie idzie wycinkami w kolejności 1–2 → 3–4 → 5. Priorytety: nice-to-have to FR-017 (trend), FR-018 (przejście do operacji), FR-024 (procent „odkładam”), FR-025 (ostatnie 12 miesięcy); reszta must-have.

### Decyzje strukturalne

1. **Struktura: dwa poziomy.** Grupa (opcjonalna, ma typ przychód/wydatek, bez ustawień i bez planu, tylko sumuje w planie miesiąca, Budżecie i raportach) → kategoria. Operacja zawsze trafia na kategorię. „Podplany” to kategorie w grupie (Przedszkole → Jan / Adam / wydarzenia; Praca → Sebastian / Jana). Kredyt czy Czynsz mogą być bez grupy. Plan roczny jest na kategorii; grupa go sumuje (pula „Zmienne” jak „Wydatki nieregularne” w Excelu).
2. **Ustawienia kategorii: rytm w planie miesiąca + opis** (poza dzisiejszymi: nazwa, ikona, kolor, kolejność, typ, archiwum). Rytm zastępuje flagi równomierna/jednorazowa i rachunek/bieżąca („trzeba scalić wszystko, aby to działało dobrze”). Domyślna kwota miesięczna niepotrzebna: wynika z planu rocznego.
3. **Cztery rytmy** (przychód bez rytmu):

   | Rytm | Przykłady z 2025 | Kwota w propozycji planu miesiąca | Dziś |
   |---|---|---|---|
   | stała opłata | kredyt, czynsz, telefon, internet, przedszkole, ubezpieczenia | ostatnia kwota; po zapłacie faktyczna | „rachunek” |
   | bieżąca | spożywcze 2 900, apteka, bilety i parking, paliwo, rozrywka, restauracje | limit na miesiąc, wiele transakcji | „bieżąca” |
   | równomierna roczna | ubrania, dom, osobiste, usługi, prezenty, edukacja, książki, elektronika | plan roczny ÷ 12, obok „zostało X na Y mies.” | „równomierna” + „Pozostało w miesiącu” |
   | nieregularna ze stawką | prąd, OC, serwis, rekolekcje, wakacje, domeny, ubezpieczenie mieszkania | stawka = plan roczny ÷ 12; „zebrane”; rachunek zużywa zebrane | brak |

   Bieżąca i równomierna roczna to dwa rytmy; różni je sygnał przekroczenia: bieżąca wobec miesiąca, równomierna wobec roku (+ odczyt „zostało X na Y mies.”).
4. **Archiwum globalne z przywracaniem.** Zarchiwizowana kategoria znika z wyboru (operacje, plan, nowy budżet), zostaje w historii, budżetach i raportach lat, w których miała wykonanie; na istniejącej operacji pozostaje i jest widoczna w selektorze tylko wtedy, gdy już jest wybrana.
5. **Cele:** „zebrane” tylko przy kategoriach nieregularnych; sekcja „odkładam / dobieram” bez zmian (użytkownik odkłada ~10 % dochodu; domyślny procent jako nice-to-have); bez celów długoterminowych i bez nazwanych pozycji odkładania.
6. **Pieniądze nieregularnych zostają na koncie głównym.** „Zebrane” to odczyt w planie, nie przelew; salda i Spendable Liquidity bez zmian. Bez osobnego odczytu „w tym zebrane” przy saldzie — zebrane tylko w wierszach kategorii.
7. **Miesiąc rachunku** (lipiec, stawka 150, zebrane 300 + 150, rachunek 400): „do wydania” obniża się tylko o stawkę; rachunek zużywa zebrane (450 → 50); przekroczenie to rachunek ponad zebrane (600 → nadwyżka 150 obniża „do wydania”), nie ponad stawkę. Wynik miesiąca i raport liczą faktyczny rachunek.
8. **Start zebranego:** od 0 w miesiącu oznaczenia kategorii jako nieregularnej, plus ręczne „zebrane startowe” (np. 300 z dotychczasowego sejfu); minione miesiące bez zmian.
9. **31 grudnia:** niewydane zebrane przechodzi na styczeń jako zebrane startowe (rezerwa na zobowiązanie, które trwa; stan jak saldo, nie strumień); stawka nowego roku = plan roczny ÷ 12; użytkownik koryguje plan, gdy bufor rośnie.
10. **Szkice zaakceptowane:** plan miesiąca z grupami (zwijane, suma grupy, sygnał przekroczenia na wierszu grupy) i rytmem w wierszu; widok „Kategorie” bez kwot (plan roczny zostaje w Budżecie, bo ta sama kategoria ma inny plan w każdym roku); nowy budżet bierze aktywne kategorie z listy, kwoty podpowiada z planu aktualnego poprzedniego roku; raport po grupach z rozwinięciem do kategorii i porównaniem z rokiem poprzednim; Budżet roczny też grupuje.

### Lista kategorii

- [new] FR-001: User can zobaczyć w widoku „Kategorie” jedną listę kategorii przez lata: grupy, kategorie z rytmem i opisem, sekcję archiwum. Priority: must-have.
  > Socratic: Rozważono: trzecie miejsce z kategoriami, martwy ekran bez kwot, 40 kategorii na telefonie. Rozstrzygnięcie: bez kontrargumentu, zostaje jak zapisano.
- [modified] FR-002: User can utworzyć kategorię i edytować jej nazwę, ikonę, kolor, kolejność, typ, grupę, rytm i opis. Priority: must-have. (Było: kategoria w jednym budżecie z nazwą, ikoną, kolorem, kolejnością, typem i dwiema flagami; bez grupy, rytmu i opisu.)
  > Socratic: Kontrargument: zmiana typu (przychód ↔ wydatek) kategorii z operacjami łamie sumy. Rozstrzygnięcie: zmiana typu zablokowana, gdy kategoria ma jakąkolwiek operację w dowolnym roku.
- [modified] FR-003: User can zarchiwizować kategorię i ją przywrócić; archiwalna znika z wyboru w operacjach, planie i nowym budżecie, a zostaje w historii i raportach. Priority: must-have. (Było: archiwizacja per budżet.)
  > Socratic: Rozważono: archiwalna z planem w bieżącym budżecie, brak „usuń”, przywrócenie z historią. Rozstrzygnięcie: bez kontrargumentu, zostaje jak zapisano (porządki literówek i danych testowych załatwia FR-023).
- [new] FR-004: User can utworzyć, nazwać, uporządkować i usunąć grupę kategorii; grupa jest opcjonalna, ma typ (przychód albo wydatek), nie ma planu ani ustawień, tylko sumuje. Priority: must-have.
  > Socratic: Kontrargument: grupy także dla przychodów? Rozstrzygnięcie: tak, dla obu typów; grupa ma typ i nie miesza kategorii różnych typów.
- [modified] FR-005: User can utworzyć nowy budżet, który bierze aktywne kategorie z listy (zamiast kopiować kategorie poprzedniego roku) i podpowiada kwoty planu z planu aktualnego poprzedniego roku. Priority: must-have. (Było: nowy budżet kopiuje aktywne kategorie z poprzedniego roku jako nowe kategorie tego budżetu, ADR-0014.)
  > Socratic: Kontrargument: „pyta tylko o kwoty” to 40 pustych pól. Rozstrzygnięcie: kwoty podpowiadane z planu aktualnego poprzedniego roku (jak dziś przy kopiowaniu); FR zaktualizowany.

### Migracja istniejących danych

- [new] FR-006: User can korzystać po wdrożeniu z istniejących budżetów, kategorii i operacji bez ręcznej pracy: kategorie o tej samej nazwie i typie w różnych latach stają się jedną kategorią listy, a kwoty i przypisania operacji nie zmieniają się. Priority: must-have.
  > Socratic: Kontrargument: literówki („Restaruacje” / „Restauracje”) nie scalą się automatycznie. Rozstrzygnięcie: ręczne scalanie kategorii po migracji — nowy FR-023 (must-have).
- [new] FR-007: User can zobaczyć rytm nadany istniejącym kategoriom automatycznie z dzisiejszych flag i go poprawić. Priority: must-have.
  > Socratic: Rozważono: wszystko jest dziś „bieżąca”, mapowanie zmienia plan bieżącego miesiąca. Rozstrzygnięcie: bez kontrargumentu, zostaje jak zapisano.

### Plan miesiąca

- [modified] FR-008: User can zobaczyć w planie miesiąca kategorie w grupach z sumą grupy (zwijane) oraz kategorie bez grupy osobno; sygnał przekroczenia kategorii jest widoczny także na wierszu zwiniętej grupy. Priority: must-have. (Było: plan miesiąca bez grup.)
  > Socratic: Kontrargument: zwinięta grupa ukrywa przekroczenie. Rozstrzygnięcie: sygnał „ponad plan” na wierszu grupy; FR zaktualizowany.
- [modified] FR-009: User can zobaczyć propozycję planu, w której kwota kategorii wynika z rytmu: stała opłata — ostatnia kwota; bieżąca — limit miesięczny; równomierna roczna — plan roczny ÷ 12 z odczytem „zostało X na Y mies.”; nieregularna — plan roczny ÷ 12. Priority: must-have. (Było: propozycja wynika z flag; wydatek płacony kilka razy w roku jako „bieżąca” startuje od ostatniej kwoty, jako „jednorazowa” od 0.)
  > Socratic: Kontrargument: „reszta roku ÷ pozostałe miesiące” skacze (1 500 we wrześniu → 200 w październiku). Rozstrzygnięcie: plan ÷ 12 z informacją o reszcie roku; FR zaktualizowany.
- [new] FR-010: User can oznaczyć kategorię jako nieregularną, podać zebrane startowe i zobaczyć przy niej stawkę oraz „zebrane”; „do wydania” obniża się o stawkę w każdym miesiącu, także bez rachunku. Priority: must-have.
  > Socratic: Kontrargument: od kiedy liczy się zebrane przy oznaczeniu w listopadzie. Rozstrzygnięcie: od 0 w miesiącu oznaczenia plus ręczne zebrane startowe; minione miesiące bez zmian; FR zaktualizowany.
- [new] FR-011: User can zaksięgować rachunek na kategorię nieregularną tak, że zużywa on „zebrane”, a „do wydania” obniża tylko nadwyżka ponad zebrane; przekroczenie to rachunek ponad zebrane. Priority: must-have.
  > Socratic: Kontrargument: zwrot, edycja lub usunięcie rachunku. Rozstrzygnięcie: zebrane jest odczytem liczonym z operacji (zwroty netto) i planów, nie zapisaną liczbą, więc każda korekta przelicza je sama.
- [new] FR-012: User can zobaczyć w nowym budżecie zebrane startowe kategorii nieregularnej przeniesione z 31 grudnia. Priority: must-have.
  > Socratic: Kontrargument: budżet 2027 tworzony w grudniu, gdy zebrane jeszcze się zmienia. Rozstrzygnięcie: zebrane startowe to odczyt stanu na 31 grudnia, nie liczba zapisana przy tworzeniu budżetu.
- [preserved] FR-013: User can korzystać z „odkładam / dobieram” jak dotąd. Priority: must-have.
  > Socratic: Kontrargument: 10 % dochodu to ustawienie, nie ręczny wpis. Rozstrzygnięcie: nowy FR-024 (nice-to-have); FR-013 bez zmian.

### Raporty

- [new] FR-014: User can wybrać okres raportu: miesiąc albo rok. Priority: must-have.
  > Socratic: Kontrargument: brak okresu „ostatnie 12 miesięcy”. Rozstrzygnięcie: nowy FR-025 (nice-to-have); v1 miesiąc i rok.
- [new] FR-015: User can zobaczyć przychody, wydatki, wynik oraz grupy i kategorie z kwotą (i udziałem % dla wydatków) w wybranym okresie, jako wykres i tabelę, z rozwinięciem grupy do kategorii. Priority: must-have.
  > Socratic: Kontrargument: udział % przychodów bez sensu. Rozstrzygnięcie: udział % tylko dla wydatków; FR zaktualizowany.
- [new] FR-016: User can porównać okres z tym samym okresem rok wcześniej (kwota, różnica) na tej samej kategorii listy. Priority: must-have.
  > Socratic: Kontrargument: 2025 jest w Excelu, nie w aplikacji. Rozstrzygnięcie: mock 2025 tylko na devie (seeder); na produkcji porównanie lat działa od 2027.
- [new] FR-017: User can zobaczyć kwoty grup i kategorii w kolejnych miesiącach roku (trend). Priority: nice-to-have.
  > Socratic: Kontrargument: sezonowość nieregularnych (0, 0, 430, 0) psuje wykres. Rozstrzygnięcie: trend domyślnie po grupach, rozwinięcie do kategorii na życzenie.
- [new] FR-018: User can przejść z kategorii w raporcie do jej operacji w wybranym okresie. Priority: nice-to-have.
  > Socratic: Kontrargument: strona Operacje filtruje po nazwie i typie (ADR-0016). Rozstrzygnięcie: filtr po kategorii listy (i grupie); zmiana ADR-0016.

### Zachowane i dostosowane

- [modified] FR-019: User can wybrać kategorię operacji spośród aktywnych kategorii listy (w grupach); kategoria archiwalna na istniejącej operacji pozostaje i jest widoczna w selektorze tylko wtedy, gdy jest już wybrana. Priority: must-have. (Było: wybór spośród kategorii budżetu roku operacji, bez grup.)
  > Socratic: Kontrargument: edycja starej operacji na kategorii archiwalnej. Rozstrzygnięcie: kategoria zostaje, formularz nie wymusza zmiany; FR zaktualizowany.
- [preserved] FR-020: User can importować operacje z podpowiedziami kategorii jak dotąd; podpowiedź wskazuje kategorię listy, trafność nie gorsza niż przed zmianą. Priority: must-have.
  > Socratic: Rozważono: pomiar „nie gorzej”, uczenie z wielu lat (ADR-0034). Rozstrzygnięcie: bez kontrargumentu, zostaje jak zapisano.
- [modified] FR-021: User can korzystać z budżetu rocznego (plan początkowy, plan aktualny, wykonanie) jak dotąd, z kategoriami w grupach i sumą grupy; sumy całości nie zmieniają się przez migrację. Priority: must-have. (Było: budżet roczny bez grup.)
  > Socratic: Kontrargument: Budżet bez grup byłby jedynym widokiem bez nich. Rozstrzygnięcie: Budżet też grupuje; zmiana z preserved na modified.
- [modified] FR-022: User can wykonać `user:backup` i `user:restore` obejmujące listę kategorii, grupy, rytm, opis, zebrane startowe i miesiąc oznaczenia; snapshot w starym formacie nadal się wczytuje. Priority: must-have. (Było: snapshot z kategoriami per budżet, bez listy, grup, rytmu i zebranego.)
  > Socratic: Rozważono: zebrane jako odczyt, restore starego formatu = ta sama migracja. Rozstrzygnięcie: bez kontrargumentu, zostaje jak zapisano.

### Dodane w rundzie Sokratesa

- [new] FR-023: User can scalić dwie kategorie listy w jedną: operacje, plany i zebrane przechodzą na kategorię docelową, po potwierdzeniu z podglądem liczb (ile operacji, jakie plany). Priority: must-have.
  > Socratic: Kontrargument: nieodwracalne. Rozstrzygnięcie: potwierdzenie z podglądem liczb przed scaleniem; FR zaktualizowany.
- [new] FR-024: User can ustawić domyślny procent planowanych przychodów, który propozycja planu wpisuje jako „odkładam”. Priority: nice-to-have.
  > Socratic: Wynika z FR-013; bez osobnego wyzwania.
- [new] FR-025: User can wybrać okres raportu „ostatnie 12 miesięcy”. Priority: nice-to-have.
  > Socratic: Wynika z FR-014; bez osobnego wyzwania.

### Usunięte

- [removed] Flagi równomierna / jednorazowa (Paced / One-off) i rachunek / bieżąca (Month Item Kind) jako osobne ustawienia kategorii — zastępuje je rytm (decyzja strukturalna 2).
- [removed] Kopiowanie kategorii poprzedniego roku przy tworzeniu budżetu — nowy budżet bierze aktywne kategorie z listy (FR-005).

## Constraints & Compatibility

### Zachowane zachowanie

Nie mogą się zepsuć:

- Kwoty: wykonanie każdej kategorii, salda kont i lokat, Spendable Liquidity, Total funds, „do wydania” zatwierdzonych planów minionych miesięcy — co do grosza jak przed migracją. Żadna operacja nie zmienia kwoty, daty, konta ani kategorii (kategoria dostaje nową tożsamość listy, ale tę samą nazwę i typ).
- Budżet roczny: plan początkowy, plan aktualny, wykonanie, „Pozostało w miesiącu” minionych miesięcy. Rytm działa na propozycje i „do wydania” od miesiąca, w którym został nadany lub zmieniony; minione miesiące bez zmian.
- Import: formaty, duplikaty, podpowiedzi kategorii (trafność mierzona na tych samych danych przed i po zmianie kodu; porządki w danych kończą się przed pomiarem „przed”).
- Plan miesiąca: zatwierdzanie, „odkładam / dobieram”, tempo, powiadomienia, Podsumowanie miesiąca.

### Kompatybilność wsteczna formatu danych

- `user:backup` / `user:restore`: nowa wersja formatu obejmuje listę kategorii, grupy, rytm, opis, zebrane startowe i miesiąc oznaczenia; snapshot w starym formacie wczytuje się przez tę samą migrację co wdrożenie (nazwa + typ → kategoria listy, rytm z flag).

### Migracja danych (wskazane przez użytkownika)

- Automatyczny `user:backup` każdego użytkownika przed migracją.
- Liczby kontrolne po migracji: suma wykonania per budżet i per kategoria, liczba operacji, salda kont — porównane przed/po i zapisane w logu; różnica zatrzymuje wdrożenie.
- Powrót do wersji sprzed (rollback): migracja odwracalna albo przywrócenie ze snapshotu bez utraty operacji dodanych po wdrożeniu.
- Kategorie o tej samej nazwie i typie w różnych budżetach stają się jedną kategorią listy; rytm z flag (mapowanie do ustalenia w ADR; dziś wszystkie wydatki są „bieżąca”); żadna kategoria nie jest nieregularna domyślnie; grupy puste po migracji.
- Produkcja ma dane, których nie wolno stracić: migracja trafia na produkcję dopiero po próbie na kopii danych produkcyjnych; bez okna serwisowego, ale bez utraty operacji.

### Wymogi istniejącego systemu

- Zmiana reguły „do wydania”, zasady „kategoria należy do jednego budżetu” (słownik: Category, _Avoid_ „a Category identity that spans years”) i kopiowania kategorii (ADR-0014) wymaga wpisów w `context/foundation/glossary.md` i nowych ADR przed kodem; dotknięte też ADR-0016 (filtr Operacji), ADR-0034 (podpowiedzi Importu), ADR-0024/0033 (pula równomiernych, plan miesiąca).
- Dane 2025 tylko w środowisku deweloperskim, do sprawdzenia porównania lat; produkcja porównuje lata od 2027.
- Każdy nowy widok (Kategorie, Raporty) i zmieniony (plan miesiąca, Budżet, selektor kategorii) jest zgodny z design systemem (`context/foundation/design-system.md`) w każdym swoim stanie; brak w design systemie, który przy tym wyjdzie, jest poprawiany w nim (Secondary).

## Business Logic Changes

Kategoria nieregularna obniża „do wydania” co miesiąc o stawkę (plan roczny ÷ 12), która powiększa jej zebrane; rachunek zużywa zebrane, a „do wydania” miesiąca obniża tylko nadwyżka rachunku ponad zebrane.

Obecnie system liczy „do wydania” jako: przychody (plan, a po wpływie kwota faktyczna) − pozycje „rachunek” (plan, a po wpłacie kwota faktyczna) − pozycje „bieżąca” (większa z: plan, kwota faktyczna) − odkładam + dobieram (`context/foundation/glossary.md`, To Spend; ADR-0033). Wydatek płacony kilka razy w roku nie pasuje: jako „bieżąca” startuje od ostatniej kwoty i obniża „do wydania” skokiem w miesiącu rachunku, jako „jednorazowa” startuje od 0 i znika z miesięcy bez płatności.

Ta zmiana modyfikuje regułę przez rytm kategorii (zastępuje flagi równomierna/jednorazowa i rachunek/bieżąca):

- **stała opłata** — jak dziś „rachunek”: plan, a po pierwszej płatności kwota faktyczna;
- **bieżąca** — jak dziś „bieżąca”: większa z planu i kwoty faktycznej; przekroczenie, gdy miesiąc > limit;
- **równomierna roczna** — jak bieżąca w „do wydania”, ale propozycja to plan roczny ÷ 12 z odczytem „zostało X na Y mies.”, a przekroczenie liczy się wobec planu rocznego, nie miesiąca;
- **nieregularna ze stawką** — do „do wydania” wchodzi stawka (plan roczny ÷ 12) w każdym miesiącu; stawka powiększa zebrane; rachunek (operacja na tej kategorii, zwroty netto) zmniejsza zebrane; część rachunku ponad zebrane (łącznie ze stawką bieżącego miesiąca) obniża „do wydania” tego miesiąca i jest przekroczeniem. Przykład: plan 1 800 → 150/mies.; zebrane 300 na 1 lipca; rachunek 400 → zebrane 50, „do wydania” lipca niżej tylko o 150; rachunek 600 → zebrane 0, „do wydania” niżej o 300.

Zebrane jest odczytem (nie zapisaną liczbą): zebrane startowe (ręczne przy oznaczeniu, albo stan na 31 grudnia poprzedniego roku) + suma stawek od miesiąca oznaczenia − rachunki (zwroty netto), nie mniej niż 0. Widać je przy wierszu kategorii w planie miesiąca i w Podsumowaniu miesiąca. Nie zmienia sald kont ani Spendable Liquidity; pieniądze zostają na koncie głównym. Na 31 grudnia zebrane przechodzi na nowy rok; stawka nowego roku = nowy plan roczny ÷ 12.

Grupa nie ma reguły: sumuje plan i wykonanie swoich kategorii w planie miesiąca, Budżecie i raportach; sygnał przekroczenia kategorii pokazuje się także na wierszu grupy.

Raport okresu i porównanie lat to nowy odczyt bez nowej reguły: kwoty kategorii w okresie to wykonanie z operacji datowanych w tym okresie, zwroty netto, bez transferów własnych i ruchów kapitału; wynik miesiąca równa się Wynikowi miesiąca (Month Result), suma roku równa się wykonaniu w Budżecie. Porównanie lat działa po kategorii listy (tej samej tożsamości), nie po nazwie.

## Access Control Changes

No access control changes — current model preserved.

Obecny model: konta tworzy administrator (`user:create`), samodzielna rejestracja jest wyłączona, każdy użytkownik widzi wyłącznie własne dane. Dostępne 2FA i passkeys. Lista kategorii i grupy są prywatne per użytkownik jak reszta danych.

## Non-Goals

- **Plan na grupie i więcej niż dwa poziomy.** Grupa tylko sumuje kategorie; bez limitu grupy, bez podgrup. Pula „Zmienne” to suma planów jej kategorii.
- **Import arkusza budżetu, liczniki mediów, wynagrodzenia.** Zakładki „Roczny”, „Media” i „Janeczka” nie mają odpowiednika w aplikacji; dane 2025 wchodzą tylko jako mock na devie.
- **Cele długoterminowe i nazwane pozycje odkładania.** IKE, poduszka, fundusz awaryjny jako byty z kwotą docelową — użytkownik ich nie używa („generalnie tylko odkładam jakieś 10 % z miesiąca”); „odkładam / dobieram” zostaje jedną kwotą.
- **Funkcje nice-to-have poza must-have zakresem tej zmiany:** trend (FR-017), przejście z raportu do operacji (FR-018), domyślny procent „odkładam” (FR-024), okres „ostatnie 12 miesięcy” (FR-025) — powstają po wycinkach must-have.

## Open Questions

1. **Mapowanie dzisiejszych flag na rytm przy migracji.** „Rachunek” → stała opłata jest jasne; „bieżąca” równomierna → bieżąca czy równomierna roczna, „jednorazowa” → równomierna roczna czy nieregularna? Na devie wszystkie wydatki są „bieżąca”. — Owner: użytkownik, przy ADR rytmu; FR-007 i tak pozwala poprawić.
2. **Słownictwo UI dla stawki i zebranego.** Wiersz nieregularny mówi dziś w szkicach „odkładam 150 · zebrane 450”, a sekcja na dole to też „odkładam”; nazwy polskie i angielskie (słownik) do ustalenia w ADR. — Owner: użytkownik.
3. **Pojęcia z dokumentów, których nie ma w kodzie** (Earmark, Reserve, Year Close, Cash Expectation) są zdefiniowane na „kategorii należącej do budżetu” i łączeniu po nazwie. Czy przepisać ich definicje w słowniku w tej zmianie, czy oznaczyć jako do rewizji? — Owner: użytkownik, przy ADR tożsamości kategorii.
4. **Porządek w danych przed migracją.** Literówki, dane testowe z września–października, błędnie przypisane operacje (Prime Video w Rozrywce, Sinsay w Artykułach spożywczych) — czy użytkownik sprząta przed wdrożeniem, czy po, przez FR-023 i zmianę kategorii operacji? Wpływa na pomiar trafności Importu „przed”. — Owner: użytkownik.
