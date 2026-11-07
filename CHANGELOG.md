# Changelog

Wszystkie istotne zmiany w Household Treasurer. Format według [Keep a Changelog](https://keepachangelog.com/pl/1.1.0/), wersje według [Semantic Versioning](https://semver.org/lang/pl/). Każda wersja to tag na `main` i release na GitHubie.

Nazwy domenowe (_Current Plan_, _Own Transfer_, _Capital Movement_…) pochodzą ze słownika w [`context/foundation/glossary.md`](context/foundation/glossary.md). Szczegóły każdej zmiany 10x leżą w `context/archive/<data>-<change-id>/`.

## [Unreleased]

## [1.3.0] — 2026-11-07

Interfejs w design systemie **Atrament** ([`context/foundation/design-system.md`](context/foundation/design-system.md)): wszystkie widoki przechodzą na wspólne tokeny, kroje i komponenty.

### Dodano

- Warstwa tokenów Atrament w `resources/css/app.css` (kolory, kroje, odstępy, powierzchnie) i jej konsumenci w całej aplikacji (`design-tokens-atrament`).
- Paleta poleceń **⌘K** z widokami, kategoriami i akcjami, skróty klawiaturowe do widoków oraz rok budżetu wybierany w segmentach na pasku bocznym (`sidebar-action`).
- Awatar konta (`AccountAvatar`) z identyfikacją banku wszędzie tam, gdzie wcześniej była sama nazwa konta (`account-avatar`).
- Skrót banku w `AccountMarkCompact` i przełącznik w wierszu tytułu strony Konta (`accounts-design-system-page`).
- Dashboard według strony design systemu: trzy miary, karta alertu i blok „Budżet &lt;rok&gt;” (`dashboard-design-system-page`).
- Konfiguracja Playwright E2E z testem seedowym i scenariuszami bramki wizualnej dla przebudowanych widoków.

### Zmieniono

- Widoki przebudowane w Atramencie, każdy z kontraktem propsów, kitchen sinkiem w `/dev/...` i bramką wizualną: Budżet, Operacje, Plan miesiąca, Dashboard, Konta, Lokaty, Ustawienia (Profil, Bezpieczeństwo, Wygląd), Import (krok 1 i podgląd) oraz pasek boczny ze znakiem „HT”.
- Dialogi kategorii i operacji (wydatek, dochód, transfer, ruch kapitału) na wspólnych elementach pola z odczytem „przed → po” (`category-dialog-atrament`, `operation-dialogs-atrament`).
- Lista Operacji według preview z ADR-0039 (`ui-operations-atrament`).
- Przyciski „Anuluj” w wariancie ghost; „Zakończyć kategorię?” korzysta z `ConfirmActionDialog` (`cancel-button-ghost`).
- Powody odrzucenia wiersza i pary w podglądzie Importu są po polsku i składane w UI (`import-preview-atrament-ui`).
- Pasek boczny bez górnej belki i bez zwijania.
- Zależności: Inertia 3.11 (Laravel 3.7), Vue 3.5.44, Tailwind 4.3.4, Vite+ 1.5.0, Pest 5.3.5, Larastan 3.14, Sloppy 1.8.2; Node 24 w `.nvmrc` i `engines`.
- CI: cache Composera, runner przypięty do `ubuntu-24.04`, testy także przy pushu na `development`.

### Naprawiono

- Edycja konta bankowego zachowuje wybrany bank.
- Kolumny kategorii w Budżecie mieszczą się bez przewijania w bok.
- Kolumna Status w Planie miesiąca jest wyrównana do prawej na każdej szerokości.
- Paleta ⌘K: filtrowanie kategorii wczytanych po wpisaniu tekstu, wyszukiwanie tylko po etykiecie, okno nowego budżetu otwierane w layoucie zamiast w arkuszu paska bocznego.
- Wiersz operacji nie pokazuje awatara konta.
- Typowane pola konta kandydata pary w `ImportPairCandidateDetector`.

### Bezpieczeństwo

- `symfony/serializer` 8.1.11 z poprawkami zgłoszonymi przez `composer audit`.

## [1.2.0] — 2026-11-04

Import z podpowiedzią kategorii i uporządkowany podgląd Importu.

### Dodano

- Podpowiedź kategorii w podglądzie Importu: wstępny wybór na podstawie wcześniejszych operacji, znacznik podpowiedzi przy wierszu oraz pomiar trafności i wydajności (`import-category-suggestions`).
- Generyczny opis dla zaimportowanych wierszy bez opisu.
- Kontrakt UI podglądu Importu: kitchen sink, macierz stanów i bramka (`import-preview-ui-contract`).

### Naprawiono

- Zatwierdzenie Importu, gdy podgląd zawiera wiersze niepoprawne.

### Zmieniono

- Zależności: Inertia (Vue) 3.9.1, Inertia Laravel 3.5.2, Fortify 1.41.0, Pest 5.3.4, `@lucide/vue` 1.64.0, `vue-input-otp` 0.4.0.

## [1.1.0] — 2026-11-03

Kamień milowy **M-1: Kontrola miesiąca bez arkusza** — plan miesiąca i „do wydania” w aplikacji zamiast w arkuszu ([`context/foundation/prd.md`](context/foundation/prd.md), [`context/foundation/roadmap.md`](context/foundation/roadmap.md)).

### Dodano

- Strona **Plan miesiąca**: propozycja planu, korekta, zatwierdzenie i odczyt „do wydania”; rodzaj pozycji (rachunek / bieżący) na kategorii; snapshot w wersji 5 (`month-plan-and-to-spend`).
- Kwota „odkładam / dobieram” w planie i jej wpływ na „do wydania” (`set-aside-and-draw`).
- Zmiana zatwierdzonego planu w trakcie miesiąca (`approved-plan-changes`).
- Plan i faktyczna kwota każdej pozycji oraz kategorie jednorazowe w zwiniętej sekcji (`month-items-plan-vs-actual`).
- Tempo wydatków względem upływu dni miesiąca, liczone tym samym wzorem w PHP i TS (`month-spending-pace`).
- „Do wydania” na Dashboardzie i w pasku miesiąca na stronie Budżet, także w miesiącu bez planu (`to-spend-replaces-remaining`).
- Powiadomienia planu miesiąca na Dashboardzie (`month-plan-notices`).
- Ostrzeżenia liczone z planu przy ręcznej operacji i w podglądzie Importu (`plan-based-entry-warnings`).
- Podsumowanie miesiąca, w tym odkładanie zamierzone i realne (`month-summary`).
- Porównanie planów miesięcy z budżetem rocznym (`month-plans-vs-year`).
- Kontrakty UI z kitchen sinkiem i macierzą 7 stanów dla Dashboardu, Budżetu, Planu miesiąca, Kont, Lokat, Operacji i paska bocznego.
- Sloppy w zależnościach i w CI.

### Zmieniono

- Decyzje domenowe planu miesiąca: nowy ADR zastępuje ADR-0024 w części o „drugim budżecie miesięcznym”, nowe terminy i progi powiadomień w słowniku (`month-plan-domain-decisions`).
- „Do wydania” zastępuje odczyt _Month Remaining_; oznaczenie _Paced_ w powiadomieniach zastąpiły powiadomienia planu miesiąca.
- Przejście na Vite+ 1.x, aktualizacja Laravela z Wayfinderem i Larastanem; konfiguracja Boost MCP dla agentów.

### Naprawiono

- Wiersze Kont i Lokat korzystają ze wspólnej klasy `holdings-list-row`.
- Zapis zmian planu jednym insertem zamiast zapisu w pętli.

## [1.0.0] — 2026-09-24

Pierwsza wersja (MVP) wdrożona na produkcję: roczny budżet domowy i płynność na kontach i lokatach, w PLN, z interfejsem po polsku ([`context/foundation/prd-mvp.md`](context/foundation/prd-mvp.md)).

### Dodano

- **Konta i płynność:** konta bankowe i gotówkowe z identyfikacją banku (Alior, BNP Paribas, ING, Lokaty Facto, mBank, Pekao, PKO BP, Revolut, Santander), _Spendable Liquidity_ i _Total funds_, archiwizacja zamiast usuwania.
- **Budżet roczny:** szkic budżetu z kategoriami dochodów i wydatków, start wykonania z zamrożonym _Initial Plan_, edycja _Current Plan_ w trakcie roku, wyrównanie planu kategorii do wykonania, kopiowanie kategorii z wcześniejszego roku, wygląd i kolejność kategorii (przeciąganie), kategorie równomierne (_Paced_) i jednorazowe (_One-off_).
- **Operacje:** ręczne dochody, wydatki, zwroty i _Own Transfers_ z podglądem limitów i ostrzeżeń, opcjonalny kontrahent, edycja i usuwanie, przeklasyfikowanie dochodu na zwrot wydatku, lista z paginacją, wyszukiwaniem i filtrami.
- **Lokaty:** _Term Deposits_ z bankiem i datą końca, _Capital Movement_ wprowadzany ręcznie i z wyciągu, przebudowa operacji lub transferu w ruch kapitału, filtr lokat na liście operacji.
- **Import wyciągów:** CSV i XLSX, nazwane formaty mBank i BNP Paribas z automatycznym mapowaniem kolumn, pomijanie śmieciowych wierszy, wykrywanie duplikatów przy ponownym imporcie, pary kandydatów zamieniane na _Own Transfers_, import ruchów kapitału i odsetek, zapis kontrahenta i surowego opisu, pomijanie numerów instrumentów płatniczych, atomowy zapis partii.
- **Dashboard** bieżącego roku: kafelki płynności, strefa uwagi z uporządkowanymi powiadomieniami o poziomie ważności, alerty kategorii równomiernych, znacznik upływu roku, sekcje budżetu z domyślnym zwinięciem, lista kont i lokat.
- Odczyt wykonania i wyniku miesiąca w pasku miesięcy na stronie Budżet.
- Logowanie z 2FA i passkeys przez Fortify; brak samodzielnej rejestracji, użytkowników tworzy administrator komendą Artisan.
- Komendy administracyjne: `user:backup`, `user:restore`, `user:reset-data`, zarządzanie kontami z Artisana, `demo:seed-household`; kopie zapasowe aplikacji (Spatie) z harmonogramem i odtwarzaniem.
- Polska strona powitalna, krój Geist z `latin-ext`, pierwszy design system z tokenami kolorów i bursztynowymi ostrzeżeniami nieblokującymi.
- Produkcyjny stos Docker (za Traefikiem i Cloudflare, HTTPS), skrypt wdrożenia z `main`, CI w GitHub Actions, test wydajności NFR-PERF-01 na PostgreSQL, PHPStan na poziomie 7.

[Unreleased]: https://github.com/sebastian-kostecki/household-treasurer/compare/v1.3.0...HEAD
[1.3.0]: https://github.com/sebastian-kostecki/household-treasurer/compare/v1.2.0...v1.3.0
[1.2.0]: https://github.com/sebastian-kostecki/household-treasurer/compare/v1.1.0...v1.2.0
[1.1.0]: https://github.com/sebastian-kostecki/household-treasurer/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/sebastian-kostecki/household-treasurer/releases/tag/v1.0.0
