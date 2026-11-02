---
project: 'Household Treasurer'
version: 1
status: draft
created: 2026-11-01
context_type: brownfield
product_type: web-app
target_scale:
    users: small
    qps: low
    data_volume: small
timeline_budget:
    delivery_weeks: 6
    hard_deadline: null
    after_hours_only: true
---

# Household Treasurer — PRD: kontrola budżetu w skali miesiąca

## Current System Overview

Household Treasurer to responsywna aplikacja webowa do planowania rocznego budżetu domowego, rejestrowania jego wykonania i kontroli środków na kontach oraz lokatach. Szczegóły: `.docs/prd.md`, słownik: `CONTEXT.md`.

- **Architektura:** monolit Laravel z frontendem Inertia + Vue.
- **Stack (`.docs/tech-stack.md`):** Laravel 13, PostgreSQL, Vue 3 + TypeScript, Inertia 3, Vite, Tailwind CSS + shadcn-vue, Pest, Docker.
- **Użytkownicy:** konta tworzone przez administratora, dane każdego użytkownika są odizolowane. Faktycznie korzysta jedna osoba.
- **Funkcje dziś:** budżet roczny (plan początkowy, plan aktualny, wykonanie, kategorie Paced i One-off), operacje ręczne i Import CSV/XLSX, konta, transfery własne, lokaty i ruchy kapitału, Dashboard.
- **Odczyty miesięczne dziś:** `Wynik` (Month Result: dochody minus wydatki zaksięgowane w miesiącu) i `Pozostało w miesiącu` (Month Remaining: bieżąca część rocznego planu kategorii równomiernych).
- **Kwoty dziś:** ewidencjonowane z dokładnością do jednego grosza, bez obliczeń zmiennoprzecinkowych (`.docs/prd.md`, sekcja 5 i BR-02).
- **Wyszukane w repo:** Agenda i Cash Expectation są opisane w `CONTEXT.md` i `.docs/specs/agenda-cash-expectations.md`, ale kod jest tylko na niezmergowanych gałęziach (ostatni commit 2026-09-14). Reserve i Year Close są opisane w dokumentach, bez implementacji. Dokumentację należy traktować ostrożnie: opisuje więcej niż kod.

## Problem Statement & Motivation

Zmiana: dodać kontrolę budżetu w skali miesiąca, bo budżet roczny nie pozwala w pełni kontrolować wydatków w codzienności. Użytkownik chce wiedzieć, ile jest jeszcze do wydania w danym miesiącu, tak aby zasadniczo nie wydawać więcej, niż zarobiono. Nie jest to zasada bez odstępstw: czasem trzeba coś odłożyć albo wydać więcej w miesiącu.

Użytkownik ocenia, że obecne odczyty `Wynik` i `Pozostało w miesiącu` są bardzo nieścisłe, opierają się tylko na założeniach i nie informują wyraźnie, ile jest do wydania.

Dlaczego teraz: pozostałe potrzebne elementy aplikacji już istnieją, a to jest brakujący element.

> chcę rozwijać dalej aplikacje - mam teraz wiele elementow, ktore chcialem: kontrolowanei budżetu rocznego, dodawanie operacji, zarządzanie kontami i lokatami; brakuje mi tylko jednego - aby wlasciciwie pilnować budzetu w ciagu miesiaca, bo na rocznym budzecie ciezko sie zorientowac czy moge jeszce cos lub nie; brakuje mi czegos w ciagu miesiaca - tak aby zasadniczo nie wydawac wiecej niz sie zarobilo - choc to nie powinna byc zasada bez odstepstw bo czasami trzeba cos odlozyc, albow wydac wiecej w ciagu miesiaca. Do tej pory uzywam czegos takiego storage/app/private/budget_szablon.xlsx

Obecne obejście: osobny arkusz `budget_szablon.xlsx`, z zakładką na każdy miesiąc. Budżet miesięczny w arkuszu ułatwia użytkownikowi tę kontrolę. Struktura arkusza (bez kwot):

- pozycje rozchodów z kolumnami plan i faktycznie, ze znacznikiem realizacji; po wpisaniu kwoty faktycznej zwykle zastępuje ona plan,
- przychody ze źródeł, plan i faktycznie,
- wynik `Pozostałe` = przychody minus wszystkie rozchody miesiąca,
- domyślne kwoty pozycji w zakładce `Ustawienia`,
- tempo codziennych wydatków względem upływu dni miesiąca,
- oszczędności jako procent zarobków oraz kwoty do wpłaty na konta oszczędnościowe,
- wydatki nieregularne i ich fundusz.

# TODO: koszt obecnego obejścia — see Open Questions

## User & Persona

Jedna osoba prowadzi budżet całego domu: loguje się i wpisuje wszystko, także dochody i wydatki drugiej osoby w gospodarstwie.

Momenty korzystania z kontroli miesiąca: na początku miesiąca, przegląd co kilka dni, na koniec miesiąca. Moment tuż przed zakupem nie został wskazany.

## Success Criteria

### Primary

- Przez pełny miesiąc kontrola miesiąca jest prowadzona wyłącznie w aplikacji, bez korzystania z zakładek miesięcznych arkusza `budget_szablon.xlsx`. Zakładki `Media` i `Oszczędności` mogą pozostać w arkuszu.

### Secondary

- Mniej niespodzianek: rzadziej dowiaduję się pod koniec miesiąca, że jest gorzej, niż się wydawało.
- Widzę realne odkładanie: miesiąc do miesiąca widać, ile faktycznie zostaje względem zamierzeń.

### Guardrails

- Budżet roczny (plan początkowy, plan aktualny, wykonanie, roczne limity) działa jak dotąd.
- Salda kont i lokat oraz Spendable Liquidity i Total funds nie zmieniają się przez nowe funkcje.
- Import i ręczne dodawanie operacji nie stają się trudniejsze ani dłuższe.
- Istniejące budżety i operacje działają bez ręcznej migracji, także miesiące bez planu.

## User Stories

### US-01: Kontrola miesiąca od planu do podsumowania

- **Given** budżet roku jest w fazie wykonania, a miesiąc nie ma jeszcze zatwierdzonego planu
- **When** użytkownik na początku miesiąca poprawia proponowane kwoty, wpisuje „odkładam / dobieram” i zatwierdza plan
- **Then** widzi jedną liczbę „do wydania”, która zmienia się wraz z operacjami, a na koniec miesiąca podsumowanie planu, wykonania i odkładania

Przedtem: ta kontrola istniała tylko w arkuszu, a aplikacja pokazywała odczyty wyliczane z planu rocznego.

#### Acceptance Criteria

- Od pierwszego dnia miesiąca propozycja planu daje liczbę „do wydania” z oznaczeniem „niezatwierdzony”.
- Liczba jest zgodna z regułą z sekcji Business Logic Changes i aktualna po każdej operacji oraz po Imporcie.
- Podsumowanie pokazuje plan zatwierdzony i końcowy, wykonanie, odkładanie zamierzone i realne oraz dobrane kwoty.
- Budżet roczny po tej ścieżce nie zmienia się.

## Scope of Change

Zakres pierwszej wersji: zatwierdzany plan, plan i faktyczna kwota na pozycji, liczba „do wydania”, tempo codziennych wydatków, rozliczenie końca miesiąca.

### Plan miesiąca

- [new] FR-001: User can zobaczyć propozycję planu miesiąca z kwotami dla kategorii dochodów i wydatków, opartą na planie poprzedniego miesiąca. Priority: must-have.
    > Socratic: Kontrargument: kategorie są osobne dla każdego roku, więc styczeń nie ma poprzedniego miesiąca w tym samym budżecie. Rozstrzygnięcie: styczeń bierze kwoty z grudnia dla kategorii o tej samej nazwie i typie, reszta to średnia z rocznego.
- [new] FR-002: User can poprawić kwoty i zatwierdzić plan miesiąca. Priority: must-have.
    > Socratic: Kontrargument: jeśli liczba pojawia się dopiero po zatwierdzeniu, zapomnienie oznacza brak kontroli przez wiele dni. Rozstrzygnięcie: propozycja od pierwszego dnia daje „do wydania” z oznaczeniem „niezatwierdzony”; zatwierdzenie utrwala kwoty.
- [new] FR-003: User can zmienić zatwierdzony plan w trakcie miesiąca. Priority: must-have.
    > Socratic: Kontrargument: swobodna edycja pozwala podnosić plan tak, by liczba zawsze wychodziła na plus. Rozstrzygnięcie: zmiany są dozwolone, a podsumowanie pokazuje plan zatwierdzony i końcowy, jak plan początkowy i aktualny w roku.
- [new] FR-004: User can wpisać w planie miesiąca kwotę „odkładam / dobieram”. Priority: must-have.
    > Socratic: Kontrargumenty: dobieranie bez pokrycia oraz ukrywanie przekroczeń. Rozstrzygnięcie: ostrzeżenie bez blokady, gdy dobierane przekracza Spendable Liquidity; podsumowanie pokazuje dobrane kwoty, także w skali roku.

### Kontrola w trakcie miesiąca

- [new] FR-005: User can zobaczyć jedną liczbę „do wydania” dla miesiąca. Priority: must-have.
    > Socratic: Kontrargument: na początku miesiąca pensji jeszcze nie ma. Rozstrzygnięcie: jak w arkuszu liczony jest planowany przychód, a po wpływie zastępuje go kwota faktyczna.
- [new] FR-006: User can zobaczyć plan i faktyczną kwotę każdej pozycji miesiąca. Priority: must-have.
    > Socratic: Kontrargument: oszczędność na rachunku powinna wrócić do puli, ale niewydane codzienne wydatki nie są wolne. Rozstrzygnięcie: na kategorii jeden rodzaj pozycji: „rachunek” (po wpłacie faktyczna zastępuje plan) albo „bieżąca” (plan obowiązuje cały miesiąc).
- [new] FR-007: User can zobaczyć tempo wydatków względem upływu dni miesiąca. Priority: must-have.
    > Socratic: Kontrargument: czynsz płacony wcześnie i zakupy raz w tygodniu dają fałszywe ostrzeżenia. Rozstrzygnięcie: tempo tylko dla pozycji „bieżących”, ostrzeżenie przy wyprzedzeniu dni o co najmniej 10 punktów procentowych.
- [new] FR-008: User can zobaczyć kategorie jednorazowe z resztą rocznego limitu w widoku miesiąca. Priority: must-have.
    > Socratic: Kontrargument: długa lista kategorii z zerowym planem zaciemnia widok. Rozstrzygnięcie: jednorazowe z planem albo wydatkiem w miesiącu są w planie, reszta w zwiniętej sekcji z saldem rocznego limitu.

### Koniec miesiąca i rok

- [new] FR-009: User can zobaczyć podsumowanie miesiąca, w tym odkładanie zamierzone i realne. Priority: must-have.
    > Socratic: Kontrargument: operacja należy do miesiąca według daty, więc pensja z końca poprzedniego miesiąca zawyża go i zaniża kolejny. Rozstrzygnięcie: u użytkownika pensja bywa wcześniej; nadwyżka wchodzi do kolejnego miesiąca przez „dobieram”, bez nowego pola przy operacjach.
- [new] FR-010: User can porównać plany miesięcy z budżetem rocznym. Priority: must-have.
    > Socratic: Kontrargument: suma planów miesięcy jest pełna dopiero w grudniu. Rozstrzygnięcie: dla każdej kategorii pokazywane jest wydane w roku plus plan bieżącego miesiąca względem planu rocznego.
- [new] FR-011: User can pracować z miesiącem bez planu: jest on wyraźnie oznaczony, a aplikacja działa dalej. Priority: must-have.
    > Socratic: Kontrargument: minione miesiące nigdy nie miały planu, a plan wstecz nic nie da. Rozstrzygnięcie: plan dostępny dla bieżącego i przyszłych miesięcy; minione zostają bez planu.

### Powiadomienia i istniejące odczyty

- [new] FR-012: User can zobaczyć na Dashboardzie powiadomienia związane z planem miesiąca: do wydania poniżej zera, pozycja ponad plan, tempo za szybkie, brak planu miesiąca. Priority: must-have.
    > Socratic: Kontrargument: powiadomienia o drobiazgach uczą ignorowania strefy uwagi. Rozstrzygnięcie: „brak planu” od 3. dnia miesiąca; „pozycja ponad plan” przy każdym przekroczeniu, bo to już fakt.
- [new] FR-019: User can ustawić na kategorii rodzaj pozycji: „rachunek” albo „bieżąca”. Priority: must-have.
    > Socratic: Kontrargument rozstrzygnięty przy FR-006, FR-007 i FR-018: bez rodzaju pozycji liczba i tempo byłyby nieścisłe. Domyślnie „bieżąca”, kopiowane na nowy rok jak flaga równomierna.
- [modified] FR-013: User can zobaczyć na Dashboardzie i stronie Budżet „do wydania” zamiast `Pozostało w miesiącu` w bieżącym i przyszłych miesiącach; minione miesiące z zatwierdzonym planem pokazują podsumowanie i końcowe „do wydania”, a minione bez planu zachowują stary odczyt. Priority: must-have.
    > Socratic: Kontrargument: szkic liczy od razu, więc stary odczyt zostaje tylko dla minionych miesięcy bez planu. Rozstrzygnięcie: zostaje dla nich, historia wygląda jak dziś, kosztem utrzymania starego kodu.
- [modified] FR-014: User can zobaczyć przy ręcznej operacji i w podglądzie Importu ostrzeżenia liczone z planu miesiąca, gdy miesiąc ma plan, także niezatwierdzoną propozycję; bez planu zostają obecne. Priority: must-have.
    > Socratic: Kontrargument: Import może mieć wiersze z kilku miesięcy. Rozstrzygnięcie: każdy wiersz sprawdzany jest według miesiąca swojej daty.

### Zachowane

- [preserved] FR-015: User can korzystać z budżetu rocznego (plan początkowy, plan aktualny, wykonanie, roczne limity) jak dotąd. Priority: must-have.
    > Socratic: Kontrargument: w trakcie roku kategorie dochodzą i są archiwizowane, a plan miesiąca ma zapisane kwoty. Rozstrzygnięcie: nowa kategoria dołącza z kwotą 0; zarchiwizowana zostaje w miesiącu, w którym ma plan albo wydatek, i znika z kolejnych propozycji.
- [preserved] FR-016: User can odczytywać salda kont i lokat oraz Spendable Liquidity i Total funds jak dotąd. Priority: must-have.
    > Socratic: Kontrargument: „do wydania” może przekraczać środki na kontach. Rozstrzygnięcie: oba światy pozostają osobne; „do wydania” to budżet, Spendable Liquidity to środki, a transfery i ruchy kapitału nie zmieniają tej liczby.
- [preserved] FR-017: User can dodawać operacje ręcznie i przez Import bez dodatkowych kroków. Priority: must-have.
    > Socratic: Kontrargument: drobne przekroczenie rachunku dawałoby ostrzeżenie przy wierszu Importu. Rozstrzygnięcie: przy operacji ostrzeżenie tylko, gdy „do wydania” zejdzie poniżej zera; pozycję ponad plan widać na Dashboardzie.
- [preserved] FR-018: User can korzystać z istniejących budżetów i operacji bez ręcznej migracji. Priority: must-have.
    > Socratic: Kontrargument: istniejące kategorie nie mają rodzaju pozycji. Rozstrzygnięcie: domyślnie „bieżąca”, rachunki użytkownik oznacza raz przy pierwszym planie, a rodzaj kopiuje się na nowy rok jak flaga równomierna.

## Constraints & Compatibility

### Zachowane zachowanie

Nie mogą się zepsuć (wskazane przez użytkownika):

- budżet roczny: plan początkowy, plan aktualny, wykonanie, roczne limity,
- salda kont i lokat: Spendable Liquidity, Total funds, transfery, ruchy kapitału,
- wprowadzanie operacji: Import i ręczne dodawanie nie stają się trudniejsze ani dłuższe,
- dane historyczne: istniejące budżety i operacje działają bez ręcznej migracji, także miesiące bez planu.

### Kompatybilność wsteczna

- ADR-0024 odrzucił „drugi budżet miesięczny”. Wybrany model tego wymaga, więc ADR trzeba zastąpić nowym.
- Snapshot `user:backup` / `user:restore` musi obejmować plany miesięcy, a przywracanie starszych wersji (1 do 4) ma działać dalej.

### Migracja danych

- Istniejące dane nie wymagają ręcznej migracji: kategorie otrzymują domyślny rodzaj „bieżąca”, minione miesiące nie mają planu.

### Wycofanie zmiany

- Wycofanie to wdrożenie poprzedniej wersji aplikacji. Migracje tylko dodają tabele i pola, a dane planów miesięcy i rodzaj pozycji zostają w bazie nieużywane. Budżet roczny i operacje nie zależą od tych danych.

### Istniejące integracje

- Import i snapshot `user:backup` / `user:restore` działają dalej na warunkach opisanych powyżej.

### Wymagania jakościowe

- Po zapisaniu operacji albo Importu kolejny odczyt pokazuje aktualną i poprawną liczbę „do wydania”.
- Istniejące wymagania aplikacji obowiązują także nowe widoki: czas odpowiedzi do 2 sekund przy 10 000 operacji, dostępność, responsywność, izolacja danych (`.docs/prd.md`, sekcja 10), dokładność kwot do jednego grosza (`.docs/prd.md`, BR-01).

## Business Logic Changes

Aplikacja pokazuje, ile można jeszcze wydać w miesiącu: przychody miesiąca minus zobowiązania miesiąca, z kwotami faktycznymi tam, gdzie już są, a odstępstwo od zasady „nie więcej, niż zarobiono” jest jawną decyzją, a nie błędem.

Obecnie system liczy `Wynik` (zaksięgowane dochody minus wydatki) oraz `Pozostało w miesiącu` (bieżąca część rocznego planu). Ta zmiana dodaje nową regułę dla bieżącego i przyszłych miesięcy oraz minionych miesięcy z zatwierdzonym planem; minione miesiące bez planu działają jak dotąd.

`do wydania` = przychody (plan, a po wpływie kwota faktyczna) − pozycje „rachunek” (plan, a po wpłacie kwota faktyczna) − pozycje „bieżąca” (większa z: plan, kwota faktyczna) − odkładam + dobieram.

Liczba może być ujemna i niczego nie blokuje, tak jak dziś przekroczenie limitu. Odstępstwo od zasady wyraża się przez kwotę „dobieram”, a podsumowanie pokazuje dobrane kwoty, także w skali roku. Transfery własne i ruchy kapitału nie zmieniają tej liczby.

### Rozstrzygnięcia domenowe

- **Model planu miesiąca: Plan miesiąca obok rocznego.** Na początku miesiąca użytkownik zatwierdza plan z proponowanymi kwotami. Zatwierdzone kwoty są zapisane dla tego miesiąca i nie zmieniają się po zmianie planu rocznego. Budżet roczny działa bez zmian. Suma planów miesięcy może różnić się od roku i ta różnica ma być widoczna.
- **Odrzucone:** roczny z korektami (domyślne kwoty byłyby średnią z roku, czyli założeniem) oraz pełny plan miesięczny (wymagałby przebudowy budżetu rocznego i migracji danych, które mają pozostać nienaruszone).
- **Pozycja planu miesiąca:** kategoria budżetu. Faktyczna kwota wynika z operacji.
- **Założenie reguły:** każda pensja ma własną kategorię, a rachunki są płacone jednorazowo. Podzielone kategorie użytkownik przekształca samodzielnie, więc reguła nie obsługuje płatności w częściach.
- **Wydatki nieregularne: sekcja + planowane, gdy znane.** Bez funduszu i przelewów. Kategoria jednorazowa jest pozycją planu z domyślną kwotą 0, a sekcja pokazuje resztę rocznego limitu. Obniża „do wydania”, gdy zostanie wpisana do planu albo gdy wydatek zostanie zaksięgowany. Odrzucone: tylko po zaksięgowaniu (ostrzeżenie po fakcie), średnia co miesiąc (fundusz oparty na założeniu).
- **Oszczędności: pole „odkładam / dobieram” w planie miesiąca.** Jedna kwota, nie kategoria i nie przelew. Odkładam obniża „do wydania”, dobieram je podnosi. Użytkownik nie chce niepotrzebnych przelewów na oszczędności.
- **Rozliczenie końca miesiąca: tylko podsumowanie.** Plan, wykonanie, odchylenia, odkładane zamierzone i realne. Kolejny miesiąc startuje od zera, pieniądze zostają na kontach, a późna operacja po prostu zmienia podsumowanie. Odrzucone: przenoszenie (łańcuch miesięcy, podwójne liczenie z „dobieram”) i zamykanie (można dołożyć później).
- **Propozycja kwot: plan poprzedniego miesiąca.** Dla pierwszego miesiąca średnia z rocznego. Kategorie jednorazowe nie kopiują kwoty: w każdej propozycji startują od 0, żeby jednorazowy wydatek nie wracał i nie zaniżał „do wydania”. Odrzucone: osobna domyślna kwota kategorii, zawsze średnia z rocznego.
- **Tempo: bez kategorii jednorazowych.** Większy jednorazowy wydatek nie daje ostrzeżenia o tempie, także gdy kategoria ma domyślny rodzaj „bieżąca”.
- **„Miesiąc ma plan” obejmuje niezatwierdzoną propozycję.** Ostrzeżenia z FR-014 i odczyt z FR-013 liczą według tej samej reguły także przed zatwierdzeniem.
- **Miniony miesiąc z zatwierdzonym planem:** podsumowanie i końcowe „do wydania”. `Pozostało w miesiącu` zostaje tylko dla minionych miesięcy bez planu.
- **`Wynik` zostaje.** To fakt (zarobione minus wydane) i na koniec miesiąca jest miarą realnego odkładania.

## Access Control Changes

No access control changes — current model preserved.

Obecny model: konta tworzy administrator (`user:create`), samodzielna rejestracja jest wyłączona, każdy użytkownik widzi wyłącznie własne dane. Dostępne 2FA i passkeys.

## Non-Goals

- **Agenda i terminy płatności.** Rachunek nie ma daty płatności, a niezmergowane gałęzie Agendy zostają poza tą zmianą.
- **Współdzielenie z domownikami.** Bez drugiego logowania do tego samego budżetu; jedna osoba prowadzi cały dom.
- **Import arkusza.** Bez przenoszenia danych z `budget_szablon.xlsx` do aplikacji.
- **Zmiany budżetu rocznego i sald.** Patrz `## Constraints & Compatibility`.
- **Przenoszenie reszty i zamykanie miesiąca.** Rozliczenie to tylko podsumowanie; kolejny miesiąc startuje od zera.
- **Fundusz wydatków nieregularnych i przelewy na oszczędności.** Oszczędności to jedna kwota „odkładam / dobieram”, nie kategoria i nie przelew.
- **Płatności w częściach.** Reguła zakłada, że każda pensja ma własną kategorię, a rachunki są płacone jednorazowo.
- **Procent od zarobków w „odkładam”.** Pierwsza wersja przyjmuje tylko kwotę; procent można dodać później bez zmiany wzoru „do wydania”.

# TODO: non-functional non-goals — see Open Questions

## Open Questions

1. **Jaki jest koszt obecnego obejścia?** Notatki opisują arkusz i jego strukturę, ale nie nazywają ceny jego utrzymania. Właściciel: użytkownik.
2. **Czy są wymiary jakości, w które ta zmiana świadomie nie celuje?** Non-Goals z notatek są wyłącznie funkcjonalne. Właściciel: użytkownik.
