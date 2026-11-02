---
project: "Household Treasurer"
context_type: brownfield
created: 2026-11-01
updated: 2026-11-01
product_type: web-app
target_scale:
  users: small
  qps: low
  data_volume: small
timeline_budget:
  delivery_weeks: 6
  hard_deadline: null
  after_hours_only: true
checkpoint:
  current_phase: 8
  phases_completed: [1, 2, 3, 4, 5, 6, 7]
  gray_areas_resolved:
    - topic: "kontekst"
      decision: "brownfield, potwierdzone przez użytkownika"
    - topic: "główna persona"
      decision: "jedna osoba prowadzi budżet całego domu"
    - topic: "kontrola dostępu"
      decision: "bez zmian"
    - topic: "model planu miesiąca"
      decision: "Plan miesiąca obok rocznego: zatwierdzany na początku miesiąca, budżet roczny bez zmian; wymaga zastąpienia ADR-0024"
    - topic: "pozycja planu"
      decision: "kategoria budżetu"
    - topic: "oszczędności"
      decision: "pole odkładam / dobieram, bez przelewów"
    - topic: "wydatki nieregularne"
      decision: "sekcja z resztą rocznego limitu; obniża do wydania, gdy planowane albo zaksięgowane"
    - topic: "rozliczenie miesiąca"
      decision: "tylko podsumowanie, bez przenoszenia i bez zamykania"
    - topic: "zakres vs czas"
      decision: "cały zakres, 6 tygodni, świadomie"
  frs_drafted: 19
  quality_check_status: accepted
---

# Shape notes: Household Treasurer

## Seed idea (verbatim)

> chcę rozwijać dalej aplikacje - mam teraz wiele elementow, ktore chcialem: kontrolowanei budżetu rocznego, dodawanie operacji, zarządzanie kontami i lokatami; brakuje mi tylko jednego - aby wlasciciwie pilnować budzetu w ciagu miesiaca, bo na rocznym budzecie ciezko sie zorientowac czy moge jeszce cos lub nie; brakuje mi czegos w ciagu miesiaca - tak aby zasadniczo nie wydawac wiecej niz sie zarobilo - choc to nie powinna byc zasada bez odstepstw bo czasami trzeba cos odlozyc, albow wydac wiecej w ciagu miesiaca. Do tej pory uzywam czegos takiego storage/app/private/budget_szablon.xlsx

## Current System

Household Treasurer to responsywna aplikacja webowa do planowania rocznego budżetu domowego, rejestrowania jego wykonania i kontroli środków na kontach oraz lokatach. Szczegóły: `.docs/prd.md`, słownik: `CONTEXT.md`.

- **Architektura:** monolit Laravel z frontendem Inertia + Vue.
- **Użytkownicy:** konta tworzone przez administratora, dane każdego użytkownika są odizolowane. Faktycznie korzysta jedna osoba.
- **Funkcje dziś:** budżet roczny (plan początkowy, plan aktualny, wykonanie, kategorie Paced i One-off), operacje ręczne i Import CSV/XLSX, konta, transfery własne, lokaty i ruchy kapitału, Dashboard.
- **Odczyty miesięczne dziś:** `Wynik` (Month Result: dochody minus wydatki zaksięgowane w miesiącu) i `Pozostało w miesiącu` (Month Remaining: bieżąca część rocznego planu kategorii równomiernych).
- **Wyszukane w repo:** Agenda i Cash Expectation są opisane w `CONTEXT.md` i `.docs/specs/agenda-cash-expectations.md`, ale kod jest tylko na niezmergowanych gałęziach (ostatni commit 2026-09-14). Reserve i Year Close są opisane w dokumentach, bez implementacji. Dokumentację należy traktować ostrożnie: opisuje więcej niż kod.

## Vision & Problem Statement

Zmiana: dodać kontrolę budżetu w skali miesiąca, bo budżet roczny nie pozwala w pełni kontrolować wydatków w codzienności. Użytkownik chce wiedzieć, ile jest jeszcze do wydania w danym miesiącu, tak aby zasadniczo nie wydawać więcej, niż zarobiono. Nie jest to zasada bez odstępstw: czasem trzeba coś odłożyć albo wydać więcej w miesiącu.

Użytkownik ocenia, że obecne odczyty `Wynik` i `Pozostało w miesiącu` są bardzo nieścisłe, opierają się tylko na założeniach i nie informują wyraźnie, ile jest do wydania.

Obecne obejście: osobny arkusz `budget_szablon.xlsx`, z zakładką na każdy miesiąc. Budżet miesięczny w arkuszu ułatwia użytkownikowi tę kontrolę. Struktura arkusza (bez kwot):

- pozycje rozchodów z kolumnami plan i faktycznie, ze znacznikiem realizacji; po wpisaniu kwoty faktycznej zwykle zastępuje ona plan,
- przychody ze źródeł, plan i faktycznie,
- wynik `Pozostałe` = przychody minus wszystkie rozchody miesiąca,
- domyślne kwoty pozycji w zakładce `Ustawienia`,
- tempo codziennych wydatków względem upływu dni miesiąca,
- oszczędności jako procent zarobków oraz kwoty do wpłaty na konta oszczędnościowe,
- wydatki nieregularne i ich fundusz.

Do potwierdzenia w Kroku 3: które z tych elementów są potrzebne w aplikacji.

## User & Persona

Jedna osoba prowadzi budżet całego domu: loguje się i wpisuje wszystko, także dochody i wydatki drugiej osoby w gospodarstwie.

Momenty korzystania z kontroli miesiąca: na początku miesiąca, przegląd co kilka dni, na koniec miesiąca. Moment tuż przed zakupem nie został wskazany.

## Access Control

Obecny model: konta tworzy administrator (`user:create`), samodzielna rejestracja jest wyłączona, każdy użytkownik widzi wyłącznie własne dane. Dostępne 2FA i passkeys.

No changes planned — current model preserved.

## Constraints & Preserved Behavior

Nie mogą się zepsuć (wskazane przez użytkownika):

- budżet roczny: plan początkowy, plan aktualny, wykonanie, roczne limity,
- salda kont i lokat: Spendable Liquidity, Total funds, transfery, ruchy kapitału,
- wprowadzanie operacji: Import i ręczne dodawanie nie stają się trudniejsze ani dłuższe,
- dane historyczne: istniejące budżety i operacje działają bez ręcznej migracji, także miesiące bez planu.

Ograniczenia wynikające z repozytorium:

- ADR-0024 odrzucił „drugi budżet miesięczny”. Wybrany model tego wymaga, więc ADR trzeba zastąpić nowym.
- Snapshot `user:backup` / `user:restore` musi obejmować plany miesięcy, a przywracanie starszych wersji (1 do 4) ma działać dalej.
- Istniejące dane nie wymagają ręcznej migracji: kategorie otrzymują domyślny rodzaj „bieżąca”, minione miesiące nie mają planu.

## Decisions

- **Model planu miesiąca: Plan miesiąca obok rocznego.** Na początku miesiąca użytkownik zatwierdza plan z proponowanymi kwotami. Zatwierdzone kwoty są zapisane dla tego miesiąca i nie zmieniają się po zmianie planu rocznego. Budżet roczny działa bez zmian. Suma planów miesięcy może różnić się od roku i ta różnica ma być widoczna.
- **Odrzucone:** roczny z korektami (domyślne kwoty byłyby średnią z roku, czyli założeniem) oraz pełny plan miesięczny (wymagałby przebudowy budżetu rocznego i migracji danych, które mają pozostać nienaruszone).
- **Pozycja planu miesiąca:** kategoria budżetu. Faktyczna kwota wynika z operacji.
- **Zakres pierwszej wersji:** zatwierdzany plan, plan i faktyczna kwota na pozycji, liczba „do wydania”, tempo codziennych wydatków, rozliczenie końca miesiąca.
- **Główne kryterium sukcesu:** przez pełny miesiąc kontrola miesiąca prowadzona wyłącznie w aplikacji, bez zakładek miesięcznych arkusza.
- **Założenie reguły:** każda pensja ma własną kategorię, a rachunki są płacone jednorazowo. Podzielone kategorie użytkownik przekształca samodzielnie, więc reguła nie obsługuje płatności w częściach.
- **Wydatki nieregularne: sekcja + planowane, gdy znane.** Bez funduszu i przelewów. Kategoria jednorazowa jest pozycją planu z domyślną kwotą 0, a sekcja pokazuje resztę rocznego limitu. Obniża „do wydania”, gdy zostanie wpisana do planu albo gdy wydatek zostanie zaksięgowany. Odrzucone: tylko po zaksięgowaniu (ostrzeżenie po fakcie), średnia co miesiąc (fundusz oparty na założeniu).
- **Oszczędności: pole „odkładam / dobieram” w planie miesiąca.** Jedna kwota, nie kategoria i nie przelew. Odkładam obniża „do wydania”, dobieram je podnosi. Użytkownik nie chce niepotrzebnych przelewów na oszczędności.
- **Rozliczenie końca miesiąca: tylko podsumowanie.** Plan, wykonanie, odchylenia, odkładane zamierzone i realne. Kolejny miesiąc startuje od zera, pieniądze zostają na kontach, a późna operacja po prostu zmienia podsumowanie. Odrzucone: przenoszenie (łańcuch miesięcy, podwójne liczenie z „dobieram”) i zamykanie (można dołożyć później).

- **Propozycja kwot: plan poprzedniego miesiąca.** Dla pierwszego miesiąca średnia z rocznego, jednorazowe od 0. Odrzucone: osobna domyślna kwota kategorii, zawsze średnia z rocznego.
- **`Wynik` zostaje.** To fakt (zarobione minus wydane) i na koniec miesiąca jest miarą realnego odkładania.

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

## Timeline acknowledgment

Acknowledged on 2026-11-01: 6-week delivery requires sustained dedication; user accepted.

## User Stories

### US-01: Kontrola miesiąca od planu do podsumowania

- **Given** budżet roku jest w fazie wykonania, a miesiąc nie ma jeszcze zatwierdzonego planu
- **When** użytkownik na początku miesiąca poprawia proponowane kwoty, wpisuje „odkładam / dobieram” i zatwierdza plan
- **Then** widzi jedną liczbę „do wydania”, która zmienia się wraz z operacjami, a na koniec miesiąca podsumowanie planu, wykonania i odkładania

Przedtem: ta kontrola istniała tylko w arkuszu, a aplikacja pokazywała odczyty wyliczane z planu rocznego.

#### Acceptance Criteria

- Od pierwszego dnia miesiąca propozycja planu daje liczbę „do wydania” z oznaczeniem „niezatwierdzony”.
- Liczba jest zgodna z regułą z sekcji Business Logic i aktualna po każdej operacji oraz po Imporcie.
- Podsumowanie pokazuje plan zatwierdzony i końcowy, wykonanie, odkładanie zamierzone i realne oraz dobrane kwoty.
- Budżet roczny po tej ścieżce nie zmienia się.

## Functional Requirements

### Plan miesiąca

- FR-001: User can zobaczyć propozycję planu miesiąca z kwotami dla kategorii dochodów i wydatków, opartą na planie poprzedniego miesiąca. Priority: must-have. Change: new
  > Socratic: Kontrargument: kategorie są osobne dla każdego roku, więc styczeń nie ma poprzedniego miesiąca w tym samym budżecie. Rozstrzygnięcie: styczeń bierze kwoty z grudnia dla kategorii o tej samej nazwie i typie, reszta to średnia z rocznego.
- FR-002: User can poprawić kwoty i zatwierdzić plan miesiąca. Priority: must-have. Change: new
  > Socratic: Kontrargument: jeśli liczba pojawia się dopiero po zatwierdzeniu, zapomnienie oznacza brak kontroli przez wiele dni. Rozstrzygnięcie: propozycja od pierwszego dnia daje „do wydania” z oznaczeniem „niezatwierdzony”; zatwierdzenie utrwala kwoty.
- FR-003: User can zmienić zatwierdzony plan w trakcie miesiąca. Priority: must-have. Change: new
  > Socratic: Kontrargument: swobodna edycja pozwala podnosić plan tak, by liczba zawsze wychodziła na plus. Rozstrzygnięcie: zmiany są dozwolone, a podsumowanie pokazuje plan zatwierdzony i końcowy, jak plan początkowy i aktualny w roku.
- FR-004: User can wpisać w planie miesiąca kwotę „odkładam / dobieram”. Priority: must-have. Change: new
  > Socratic: Kontrargumenty: dobieranie bez pokrycia oraz ukrywanie przekroczeń. Rozstrzygnięcie: ostrzeżenie bez blokady, gdy dobierane przekracza Spendable Liquidity; podsumowanie pokazuje dobrane kwoty, także w skali roku.

### Kontrola w trakcie miesiąca

- FR-005: User can zobaczyć jedną liczbę „do wydania” dla miesiąca. Priority: must-have. Change: new
  > Socratic: Kontrargument: na początku miesiąca pensji jeszcze nie ma. Rozstrzygnięcie: jak w arkuszu liczony jest planowany przychód, a po wpływie zastępuje go kwota faktyczna.
- FR-006: User can zobaczyć plan i faktyczną kwotę każdej pozycji miesiąca. Priority: must-have. Change: new
  > Socratic: Kontrargument: oszczędność na rachunku powinna wrócić do puli, ale niewydane codzienne wydatki nie są wolne. Rozstrzygnięcie: na kategorii jeden rodzaj pozycji: „rachunek” (po wpłacie faktyczna zastępuje plan) albo „bieżąca” (plan obowiązuje cały miesiąc).
- FR-007: User can zobaczyć tempo wydatków względem upływu dni miesiąca. Priority: must-have. Change: new
  > Socratic: Kontrargument: czynsz płacony wcześnie i zakupy raz w tygodniu dają fałszywe ostrzeżenia. Rozstrzygnięcie: tempo tylko dla pozycji „bieżących”, ostrzeżenie przy wyprzedzeniu dni o co najmniej 10 punktów procentowych.
- FR-008: User can zobaczyć kategorie jednorazowe z resztą rocznego limitu w widoku miesiąca. Priority: must-have. Change: new
  > Socratic: Kontrargument: długa lista kategorii z zerowym planem zaciemnia widok. Rozstrzygnięcie: jednorazowe z planem albo wydatkiem w miesiącu są w planie, reszta w zwiniętej sekcji z saldem rocznego limitu.

### Koniec miesiąca i rok

- FR-009: User can zobaczyć podsumowanie miesiąca, w tym odkładanie zamierzone i realne. Priority: must-have. Change: new
  > Socratic: Kontrargument: operacja należy do miesiąca według daty, więc pensja z końca poprzedniego miesiąca zawyża go i zaniża kolejny. Rozstrzygnięcie: u użytkownika pensja bywa wcześniej; nadwyżka wchodzi do kolejnego miesiąca przez „dobieram”, bez nowego pola przy operacjach.
- FR-010: User can porównać plany miesięcy z budżetem rocznym. Priority: must-have. Change: new
  > Socratic: Kontrargument: suma planów miesięcy jest pełna dopiero w grudniu. Rozstrzygnięcie: dla każdej kategorii pokazywane jest wydane w roku plus plan bieżącego miesiąca względem planu rocznego.
- FR-011: User can pracować z miesiącem bez planu: jest on wyraźnie oznaczony, a aplikacja działa dalej. Priority: must-have. Change: new
  > Socratic: Kontrargument: minione miesiące nigdy nie miały planu, a plan wstecz nic nie da. Rozstrzygnięcie: plan dostępny dla bieżącego i przyszłych miesięcy; minione zostają bez planu.

### Powiadomienia i istniejące odczyty

- FR-012: User can zobaczyć na Dashboardzie powiadomienia związane z planem miesiąca: do wydania poniżej zera, pozycja ponad plan, tempo za szybkie, brak planu miesiąca. Priority: must-have. Change: new
  > Socratic: Kontrargument: powiadomienia o drobiazgach uczą ignorowania strefy uwagi. Rozstrzygnięcie: „brak planu” od 3. dnia miesiąca; „pozycja ponad plan” przy każdym przekroczeniu, bo to już fakt.
- FR-019: User can ustawić na kategorii rodzaj pozycji: „rachunek” albo „bieżąca”. Priority: must-have. Change: new
  > Socratic: Kontrargument rozstrzygnięty przy FR-006, FR-007 i FR-018: bez rodzaju pozycji liczba i tempo byłyby nieścisłe. Domyślnie „bieżąca”, kopiowane na nowy rok jak flaga równomierna.
- FR-013: User can zobaczyć na Dashboardzie i stronie Budżet „do wydania” zamiast `Pozostało w miesiącu` w bieżącym i przyszłych miesiącach; minione miesiące zachowują stary odczyt. Priority: must-have. Change: modified
  > Socratic: Kontrargument: szkic liczy od razu, więc stary odczyt zostaje tylko dla minionych miesięcy. Rozstrzygnięcie: zostaje dla minionych, historia wygląda jak dziś, kosztem utrzymania starego kodu.
- FR-014: User can zobaczyć przy ręcznej operacji i w podglądzie Importu ostrzeżenia liczone z planu miesiąca, gdy miesiąc ma plan; bez planu zostają obecne. Priority: must-have. Change: modified
  > Socratic: Kontrargument: Import może mieć wiersze z kilku miesięcy. Rozstrzygnięcie: każdy wiersz sprawdzany jest według miesiąca swojej daty.

### Zachowane

- FR-015: User can korzystać z budżetu rocznego (plan początkowy, plan aktualny, wykonanie, roczne limity) jak dotąd. Priority: must-have. Change: preserved
  > Socratic: Kontrargument: w trakcie roku kategorie dochodzą i są archiwizowane, a plan miesiąca ma zapisane kwoty. Rozstrzygnięcie: nowa kategoria dołącza z kwotą 0; zarchiwizowana zostaje w miesiącu, w którym ma plan albo wydatek, i znika z kolejnych propozycji.
- FR-016: User can odczytywać salda kont i lokat oraz Spendable Liquidity i Total funds jak dotąd. Priority: must-have. Change: preserved
  > Socratic: Kontrargument: „do wydania” może przekraczać środki na kontach. Rozstrzygnięcie: oba światy pozostają osobne; „do wydania” to budżet, Spendable Liquidity to środki, a transfery i ruchy kapitału nie zmieniają tej liczby.
- FR-017: User can dodawać operacje ręcznie i przez Import bez dodatkowych kroków. Priority: must-have. Change: preserved
  > Socratic: Kontrargument: drobne przekroczenie rachunku dawałoby ostrzeżenie przy wierszu Importu. Rozstrzygnięcie: przy operacji ostrzeżenie tylko, gdy „do wydania” zejdzie poniżej zera; pozycję ponad plan widać na Dashboardzie.
- FR-018: User can korzystać z istniejących budżetów i operacji bez ręcznej migracji. Priority: must-have. Change: preserved
  > Socratic: Kontrargument: istniejące kategorie nie mają rodzaju pozycji. Rozstrzygnięcie: domyślnie „bieżąca”, rachunki użytkownik oznacza raz przy pierwszym planie, a rodzaj kopiuje się na nowy rok jak flaga równomierna.

## Business Logic

Aplikacja pokazuje, ile można jeszcze wydać w miesiącu: przychody miesiąca minus zobowiązania miesiąca, z kwotami faktycznymi tam, gdzie już są, a odstępstwo od zasady „nie więcej, niż zarobiono” jest jawną decyzją, a nie błędem.

Obecnie system liczy `Wynik` (zaksięgowane dochody minus wydatki) oraz `Pozostało w miesiącu` (bieżąca część rocznego planu). Ta zmiana dodaje nową regułę dla bieżącego i przyszłych miesięcy; minione miesiące działają jak dotąd.

`do wydania` = przychody (plan, a po wpływie kwota faktyczna) − pozycje „rachunek” (plan, a po wpłacie kwota faktyczna) − pozycje „bieżąca” (większa z: plan, kwota faktyczna) − odkładam + dobieram.

Liczba może być ujemna i niczego nie blokuje, tak jak dziś przekroczenie limitu. Odstępstwo od zasady wyraża się przez kwotę „dobieram”, a podsumowanie pokazuje dobrane kwoty, także w skali roku. Transfery własne i ruchy kapitału nie zmieniają tej liczby.

## Non-Functional Requirements

- Po zapisaniu operacji albo Importu kolejny odczyt pokazuje aktualną i poprawną liczbę „do wydania”.
- Istniejące wymagania aplikacji obowiązują także nowe widoki: czas odpowiedzi do 2 sekund przy 10 000 operacji, dostępność, responsywność, izolacja danych, kwoty bez obliczeń zmiennoprzecinkowych (`.docs/prd.md`, sekcja 10).

## Non-Goals

- **Agenda i terminy płatności.** Rachunek nie ma daty płatności, a niezmergowane gałęzie Agendy zostają poza tą zmianą.
- **Współdzielenie z domownikami.** Bez drugiego logowania do tego samego budżetu; jedna osoba prowadzi cały dom.
- **Import arkusza.** Bez przenoszenia danych z `budget_szablon.xlsx` do aplikacji.
- **Zmiany budżetu rocznego i sald.** Patrz Constraints & Preserved Behavior.

## Open Questions

1. **Czy „odkładam” ma obsługiwać procent od zarobków?** Arkusz liczy oszczędności jako procent przychodów; użytkownik nie wykluczył tego w Non-Goals, a pierwsza wersja zakłada kwotę. Właściciel: użytkownik.

## Forward: technical-roadmap

- `user:backup` i `user:restore` mają format wersjonowany (obecnie 4). Plany miesięcy muszą w nim być, bo inaczej snapshot je utraci.
- Nowy ADR musi zastąpić ADR-0024 w części o „drugim budżecie miesięcznym”.
- `CONTEXT.md` zawiera terminy bez implementacji (Agenda, Cash Expectation, Reserve, Year Close). Nowe pojęcia dopisać do słownika.
- `.ai/rules/support.md` i ADR-0032: każde nowe powiadomienie Dashboardu o wadze `warning` wymaga terminu i progów w `CONTEXT.md` przed kodem.
- `Pozostało w miesiącu` działa w pięciu miejscach: kafelek Dashboardu, powiadomienia Dashboardu, strona Budżet, ostrzeżenie przy ręcznej operacji, podgląd Importu.
