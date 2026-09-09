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
- Ułatwić wprowadzanie operacji przez kontrolowany Import z pliku CSV lub XLSX.
- Zapewnić przejrzysty obraz wykonania budżetu i bieżącej płynności.

### 3.2. Mierniki sukcesu MVP

MVP spełnia cel produktowy, gdy użytkownik może samodzielnie:

1. utworzyć konta i rozpocząć ich ewidencję od poprawnych sald otwarcia,
2. przygotować jeden budżet roczny z kategoriami, planowanymi dochodami i limitami wydatków,
3. rozpocząć wykonanie i zachować niezmienny plan początkowy,
4. rejestrować, edytować i usuwać dochody, wydatki, zwroty oraz transfery własne,
5. importować wybrane operacje z pliku CSV lub XLSX po sprawdzeniu podglądu,
6. porównywać plan początkowy, plan aktualny i wykonanie każdej kategorii,
7. odczytać salda poszczególnych kont i łączną wartość środków,
8. rozpoznać przekroczenie limitu niezależnie od stanu środków na kontach,
9. rozpocząć kolejny rok, kopiując kategorie i plan aktualny z wcześniejszego budżetu.

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
- Przy tworzeniu szkicu użytkownik może skopiować kategorie i plan aktualny z wcześniejszego własnego budżetu; kopia tworzy nowe kategorie, a nie tożsamość ponad latami.
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
4. Tworzy szkic budżetu dla wybranego roku. Gdy ma wcześniejszy budżet z niezarchiwizowanymi kategoriami, może skopiować te kategorie wraz z planem aktualnym.
5. W razie potrzeby poprawia, usuwa lub dopisuje kategorie oraz planowane kwoty na szkicu.
6. Sprawdza równowagę planu i rozpoczyna wykonanie.

### 6.2. Bieżąca ewidencja

1. Użytkownik wybiera ręczne dodanie operacji albo Import z pliku.
2. Wskazuje datę, kwotę, opis, opcjonalnie nadawcę / odbiorcę, konto oraz właściwą kategorię.
3. Przed zapisaniem wydatku widzi dostępny limit i wartość po operacji.
4. Zapisana operacja zmienia saldo konta i wykonanie kategorii.
5. Transfer własny zmienia wyłącznie salda dwóch kont.
6. Przy Imporcie podgląd oznacza już zapisany wiersz z tego konta (Import duplicate) oraz drugą nogę transferu własnego z innego konta (Import pair candidate). Użytkownik włącza te wiersze świadomie.

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
- **FR-ACC-02**: Konto zawiera nazwę, typ, datę otwarcia, saldo otwarcia i saldo bieżące. Rachunek bankowy zawiera także Bank (mBank, BNP Paribas albo Inny).
- **FR-ACC-03**: Użytkownik może edytować nazwę, typ i saldo otwarcia konta. Na rachunku bankowym może edytować także Bank.
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
- **FR-BUD-11**: Przy tworzeniu szkicu budżetu użytkownik może skopiować kategorie i ich plan aktualny z innego własnego budżetu o wcześniejszym roku.
- **FR-BUD-12**: Dialog tworzenia oferuje wyłącznie budżety źródłowe tego użytkownika z rokiem wcześniejszym niż rok docelowy i z co najmniej jedną niezarchiwizowaną kategorią. Domyślne źródło to budżet poprzedniego roku kalendarzowego, a gdy go nie ma — najnowszy kwalifikowany wcześniejszy budżet. Brak kwalifikowanego źródła oznacza utworzenie pustego szkicu jak dotychczas.
- **FR-BUD-13**: Użytkownik może utworzyć pusty szkic mimo dostępnego źródła.
- **FR-BUD-14**: Utworzenie budżetu ze skopiowanymi kategoriami jest atomowe. Nowy budżet pozostaje szkicem i nie rozpoczyna wykonania.

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
- **FR-CAT-12**: Skopiowana kategoria jest nowym rekordem docelowego budżetu. Otrzymuje nazwę, typ, ikonę, kolor, kolejność oraz plan aktualny źródła. Nie przejmuje tożsamości, wykonania, planu początkowego ani archiwum źródła.
- **FR-CAT-13**: Zarchiwizowane kategorie źródła nie są kopiowane.

### 7.5. Dochody, wydatki i zwroty

- **FR-TRX-01**: Użytkownik może ręcznie utworzyć dochód albo wydatek.
- **FR-TRX-02**: Operacja zawiera datę, niezerową kwotę, opis, konto i kategorię odpowiedniego typu; może też zawierać opcjonalnego nadawcę / odbiorcę (Counterparty).
- **FR-TRX-03**: Data operacji nie może przypadać w przyszłości.
- **FR-TRX-04**: Data operacji wskazuje budżet roczny, na którego wykonanie operacja wpływa.
- **FR-TRX-05**: Dochód albo wydatek można zapisać tylko dla roku z budżetem w fazie wykonania.
- **FR-TRX-06**: Zwykły dochód i zwykły wydatek mają kwotę dodatnią.
- **FR-TRX-07**: Zwrot wydatku jest ujemnym wydatkiem przypisanym do kategorii wydatkowej; może też zawierać opcjonalnego nadawcę / odbiorcę (Counterparty).
- **FR-TRX-08**: Przed zapisaniem wydatku system pokazuje aktualny dostępny limit i przewidywany limit po operacji.
- **FR-TRX-09**: Przekroczenie limitu nie blokuje zapisu operacji.
- **FR-TRX-10**: Użytkownik może edytować i usuwać operacje w budżecie znajdującym się w fazie wykonania, w tym opcjonalnego nadawcę / odbiorcę (Counterparty).
- **FR-TRX-11**: Zmiana daty na inny rok wymaga wskazania kategorii z docelowego budżetu będącego w fazie wykonania.
- **FR-TRX-12**: Edycja i usunięcie operacji przelicza saldo konta oraz wykonanie kategorii.
- **FR-TRX-13**: Przy edycji użytkownik może zmienić dochód w zwrot wydatku: wskazuje kategorię wydatkową, system odwraca wpływ dochodu i księguje zwrot. Przy tym samym koncie i tej samej kwocie bezwzględnej saldo konta nie zmienia się.
- **FR-TRX-14**: Przy edycji użytkownik może zmienić zwrot wydatku w dochód: wskazuje kategorię dochodową, system odwraca wpływ zwrotu i księguje dochód.
- **FR-TRX-15**: Edycja nie zamienia zwykłego (dodatniego) wydatku w dochód ani dochodu w zwykły wydatek.
- **FR-TRX-16**: Import nie zgaduje zwrotu. Użytkownik może zapisać wpływ jako dochód i poprawić klasyfikację później przez edycję. Podgląd Importu nie podpowiada pary z konkretnym wydatkiem.

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

### 7.7. Import

Import odbywa się w tym samym kreatorze dla CSV i XLSX. Użytkownik wybiera **Import format**, wskazuje konto, przegląda podgląd i zatwierdza wybrane wiersze. To nie jest połączenie z bankiem.

Podgląd oznacza dwa rodzaje już znanych środków jako osobne, poprawne nakładki: **Import duplicate** (to samo konto, ten sam zamrożony wiersz Importu) oraz **Import pair candidate** (inne konto już zaksięgowało te środki przez wcześniejszy Import). Oba startują odznaczone; użytkownik je włącza. Niepoprawny wiersz wygrywa z obiema nakładkami. Jeden Import dotyczy jednego wskazanego konta.

- **FR-IMP-01**: Użytkownik może wczytać plik CSV albo XLSX.
- **FR-IMP-02**: Użytkownik wybiera Import format: mBank, BNP Paribas albo Inny. System może wstępnie zaznaczyć format na podstawie rozszerzenia pliku; użytkownik może to zmienić. System nie importuje po cichej autodetekcji.
- **FR-IMP-03**: Plik musi pasować do wybranego formatu: mBank i Inny wymagają CSV, BNP Paribas wymaga XLSX. Niezgodność jest błędem pliku.
- **FR-IMP-04**: Użytkownik wskazuje konto, którego dotyczy Import.
- **FR-IMP-05**: Format mBank: CSV. System znajduje wiersz nagłówka `#Data operacji`. Linie przed nim nie trafiają do podglądu. Data = Data operacji, opis = Opis operacji, kwota = Kwota (kolumna ze znakiem). Nadawca / odbiorca pozostaje pusty. Oryginalny opis z wyciągu = Opis operacji.
- **FR-IMP-06**: Format BNP Paribas: XLSX, wyłącznie pierwszy arkusz, nagłówek w pierwszym wierszu. Data = Data transakcji (w tym daty seryjne Excela). Nadawca / odbiorca pochodzi z kolumny Nadawca / odbiorca, albo — gdy eksport rozdziela strony — z Odbiorcy przy kwocie ujemnej i z Nadawcy przy kwocie dodatniej (gdy wybrana komórka pusta, używana jest druga; pusty dozwolony). Opis → opis. Gdy Opis jest pusty, a nadawca / odbiorca nie — opis i nadawca / odbiorca równe tej wartości. Gdy oba puste — wiersz niepoprawny (brak opisu). Oryginalny opis z wyciągu = niepusty nadawca / odbiorca i Opis połączone (nowa linia, gdy oba). Kwota = Kwota (kolumna ze znakiem). Wiersze blokad i statusu „W trakcie realizacji” pozostają w podglądzie; użytkownik je odznacza.
- **FR-IMP-07**: Format Inny: wyłącznie CSV. Użytkownik mapuje kolumny daty, opisu i kwoty oraz oznacza, czy plik ma nagłówek. Opcjonalnie mapuje kolumnę nadawcy / odbiorcy. Pliki trzykolumnowe nadal działają. Oryginalny opis z wyciągu = zmapowana komórka opisu plus zmapowana komórka nadawcy / odbiorcy, gdy jest. Kwota może być jedną kolumną ze znakiem albo osobnymi kolumnami wpływów i obciążeń. Dla tego formatu pokazywany jest ekran mapowania kolumn. Formaty nazwane nie pokazują mapowania kolumn; mogą pokazać nieedytowalny skrót użytych pól.
- **FR-IMP-08**: Po nagłówku formatu (albo po starcie wynikającym z mapowania) wiersze, w których zmapowana data nie jest datą albo zmapowana kwota nie jest kwotą, są pomijane i nie pojawiają się w podglądzie jako błędy.
- **FR-IMP-09**: Wiersz, który wygląda jak operacja, ale łamie reguły biznesowe, jest oznaczony jako niepoprawny wraz z przyczyną i nie może zostać wybrany do Importu.
- **FR-IMP-10**: Z komórki kwoty zdejmowane są suffiksy `PLN` i `zł`. Inny kod waluty w tej komórce czyni wiersz niepoprawnym. Osobna kolumna waluty nie wchodzi do mapowania.
- **FR-IMP-11**: Kolumny kategorii banku nie są mapowane na Category. Kategorię Household Treasurer użytkownik wskazuje w podglądzie.
- **FR-IMP-12**: System pokazuje podgląd sparsowanych wierszy przed utworzeniem operacji.
- **FR-IMP-13**: Użytkownik wybiera poprawne wiersze przeznaczone do Importu.
- **FR-IMP-14**: Każdy wybrany wiersz musi otrzymać typ operacji oraz dane wymagane dla tego typu.
- **FR-IMP-15**: Użytkownik może utworzyć z wiersza dochód, wydatek, zwrot albo transfer własny.
- **FR-IMP-16**: Dla transferu użytkownik wskazuje drugie konto, a system tworzy cały transfer atomowo. Transfer własny z pierwszego pliku rusza oba salda przy tym zatwierdzeniu; system nie czeka na drugi Import.
- **FR-IMP-17**: Dwa wiersze tego samego pliku nie są parowane jako strony jednego transferu własnego.
- **FR-IMP-18**: Podgląd oznacza Import duplicate, gdy wiersz jest tym samym wierszem Importu co już zatwierdzona pozycja z Importu tego użytkownika na tym koncie: data z pliku, kwota ze znakiem i oryginalny opis z wyciągu, zamrożone w chwili zapisu. Wiersz pozostaje poprawny; pole wyboru jest odznaczone. Ręczne wpisy oraz rekordy zapisane, zanim istniały odciski Importu, nie są Import duplicate. Edycja daty, kwoty, konta, opisu albo nadawcy / odbiorcy nie zmienia odcisku. Usunięcie rekordu usuwa odcisk.
- **FR-IMP-19**: Włączenie Import duplicate i zatwierdzenie może utworzyć drugi rekord. Potwierdzenie jest wymagane tylko wtedy, gdy użytkownik wybrał co najmniej jeden Import duplicate. System oznacza znane trafienia i nie gwarantuje unikalności wszystkich wierszy.
- **FR-IMP-20**: Wszystkie wybrane poprawne pozycje — w tym pary i zwykłe księgowania — są zapisywane atomowo albo żadna nie zostaje zapisana.
- **FR-IMP-21**: Podgląd oznacza Import pair candidate, gdy wiersz należy do tego użytkownika, leży na innym koncie niż istniejąca pozycja z Importu, zamrożone kwoty ze znakiem są dokładnymi przeciwieństwami, a zamrożone daty z pliku mieszczą się w oknie trzech dni włącznie. Ręczne wpisy nie są kandydatami. To samo konto i ten sam zamrożony wiersz to Import duplicate, nie para.
- **FR-IMP-22**: Zaznaczenie Import pair candidate i zatwierdzenie pozostawia jeden transfer własny: albo przekształca pierwszą nogę będącą dochodem albo wydatkiem (wykonanie kategorii wraca, potem ruszają salda obu kont), albo dopina drugą tożsamość Importu do istniejącego transferu własnego bez ponownego ruchu sald. Usunięcie transferu własnego usuwa obie tożsamości; nie ma osobnego rozparowania przywracającego dochód albo wydatek.
- **FR-IMP-23**: Użytkownik może sklasyfikować kandydata będącego wyłącznie dochodem albo wydatkiem jako dochód, wydatek, zwrot albo nowy transfer własny na inne konto. Gdy w zbiorze kandydatów jest transfer własny, który już ruszył konto z podglądu, typ jest zablokowany na transfer własny.
- **FR-IMP-24**: Po parze transfer własny przechowuje dwa oryginalne opisy z wyciągu i dwa odciski Import duplicate, po jednym na konto z Importu. Ponowny Import dowolnego z tych plików oznacza wiersz jako Import duplicate.

### 7.8. Raportowanie i kontrola wykonania

- **FR-REP-01**: System pokazuje plan początkowy, plan aktualny, wykonanie, pozostałą kwotę i procent wykonania każdej kategorii.
- **FR-REP-02**: Dla kategorii dodanej podczas wykonania plan początkowy jest prezentowany jako 0 PLN.
- **FR-REP-03**: Dla planu aktualnego równego 0 PLN procent wykonania jest prezentowany jako „nie dotyczy”.
- **FR-REP-04**: Ujemna pozostała kwota jest prezentowana jako przekroczenie limitu.
- **FR-REP-05**: System pokazuje sumę planowanych i rzeczywistych dochodów.
- **FR-REP-06**: System pokazuje sumę limitów oraz rzeczywistych wydatków.
- **FR-REP-07**: System pokazuje salda kont niezależnie od wykonania kategorii.
- **FR-REP-08**: Zarchiwizowane konta i kategorie pozostają widoczne w danych historycznych.

### 7.9. Narzędzia operacyjne (Artisan)

Komendy CLI dla administratora i pracy lokalnej. Nie są częścią interfejsu webowego MVP. Produkcyjny backup całej bazy pozostaje zrzutem PostgreSQL poza VPS (por. tech stack); poniższe komendy eksportują i przywracają **dane domenowe jednego użytkownika** w formacie JSON.

- **FR-ADM-01**: Administrator może wyeksportować snapshot danych domenowych użytkownika (`user:backup {user}`). Snapshot zawiera konta, budżety, kategorie, operacje finansowe, transfery własne oraz stan checklisty pierwszego uruchomienia. Nie zawiera hasła, 2FA, passkeys ani sesji. Plik zapisywany jest jako JSON wersji 1 w `storage/app/private/user-snapshots/` z nazwą `{user-id}-{timestamp}.json`.
- **FR-ADM-02**: Administrator może przywrócić snapshot na **istniejącego** użytkownika-cel (`user:restore {user} {path}`). Przed wgraniem system usuwa wszystkie dane domenowe użytkownika-celu (jak `user:reset-data`). Restore nadaje rekordom nowe identyfikatory i przepina powiązania, więc snapshot można przenieść między różnymi użytkownikami. Operacja wymaga potwierdzenia; opcja `--force` pomija pytanie.
- **FR-ADM-03**: Administrator może wyczyścić dane domenowe użytkownika bez usuwania konta logowania (`user:reset-data {user}`). Komenda usuwa konta, budżety, operacje, transfery i sesje użytkownika oraz resetuje checklistę pierwszego uruchomienia.
- **FR-ADM-04**: Restore odrzuca plik z nieobsługiwaną wersją formatu lub uszkodzonym JSON.

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
- **BR-16**: Skopiowany plan aktualny pochodzi z planu aktualnego źródła, nie z planu początkowego ani wykonania źródła. Plan początkowy docelowego budżetu powstaje dopiero przy rozpoczęciu wykonania.
- **BR-17**: Edycja może zmienić dochód w zwrot wydatku i zwrot wydatku w dochód. Nie wiąże zwrotu z konkretnym wcześniejszym wydatkiem. Nie zamienia zwykłego wydatku w dochód.

## 9. Przypadki brzegowe i obsługa błędów

- Próba zapisania dochodu lub wydatku bez budżetu w fazie wykonania kończy się komunikatem wskazującym konieczność utworzenia albo rozpoczęcia wykonania budżetu.
- Próba użycia zarchiwizowanego konta lub kategorii jest blokowana bez zmiany danych.
- Próba zapisania operacji wcześniejszej niż data otwarcia konta albo późniejszej niż bieżący dzień jest blokowana.
- Przekroczenie limitu i zejście salda poniżej zera są pokazywane jako ostrzeżenia, ale nie blokują zapisu.
- Zmiana daty operacji na inny rok nie zachowuje kategorii ze starego budżetu; użytkownik wybiera kategorię docelową.
- Próba zmiany zwykłego wydatku w dochód albo dochodu w zwykły wydatek jest odrzucana; dochód może stać się zwrotem i odwrotnie.
- Błąd dowolnej strony transferu wycofuje całą zmianę.
- Błąd zapisu dowolnej wybranej pozycji importu wycofuje cały zatwierdzany zestaw.
- Niepoprawny wiersz Importu pozostaje w podglądzie z opisem błędu i nie blokuje wyboru pozostałych poprawnych wierszy.
- Preambuła, nagłówek i inne linie niebędące operacją nie pojawiają się w podglądzie jako błędy.
- Wczytanie pliku niezgodnego z wybranym Import format (np. XLSX przy mBank) jest odrzucane przed podglądem.
- Podgląd oznacza znane Import duplicate i Import pair candidate jako osobne, odznaczone nakładki. Potwierdzenie jest wymagane tylko przy wyborze Import duplicate. Ręczne wpisy i rekordy bez odcisku nie są oznaczane.
- Błędy formularzy są prezentowane przy odpowiednich polach bez utraty poprawnie wprowadzonych danych.
- Odrzucenie kopii (nieistniejące, cudze, późniejsze albo puste źródło) nie tworzy budżetu.
- Niepowodzenie zapisu którejkolwiek skopiowanej kategorii wycofuje cały nowy budżet.

## 10. Wymagania niefunkcjonalne

- **NFR-SEC-01**: Każde odczytanie i zapis danych wymaga uwierzytelnienia.
- **NFR-SEC-02**: Dane użytkownika są odizolowane od danych wszystkich pozostałych użytkowników.
- **NFR-SEC-03**: Hasła nie są przechowywane ani rejestrowane w postaci jawnej.
- **NFR-DAT-01**: Zapis transferu, zatwierdzenie importu oraz utworzenie budżetu z kopią kategorii zapewniają określoną w wymaganiach atomowość.
- **NFR-DAT-02**: Ponowne odczytanie zapisanych danych daje te same kwoty i powiązania.
- **NFR-DAT-03**: Wszystkie operacje zmieniające dane zachowują spójność sald oraz wykonania kategorii.
- **NFR-PERF-01**: Przy 10 000 operacji finansowych użytkownika otwarcie listy kont, otwarcie podsumowania budżetu, otwarcie pierwszej strony listy operacji i zapis pojedynczej ręcznej operacji kończą się w ciągu 2 sekund w środowisku testów akceptacyjnych.
- **NFR-PERF-02**: Import obsługuje plik zawierający do 5 000 wierszy.
- **NFR-ACC-01**: Kluczowe przepływy logowania, budżetu, kont, operacji i importu spełniają WCAG 2.2 na poziomie AA.
- **NFR-ACC-02**: Kluczowe przepływy są w pełni obsługiwalne za pomocą klawiatury.
- **NFR-UX-01**: Interfejs dostosowuje się do ekranów komputerów i urządzeń mobilnych.
- **NFR-UX-02**: Wartości pieniężne są prezentowane jednoznacznie jako PLN.

Weryfikacja NFR-ACC-01 i NFR-ACC-02 następuje po pierwszym tygodniu dogfoodu; to wydanie nie zawiera formalnego audytu WCAG. NFR-PERF-02 oznacza górny limit 5 000 wierszy Importu i nie wprowadza osobnego limitu czasu.

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
6. Utworzenie budżetu ze źródłem kopiuje wszystkie niezarchiwizowane kategorie źródła z planem aktualnym, wyglądem i kolejnością; nowy budżet jest szkicem, a wykonanie źródła pozostaje bez zmian.
7. Skopiowane kategorie są osobnymi rekordami; ta sama nazwa w dwóch latach nie oznacza tej samej kategorii.
8. Opcja bez kopiowania tworzy szkic bez kategorii, nawet gdy źródło jest dostępne.
9. Zarchiwizowane kategorie źródła nie pojawiają się na nowym szkicu.
10. Domyślne źródło to poprzedni rok kalendarzowy, a przy jego braku najnowszy kwalifikowany wcześniejszy budżet; budżet z przyszłości nie jest źródłem.
11. Użytkownik nie może użyć budżetu innego użytkownika jako źródła kopii.

### 11.4. Operacje

1. Dochód zwiększa saldo konta i wykonanie właściwej kategorii dochodowej o tę samą kwotę.
2. Wydatek zmniejsza saldo konta i dostępny limit właściwej kategorii wydatkowej o tę samą kwotę.
3. Zwrot zwiększa saldo konta i dostępny limit kategorii wydatkowej o wartość zwrotu.
4. Przed zapisaniem wydatku użytkownik widzi limit przed operacją i po niej.
5. Operację finansową przekraczającą limit lub powodującą ujemne saldo można zapisać po wyświetleniu ostrzeżenia.
6. Edycja albo usunięcie operacji odwraca jej poprzedni wpływ przed zastosowaniem nowego.
7. Edycja dochodu w zwrot na kategorii wydatkowej zmniejsza wykonanie dochodu i wykonanie tej kategorii o wartość zwrotu; przy tym samym koncie i kwocie bezwzględnej saldo konta zostaje.
8. Edycja zwrotu w dochód odwrotnie przenosi kwotę z kategorii wydatkowej na dochodową.
9. Edycja nie zamienia zwykłego wydatku w dochód ani dochodu w zwykły wydatek.

### 11.5. Transfery

1. Transfer między dwoma różnymi kontami zmniejsza jedno saldo i zwiększa drugie o tę samą kwotę.
2. Transfer nie zmienia wykonania żadnej kategorii.
3. Niepowodzenie aktualizacji jednej strony nie pozostawia zmiany na drugiej stronie.
4. Edycja i usunięcie transferu zachowują spójność obu kont.
5. Transfer powodujący ujemne saldo konta źródłowego można zapisać po wyświetleniu ostrzeżenia.

### 11.6. Import

1. Użytkownik wybiera Import format. mBank pomija preambułę CSV i wczytuje operacje z kolumn Data operacji, Opis operacji i Kwota. Nadawca / odbiorca pozostaje pusty; oryginalny opis z wyciągu to Opis operacji.
2. BNP Paribas wczytuje pierwszy arkusz XLSX; data pochodzi z Data transakcji, nadawca / odbiorca z kolumny Nadawca / odbiorca albo z Odbiorcy przy wydatku i Nadawcy przy wpływie, opis z Opisu (gdy Opis pusty i nadawca / odbiorca nie — oba równe tej wartości), oryginalny opis z wyciągu z połączenia niepustych nadawcy / odbiorcy i Opisu, kwota z Kwoty. Blokady pozostają w podglądzie do odznaczenia.
3. Format Inny pozwala zmapować wymagane kolumny CSV dla obu obsługiwanych układów kwot, opcjonalnie kolumnę nadawcy / odbiorcy, i pomija linie bez daty i kwoty.
4. Podgląd odróżnia poprawne i niepoprawne wiersze operacji oraz podaje przyczyny błędów; linie niebędące operacją nie są pokazywane jako błędy.
5. Zatwierdzenie tworzy operacje wyłącznie z wybranych poprawnych wierszy.
6. Transfer utworzony z Importu jest jedną atomową operacją między wskazanymi kontami.
7. Błąd zapisu jednej wybranej pozycji nie pozostawia częściowo zapisanego Importu.
8. Podgląd oznacza Import duplicate i Import pair candidate jako osobne, poprawne wiersze z odznaczonym wyborem. Niepoprawny wiersz wygrywa z obiema nakładkami.
9. Zatwierdzenie bez włączonych Import duplicate i Import pair candidate zapisuje pozostałe wybrane wiersze.
10. Włączenie Import duplicate wymaga potwierdzenia i może utworzyć drugi rekord. Ręczne wpisy oraz rekordy bez odcisku nie są Import duplicate.
11. Zatwierdzenie Import pair candidate zostawia jeden transfer własny: przekształca dochód albo wydatek albo dopina drugą tożsamość Importu bez ponownego ruchu sald.
12. Transfer własny z pierwszego pliku rusza oba salda przy tym zatwierdzeniu. Dwa wiersze tego samego pliku nie stają się stronami jednego transferu własnego.
13. Podgląd pokazuje nadawcę / odbiorcę i oryginalny opis z wyciągu dla każdego wiersza.
14. Import zapisuje oryginalny opis z wyciągu jednorazowo przy utworzeniu operacji finansowej lub transferu własnego. Ręczne tworzenie pozostawia go pustym. Edycja opisu lub nadawcy / odbiorcy nie zmienia oryginalnego opisu z wyciągu.
15. Dialog edycji pokazuje oryginalny opis z wyciągu jako pole tylko do odczytu, gdy jest ustawiony. Po parze transfer własny pokazuje oba oryginalne opisy z wyciągu.

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
- tożsamość Category ponad latami oraz szablon kategorii,
- kopiowanie kategorii do już istniejącego budżetu,
- wybór podzbioru kategorii w dialogu tworzenia roku,
- kopiowanie zarchiwizowanych kategorii,
- przenoszenie wykonania albo planu początkowego źródła na nowy rok,
- automatyczne rozpoczęcie wykonania przy tworzeniu roku,
- raporty kwartalne i rozbudowane raporty okresowe,
- parowanie dwóch wierszy w jednym pliku Importu jako stron transferu własnego,
- gwarantowana unikalność każdego wiersza Importu,
- dopasowywanie ręcznych wpisów jako Import duplicate albo Import pair candidate,
- osobne rozparowanie przywracające dochód albo wydatek z transferu własnego,
- bezpośrednie integracje bankowe (API, logowanie do banku, pobieranie historii bez pliku),
- kolejne Import format poza mBank CSV, BNP Paribas XLSX i Inny CSV,
- generyczny Import XLSX z ręcznym mapowaniem kolumn,
- automatyczne mapowanie kategorii banku na Category,
- podpowiedź zwrotu w podglądzie Importu oraz dopasowanie wpływu do konkretnego wydatku,
- powiązanie zwrotu wydatku z konkretną operacją wydatku,
- zmiana zwykłego wydatku w dochód (albo dochodu w zwykły wydatek) przy edycji,
- oznaczanie nowo tworzonego dochodu jako zwrotu w dialogu dochodu,
- cicha autodetekcja formatu bez potwierdzenia użytkownika,
- budżety współdzielone przez wielu domowników,
- waluty inne niż PLN i przeliczanie walut,
- natywne aplikacje mobilne,
- funkcje AI,
- portfel inwestycyjny,
- kredyty, pożyczki i rozliczenia podatkowe,
- nieruchomości, pojazdy i inne niepłynne składniki majątku.

## 13. Ryzyka i zależności

- Bank może zmienić układ eksportu; nazwany Import format wtedy się rozjedzie, a format Inny oraz podgląd ograniczają skutki.
- Blokady kart w podglądzie BNP mogą zostać omyłkowo zatwierdzone i później zdublować się z operacją zrealizowaną; użytkownik je odznacza.
- Import duplicate obejmuje tylko wcześniejsze zatwierdzenia Importu z odciskiem; ręczne wpisy, rekordy sprzed odcisku i bliźniaki w pliku bez wcześniejszego zapisu nie są oznaczane. Ponowne włączenie Import duplicate może utworzyć drugi rekord.
- Okno trzech dni i dokładna przeciwna kwota mogą wskazać więcej niż jednego Import pair candidate; użytkownik wybiera nogę. Para nie gwarantuje, że bankowy przelew wewnętrzny należy do tego użytkownika.
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
- strategii wdrożenia i hostingu,
- wyboru konkretnej biblioteki PHP do odczytu XLSX (musi być wyłącznie do odczytu).

Wybrane rozwiązania muszą spełnić wymagania funkcjonalne, reguły biznesowe, wymagania niefunkcjonalne i kryteria akceptacji opisane w tym dokumencie.
