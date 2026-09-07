# Household Treasurer — Product Requirements Document

## 1. Streszczenie produktu

Household Treasurer jest responsywną aplikacją webową do planowania rocznego budżetu domowego, rejestrowania jego wykonania oraz kontrolowania rzeczywistych środków na rachunkach bankowych i kontach gotówkowych.

Produkt łączy dwie odrębne perspektywy:

- **budżet** — plan początkowy, plan aktualny i wykonanie kategorii,
- **płynność** — salda kont i łączna wartość posiadanych środków.

MVP obsługuje wielu niezależnych użytkowników. Każdy zarządza wyłącznie własnymi danymi, używając stałej waluty bazowej PLN.

## 2. Problem

Osoba prowadząca budżet domowy potrzebuje jednocześnie:

- zaplanować dochody i limity wydatków na cały rok,
- zachować punkt odniesienia w postaci pierwotnego planu,
- aktualizować plan w reakcji na zmieniającą się sytuację,
- rejestrować rzeczywiste dochody, wydatki i transfery,
- wiedzieć, ile środków faktycznie znajduje się na kontach,
- kontrolować wykonanie kategorii bez mylenia limitu budżetowego z dostępnymi środkami.

Arkusze kalkulacyjne i oddzielne zestawienia rachunków wymagają ręcznego uzgadniania danych. Utrudnia to ocenę, czy plan jest wykonywany oraz czy użytkownik posiada środki potrzebne do jego realizacji.

## 3. Cele i mierniki sukcesu

### 3.1. Cele

- Umożliwić przygotowanie kompletnego budżetu dla roku kalendarzowego.
- Zachować niezmienny plan początkowy i oddzielić go od planu aktualnego.
- Powiązać rzeczywiste dochody i wydatki jednocześnie z kontem oraz kategorią.
- Zapewnić poprawną ewidencję transferów między własnymi kontami.
- Ułatwić wprowadzanie operacji przez kontrolowany import CSV.
- Zapewnić przejrzysty obraz wykonania budżetu i bieżącej płynności.

### 3.2. Mierniki sukcesu MVP

MVP spełnia cel produktowy, gdy użytkownik może samodzielnie:

1. utworzyć konta i rozpocząć ich ewidencję od poprawnych sald otwarcia,
2. przygotować jeden budżet roczny z kategoriami, planowanymi dochodami i limitami wydatków,
3. rozpocząć wykonanie i zachować niezmienny plan początkowy,
4. rejestrować, edytować i usuwać dochody, wydatki, zwroty oraz transfery własne,
5. importować wybrane operacje z CSV po sprawdzeniu podglądu,
6. porównywać plan początkowy, plan aktualny i wykonanie każdej kategorii,
7. odczytać salda poszczególnych kont i łączną wartość środków,
8. rozpoznać przekroczenie limitu niezależnie od stanu środków na kontach.

## 4. Użytkownik i platforma

### 4.1. Główna persona

Osoba samodzielnie zarządzająca własnymi finansami domowymi, która chce planować budżet w ujęciu rocznym i regularnie porównywać plan z rzeczywistymi przepływami pieniężnymi.

### 4.2. Model użytkowników

- System może obsługiwać wielu zarejestrowanych użytkowników.
- Każdy użytkownik ma własną, odizolowaną przestrzeń danych.
- Budżety nie są współdzielone między użytkownikami ani domownikami.

### 4.3. Platforma

- MVP jest responsywną aplikacją webową.
- Kluczowe przepływy mają być dostępne na komputerach i urządzeniach mobilnych przez przeglądarkę.
- Natywne aplikacje mobilne pozostają poza zakresem.

## 5. Założenia i zakres

- Podstawowym okresem budżetowym jest rok kalendarzowy.
- Użytkownik może mieć najwyżej jeden budżet dla danego roku.
- Budżety różnych lat mogą istnieć równolegle w różnych fazach.
- Konta istnieją niezależnie od budżetów i przechodzą między latami.
- Walutą wszystkich kont, budżetów i operacji w MVP jest PLN.
- Dochody i wydatki są ujmowane kasowo, w dniu rzeczywistej zapłaty.
- Wszystkie dane są zapisywane trwale.
- Kwoty są ewidencjonowane z dokładnością do jednego grosza, bez obliczeń zmiennoprzecinkowych.

## 6. Główne przepływy użytkownika

### 6.1. Rozpoczęcie pracy

1. Użytkownik rejestruje się i loguje.
2. Tworzy rachunki bankowe oraz konta gotówkowe.
3. Dla każdego konta podaje datę i saldo otwarcia.
4. Tworzy szkic budżetu dla wybranego roku.
5. Dodaje kategorie dochodowe i wydatkowe.
6. Wprowadza planowane dochody oraz limity wydatków.
7. Sprawdza równowagę planu i rozpoczyna wykonanie.

### 6.2. Bieżąca ewidencja

1. Użytkownik wybiera ręczne dodanie operacji albo import CSV.
2. Wskazuje datę, kwotę, opis, konto oraz właściwą kategorię.
3. Przed zapisaniem wydatku widzi dostępny limit i wartość po operacji.
4. Zapisana operacja zmienia saldo konta i wykonanie kategorii.
5. Transfer własny zmienia wyłącznie salda dwóch kont.

### 6.3. Kontrola

1. Użytkownik przegląda salda kont i łączną wartość środków.
2. Dla każdej kategorii porównuje plan początkowy, plan aktualny i wykonanie.
3. Analizuje pozostałą kwotę, procent wykonania oraz przekroczenia.
4. W razie potrzeby aktualizuje plan albo pozostawia widoczne odchylenie.

## 7. Wymagania funkcjonalne

### 7.1. Tożsamość i dostęp

- **FR-AUTH-01**: Użytkownik może zarejestrować konto przy użyciu adresu e-mail i hasła.
- **FR-AUTH-02**: Użytkownik może się zalogować i wylogować.
- **FR-AUTH-03**: Użytkownik może rozpocząć i zakończyć proces resetowania hasła.
- **FR-AUTH-04**: Po zalogowaniu użytkownik ma dostęp wyłącznie do własnych danych.

### 7.2. Konta

- **FR-ACC-01**: Użytkownik może utworzyć rachunek bankowy albo konto gotówkowe.
- **FR-ACC-02**: Konto zawiera nazwę, typ, datę otwarcia, saldo otwarcia i saldo bieżące.
- **FR-ACC-03**: Użytkownik może edytować nazwę, typ i saldo otwarcia konta.
- **FR-ACC-04**: Data otwarcia nie może być późniejsza niż najstarsza operacja zapisana na koncie.
- **FR-ACC-05**: Operacja nie może mieć daty wcześniejszej niż data otwarcia użytego konta.
- **FR-ACC-06**: Konto bez operacji może zostać usunięte.
- **FR-ACC-07**: Konto użyte w operacji nie może zostać usunięte, ale może zostać zarchiwizowane.
- **FR-ACC-08**: Zarchiwizowane konto nie jest dostępne przy tworzeniu nowych operacji i może zostać przywrócone.
- **FR-ACC-09**: System pokazuje saldo bieżące każdego konta oraz sumę sald wszystkich kont, z wyraźnym oznaczeniem kont zarchiwizowanych.
- **FR-ACC-10**: Saldo konta może być ujemne i musi być wtedy jednoznacznie oznaczone.

### 7.3. Budżet roczny

- **FR-BUD-01**: Użytkownik może utworzyć szkic budżetu dla roku, dla którego nie ma jeszcze budżetu.
- **FR-BUD-02**: Użytkownik może równolegle posiadać budżety dla różnych lat.
- **FR-BUD-03**: Szkic budżetu może zostać usunięty.
- **FR-BUD-04**: Użytkownik może rozpocząć wykonanie szkicu budżetu.
- **FR-BUD-05**: Rozpoczęcie wykonania zapisuje plan początkowy jako kopię bieżącego planu.
- **FR-BUD-06**: Rozpoczęcie wykonania jest nieodwracalne.
- **FR-BUD-07**: Budżetu w fazie wykonania nie można usunąć.
- **FR-BUD-08**: Brak formalnego zamknięcia roku oznacza, że starszy budżet pozostaje w fazie wykonania i może przyjmować operacje historyczne.
- **FR-BUD-09**: Przed rozpoczęciem wykonania system pokazuje ostrzeżenie, jeżeli planowany deficyt nie ma pokrycia w saldach kont obliczonych na początek roku budżetowego.
- **FR-BUD-10**: Ostrzeżenie o planowanym deficycie nie blokuje rozpoczęcia wykonania.

### 7.4. Kategorie i plany

- **FR-CAT-01**: Użytkownik może dodać do budżetu kategorię dochodową albo wydatkową.
- **FR-CAT-02**: Kategoria należy do jednego budżetu rocznego.
- **FR-CAT-03**: Kategoria dochodowa ma nieujemną planowaną kwotę dochodu.
- **FR-CAT-04**: Kategoria wydatkowa ma nieujemny limit wydatków.
- **FR-CAT-05**: W szkicu użytkownik może zmieniać nazwy, typy i planowane kwoty kategorii.
- **FR-CAT-06**: Po rozpoczęciu wykonania nie można zmienić typu istniejącej kategorii.
- **FR-CAT-07**: Po rozpoczęciu wykonania użytkownik może zmieniać plan aktualny bez modyfikowania planu początkowego.
- **FR-CAT-08**: Po rozpoczęciu wykonania użytkownik może dodać kategorię, której plan początkowy wynosi 0 PLN, a plan aktualny przyjmuje podaną kwotę.
- **FR-CAT-09**: Kategoria może zostać usunięta ze szkicu budżetu.
- **FR-CAT-10**: Po rozpoczęciu wykonania kategoria nie może zostać usunięta, ale może zostać zarchiwizowana.
- **FR-CAT-11**: Zarchiwizowana kategoria nie jest dostępna przy tworzeniu nowych operacji i może zostać przywrócona.

### 7.5. Dochody, wydatki i zwroty

- **FR-TRX-01**: Użytkownik może ręcznie utworzyć dochód albo wydatek.
- **FR-TRX-02**: Operacja zawiera datę, niezerową kwotę, opis, konto i kategorię odpowiedniego typu.
- **FR-TRX-03**: Data operacji nie może przypadać w przyszłości.
- **FR-TRX-04**: Data operacji wskazuje budżet roczny, na którego wykonanie operacja wpływa.
- **FR-TRX-05**: Dochód albo wydatek można zapisać tylko dla roku z budżetem w fazie wykonania.
- **FR-TRX-06**: Zwykły dochód i zwykły wydatek mają kwotę dodatnią.
- **FR-TRX-07**: Zwrot wydatku jest ujemnym wydatkiem przypisanym do kategorii wydatkowej.
- **FR-TRX-08**: Przed zapisaniem wydatku system pokazuje aktualny dostępny limit i przewidywany limit po operacji.
- **FR-TRX-09**: Przekroczenie limitu nie blokuje zapisu operacji.
- **FR-TRX-10**: Użytkownik może edytować i usuwać operacje w budżecie znajdującym się w fazie wykonania.
- **FR-TRX-11**: Zmiana daty na inny rok wymaga wskazania kategorii z docelowego budżetu będącego w fazie wykonania.
- **FR-TRX-12**: Edycja i usunięcie operacji przelicza saldo konta oraz wykonanie kategorii.

### 7.6. Transfery własne

- **FR-TRF-01**: Użytkownik może utworzyć transfer własny między dwoma różnymi, niezarchiwizowanymi kontami.
- **FR-TRF-02**: Transfer zawiera datę, dodatnią niezerową kwotę, opis, konto źródłowe i konto docelowe.
- **FR-TRF-03**: Transfer nie ma kategorii budżetowej i nie wpływa na wykonanie budżetu.
- **FR-TRF-04**: Transfer atomowo zmniejsza saldo konta źródłowego i zwiększa saldo konta docelowego o tę samą kwotę.
- **FR-TRF-05**: Użytkownik może edytować i usuwać transfer.
- **FR-TRF-06**: Utworzenie, edycja albo usunięcie transferu aktualizuje obie strony albo żadnej.
- **FR-TRF-07**: Transfer może spowodować ujemne saldo konta źródłowego.
- **FR-TRF-08**: Wypłata z bankomatu jest rejestrowana jako transfer z rachunku bankowego na konto gotówkowe.
- **FR-TRF-09**: Data transferu nie może przypadać w przyszłości ani być wcześniejsza niż data otwarcia któregokolwiek z użytych kont.

### 7.7. Import CSV

- **FR-CSV-01**: Użytkownik może wczytać plik CSV.
- **FR-CSV-02**: Użytkownik może zmapować kolumny daty, opisu i kwoty.
- **FR-CSV-03**: Import obsługuje pojedynczą kolumnę kwoty ze znakiem albo osobne kolumny wpływów i obciążeń.
- **FR-CSV-04**: Użytkownik wskazuje konto, którego dotyczy import.
- **FR-CSV-05**: System pokazuje podgląd sparsowanych wierszy przed utworzeniem operacji.
- **FR-CSV-06**: Niepoprawne wiersze są oznaczone wraz z przyczyną i nie mogą zostać wybrane do importu.
- **FR-CSV-07**: Użytkownik wybiera poprawne wiersze przeznaczone do importu.
- **FR-CSV-08**: Każdy wybrany wiersz musi otrzymać typ operacji oraz dane wymagane dla tego typu.
- **FR-CSV-09**: Użytkownik może utworzyć z wiersza dochód, wydatek, zwrot albo transfer własny.
- **FR-CSV-10**: Dla transferu użytkownik wskazuje drugie konto, a system tworzy cały transfer atomowo.
- **FR-CSV-11**: System nie dopasowuje automatycznie dwóch pozycji CSV jako stron tego samego transferu.
- **FR-CSV-12**: System ostrzega, że nie gwarantuje wykrycia duplikatów.
- **FR-CSV-13**: Ponowne zatwierdzenie tej samej pozycji może utworzyć duplikat.
- **FR-CSV-14**: Wszystkie wybrane poprawne pozycje są zapisywane atomowo albo żadna nie zostaje zapisana.

### 7.8. Raportowanie i kontrola wykonania

- **FR-REP-01**: System pokazuje plan początkowy, plan aktualny, wykonanie, pozostałą kwotę i procent wykonania każdej kategorii.
- **FR-REP-02**: Dla kategorii dodanej podczas wykonania plan początkowy jest prezentowany jako 0 PLN.
- **FR-REP-03**: Dla planu aktualnego równego 0 PLN procent wykonania jest prezentowany jako „nie dotyczy”.
- **FR-REP-04**: Ujemna pozostała kwota jest prezentowana jako przekroczenie limitu.
- **FR-REP-05**: System pokazuje sumę planowanych i rzeczywistych dochodów.
- **FR-REP-06**: System pokazuje sumę limitów oraz rzeczywistych wydatków.
- **FR-REP-07**: System pokazuje salda kont niezależnie od wykonania kategorii.
- **FR-REP-08**: Zarchiwizowane konta i kategorie pozostają widoczne w danych historycznych.

## 8. Reguły biznesowe

- **BR-01**: Wszystkie kwoty są wyrażone w PLN i mają dokładność do jednego grosza.
- **BR-02**: Obliczenia pieniężne nie mogą używać arytmetyki zmiennoprzecinkowej.
- **BR-03**: Użytkownik ma najwyżej jeden budżet dla danego roku kalendarzowego.
- **BR-04**: Plan początkowy powstaje raz i po rozpoczęciu wykonania jest niezmienny.
- **BR-05**: Plan aktualny może zmieniać się bez zmiany planu początkowego.
- **BR-06**: Dochód zwiększa saldo konta i wykonanie kategorii dochodowej.
- **BR-07**: Wydatek zmniejsza saldo konta i zwiększa wykonanie kategorii wydatkowej.
- **BR-08**: Zwrot wydatku zwiększa saldo konta i zmniejsza wykonanie kategorii wydatkowej.
- **BR-09**: Transfer własny wpływa wyłącznie na salda kont.
- **BR-10**: Dostępny limit jest równy planowi aktualnemu kategorii wydatkowej pomniejszonemu o jej zaksięgowane wydatki.
- **BR-11**: Dostępny limit nie jest informacją o dostępności środków na kontach.
- **BR-12**: Przekroczenie limitu i ujemne saldo konta są dozwolone.
- **BR-13**: Dochód lub wydatek należy do budżetu wskazanego przez rok daty operacji.
- **BR-14**: Przyszłe płatności i zobowiązania nie są operacjami finansowymi MVP.
- **BR-15**: Historia elementu użytego w operacjach jest zachowywana przez archiwizację zamiast usunięcia.

## 9. Przypadki brzegowe i obsługa błędów

- Próba zapisania dochodu lub wydatku bez budżetu w fazie wykonania kończy się komunikatem wskazującym konieczność utworzenia albo rozpoczęcia wykonania budżetu.
- Próba użycia zarchiwizowanego konta lub kategorii jest blokowana bez zmiany danych.
- Próba zapisania operacji wcześniejszej niż data otwarcia konta albo późniejszej niż bieżący dzień jest blokowana.
- Przekroczenie limitu i zejście salda poniżej zera są pokazywane jako ostrzeżenia, ale nie blokują zapisu.
- Zmiana daty operacji na inny rok nie zachowuje kategorii ze starego budżetu; użytkownik wybiera kategorię docelową.
- Błąd dowolnej strony transferu wycofuje całą zmianę.
- Błąd zapisu dowolnej wybranej pozycji importu wycofuje cały zatwierdzany zestaw.
- Niepoprawny wiersz CSV pozostaje w podglądzie z opisem błędu i nie blokuje wyboru pozostałych poprawnych wierszy.
- Przed zatwierdzeniem importu użytkownik widzi ostrzeżenie o możliwych duplikatach i odpowiada za sprawdzenie podglądu.
- Błędy formularzy są prezentowane przy odpowiednich polach bez utraty poprawnie wprowadzonych danych.

## 10. Wymagania niefunkcjonalne

- **NFR-SEC-01**: Każde odczytanie i zapis danych wymaga uwierzytelnienia.
- **NFR-SEC-02**: Dane użytkownika są odizolowane od danych wszystkich pozostałych użytkowników.
- **NFR-SEC-03**: Hasła nie są przechowywane ani rejestrowane w postaci jawnej.
- **NFR-DAT-01**: Zapis transferu i zatwierdzenie importu zapewniają określoną w wymaganiach atomowość.
- **NFR-DAT-02**: Ponowne odczytanie zapisanych danych daje te same kwoty i powiązania.
- **NFR-DAT-03**: Wszystkie operacje zmieniające dane zachowują spójność sald oraz wykonania kategorii.
- **NFR-PERF-01**: Przy 10 000 operacji użytkownika otwarcie listy kont, otwarcie podsumowania budżetu i zapis pojedynczej ręcznej operacji kończą się w ciągu 2 sekund w środowisku testów akceptacyjnych.
- **NFR-PERF-02**: Import obsługuje plik zawierający do 5 000 wierszy.
- **NFR-ACC-01**: Kluczowe przepływy logowania, budżetu, kont, operacji i importu spełniają WCAG 2.2 na poziomie AA.
- **NFR-ACC-02**: Kluczowe przepływy są w pełni obsługiwalne za pomocą klawiatury.
- **NFR-UX-01**: Interfejs dostosowuje się do ekranów komputerów i urządzeń mobilnych.
- **NFR-UX-02**: Wartości pieniężne są prezentowane jednoznacznie jako PLN.

## 11. Kryteria akceptacji

### 11.1. Dostęp i izolacja

1. Zarejestrowany użytkownik może zalogować się, wylogować i odzyskać dostęp przez reset hasła.
2. Użytkownik nie może odczytać ani zmienić budżetu, konta lub operacji należących do innego użytkownika.

### 11.2. Konta i płynność

1. Utworzenie konta z datą i saldem otwarcia pokazuje saldo bieżące równe saldu otwarcia.
2. Dodanie dochodu, wydatku, zwrotu albo transferu aktualizuje właściwe salda zgodnie z regułami biznesowymi.
3. Zmiana salda otwarcia przelicza saldo bieżące bez tworzenia dochodu.
4. Konto z historią można zarchiwizować i przywrócić, ale nie można go usunąć.

### 11.3. Budżet i plany

1. System blokuje utworzenie drugiego budżetu dla tego samego użytkownika i roku.
2. Rozpoczęcie wykonania zachowuje plan początkowy równy planowi ze szkicu.
3. Późniejsza zmiana planu aktualnego nie modyfikuje planu początkowego.
4. Nowa kategoria dodana podczas wykonania ma plan początkowy 0 PLN.
5. Ostrzeżenie o niepokrytym planowanym deficycie nie blokuje rozpoczęcia wykonania.

### 11.4. Operacje

1. Dochód zwiększa saldo konta i wykonanie właściwej kategorii dochodowej o tę samą kwotę.
2. Wydatek zmniejsza saldo konta i dostępny limit właściwej kategorii wydatkowej o tę samą kwotę.
3. Zwrot zwiększa saldo konta i dostępny limit kategorii wydatkowej o wartość zwrotu.
4. Przed zapisaniem wydatku użytkownik widzi limit przed operacją i po niej.
5. Operację przekraczającą limit lub powodującą ujemne saldo można zapisać po wyświetleniu ostrzeżenia.
6. Edycja albo usunięcie operacji odwraca jej poprzedni wpływ przed zastosowaniem nowego.

### 11.5. Transfery

1. Transfer między dwoma różnymi kontami zmniejsza jedno saldo i zwiększa drugie o tę samą kwotę.
2. Transfer nie zmienia wykonania żadnej kategorii.
3. Niepowodzenie aktualizacji jednej strony nie pozostawia zmiany na drugiej stronie.
4. Edycja i usunięcie transferu zachowują spójność obu kont.

### 11.6. Import CSV

1. Użytkownik może zmapować wymagane kolumny dla obu obsługiwanych układów kwot.
2. Podgląd odróżnia poprawne i niepoprawne wiersze oraz podaje przyczyny błędów.
3. Zatwierdzenie tworzy operacje wyłącznie z wybranych poprawnych wierszy.
4. Transfer utworzony z CSV jest jedną atomową operacją między wskazanymi kontami.
5. Błąd zapisu jednej wybranej pozycji nie pozostawia częściowo zapisanego importu.
6. Przed zatwierdzeniem użytkownik otrzymuje ostrzeżenie, że duplikaty mogą nie zostać wykryte.

### 11.7. Raportowanie

1. Dla każdej kategorii wartości planu początkowego, planu aktualnego, wykonania i pozostałej kwoty zgadzają się z zapisanymi operacjami.
2. Przekroczenie limitu jest widoczne jako wartość ujemna.
3. Przy planie aktualnym 0 PLN system nie oblicza procentu i pokazuje „nie dotyczy”.
4. Łączna wartość środków jest równa sumie sald prezentowanych kont.
5. Salda kont nie są prezentowane jako dostępne limity kategorii ani odwrotnie.

## 12. Poza zakresem MVP

- zobowiązania i planowane przyszłe płatności,
- rezerwy budżetowe,
- miesięczne harmonogramy oraz prognozy płynności,
- formalne zatwierdzanie zmian budżetowych,
- zamknięcie roku i blokowanie okresów,
- pełny ślad audytowy oraz wersjonowane sprawozdania,
- korekty salda inne niż zmiana salda otwarcia,
- kopiowanie kategorii i planów między latami,
- raporty kwartalne i rozbudowane raporty okresowe,
- automatyczne uzgadnianie transferów,
- gwarantowane lub zaawansowane wykrywanie duplikatów CSV,
- bezpośrednie integracje bankowe,
- budżety współdzielone przez wielu domowników,
- waluty inne niż PLN i przeliczanie walut,
- natywne aplikacje mobilne,
- funkcje AI,
- portfel inwestycyjny,
- kredyty, pożyczki i rozliczenia podatkowe,
- nieruchomości, pojazdy i inne niepłynne składniki majątku.

## 13. Ryzyka i zależności

- Różnice między formatami CSV banków mogą powodować błędy mapowania; podgląd i jawne błędy wierszy ograniczają to ryzyko.
- Brak wykrywania duplikatów wymaga uważnej kontroli użytkownika przed zatwierdzeniem importu.
- Dopuszczenie korekt historycznych bez śladu audytowego może utrudniać wyjaśnienie zmian; jest to świadomy kompromis MVP.
- Brak zamknięcia roku pozwala zmieniać dane historyczne; formalne zamykanie okresów pozostaje późniejszym rozszerzeniem.
- Poprawność sald, wykonania i transferów zależy od atomowego i dokładnego przetwarzania kwot.

## 14. Decyzje pozostawione implementacji

PRD nie narzuca:

- architektury aplikacji ani podziału na moduły,
- technologii interfejsu, serwera i trwałego zapisu,
- schematu bazy danych,
- dostawcy poczty i mechanizmu wysyłania resetu hasła,
- szczegółowego wyglądu ekranów,
- strategii wdrożenia i hostingu.

Wybrane rozwiązania muszą spełnić wymagania funkcjonalne, reguły biznesowe, wymagania niefunkcjonalne i kryteria akceptacji opisane w tym dokumencie.
