# Household Treasurer — Product Requirements Document

## 1. Streszczenie produktu

Household Treasurer jest responsywną aplikacją webową do planowania rocznego budżetu domowego, rejestrowania jego wykonania oraz kontrolowania rzeczywistych środków na rachunkach bankowych, kontach gotówkowych i lokatach bankowych (Term Deposit).

Produkt łączy dwie odrębne perspektywy:

- **budżet** — plan początkowy, plan aktualny i wykonanie kategorii, w tym Paced Category i One-off Category oraz odczyt Month Remaining,
- **płynność** — Spendable Liquidity (salda kont) i Total funds (te salda plus bieżące salda lokat).

MVP obsługuje wielu niezależnych użytkowników. Konta logowania tworzy administrator. Każdy użytkownik zarządza wyłącznie własnymi danymi, używając stałej waluty bazowej PLN.

## 2. Problem

Osoba prowadząca budżet domowy potrzebuje jednocześnie:

- zaplanować dochody i limity wydatków na cały rok,
- zachować punkt odniesienia w postaci pierwotnego planu,
- aktualizować plan w reakcji na zmieniającą się sytuację,
- rejestrować rzeczywiste dochody, wydatki, transfery i ruchy kapitału na lokaty,
- wiedzieć, ile środków jest do dyspozycji na kontach, a ile łącznie z lokatami,
- kontrolować wykonanie kategorii bez mylenia limitu budżetowego z dostępnymi środkami.

Arkusze kalkulacyjne i oddzielne zestawienia rachunków wymagają ręcznego uzgadniania danych. Utrudnia to ocenę, czy plan jest wykonywany oraz czy użytkownik posiada środki potrzebne do jego realizacji.

## 3. Cele i mierniki sukcesu

### 3.1. Cele

- Umożliwić przygotowanie kompletnego budżetu dla roku kalendarzowego.
- Zachować niezmienny plan początkowy i oddzielić go od planu aktualnego.
- Powiązać rzeczywiste dochody i wydatki jednocześnie z kontem oraz kategorią.
- Zapewnić poprawną ewidencję transferów między własnymi kontami.
- Umożliwić ewidencję lokat bankowych (Term Deposit) i ruchów kapitału między kontem a lokatą bez wpływu na wykonanie budżetu.
- Ułatwić wprowadzanie operacji przez kontrolowany Import z pliku CSV lub XLSX.
- Zapewnić przejrzysty obraz wykonania budżetu, Planned Balance, Month Result, Month Remaining puli równomiernych oraz Spendable Liquidity i Total funds.

### 3.2. Mierniki sukcesu MVP

MVP spełnia cel produktowy, gdy użytkownik może samodzielnie:

1. utworzyć konta i rozpocząć ich ewidencję od poprawnych sald otwarcia,
2. przygotować jeden budżet roczny z kategoriami, planowanymi dochodami i limitami wydatków,
3. rozpocząć wykonanie i zachować niezmienny plan początkowy,
4. rejestrować, edytować i usuwać dochody, wydatki, zwroty oraz transfery własne,
5. importować wybrane operacje z pliku CSV lub XLSX po sprawdzeniu podglądu,
6. porównywać plan początkowy, plan aktualny i wykonanie każdej kategorii,
7. odczytać salda poszczególnych kont (Spendable Liquidity) oraz Total funds łącznie z lokatami,
8. rozpoznać przekroczenie limitu niezależnie od stanu środków na kontach,
9. rozpocząć kolejny rok, kopiując kategorie i plan aktualny z wcześniejszego budżetu,
10. utworzyć lokatę z bankiem, datą i saldem otwarcia ewidencji oraz przenieść kapitał między kontem a lokatą,
11. sklasyfikować wiersz Importu jako Capital Movement na istniejącą lokatę, w tym rozdzielić zwrot kapitału i odsetki,
12. oznaczyć kategorię wydatkową jako Paced Category albo One-off Category i odczytać Month Remaining,
13. wyrównać plan aktualny do wykonania (Align) bez zmiany planu początkowego,
14. filtrować listę operacji po roku, nazwie i typie kategorii, koncie, lokacie, zakresie dat i tekście.

## 4. Użytkownik i platforma

### 4.1. Główna persona

Osoba samodzielnie zarządzająca własnymi finansami domowymi, która chce planować budżet w ujęciu rocznym i regularnie porównywać plan z rzeczywistymi przepływami pieniężnymi.

### 4.2. Model użytkowników

- System może obsługiwać wielu użytkowników.
- Konto logowania tworzy administrator (`user:create`). Samodzielna rejestracja w interfejsie webowym jest wyłączona.
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
- Term Deposit (lokata bankowa) nie jest kontem ani kategorią. Każda lokata jest osobnym holdingiem, nie wspólną pulą. Istnieje niezależnie od roku budżetowego. Wymaga Bank. Opcjonalna data zakończenia jest wyłącznie informacyjna: nie zmienia sald ani Capital Movements i nie jest kalendarzem zapadalności.
- Kategoria wydatkowa jest albo Paced Category (domyślnie), albo One-off Category. Kategoria dochodowa nie ma tej flagi. Month Remaining dotyczy wyłącznie puli równomiernych i nie jest Available Limit.
- Bank na rachunku bankowym i lokacie to instytucja (Alior, BNP Paribas, ING, Lokaty Facto, mBank, Pekao, PKO BP, Revolut, Santander albo Inny). To nie jest Import format.
- Walutą wszystkich kont, lokat, budżetów i operacji w MVP jest PLN.
- Dochody i wydatki są ujmowane kasowo, w dniu rzeczywistej zapłaty. Odsetki od lokaty są zwykłym dochodem na koncie i kategorii dochodowej; nie zmieniają salda lokaty.
- Wszystkie dane są zapisywane trwale.
- Kwoty są ewidencjonowane z dokładnością do jednego grosza, bez obliczeń zmiennoprzecinkowych.

## 6. Główne przepływy użytkownika

### 6.1. Rozpoczęcie pracy

1. Administrator tworzy konto logowania. Użytkownik loguje się (po weryfikacji e-mail, gdy konto jej wymaga).
2. Tworzy rachunki bankowe oraz konta gotówkowe.
3. Dla każdego konta podaje datę i saldo otwarcia. Na rachunku bankowym wskazuje Bank.
4. W razie potrzeby tworzy lokaty (Term Deposit) z Bank, datą i saldem otwarcia ewidencji oraz opcjonalną datą zakończenia. Saldo otwarcia lokaty nie jest dochodem i nie zmienia salda konta.
5. Tworzy szkic budżetu dla wybranego roku. Gdy ma wcześniejszy budżet z niezarchiwizowanymi kategoriami, może skopiować te kategorie wraz z planem aktualnym.
6. W razie potrzeby poprawia, usuwa lub dopisuje kategorie oraz planowane kwoty na szkicu.
7. Sprawdza równowagę planu i rozpoczyna wykonanie.

### 6.2. Bieżąca ewidencja

1. Użytkownik wybiera ręczne dodanie operacji albo Import z pliku.
2. Wskazuje datę, kwotę, opis, opcjonalnie nadawcę / odbiorcę, konto oraz właściwą kategorię.
3. Przed zapisaniem wydatku widzi dostępny limit i wartość po operacji.
4. Zapisana operacja zmienia saldo konta i wykonanie kategorii.
5. Transfer własny zmienia wyłącznie salda dwóch kont.
6. Capital Movement przenosi kapitał między kontem a istniejącą lokatą i nie wpływa na wykonanie budżetu.
7. Przy Imporcie podgląd oznacza już zapisany wiersz z tego konta (Import duplicate) oraz drugą nogę transferu własnego z innego konta (Import pair candidate). Użytkownik włącza te wiersze świadomie. Wiersz może zostać sklasyfikowany jako Capital Movement; Capital Movement nie jest kandydatem pary.

### 6.3. Kontrola

1. Użytkownik przegląda salda kont (Spendable Liquidity), salda lokat oraz Total funds.
2. Dla każdej kategorii porównuje plan początkowy, plan aktualny i wykonanie. Widzi Planned Balance (Bilans) oraz Month Result (Wynik) wybranego miesiąca.
3. Analizuje Available Limit, Month Remaining puli równomiernych, procent wykonania oraz przekroczenia.
4. W razie potrzeby aktualizuje plan, wyrównuje go do wykonania (Align) albo pozostawia widoczne odchylenie.

## 7. Wymagania funkcjonalne

### 7.1. Tożsamość i dostęp

- **FR-AUTH-01**: Administrator może utworzyć konto logowania (`user:create`) z nazwą, adresem e-mail i hasłem. Domyślnie konto jest od razu zweryfikowane; opcja `--unverified` zostawia weryfikację e-mail do wykonania przez użytkownika. Samodzielna rejestracja w interfejsie webowym jest wyłączona.
- **FR-AUTH-02**: Użytkownik może się zalogować i wylogować.
- **FR-AUTH-03**: Użytkownik może rozpocząć i zakończyć proces resetowania hasła.
- **FR-AUTH-04**: Po zalogowaniu użytkownik ma dostęp wyłącznie do własnych danych.
- **FR-AUTH-05**: Nieweryfikowany użytkownik nie ma dostępu do aplikacji, dopóki nie potwierdzi adresu e-mail.
- **FR-AUTH-06**: Użytkownik może włączyć uwierzytelnianie dwuskładnikowe (TOTP) oraz klucze dostępu (passkeys).

### 7.2. Konta

- **FR-ACC-01**: Użytkownik może utworzyć rachunek bankowy albo konto gotówkowe.
- **FR-ACC-02**: Konto zawiera nazwę, typ, datę otwarcia, saldo otwarcia i saldo bieżące. Rachunek bankowy zawiera także Bank (Alior, BNP Paribas, ING, Lokaty Facto, mBank, Pekao, PKO BP, Revolut, Santander albo Inny).
- **FR-ACC-03**: Użytkownik może edytować nazwę, typ i saldo otwarcia konta. Na rachunku bankowym może edytować także Bank.
- **FR-ACC-04**: Data otwarcia nie może być późniejsza niż najstarsza operacja zapisana na koncie.
- **FR-ACC-05**: Operacja nie może mieć daty wcześniejszej niż data otwarcia użytego konta.
- **FR-ACC-06**: Konto bez operacji może zostać usunięte.
- **FR-ACC-07**: Konto użyte w operacji nie może zostać usunięte, ale może zostać zarchiwizowane.
- **FR-ACC-08**: Zarchiwizowane konto nie jest dostępne przy tworzeniu nowych operacji i może zostać przywrócone.
- **FR-ACC-09**: System pokazuje saldo bieżące każdego konta oraz sumę sald wszystkich kont (Spendable Liquidity), z wyraźnym oznaczeniem kont zarchiwizowanych. Ta suma nie obejmuje sald lokat.
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
- **FR-BUD-09**: Przed rozpoczęciem wykonania system pokazuje ostrzeżenie, jeżeli planowany deficyt nie ma pokrycia w saldach kont obliczonych na początek roku budżetowego. Pokrycie liczy się ze Spendable Liquidity (konta), nie z Total funds ani z sald lokat.
- **FR-BUD-10**: Ostrzeżenie o planowanym deficycie nie blokuje rozpoczęcia wykonania.
- **FR-BUD-11**: Przy tworzeniu szkicu budżetu użytkownik może skopiować kategorie i ich plan aktualny z innego własnego budżetu o wcześniejszym roku.
- **FR-BUD-12**: Dialog tworzenia oferuje wyłącznie budżety źródłowe tego użytkownika z rokiem wcześniejszym niż rok docelowy i z co najmniej jedną niezarchiwizowaną kategorią. Domyślne źródło to budżet poprzedniego roku kalendarzowego, a gdy go nie ma — najnowszy kwalifikowany wcześniejszy budżet. Brak kwalifikowanego źródła oznacza utworzenie pustego szkicu jak dotychczas.
- **FR-BUD-13**: Użytkownik może utworzyć pusty szkic mimo dostępnego źródła.
- **FR-BUD-14**: Utworzenie budżetu ze skopiowanymi kategoriami jest atomowe. Nowy budżet pozostaje szkicem i nie rozpoczyna wykonania.

### 7.4. Kategorie i plany

- **FR-CAT-01**: Użytkownik może dodać do budżetu kategorię dochodową albo wydatkową z nazwą, ikoną, kolorem i planowaną kwotą.
- **FR-CAT-02**: Kategoria należy do jednego budżetu rocznego.
- **FR-CAT-03**: Kategoria dochodowa ma nieujemną planowaną kwotę dochodu.
- **FR-CAT-04**: Kategoria wydatkowa ma nieujemny limit wydatków.
- **FR-CAT-05**: W szkicu użytkownik może zmieniać nazwy, typy, wygląd, flagę Paced i planowane kwoty kategorii.
- **FR-CAT-06**: Po rozpoczęciu wykonania nie można zmienić typu istniejącej kategorii.
- **FR-CAT-07**: Po rozpoczęciu wykonania użytkownik może zmieniać plan aktualny bez modyfikowania planu początkowego.
- **FR-CAT-08**: Po rozpoczęciu wykonania użytkownik może dodać kategorię, której plan początkowy wynosi 0 PLN, a plan aktualny przyjmuje podaną kwotę.
- **FR-CAT-09**: Kategoria może zostać usunięta ze szkicu budżetu.
- **FR-CAT-10**: Po rozpoczęciu wykonania kategoria nie może zostać usunięta, ale może zostać zarchiwizowana.
- **FR-CAT-11**: Zarchiwizowana kategoria nie jest dostępna przy tworzeniu nowych operacji i może zostać przywrócona.
- **FR-CAT-12**: Skopiowana kategoria jest nowym rekordem docelowego budżetu. Otrzymuje nazwę, typ, ikonę, kolor, kolejność, flagę Paced oraz plan aktualny źródła. Nie przejmuje tożsamości, wykonania, planu początkowego ani archiwum źródła.
- **FR-CAT-13**: Zarchiwizowane kategorie źródła nie są kopiowane.
- **FR-CAT-14**: Użytkownik może zmieniać kolejność kategorii w obrębie typu (dochód albo wydatek).
- **FR-CAT-15**: Kategoria wydatkowa jest Paced Category (domyślnie) albo One-off Category. Kategoria dochodowa nie przyjmuje flagi Paced. Flaga jest żywa jak plan aktualny: użytkownik może ją zmieniać na niezarchiwizowanej kategorii wydatkowej także podczas wykonania.
- **FR-CAT-16**: One-off Category nie ma Month Remaining i nie wchodzi do puli równomiernych. Nadal ma roczny Available Limit.
- **FR-CAT-17**: Align (w UI: „Zakończ”) na niezarchiwizowanej kategorii budżetu w fazie wykonania ustawia plan aktualny na `max(0, Execution)`. Plan początkowy nie zmienia się. To nie jest rytuał końca roku ani zamknięcie Month Remaining.

### 7.5. Dochody, wydatki i zwroty

- **FR-TRX-01**: Użytkownik może ręcznie utworzyć dochód albo wydatek.
- **FR-TRX-02**: Operacja zawiera datę, niezerową kwotę, opis, konto i kategorię odpowiedniego typu; może też zawierać opcjonalnego nadawcę / odbiorcę (Counterparty).
- **FR-TRX-03**: Data operacji nie może przypadać w przyszłości.
- **FR-TRX-04**: Data operacji wskazuje budżet roczny, na którego wykonanie operacja wpływa.
- **FR-TRX-05**: Dochód albo wydatek można zapisać tylko dla roku z budżetem w fazie wykonania.
- **FR-TRX-06**: Zwykły dochód i zwykły wydatek mają kwotę dodatnią.
- **FR-TRX-07**: Zwrot wydatku jest ujemnym wydatkiem przypisanym do kategorii wydatkowej; może też zawierać opcjonalnego nadawcę / odbiorcę (Counterparty).
- **FR-TRX-08**: Przed zapisaniem wydatku system pokazuje aktualny Available Limit i przewidywany limit po operacji. Dla Paced Category pokazuje także Month Remaining przed operacją i po niej.
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

### 7.7. Lokaty (Term Deposit)

Term Deposit jest holdingiem środków w bankowym produkcie lokaty. Nie jest kontem, kategorią, końcem transferu własnego ani kontem operacji finansowej. W interfejsie: „lokata”.

- **FR-TD-01**: Użytkownik może utworzyć lokatę z nazwą, Bank, datą otwarcia ewidencji i saldem otwarcia. Opcjonalnie podaje datę zakończenia.
- **FR-TD-02**: Data otwarcia to pierwszy dzień tego holdingu w ewidencji, nie data rozpoczęcia produktu bankowego. Saldo otwarcia jest migawką: nie jest dochodem, nie jest ruchem kapitału, nie rozdziela kapitału od odsetek i nie zmienia żadnego konta. Może być zerowe albo ujemne.
- **FR-TD-03**: Saldo bieżące lokaty jest równe tej migawce plus Capital Movements. Odsetki nie zmieniają salda lokaty.
- **FR-TD-04**: Po utworzeniu data otwarcia jest niezmienna. Użytkownik może edytować nazwę, Bank, saldo otwarcia i datę zakończenia; zmiana salda otwarcia przelicza saldo bieżące o tę samą różnicę, bez tworzenia dochodu ani Capital Movement.
- **FR-TD-05**: Lokatę bez ruchów kapitału można usunąć. Lokaty użytej w Capital Movement nie można usunąć, ale można ją zarchiwizować i przywrócić.
- **FR-TD-06**: Zarchiwizowana lokata nie jest dostępna przy tworzeniu nowych Capital Movements. Historyczne ruchy pozostają. Lista lokat ukrywa zarchiwizowane pozycje, dopóki użytkownik ich nie pokaże.
- **FR-TD-07**: Użytkownik ma osobną pozycję nawigacji „Lokaty” (po Kontach): lista i dialogi tworzenia oraz edycji. Karty na pulpicie są tylko do odczytu i pomijają zarchiwizowane lokaty. Lista operacji może filtrować po lokacie.
- **FR-TD-08**: Saldo bieżące lokaty może być ujemne i musi być wtedy jednoznacznie oznaczone na każdej powierzchni, która je pokazuje.
- **FR-TD-09**: Utworzenie albo korekta migawki otwarcia, która zostawiłaby saldo bieżące ujemne, pokazuje Negative Balance Warning i nie blokuje zapisu. Ta operacja nie zmienia konta, więc ostrzeżenie dotyczy wyłącznie lokaty.
- **FR-TD-10**: Import nie tworzy lokaty. Użytkownik wybiera istniejącą lokatę przy klasyfikacji wiersza.
- **FR-TD-11**: First Run nie wymaga lokaty. Checklista pierwszego uruchomienia nadal obejmuje wyłącznie konto, budżet, kategorię i budżet w wykonaniu.
- **FR-TD-12**: Data zakończenia jest opcjonalna i wyłącznie informacyjna. Nie wpływa na salda, Capital Movements ani Total funds. Nie może przypadać przed datą otwarcia. Nie jest kalendarzem zapadalności ani cechą oprocentowania.

### 7.8. Ruchy kapitału (Capital Movement)

- **FR-CM-01**: Użytkownik może ręcznie utworzyć Capital Movement między jednym niezarchiwizowanym kontem a jedną niezarchiwizowaną lokatą. Gotówka jest poprawnym kontem.
- **FR-CM-02**: Rekord zawiera datę, dodatnią niezerową kwotę, opis, konto, lokatę i kierunek: konto → lokata albo lokata → konto. Nie ma kategorii i nie wpływa na wykonanie budżetu. Nie ma Counterparty.
- **FR-CM-03**: Jeden rekord zmienia oba holdingi o tę samą kwotę. Lokata może mieć wiele ruchów; każdy wskazuje własne konto, więc zwrot kapitału nie musi używać tego samego konta co otwarcie.
- **FR-CM-04**: Data nie może przypadać w przyszłości, być wcześniejsza niż data otwarcia konta ani wcześniejsza niż data otwarcia lokaty.
- **FR-CM-05**: Użytkownik może edytować i usuwać Capital Movement. Utworzenie, edycja albo usunięcie aktualizuje obie strony albo żadnej.
- **FR-CM-06**: Ruch może spowodować ujemne saldo konta, lokaty albo obu. Negative Balance Warning nie blokuje zapisu; gdy obie strony zejdą poniżej zera, system pokazuje dwa ostrzeżenia.
- **FR-CM-07**: Przy edycji użytkownik może przebudować operację finansową albo transfer własny w Capital Movement i odwrotnie. Przebudowa odwraca poprzedni wpływ i księguje nowy typ; nie zostawia drugiego zaksięgowanego ruchu. Odcisk Importu, jeśli istnieje, przechodzi na nowy rekord. Podział zwrotu kapitału i odsetek jest wyłącznie w Imporcie — edycja nie przyjmuje pól odsetek.
- **FR-CM-08**: Capital Movement z Importu przechowuje co najwyżej jeden oryginalny opis z wyciągu (konto płynne). Ręczne utworzenie pozostawia go pustym. Edycja opisu nie zmienia oryginalnego opisu z wyciągu.
- **FR-CM-09**: Brak bezpośredniego ruchu lokata → lokata. Przeniesienie kapitału między lokatami to zwrot na konto, potem otwarcie albo zasilenie drugiej lokaty.

### 7.9. Import

Import odbywa się w tym samym kreatorze dla CSV i XLSX. Użytkownik wybiera **Import format**, wskazuje konto, przegląda podgląd i zatwierdza wybrane wiersze. To nie jest połączenie z bankiem. Import może utworzyć operacje finansowe, transfery własne i Capital Movements.

Podgląd oznacza dwa rodzaje już znanych środków jako osobne, poprawne nakładki: **Import duplicate** (to samo konto, ten sam zamrożony wiersz Importu) oraz **Import pair candidate** (inne konto już zaksięgowało te środki przez wcześniejszy Import). Oba startują odznaczone; użytkownik je włącza. Niepoprawny wiersz wygrywa z obiema nakładkami. Jeden Import dotyczy jednego wskazanego konta. Capital Movement nigdy nie jest Import pair candidate ani pierwszą nogą pary.

- **FR-IMP-01**: Użytkownik może wczytać plik CSV albo XLSX.
- **FR-IMP-02**: Użytkownik wybiera Import format: mBank, BNP Paribas albo Inny. System może wstępnie zaznaczyć format na podstawie rozszerzenia pliku; użytkownik może to zmienić. System nie importuje po cichej autodetekcji.
- **FR-IMP-03**: Plik musi pasować do wybranego formatu: mBank i Inny wymagają CSV, BNP Paribas wymaga XLSX. Niezgodność jest błędem pliku.
- **FR-IMP-04**: Użytkownik wskazuje konto, którego dotyczy Import.
- **FR-IMP-05**: Format mBank: CSV. System znajduje wiersz nagłówka `#Data operacji`. Linie przed nim nie trafiają do podglądu. Data = Data operacji, opis = Opis operacji, kwota = Kwota (kolumna ze znakiem). Nadawca / odbiorca pozostaje pusty. Opis i nadawca / odbiorca na liście pomijają numery instrumentów płatniczych (NRB, IBAN, PAN). Oryginalny opis z wyciągu = Opis operacji bez zmian.
- **FR-IMP-06**: Format BNP Paribas: XLSX, wyłącznie pierwszy arkusz, nagłówek w pierwszym wierszu. Data = Data transakcji (w tym daty seryjne Excela). Nadawca / odbiorca pochodzi z kolumny Nadawca / odbiorca, albo — gdy eksport rozdziela strony — z Odbiorcy przy kwocie ujemnej i z Nadawcy przy kwocie dodatniej (gdy wybrana komórka pusta, używana jest druga; pusty dozwolony). Opis → opis. Gdy Opis jest pusty, a nadawca / odbiorca nie — opis i nadawca / odbiorca równe tej wartości. Gdy oba puste — wiersz niepoprawny (brak opisu). Opis i nadawca / odbiorca na liście pomijają numery instrumentów płatniczych. Oryginalny opis z wyciągu = niepusty nadawca / odbiorca i Opis połączone (nowa linia, gdy oba) bez zmian. Kwota = Kwota (kolumna ze znakiem). Wiersze blokad i statusu „W trakcie realizacji” pozostają w podglądzie; użytkownik je odznacza.
- **FR-IMP-07**: Format Inny: wyłącznie CSV. Użytkownik mapuje kolumny daty, opisu i kwoty oraz oznacza, czy plik ma nagłówek. Opcjonalnie mapuje kolumnę nadawcy / odbiorcy. Pliki trzykolumnowe nadal działają. Opis i nadawca / odbiorca na liście pomijają numery instrumentów płatniczych. Oryginalny opis z wyciągu = zmapowana komórka opisu plus zmapowana komórka nadawcy / odbiorcy, gdy jest, bez zmian. Kwota może być jedną kolumną ze znakiem albo osobnymi kolumnami wpływów i obciążeń. Dla tego formatu pokazywany jest ekran mapowania kolumn. Formaty nazwane nie pokazują mapowania kolumn; mogą pokazać nieedytowalny skrót użytych pól.
- **FR-IMP-08**: Po nagłówku formatu (albo po starcie wynikającym z mapowania) wiersze, w których zmapowana data nie jest datą albo zmapowana kwota nie jest kwotą, są pomijane i nie pojawiają się w podglądzie jako błędy.
- **FR-IMP-09**: Wiersz, który wygląda jak operacja, ale łamie reguły biznesowe, jest oznaczony jako niepoprawny wraz z przyczyną i nie może zostać wybrany do Importu.
- **FR-IMP-10**: Z komórki kwoty zdejmowane są suffiksy `PLN` i `zł`. Inny kod waluty w tej komórce czyni wiersz niepoprawnym. Osobna kolumna waluty nie wchodzi do mapowania.
- **FR-IMP-11**: Kolumny kategorii banku nie są mapowane na Category. Kategorię Household Treasurer użytkownik wskazuje w podglądzie.
- **FR-IMP-12**: System pokazuje podgląd sparsowanych wierszy przed utworzeniem operacji.
- **FR-IMP-13**: Użytkownik wybiera poprawne wiersze przeznaczone do Importu.
- **FR-IMP-14**: Każdy wybrany wiersz musi otrzymać typ operacji oraz dane wymagane dla tego typu.
- **FR-IMP-15**: Użytkownik może utworzyć z wiersza dochód, wydatek, zwrot, transfer własny albo Capital Movement.
- **FR-IMP-16**: Dla transferu użytkownik wskazuje drugie konto, a system tworzy cały transfer atomowo. Transfer własny z pierwszego pliku rusza oba salda przy tym zatwierdzeniu; system nie czeka na drugi Import.
- **FR-IMP-17**: Dwa wiersze tego samego pliku nie są parowane jako strony jednego transferu własnego.
- **FR-IMP-18**: Podgląd oznacza Import duplicate, gdy wiersz jest tym samym wierszem Importu co już zatwierdzona pozycja z Importu tego użytkownika na tym koncie: data z pliku, kwota ze znakiem i oryginalny opis z wyciągu, zamrożone w chwili zapisu. Trafienie obejmuje operację finansową, transfer własny i Capital Movement. Wiersz pozostaje poprawny; pole wyboru jest odznaczone. Ręczne wpisy oraz rekordy zapisane, zanim istniały odciski Importu, nie są Import duplicate. Edycja daty, kwoty, konta, opisu albo nadawcy / odbiorcy nie zmienia odcisku. Usunięcie rekordu usuwa odcisk.
- **FR-IMP-19**: Włączenie Import duplicate i zatwierdzenie może utworzyć drugi rekord. Potwierdzenie jest wymagane tylko wtedy, gdy użytkownik wybrał co najmniej jeden Import duplicate. System oznacza znane trafienia i nie gwarantuje unikalności wszystkich wierszy.
- **FR-IMP-20**: Wszystkie wybrane poprawne pozycje — w tym pary i zwykłe księgowania — są zapisywane atomowo albo żadna nie zostaje zapisana.
- **FR-IMP-21**: Podgląd oznacza Import pair candidate, gdy wiersz należy do tego użytkownika, leży na innym koncie niż istniejąca pozycja z Importu, zamrożone kwoty ze znakiem są dokładnymi przeciwieństwami, a zamrożone daty z pliku mieszczą się w oknie trzech dni włącznie. Ręczne wpisy nie są kandydatami. To samo konto i ten sam zamrożony wiersz to Import duplicate, nie para. Capital Movement nigdy nie jest kandydatem pary i nigdy nie jest pierwszą nogą.
- **FR-IMP-22**: Zaznaczenie Import pair candidate i zatwierdzenie pozostawia jeden transfer własny: albo przekształca pierwszą nogę będącą dochodem albo wydatkiem (wykonanie kategorii wraca, potem ruszają salda obu kont), albo dopina drugą tożsamość Importu do istniejącego transferu własnego bez ponownego ruchu sald. Usunięcie transferu własnego usuwa obie tożsamości; nie ma osobnego rozparowania przywracającego dochód albo wydatek.
- **FR-IMP-23**: Użytkownik może sklasyfikować kandydata będącego wyłącznie dochodem albo wydatkiem jako dochód, wydatek, zwrot albo nowy transfer własny na inne konto. Gdy w zbiorze kandydatów jest transfer własny, który już ruszył konto z podglądu, typ jest zablokowany na transfer własny.
- **FR-IMP-24**: Po parze transfer własny przechowuje dwa oryginalne opisy z wyciągu i dwa odciski Import duplicate, po jednym na konto z Importu. Ponowny Import dowolnego z tych plików oznacza wiersz jako Import duplicate.
- **FR-IMP-25**: Domyślny typ w podglądzie nadal wynika ze znaku kwoty (uznanie = dochód, obciążenie = wydatek). Użytkownik musi świadomie przełączyć typ na Capital Movement i wskazać istniejącą lokatę. Kierunek wynika ze znaku na koncie: obciążenie to konto → lokata, uznanie to lokata → konto. System nie zgaduje lokaty z układu pliku banku.
- **FR-IMP-26**: Na wierszu uznania sklasyfikowanym jako Capital Movement użytkownik może podać kwotę odsetek i kategorię dochodową. Zatwierdzenie tworzy dwa niezależne rekordy: Capital Movement (lokata → konto) na resztę kapitału oraz operację finansową (dochód) na wpisane odsetki. Nie są one trwałą parą. Kwota odsetek musi być dodatnia i mniejsza od kwoty z pliku, tak aby kapitał pozostał dodatni. Ręczne dodanie nigdy nie tworzy obu rekordów w jednym zapisie.
- **FR-IMP-27**: Gdy jeden wiersz zatwierdza Capital Movement i dochód z odsetek, oba rekordy otrzymują tę samą tożsamość wiersza pliku (nie dwa odciski rozdzielonych kwot). Capital Movement ma jeden odcisk, na koncie płynnym.

### 7.10. Lista operacji

- **FR-OPS-01**: Lista operacji pokazuje operacje finansowe, transfery własne i Capital Movements użytkownika.
- **FR-OPS-02**: Użytkownik może filtrować listę po tekście, roku budżetowym, parze nazwa+typ Category, koncie, lokacie oraz zakresie dat.
- **FR-OPS-03**: Filtr kategorii jest zapytaniem po nazwie i typie, nie po tożsamości rekordu. Ta sama nazwa i typ w wielu latach trafia do jednego filtra; zmiana nazwy rozdziela historię. To nie jest tożsamość Category ponad latami.

### 7.11. Raportowanie i kontrola wykonania

- **FR-REP-01**: System pokazuje plan początkowy, plan aktualny, wykonanie, pozostałą kwotę i procent wykonania każdej kategorii.
- **FR-REP-02**: Dla kategorii dodanej podczas wykonania plan początkowy jest prezentowany jako 0 PLN.
- **FR-REP-03**: Dla planu aktualnego równego 0 PLN procent wykonania jest prezentowany jako „nie dotyczy”.
- **FR-REP-04**: Ujemna pozostała kwota jest prezentowana jako przekroczenie limitu.
- **FR-REP-05**: System pokazuje sumę planowanych i rzeczywistych dochodów.
- **FR-REP-06**: System pokazuje sumę limitów oraz rzeczywistych wydatków.
- **FR-REP-07**: System pokazuje salda kont niezależnie od wykonania kategorii (Spendable Liquidity).
- **FR-REP-08**: Zarchiwizowane konta, kategorie i lokaty pozostają widoczne w danych historycznych.
- **FR-REP-09**: Spendable Liquidity jest sumą bieżących sald wszystkich kont użytkownika, w tym zarchiwizowanych. Nie obejmuje sald lokat.
- **FR-REP-10**: Total funds jest sumą Spendable Liquidity i bieżących sald wszystkich lokat użytkownika, w tym zarchiwizowanych. Capital Movement z konta na lokatę nie zmienia Total funds.
- **FR-REP-11**: Pulpit pokazuje Spendable Liquidity, Total funds, karty niezarchiwizowanych kont oraz karty niezarchiwizowanych lokat.
- **FR-REP-12**: System pokazuje Planned Balance: plan aktualny dochodów minus plan aktualny wydatków dla jednego budżetu. To nie jest saldo kont.
- **FR-REP-13**: System pokazuje Month Remaining dla Paced Category i dla puli równomiernych w wybranym miesiącu roku budżetowego. One-off Category nie ma tego odczytu. Month Remaining nie jest Available Limit i nie jest równym podziałem planu aktualnego.
- **FR-REP-14**: System pokazuje Month Result wybranego miesiąca: wykonanie dochodów minus wykonanie wydatków (w tym zarchiwizowane i One-off; zwroty netto w wydatkach). Own Transfer i Capital Movement nie wchodzą do tego odczytu.
- **FR-REP-15**: Mierniki pulpitu i sumy planu (dochody, wydatki, Planned Balance) pomijają zarchiwizowane kategorie. Lista budżetu pokazuje je w osobnej sekcji i pomija w sumach planu, dopóki użytkownik nie poprosi o pokazanie zarchiwizowanych. Arytmetyka Month Remaining nadal używa wszystkich Paced Category tego budżetu, w tym zarchiwizowanych.

### 7.12. Narzędzia operacyjne (Artisan)

Komendy CLI dla administratora i pracy lokalnej. Nie są częścią interfejsu webowego MVP. Produkcyjny backup całej instancji to archiwum Spatie (PostgreSQL plus `storage/app`, por. ADR-0013 i tech stack). Poniższe komendy `user:*` eksportują i przywracają **dane domenowe jednego użytkownika** w formacie JSON.

- **FR-ADM-01**: Administrator może wyeksportować snapshot danych domenowych użytkownika (`user:backup {user}`). Snapshot zawiera konta, lokaty, budżety, kategorie (w tym flagę Paced), operacje finansowe, transfery własne, Capital Movements oraz stan checklisty pierwszego uruchomienia. Nie zawiera hasła, 2FA, passkeys ani sesji. Plik zapisywany jest jako JSON wersji 4 w `storage/app/private/user-snapshots/` z nazwą `{user-id}-{timestamp}.json`. Restore przyjmuje także starsze wersje 1–3 (wersja 1 bez lokat i ruchów kapitału; wersja 2 z lokatami bez ruchów kapitału; brak `is_paced` przywraca domyślne `true` dla kategorii wydatkowej).
- **FR-ADM-02**: Administrator może przywrócić snapshot na **istniejącego** użytkownika-cel (`user:restore {user} {path}`). Przed wgraniem system usuwa wszystkie dane domenowe użytkownika-celu (jak `user:reset-data`). Restore nadaje rekordom nowe identyfikatory i przepina powiązania, więc snapshot można przenieść między różnymi użytkownikami. Operacja wymaga potwierdzenia; opcja `--force` pomija pytanie.
- **FR-ADM-03**: Administrator może wyczyścić dane domenowe użytkownika bez usuwania konta logowania (`user:reset-data {user}`). Komenda usuwa konta, lokaty, budżety, operacje, transfery, Capital Movements i sesje użytkownika oraz resetuje checklistę pierwszego uruchomienia.
- **FR-ADM-04**: Restore odrzuca plik z nieobsługiwaną wersją formatu lub uszkodzonym JSON.
- **FR-ADM-05**: Administrator może przywrócić backup całej instancji ze zipa Spatie (`backup:restore {path}`). To nie jest `user:restore`.

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
- **BR-12**: Przekroczenie limitu oraz ujemne saldo konta albo lokaty są dozwolone.
- **BR-13**: Dochód lub wydatek należy do budżetu wskazanego przez rok daty operacji.
- **BR-14**: Przyszłe płatności i zobowiązania nie są operacjami finansowymi MVP.
- **BR-15**: Historia elementu użytego w operacjach albo ruchach kapitału jest zachowywana przez archiwizację zamiast usunięcia.
- **BR-16**: Skopiowany plan aktualny pochodzi z planu aktualnego źródła, nie z planu początkowego ani wykonania źródła. Plan początkowy docelowego budżetu powstaje dopiero przy rozpoczęciu wykonania.
- **BR-17**: Edycja może zmienić dochód w zwrot wydatku i zwrot wydatku w dochód. Nie wiąże zwrotu z konkretnym wcześniejszym wydatkiem. Nie zamienia zwykłego wydatku w dochód.
- **BR-18**: Term Deposit nie jest kontem. Operacja finansowa i transfer własny nigdy nie wskazują lokaty jako konta.
- **BR-19**: Capital Movement nie wpływa na wykonanie budżetu. Nie jest transferem własnym ani operacją finansową.
- **BR-20**: Spendable Liquidity to suma sald kont. Total funds to Spendable Liquidity plus salda lokat. Otwarcie albo zasilenie lokaty nie zmienia Total funds.
- **BR-21**: Odsetki od lokaty są zwykłym dochodem na koncie i kategorii dochodowej. Rekord dochodu nie wskazuje lokaty i nie jest Capital Movement.
- **BR-22**: Migawka otwarcia lokaty nie jest dochodem, ruchem kapitału ani podziałem kapitału i odsetek.
- **BR-23**: Bank na rachunku bankowym i lokacie jest instytucją, nie Import format.
- **BR-24**: Month Remaining nie jest Available Limit. Unpaid przyszłe płatności nie zmniejszają Available Limit, bo nie są operacjami finansowymi.
- **BR-25**: Align przepisuje plan aktualny na `max(0, Execution)` i nie zmienia planu początkowego.
- **BR-26**: Data zakończenia lokaty nie wchodzi do sald, Capital Movements ani Total funds.

## 9. Przypadki brzegowe i obsługa błędów

- Próba zapisania dochodu lub wydatku bez budżetu w fazie wykonania kończy się komunikatem wskazującym konieczność utworzenia albo rozpoczęcia wykonania budżetu.
- Próba użycia zarchiwizowanego konta, kategorii albo lokaty jest blokowana bez zmiany danych.
- Próba zapisania operacji wcześniejszej niż data otwarcia konta albo późniejszej niż bieżący dzień jest blokowana.
- Próba zapisania Capital Movement wcześniejszego niż data otwarcia konta albo lokaty, albo z datą w przyszłości, jest blokowana.
- Przekroczenie limitu, zejście Month Remaining poniżej zera oraz zejście salda konta albo lokaty poniżej zera są pokazywane jako ostrzeżenia, ale nie blokują zapisu.
- Data zakończenia lokaty wcześniejsza niż data otwarcia jest odrzucana.
- Flaga Paced na kategorii dochodowej jest odrzucana.
- Zmiana daty operacji na inny rok nie zachowuje kategorii ze starego budżetu; użytkownik wybiera kategorię docelową.
- Próba zmiany zwykłego wydatku w dochód albo dochodu w zwykły wydatek jest odrzucana; dochód może stać się zwrotem i odwrotnie.
- Błąd dowolnej strony transferu wycofuje całą zmianę.
- Błąd zapisu dowolnej wybranej pozycji importu wycofuje cały zatwierdzany zestaw.
- Niepoprawny wiersz Importu pozostaje w podglądzie z opisem błędu i nie blokuje wyboru pozostałych poprawnych wierszy.
- Preambuła, nagłówek i inne linie niebędące operacją nie pojawiają się w podglądzie jako błędy.
- Wczytanie pliku niezgodnego z wybranym Import format (np. XLSX przy mBank) jest odrzucane przed podglądem.
- Podgląd oznacza znane Import duplicate i Import pair candidate jako osobne, odznaczone nakładki. Potwierdzenie jest wymagane tylko przy wyborze Import duplicate. Ręczne wpisy i rekordy bez odcisku nie są oznaczane.
- Błędy formularzy są prezentowane przy odpowiednich polach bez utraty poprawnie wprowadzonych danych.
- Import zapisuje oryginalny opis z wyciągu jednorazowo przy utworzeniu operacji finansowej, transferu własnego albo Capital Movement. Ręczne tworzenie pozostawia go pustym.
- Wiersz Importu sklasyfikowany jako Capital Movement bez istniejącej lokaty jest niepoprawny. Odsetki na wierszu obciążenia albo kwota odsetek niemniejsza niż kwota z pliku czynią wiersz niepoprawnym.
- Błąd zapisu dowolnej strony Capital Movement wycofuje całą zmianę.
- Odrzucenie kopii (nieistniejące, cudze, późniejsze albo puste źródło) nie tworzy budżetu.
- Niepowodzenie zapisu którejkolwiek skopiowanej kategorii wycofuje cały nowy budżet.

## 10. Wymagania niefunkcjonalne

- **NFR-SEC-01**: Każde odczytanie i zapis danych wymaga uwierzytelnienia.
- **NFR-SEC-02**: Dane użytkownika są odizolowane od danych wszystkich pozostałych użytkowników.
- **NFR-SEC-03**: Hasła nie są przechowywane ani rejestrowane w postaci jawnej.
- **NFR-DAT-01**: Zapis transferu, zatwierdzenie importu, utworzenie budżetu z kopią kategorii oraz zapis Capital Movement zapewniają określoną w wymaganiach atomowość.
- **NFR-DAT-02**: Ponowne odczytanie zapisanych danych daje te same kwoty i powiązania.
- **NFR-DAT-03**: Wszystkie operacje zmieniające dane zachowują spójność sald kont, sald lokat oraz wykonania kategorii.
- **NFR-PERF-01**: Przy 10 000 operacji finansowych użytkownika otwarcie listy kont, otwarcie podsumowania budżetu, otwarcie pierwszej strony listy operacji i zapis pojedynczej ręcznej operacji kończą się w ciągu 2 sekund w środowisku testów akceptacyjnych.
- **NFR-PERF-02**: Import obsługuje plik zawierający do 5 000 wierszy.
- **NFR-ACC-01**: Kluczowe przepływy logowania, budżetu, kont, lokat, operacji i importu spełniają WCAG 2.2 na poziomie AA.
- **NFR-ACC-02**: Kluczowe przepływy są w pełni obsługiwalne za pomocą klawiatury.
- **NFR-UX-01**: Interfejs dostosowuje się do ekranów komputerów i urządzeń mobilnych.
- **NFR-UX-02**: Wartości pieniężne są prezentowane jednoznacznie jako PLN.

Weryfikacja NFR-ACC-01 i NFR-ACC-02 następuje po pierwszym tygodniu dogfoodu; to wydanie nie zawiera formalnego audytu WCAG. NFR-PERF-02 oznacza górny limit 5 000 wierszy Importu i nie wprowadza osobnego limitu czasu.

## 11. Kryteria akceptacji

### 11.1. Dostęp i izolacja

1. Administrator tworzy konto logowania. Użytkownik może zalogować się, wylogować, odzyskać dostęp przez reset hasła, potwierdzić e-mail, włączyć 2FA i passkeys. Samodzielna rejestracja jest niedostępna.
2. Użytkownik nie może odczytać ani zmienić budżetu, konta, lokaty lub operacji należących do innego użytkownika.

### 11.2. Konta i płynność

1. Utworzenie konta z datą i saldem otwarcia pokazuje saldo bieżące równe saldu otwarcia.
2. Dodanie dochodu, wydatku, zwrotu albo transferu aktualizuje właściwe salda zgodnie z regułami biznesowymi.
3. Zmiana salda otwarcia przelicza saldo bieżące bez tworzenia dochodu.
4. Konto z historią można zarchiwizować i przywrócić, ale nie można go usunąć.
5. Spendable Liquidity jest równa sumie sald prezentowanych kont i nie zawiera sald lokat.
6. Total funds jest równa Spendable Liquidity plus salda lokat.

### 11.3. Budżet i plany

1. System blokuje utworzenie drugiego budżetu dla tego samego użytkownika i roku.
2. Rozpoczęcie wykonania zachowuje plan początkowy równy planowi ze szkicu.
3. Późniejsza zmiana planu aktualnego nie modyfikuje planu początkowego.
4. Nowa kategoria dodana podczas wykonania ma plan początkowy 0 PLN.
5. Ostrzeżenie o niepokrytym planowanym deficycie nie blokuje rozpoczęcia wykonania.
6. Utworzenie budżetu ze źródłem kopiuje wszystkie niezarchiwizowane kategorie źródła z planem aktualnym, wyglądem, kolejnością i flagą Paced; nowy budżet jest szkicem, a wykonanie źródła pozostaje bez zmian.
7. Skopiowane kategorie są osobnymi rekordami; ta sama nazwa w dwóch latach nie oznacza tej samej kategorii.
8. Opcja bez kopiowania tworzy szkic bez kategorii, nawet gdy źródło jest dostępne.
9. Zarchiwizowane kategorie źródła nie pojawiają się na nowym szkicu.
10. Domyślne źródło to poprzedni rok kalendarzowy, a przy jego braku najnowszy kwalifikowany wcześniejszy budżet; budżet z przyszłości nie jest źródłem.
11. Użytkownik nie może użyć budżetu innego użytkownika jako źródła kopii.
12. Align ustawia plan aktualny na `max(0, Execution)` i zostawia plan początkowy bez zmian.
13. Nowa kategoria wydatkowa jest Paced Category, dopóki użytkownik nie oznaczy jej jako One-off. One-off nie ma Month Remaining.

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
14. Import zapisuje oryginalny opis z wyciągu jednorazowo przy utworzeniu operacji finansowej, transferu własnego albo Capital Movement. Ręczne tworzenie pozostawia go pustym. Edycja opisu lub nadawcy / odbiorcy nie zmienia oryginalnego opisu z wyciągu.
15. Dialog edycji pokazuje oryginalny opis z wyciągu jako pole tylko do odczytu, gdy jest ustawiony. Po parze transfer własny pokazuje oba oryginalne opisy z wyciągu.
16. Wiersz sklasyfikowany jako Capital Movement wymaga istniejącej lokaty. Kierunek wynika ze znaku kwoty na koncie.
17. Wiersz uznania z podaną kwotą odsetek i kategorią dochodową tworzy Capital Movement na resztę oraz dochód na odsetki; oba rekordy mają tę samą tożsamość wiersza pliku.
18. Capital Movement nie jest oznaczany jako Import pair candidate.

### 11.7. Raportowanie

1. Dla każdej kategorii wartości planu początkowego, planu aktualnego, wykonania i pozostałej kwoty zgadzają się z zapisanymi operacjami.
2. Przekroczenie limitu jest widoczne jako wartość ujemna.
3. Przy planie aktualnym 0 PLN system nie oblicza procentu i pokazuje „nie dotyczy”.
4. Spendable Liquidity jest równa sumie sald prezentowanych kont i nie jest Total funds.
5. Salda kont nie są prezentowane jako dostępne limity kategorii ani odwrotnie.
6. Total funds jest równa Spendable Liquidity plus salda lokat.
7. Planned Balance jest równa planowi aktualnemu dochodów minus planowi aktualnemu wydatków i nie jest saldem kont.
8. Month Remaining Paced Category nie jest równe Available Limit tej kategorii.
9. Month Result miesiąca zgadza się z operacjami finansowymi datowanymi w tym miesiącu i nie obejmuje Own Transfer ani Capital Movement.

### 11.8. Lokaty i ruchy kapitału

1. Utworzenie lokaty z Bank, datą i saldem otwarcia pokazuje saldo bieżące równe tej migawce i nie zmienia żadnego konta ani wykonania kategorii. Opcjonalna data zakończenia nie rusza sald.
2. Zmiana salda otwarcia przelicza saldo bieżące lokaty bez tworzenia dochodu ani Capital Movement. Data otwarcia po utworzeniu nie daje się zmienić.
3. Capital Movement konto → lokata zmniejsza saldo konta i zwiększa saldo lokaty o tę samą kwotę. Kierunek odwrotny robi odwrotnie. Wykonanie kategorii nie zmienia się. Total funds nie zmienia się.
4. Odsetki zaksięgowane jako dochód zwiększają saldo konta i wykonanie kategorii dochodowej; saldo lokaty pozostaje.
5. Lokatę z historią ruchów można zarchiwizować i przywrócić, ale nie można jej usunąć. Zarchiwizowana lokata nie jest dostępna przy nowym Capital Movement.
6. Ujemne saldo lokaty jest dozwolone po ostrzeżeniu i jest oznaczone na pulpicie oraz na liście lokat.
7. Przebudowa dochodu, wydatku albo transferu własnego w Capital Movement zostawia jeden rekord i spójne salda. Przebudowa odwrotna analogicznie.

### 11.9. Lista operacji

1. Lista filtruje operacje finansowe, transfery własne i Capital Movements po tekście, roku, koncie, lokacie i zakresie dat.
2. Filtr kategorii po nazwie i typie obejmuje rekordy z wielu lat o tej samej parze; nie jest tożsamością Category ponad latami.

## 12. Poza zakresem MVP

- księga długów, pozostałe saldo kredytu, recurrences i automatyczne zobowiązania,
- Agenda i Cash Expectation jako powierzchnia produktu (specyfikacja następnego przyrostu: `.docs/specs/agenda-cash-expectations.md`; słownik: `CONTEXT.md`); nie są jeszcze w tym wydaniu,
- samodzielna rejestracja w interfejsie webowym,
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
- portfel inwestycyjny (fundusze, akcje, IKE/IKZE); lokata bankowa (Term Deposit) jest w zakresie,
- kredyty, pożyczki i rozliczenia podatkowe,
- nieruchomości, pojazdy i inne niepłynne składniki majątku,
- kalendarz zapadalności lokaty oraz oprocentowanie jako cecha produktu (harmonogram, APR); opcjonalna informacyjna data zakończenia jest w zakresie,
- automatyczne odnawianie lokaty,
- bezpośredni ruch kapitału lokata → lokata bez konta płynnego,
- Counterparty na Capital Movement,
- osobny wyciąg lokaty i parowanie go z Importem konta,
- kara za zerwanie lokaty jako osobny typ wiersza (haircut, opłata, zwrot odsetek),
- zgadywanie lokaty z układu eksportu mBank albo BNP Paribas.

## 13. Ryzyka i zależności

- Bank może zmienić układ eksportu; nazwany Import format wtedy się rozjedzie, a format Inny oraz podgląd ograniczają skutki.
- Blokady kart w podglądzie BNP mogą zostać omyłkowo zatwierdzone i później zdublować się z operacją zrealizowaną; użytkownik je odznacza.
- Import duplicate obejmuje tylko wcześniejsze zatwierdzenia Importu z odciskiem; ręczne wpisy, rekordy sprzed odcisku i bliźniaki w pliku bez wcześniejszego zapisu nie są oznaczane. Ponowne włączenie Import duplicate może utworzyć drugi rekord.
- Okno trzech dni i dokładna przeciwna kwota mogą wskazać więcej niż jednego Import pair candidate; użytkownik wybiera nogę. Para nie gwarantuje, że bankowy przelew wewnętrzny należy do tego użytkownika.
- Dopuszczenie korekt historycznych bez śladu audytowego może utrudniać wyjaśnienie zmian; jest to świadomy kompromis MVP.
- Brak zamknięcia roku pozwala zmieniać dane historyczne; formalne zamykanie okresów pozostaje późniejszym rozszerzeniem.
- Poprawność sald, wykonania i transferów zależy od atomowego i dokładnego przetwarzania kwot.
- Oficjalne eksporty detaliczne mBank i BNP Paribas oraz fixture w repozytorium nie oznaczają wierszy lokaty (otwarcie, zwrot, odsetki, łącznie). Użytkownik klasyfikuje te wiersze ręcznie; zły typ psuje Spendable Liquidity albo wykonanie, dopóki nie przebuduje rekordu.
- Podział zwrotu kapitału i odsetek w Imporcie zależy od kwoty wpisanej przez użytkownika, nie od kolumny banku.

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
