# Household Treasurer — MVP

## 1. Cel produktu

Household Treasurer pomaga jednej osobie przygotować roczny budżet domowy, rejestrować jego wykonanie i kontrolować środki na rachunkach bankowych, w gotówce oraz na lokatach (Term Deposit).

Produkt łączy planowanie według kategorii z ewidencją rzeczywistych przepływów. Dzięki temu użytkownik widzi zarówno wykonanie budżetu, jak i Spendable Liquidity oraz Total funds.

## 2. Założenia

- Konta logowania tworzy administrator; samodzielna rejestracja jest wyłączona. Każdy użytkownik zarządza wyłącznie własnymi danymi.
- Rok kalendarzowy jest podstawowym okresem budżetowym.
- Walutą MVP jest PLN.
- MVP obsługuje rachunki bankowe, konta gotówkowe i lokaty (Term Deposit). Bank na rachunku i lokacie to instytucja, nie Import format.
- Dochody i wydatki są ujmowane w momencie zapłaty.
- Operacje można dodawać ręcznie albo tworzyć z pozycji importowanych z pliku CSV albo XLSX.
- Aplikacja wymaga logowania, trwałego zapisu danych i izolacji danych użytkowników.

### 2.1. Założenia budżetowe

- **Roczność** — podstawowym okresem budżetowym jest rok kalendarzowy.
- **Uprzedniość** — plan początkowy powstaje przed rozpoczęciem wykonania budżetu.
- **Kompletność** — budżet obejmuje planowane dochody, wydatki oraz środki płynne wykazane na kontach.
- **Szczegółowość** — dochody i wydatki są przypisane do kategorii z określonymi planami lub limitami.
- **Realność i ostrożność** — planowane kwoty powinny mieć uzasadnione podstawy i uwzględniać prawdopodobne koszty.
- **Równowaga** — planowany deficyt powinien mieć pokrycie w środkach wykazanych w saldach otwarcia kont.
- **Celowość i efektywność** — planowane wydatki mają określone przeznaczenie.
- **Kontrola uprzednia** — przed zapisaniem wydatku użytkownik widzi dostępny limit kategorii.
- **Przejrzystość** — system rozróżnia plan początkowy, plan aktualny i wykonanie.
- **Rozdzielenie budżetu od płynności** — limit kategorii nie oznacza dostępności środków na kontach.

## 3. Zakres funkcjonalny

### 3.1. Budżet roczny

Użytkownik:

- tworzy budżet dla wybranego roku,
- przy tworzeniu szkicu może skopiować kategorie i plan aktualny z wcześniejszego własnego budżetu (nowe rekordy, bez tożsamości ponad latami),
- tworzy kategorie dochodów i wydatków (wygląd, kolejność; kategoria wydatkowa jest Paced Category albo One-off Category),
- określa roczne planowane kwoty dochodów,
- przypisuje roczne limity kategoriom wydatkowym,
- wyrównuje plan aktualny do wykonania (Align) bez zmiany planu początkowego,
- rozpoczyna wykonanie budżetu.

W chwili rozpoczęcia wykonania system zapisuje plan początkowy. Podczas wykonywania budżetu użytkownik może bezpośrednio zmieniać planowane kwoty i limity. System pokazuje osobno plan początkowy i plan aktualny, ale MVP nie wymaga uzasadnienia ani formalnego procesu zatwierdzania zmian.

### 3.2. Konta

Konto reprezentuje środki płynne użytkownika. W MVP może być rachunkiem bankowym albo kontem gotówkowym i zawiera:

- nazwę,
- typ,
- Bank (na rachunku bankowym),
- saldo otwarcia,
- saldo bieżące wynikające z operacji.

Saldo otwarcia opisuje środki posiadane na początku ewidencji i nie jest dochodem. MVP nie obejmuje ewidencji nieruchomości, pojazdów, inwestycji ani innych niepłynnych składników majątku.

### 3.3. Operacje finansowe

Operacja finansowa ma jeden z typów:

- **dochód** — zwiększa saldo konta i wykonanie planu wskazanej kategorii dochodowej,
- **wydatek** — zmniejsza saldo konta i wykorzystuje limit wskazanej kategorii wydatkowej,
- **zwrot wydatku** — ujemny wydatek na kategorii wydatkowej.

**Transfer własny** przenosi środki między dwoma kontami użytkownika bez wpływu na wykonanie budżetu.

**Capital Movement** przenosi kapitał między kontem a istniejącą lokatą i nie wpływa na wykonanie budżetu.

Dochód i wydatek zawierają co najmniej datę, kwotę, opis, konto i kategorię odpowiedniego typu. Użytkownik może edytować i usuwać operacje. Edycja może zmienić dochód w zwrot wydatku (kategoria wydatkowa) i zwrot z powrotem w dochód; Import nie zgaduje tej klasyfikacji.

Transfer tworzy atomowo obciążenie konta źródłowego i uznanie konta docelowego. Konta muszą być różne, a transfer nie otrzymuje kategorii budżetowej. Wypłata z bankomatu jest transferem z rachunku bankowego na konto gotówkowe.

### 3.4. Kontrola wykonania

Dla każdej kategorii system pokazuje:

- plan początkowy,
- plan aktualny,
- wykonanie,
- pozostałą kwotę (Available Limit),
- procent wykonania,
- Month Remaining dla Paced Category,
- Planned Balance i Month Result.

Dla kategorii wydatkowej obowiązuje reguła:

`dostępny limit = aktualny limit - zaksięgowane wydatki`

Przekroczenie limitu jest dozwolone i wyświetlane jako wartość ujemna. Użytkownik może następnie zmienić aktualny limit albo pozostawić widoczne przekroczenie.

### 3.5. Import

Import przebiega w trzech krokach:

1. wczytanie pliku CSV albo XLSX, wybór Import format (mBank, BNP Paribas albo Inny) i — dla formatu Inny — mapowanie kolumn daty, kwoty i opisu,
2. wskazanie konta oraz podgląd pozycji,
3. zatwierdzenie pozycji, które mają zostać utworzone jako operacje.

Nazwane formaty znają układ eksportu banku (w tym preambułę mBanku oraz arkusz BNP z kolumną Nadawca / odbiorca albo rozdzielonymi Nadawcą i Odbiorcą). Format Inny zostaje dla nieznanych CSV. Podgląd oznacza Import duplicate (ponowny Import już zapisanego wiersza z tego konta) oraz Import pair candidate (druga noga transferu własnego z innego konta i wcześniejszego Importu). Oba startują odznaczone. Dwa wiersze tego samego pliku nie są parowane. Ręczne wpisy nie są oznaczane. Za włączenie tych wierszy przed zatwierdzeniem odpowiada użytkownik.

### 3.6. Podstawowe raportowanie

System udostępnia:

- salda wszystkich kont (Spendable Liquidity),
- Total funds (Spendable Liquidity plus salda lokat),
- plan początkowy, plan aktualny i wykonanie dla każdej kategorii,
- sumę planowanych i rzeczywistych dochodów,
- sumę limitów i rzeczywistych wydatków.

## 4. Główne obiekty

- Użytkownik
- Rok budżetowy
- Konto
- Term Deposit
- Kategoria (Paced Category / One-off Category)
- Plan początkowy
- Plan aktualny
- Operacja finansowa
- Transfer własny
- Capital Movement
- Import

## 5. Główny przebieg użytkownika

1. Administrator tworzy konto logowania. Użytkownik loguje się, tworzy rachunki bankowe i konta gotówkowe oraz podaje ich salda otwarcia. W razie potrzeby dodaje lokaty.
2. Tworzy budżet roczny; gdy ma wcześniejszy budżet z niezarchiwizowanymi kategoriami, może skopiować te kategorie wraz z planem aktualnym.
3. W razie potrzeby poprawia kategorie oraz planuje dochody i limity wydatków.
4. Rozpoczyna wykonanie i zapisuje plan początkowy.
5. Dodaje ręcznie albo importuje dochody i wydatki.
6. Rejestruje transfery między własnymi kontami oraz ruchy kapitału na lokaty.
7. Kontroluje salda kont, wykonanie kategorii i dostępne limity.
8. W razie potrzeby aktualizuje planowane kwoty lub pozostawia widoczne odchylenia.

## 6. Najważniejsze reguły

- Wszystkie kwoty są przechowywane bez obliczeń zmiennoprzecinkowych.
- Dochód lub wydatek wpływa jednocześnie na saldo konta i wykonanie właściwej kategorii.
- Transfer własny wpływa wyłącznie na salda kont.
- Capital Movement wpływa na saldo konta i lokaty, nie na wykonanie budżetu.
- Utworzenie, edycja albo usunięcie transferu albo Capital Movement aktualizuje obie strony atomowo.
- Dane jednego użytkownika nie są dostępne dla innych użytkowników.

## 7. Poza zakresem MVP

- księga długów, recurrences; Agenda i Cash Expectation (specyfikacja następnego przyrostu, nie w tym wydaniu),
- samodzielna rejestracja w interfejsie webowym,
- rezerwy budżetowe,
- miesięczne harmonogramy i prognozy płynności,
- formalne zatwierdzanie zmian budżetowych,
- korekty salda i pełny ślad audytowy,
- zamknięcie roku oraz wersjonowane sprawozdania,
- tożsamość Category ponad latami oraz szablon kategorii,
- kopiowanie kategorii do już istniejącego budżetu,
- wybór podzbioru kategorii w dialogu tworzenia roku,
- raporty kwartalne i rozbudowane raporty okresowe,
- parowanie dwóch wierszy w jednym pliku Importu oraz dopasowywanie ręcznych wpisów jako Import duplicate albo Import pair candidate,
- bezpośrednie integracje bankowe,
- budżety współdzielone przez wielu domowników,
- wiele walut w jednym budżecie,
- natywne aplikacje mobilne,
- funkcje AI,
- zarządzanie portfelem inwestycyjnym (lokata bankowa jest w zakresie),
- kredyty, pożyczki i rozliczenia podatkowe,
- ewidencja nieruchomości, pojazdów i innych niepłynnych składników majątku.

## 8. Kryteria sukcesu

MVP jest kompletne, gdy użytkownik może:

1. utworzyć roczny budżet z kategoriami oraz planowanymi dochodami i limitami wydatków, w tym rozpocząć kolejny rok przez skopiowanie kategorii i planu aktualnego,
2. zapisać plan początkowy i później aktualizować plan bieżący,
3. prowadzić salda rachunków bankowych, kont gotówkowych i lokat,
4. rejestrować dochody, wydatki i zwroty przypisane do kont oraz kategorii,
5. przenosić środki między własnymi kontami oraz kapitał między kontem a lokatą bez wpływu na wykonanie budżetu,
6. importować podstawowe operacje z pliku CSV albo XLSX po sprawdzeniu podglądu,
7. porównywać plan początkowy, plan aktualny i wykonanie,
8. rozróżniać wykonanie budżetu od bieżącego stanu środków.
