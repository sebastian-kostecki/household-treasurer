# Household Treasurer — MVP

## 1. Cel produktu

Household Treasurer pomaga jednej osobie przygotować roczny budżet domowy, rejestrować jego wykonanie i kontrolować środki na rachunkach bankowych oraz w gotówce.

Produkt łączy planowanie według kategorii z ewidencją rzeczywistych przepływów. Dzięki temu użytkownik widzi zarówno wykonanie budżetu, jak i bieżące salda kont.

## 2. Założenia

- Jeden użytkownik zarządza wyłącznie własnymi danymi.
- Rok kalendarzowy jest podstawowym okresem budżetowym.
- Jeden budżet roczny korzysta z jednej waluty.
- MVP obsługuje rachunki bankowe i konta gotówkowe w walucie budżetu.
- Dochody i wydatki są ujmowane w momencie zapłaty.
- Operacje można dodawać ręcznie albo tworzyć z pozycji importowanych z pliku CSV.
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
- tworzy kategorie dochodów i wydatków,
- określa roczne planowane kwoty dochodów,
- przypisuje roczne limity kategoriom wydatkowym,
- rozpoczyna wykonanie budżetu.

W chwili rozpoczęcia wykonania system zapisuje plan początkowy. Podczas wykonywania budżetu użytkownik może bezpośrednio zmieniać planowane kwoty i limity. System pokazuje osobno plan początkowy i plan aktualny, ale MVP nie wymaga uzasadnienia ani formalnego procesu zatwierdzania zmian.

### 3.2. Konta

Konto reprezentuje środki płynne użytkownika. W MVP może być rachunkiem bankowym albo kontem gotówkowym i zawiera:

- nazwę,
- typ,
- walutę,
- saldo otwarcia,
- saldo bieżące wynikające z operacji.

Saldo otwarcia opisuje środki posiadane na początku ewidencji i nie jest dochodem. MVP nie obejmuje ewidencji nieruchomości, pojazdów, inwestycji ani innych niepłynnych składników majątku.

### 3.3. Operacje finansowe

Operacja ma jeden z trzech typów:

- **dochód** — zwiększa saldo konta i wykonanie planu wskazanej kategorii dochodowej,
- **wydatek** — zmniejsza saldo konta i wykorzystuje limit wskazanej kategorii wydatkowej,
- **transfer własny** — przenosi środki między dwoma kontami użytkownika bez wpływu na wykonanie budżetu.

Dochód i wydatek zawierają co najmniej datę, kwotę, opis, konto i kategorię odpowiedniego typu. Użytkownik może edytować i usuwać operacje.

Transfer tworzy atomowo obciążenie konta źródłowego i uznanie konta docelowego. Konta muszą być różne, a transfer nie otrzymuje kategorii budżetowej. Wypłata z bankomatu jest transferem z rachunku bankowego na konto gotówkowe.

### 3.4. Kontrola wykonania

Dla każdej kategorii system pokazuje:

- plan początkowy,
- plan aktualny,
- wykonanie,
- pozostałą kwotę,
- procent wykonania.

Dla kategorii wydatkowej obowiązuje reguła:

`dostępny limit = aktualny limit - zaksięgowane wydatki`

Przekroczenie limitu jest dozwolone i wyświetlane jako wartość ujemna. Użytkownik może następnie zmienić aktualny limit albo pozostawić widoczne przekroczenie.

### 3.5. Import CSV

Import przebiega w trzech krokach:

1. wczytanie pliku i przypisanie podstawowych kolumn, takich jak data, kwota i opis,
2. wskazanie konta oraz podgląd pozycji,
3. zatwierdzenie pozycji, które mają zostać utworzone jako operacje.

Przed zatwierdzeniem użytkownik może przypisać typ i kategorię operacji. MVP nie dopasowuje automatycznie obu stron transferów i nie zapewnia zaawansowanego wykrywania duplikatów. Za kontrolę podglądu przed zatwierdzeniem odpowiada użytkownik.

### 3.6. Podstawowe raportowanie

System udostępnia:

- salda wszystkich kont,
- łączną wartość środków płynnych,
- plan początkowy, plan aktualny i wykonanie dla każdej kategorii,
- sumę planowanych i rzeczywistych dochodów,
- sumę limitów i rzeczywistych wydatków.

## 4. Główne obiekty

- Użytkownik
- Rok budżetowy
- Konto
- Kategoria
- Plan początkowy
- Plan aktualny
- Operacja finansowa
- Transfer własny
- Import CSV

## 5. Główny przebieg użytkownika

1. Użytkownik tworzy rachunki bankowe i konta gotówkowe oraz podaje ich salda otwarcia.
2. Tworzy budżet roczny i kategorie.
3. Planuje dochody oraz limity wydatków.
4. Rozpoczyna wykonanie i zapisuje plan początkowy.
5. Dodaje ręcznie albo importuje dochody i wydatki.
6. Rejestruje transfery między własnymi kontami.
7. Kontroluje salda kont, wykonanie kategorii i dostępne limity.
8. W razie potrzeby aktualizuje planowane kwoty lub pozostawia widoczne odchylenia.

## 6. Najważniejsze reguły

- Wszystkie kwoty są przechowywane bez obliczeń zmiennoprzecinkowych.
- Dochód lub wydatek wpływa jednocześnie na saldo konta i wykonanie właściwej kategorii.
- Transfer własny wpływa wyłącznie na salda kont.
- Utworzenie, edycja albo usunięcie transferu aktualizuje obie jego strony atomowo.
- Dane jednego użytkownika nie są dostępne dla innych użytkowników.

## 7. Poza zakresem MVP

- zobowiązania i planowane przyszłe płatności,
- rezerwy budżetowe,
- miesięczne harmonogramy i prognozy płynności,
- formalne zatwierdzanie zmian budżetowych,
- korekty salda i pełny ślad audytowy,
- zamknięcie roku oraz wersjonowane sprawozdania,
- raporty kwartalne i rozbudowane raporty okresowe,
- automatyczne uzgadnianie transferów oraz zaawansowane wykrywanie duplikatów w CSV,
- bezpośrednie integracje bankowe,
- budżety współdzielone przez wielu domowników,
- wiele walut w jednym budżecie,
- natywne aplikacje mobilne,
- funkcje AI,
- zarządzanie portfelem inwestycyjnym,
- kredyty, pożyczki i rozliczenia podatkowe,
- ewidencja nieruchomości, pojazdów i innych niepłynnych składników majątku.

## 8. Kryteria sukcesu

MVP jest kompletne, gdy użytkownik może:

1. utworzyć roczny budżet z kategoriami oraz planowanymi dochodami i limitami wydatków,
2. zapisać plan początkowy i później aktualizować plan bieżący,
3. prowadzić salda rachunków bankowych i kont gotówkowych,
4. rejestrować dochody i wydatki przypisane do kont oraz kategorii,
5. przenosić środki między własnymi kontami bez wpływu na wykonanie budżetu,
6. importować podstawowe operacje z pliku CSV po sprawdzeniu podglądu,
7. porównywać plan początkowy, plan aktualny i wykonanie,
8. rozróżniać wykonanie budżetu od bieżącego stanu środków.
